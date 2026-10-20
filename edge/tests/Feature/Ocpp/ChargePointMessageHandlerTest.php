<?php

namespace Tests\Feature\Ocpp;

use App\Models\EdgeChargingSession;
use App\Models\OcppCall;
use App\Models\PendingTransaction;
use App\Models\SyncOutbox;
use App\Services\Charging\EdgeChargingService;
use App\Services\Ocpp\ChargePointCommander;
use App\Services\Ocpp\ChargePointMessageHandler;
use App\Services\Ocpp\OcppError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargePointMessageHandlerTest extends TestCase
{
    use OcppFixtures;
    use RefreshDatabase;

    private ChargePointMessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStation();
        $this->handler = app(ChargePointMessageHandler::class);
    }

    protected function tearDown(): void
    {
        ChargePointCommander::markInsideServer(false);
        parent::tearDown();
    }

    public function test_boot_notification_is_accepted_and_stores_device_info(): void
    {
        $response = $this->handler->handle('CP-T1', 'BootNotification', [
            'chargePointVendor' => 'ABB',
            'chargePointModel' => 'Terra 54',
            'chargePointSerialNumber' => 'SN-1',
            'firmwareVersion' => '1.2.3',
        ]);

        $this->assertSame('Accepted', $response['status']);
        $this->assertSame(60, $response['interval']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $response['currentTime']);
        $this->assertSame('ABB', $this->charger->fresh()->vendor);
        $this->assertSame('1.2.3', $this->charger->fresh()->firmware_version);
    }

    public function test_missing_required_field_raises_ocpp_error(): void
    {
        $this->expectException(OcppError::class);
        $this->handler->handle('CP-T1', 'BootNotification', ['chargePointVendor' => 'ABB']);
    }

    public function test_unknown_action_is_not_implemented(): void
    {
        try {
            $this->handler->handle('CP-T1', 'FooBar', []);
            $this->fail('Expected OcppError');
        } catch (OcppError $e) {
            $this->assertSame('NotImplemented', $e->errorCode);
        }
    }

    public function test_status_notification_updates_and_creates_connectors(): void
    {
        $this->handler->handle('CP-T1', 'StatusNotification', ['connectorId' => 1, 'errorCode' => 'NoError', 'status' => 'Preparing']);
        $this->handler->handle('CP-T1', 'StatusNotification', ['connectorId' => 2, 'errorCode' => 'GroundFailure', 'status' => 'Faulted']);

        $this->assertSame('Preparing', $this->connector->fresh()->ocpp_status);
        $second = $this->charger->connectors()->where('connector_number', 2)->first();
        $this->assertSame('Faulted', $second->ocpp_status);
        $this->assertSame('GroundFailure', $second->error_code);
        $this->assertSame(2, SyncOutbox::where('event_type', 'connector.status')->count());
    }

    public function test_authorize_known_and_unknown_tags(): void
    {
        $this->assertSame('Accepted', $this->handler->handle('CP-T1', 'Authorize', ['idTag' => 'card-001'])['idTagInfo']['status']);
        $this->assertSame('Invalid', $this->handler->handle('CP-T1', 'Authorize', ['idTag' => 'NOPE'])['idTagInfo']['status']);
    }

    public function test_authorize_blocks_when_wallet_is_empty(): void
    {
        $this->wallet->update(['balance' => 0]);

        $this->assertSame('Blocked', $this->handler->handle('CP-T1', 'Authorize', ['idTag' => 'CARD-001'])['idTagInfo']['status']);
    }

    public function test_rfid_session_started_on_charger_is_metered_and_billed(): void
    {
        $start = $this->handler->handle('CP-T1', 'StartTransaction', [
            'connectorId' => 1, 'idTag' => 'CARD-001', 'meterStart' => 50_000, 'timestamp' => now()->toIso8601String(),
        ]);

        $this->assertSame('Accepted', $start['idTagInfo']['status']);
        $session = EdgeChargingSession::where('ocpp_transaction_id', $start['transactionId'])->firstOrFail();
        $this->assertSame('rfid', $session->auth_method);
        $this->assertSame('active', $session->status);

        $this->handler->handle('CP-T1', 'MeterValues', [
            'connectorId' => 1,
            'transactionId' => $start['transactionId'],
            'meterValue' => [[
                'timestamp' => now()->toIso8601String(),
                'sampledValue' => [
                    ['value' => '52500', 'measurand' => 'Energy.Active.Import.Register', 'unit' => 'Wh'],
                    ['value' => '11000', 'measurand' => 'Power.Active.Import', 'unit' => 'W'],
                ],
            ]],
        ]);

        $session->refresh();
        $this->assertEquals(2.5, (float) $session->energy_kwh);
        $this->assertEquals(11.0, $session->meta['power_kw']);

        $stop = $this->handler->handle('CP-T1', 'StopTransaction', [
            'transactionId' => $start['transactionId'], 'idTag' => 'CARD-001',
            'meterStop' => 60_000, 'timestamp' => now()->toIso8601String(), 'reason' => 'EVDisconnected',
        ]);

        $this->assertSame('Accepted', $stop['idTagInfo']['status']);
        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertEquals(10.0, (float) $session->energy_kwh);
        $this->assertEquals(300_000.0, (float) $session->cost_amount);
        $this->assertSame('EVDisconnected', $session->stop_reason);
        $this->assertEquals(700_000.0, (float) $this->wallet->fresh()->balance);
        $this->assertSame(1, PendingTransaction::where('charging_session_uuid', $session->uuid)->count());
        $this->assertEqualsCanonicalizing(
            ['charging.session.started', 'charging.session.stopped'],
            SyncOutbox::where('aggregate_uuid', $session->uuid)->pluck('event_type')->all(),
        );

        // StopTransaction تکراری (ارسال مجدد پس از قطع ارتباط) نباید دوباره برداشت کند.
        $this->handler->handle('CP-T1', 'StopTransaction', [
            'transactionId' => $start['transactionId'], 'meterStop' => 60_000, 'timestamp' => now()->toIso8601String(),
        ]);
        $this->assertEquals(700_000.0, (float) $this->wallet->fresh()->balance);
    }

    public function test_remote_start_links_to_start_transaction(): void
    {
        ChargePointCommander::markInsideServer(); // enqueue without waiting for the socket loop

        $session = app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');
        $call = OcppCall::where('action', 'RemoteStartTransaction')->firstOrFail();
        $this->assertSame(['connectorId' => 1, 'idTag' => 'CARD-001'], $call->payload);
        $this->assertSame('starting', $session->meta['ocpp_state']);

        $start = $this->handler->handle('CP-T1', 'StartTransaction', [
            'connectorId' => 1, 'idTag' => 'CARD-001', 'meterStart' => 0, 'timestamp' => now()->toIso8601String(),
        ]);

        $session->refresh();
        $this->assertSame($start['transactionId'], (int) $session->ocpp_transaction_id);
        $this->assertSame('charging', $session->meta['ocpp_state']);
        $this->assertSame('qr', $session->auth_method);
        $this->assertSame(1, EdgeChargingSession::count());
    }

    public function test_rejected_remote_start_cancels_session(): void
    {
        ChargePointCommander::markInsideServer();
        $session = app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');
        $call = OcppCall::where('action', 'RemoteStartTransaction')->firstOrFail();

        $this->handler->onCallResult($call, ['status' => 'Rejected']);

        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertSame('RemoteStartRejected', $session->stop_reason);
        $this->assertEquals(0.0, (float) $session->cost_amount);
    }

    public function test_start_is_refused_when_charger_offline(): void
    {
        $this->charger->update(['is_online' => false]);

        $this->expectException(\RuntimeException::class);
        app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');
    }

    public function test_connector_already_in_use_is_refused(): void
    {
        ChargePointCommander::markInsideServer();
        app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');

        $this->expectExceptionMessage('این کانکتور در حال استفاده است.');
        app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');
    }

    public function test_start_without_cable_times_out(): void
    {
        ChargePointCommander::markInsideServer();
        $session = app(EdgeChargingService::class)->startOnConnector($this->user, $this->connector, 'qr');
        EdgeChargingSession::whereKey($session->id)->update(['created_at' => now()->subMinutes(10)]);

        $this->handler->housekeeping();

        $this->assertSame('NoCableConnected', $session->fresh()->stop_reason);
    }

    public function test_low_balance_triggers_remote_stop(): void
    {
        $this->wallet->update(['balance' => 50_000]);
        $start = $this->handler->handle('CP-T1', 'StartTransaction', [
            'connectorId' => 1, 'idTag' => 'CARD-001', 'meterStart' => 0, 'timestamp' => now()->toIso8601String(),
        ]);

        $this->handler->handle('CP-T1', 'MeterValues', [
            'connectorId' => 1,
            'transactionId' => $start['transactionId'],
            'meterValue' => [['timestamp' => now()->toIso8601String(), 'sampledValue' => [['value' => '2000']]]],
        ]);

        $call = OcppCall::where('action', 'RemoteStopTransaction')->first();
        $this->assertNotNull($call);
        $this->assertSame(['transactionId' => $start['transactionId']], $call->payload);
    }

    public function test_unknown_id_tag_gets_invalid_transaction(): void
    {
        $start = $this->handler->handle('CP-T1', 'StartTransaction', [
            'connectorId' => 1, 'idTag' => 'STRANGER', 'meterStart' => 0, 'timestamp' => now()->toIso8601String(),
        ]);

        $this->assertSame('Invalid', $start['idTagInfo']['status']);
        $this->assertIsInt($start['transactionId']);
        $this->assertSame(0, EdgeChargingSession::count());
    }
}
