<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeCommand;
use App\Models\EdgeStation;
use App\Models\Station;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Edge\EdgeCommandService;
use App\Services\Sync\SyncIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EdgeChargingSyncTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private EdgeStation $edge;

    private Connector $connector;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->station = Station::create(['code' => 'ST-1', 'name' => 'Station']);
        $this->edge = EdgeStation::create([
            'station_id' => $this->station->id,
            'edge_code' => 'EDGE-1',
            'api_token_hash' => hash('sha256', 'token'),
        ]);
        $charger = Charger::create([
            'station_id' => $this->station->id,
            'charge_point_id' => 'CP-1',
            'max_power_kw' => 22,
        ]);
        $this->connector = Connector::create([
            'charger_id' => $charger->id,
            'connector_number' => 1,
            'ocpp_status' => 'Available',
        ]);
        $this->user = User::create([
            'name' => 'Driver',
            'email' => 'driver@example.test',
            'phone' => '09120000000',
            'password' => 'secret-password',
            'role' => UserRole::Customer,
            'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 500_000, 'currency' => 'IRR']);
    }

    public function test_online_start_is_sent_to_edge_and_linked_when_edge_reports_back(): void
    {
        $service = app(CloudChargingSessionService::class);
        $session = $service->requestStart($this->user, $this->connector, 'qr', null, null, 80);

        $command = EdgeCommand::where('command_type', 'remote_start')->firstOrFail();
        $this->assertSame($session->uuid, $command->payload['cloud_session_uuid']);
        $this->assertSame('requested', $session->meta['edge_state']);
        $this->assertSame('Available', $this->connector->fresh()->ocpp_status, 'status must come from the charger, not be assumed');

        $edgeUuid = (string) Str::uuid();
        $this->ingest('charging.session.started', [
            'uuid' => $edgeUuid,
            'cloud_session_uuid' => $session->uuid,
            'cloud_user_uuid' => $this->user->uuid,
            'cloud_connector_uuid' => $this->connector->uuid,
            'ocpp_transaction_id' => 7,
            'meta' => ['ocpp_state' => 'charging'],
        ]);
        $this->ingest('charging.session.progress', [
            'uuid' => $edgeUuid, 'energy_kwh' => 3.25, 'cost_amount' => 97_500, 'meta' => ['power_kw' => 21.5],
        ]);

        $session->refresh();
        $this->assertSame($edgeUuid, $session->edge_session_uuid);
        $this->assertSame(1, ChargingSession::count(), 'no duplicate session for an online start');
        $this->assertEquals(3.25, (float) $session->energy_kwh);
        $this->assertSame(3.25, $service->liveMetrics($session)['energy']);

        $service->requestStop($session, $this->user);
        $this->assertSame(1, EdgeCommand::where('command_type', 'remote_stop')->count());
        $this->assertSame('active', $session->fresh()->status, 'billing waits for the charger meter');

        $this->ingest('wallet.debited', [
            'uuid' => (string) Str::uuid(), 'session_uuid' => $edgeUuid, 'cloud_user_uuid' => $this->user->uuid,
            'amount' => 120_000, 'balance_after' => 999_999,
        ]);
        $this->ingest('charging.session.stopped', [
            'uuid' => $edgeUuid, 'energy_kwh' => 4, 'cost_amount' => 120_000, 'stop_reason' => 'Remote', 'duration_seconds' => 600,
        ]);

        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertEquals(120_000.0, (float) $session->cost_amount);
        // موجودی ابر از موجودی خودش کم می‌شود، نه از balance_after کش Edge.
        $this->assertEquals(380_000.0, (float) $this->user->wallet()->first()->balance);
    }

    public function test_failed_remote_start_closes_session_without_charge(): void
    {
        $service = app(CloudChargingSessionService::class);
        $session = $service->requestStart($this->user, $this->connector, 'qr', null, null, 80);
        $command = EdgeCommand::where('command_type', 'remote_start')->firstOrFail();

        app(EdgeCommandService::class)->acknowledge($this->edge, $command->uuid, false, [], 'Charger offline');

        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertSame('EdgeStartFailed', $session->stop_reason);
        $this->assertEquals(0.0, (float) $session->cost_amount);
    }

    public function test_stop_before_edge_picks_up_cancels_command(): void
    {
        $service = app(CloudChargingSessionService::class);
        $session = $service->requestStart($this->user, $this->connector, 'qr', null, null, 80);

        $stopped = $service->requestStop($session, $this->user);

        $this->assertSame('stopped', $stopped->status);
        $this->assertSame('cancelled', EdgeCommand::where('command_type', 'remote_start')->value('status'));
    }

    public function test_unanswered_start_expires(): void
    {
        $service = app(CloudChargingSessionService::class);
        $session = $service->requestStart($this->user, $this->connector, 'qr', null, null, 80);
        ChargingSession::whereKey($session->id)->update(['created_at' => now()->subMinutes(10)]);

        $this->assertSame(1, $service->expireStaleRequests());
        $this->assertSame('EdgeTimeout', $session->fresh()->stop_reason);
    }

    public function test_device_status_events_update_connector_and_charger(): void
    {
        $this->ingest('connector.status', [
            'cloud_connector_uuid' => $this->connector->uuid, 'ocpp_status' => 'Faulted', 'at' => now()->toIso8601String(),
        ]);
        $this->ingest('charger.status', [
            'charge_point_id' => 'CP-1', 'is_online' => false, 'vendor' => 'ABB',
        ]);

        $this->assertSame('Faulted', $this->connector->fresh()->ocpp_status);
        $charger = $this->connector->charger()->first();
        $this->assertFalse((bool) $charger->is_online);
        $this->assertSame('ABB', $charger->vendor);
    }

    public function test_station_without_edge_is_refused_in_production_mode(): void
    {
        config(['safircharge.charging.allow_without_edge' => false]);
        $this->edge->delete();

        $this->expectException(\RuntimeException::class);
        app(CloudChargingSessionService::class)->requestStart($this->user, $this->connector->fresh(), 'qr', null, null, 80);
    }

    /** @param array<string, mixed> $payload */
    private function ingest(string $type, array $payload): void
    {
        $result = app(SyncIngestService::class)->ingest($this->edge, [[
            'event_uuid' => (string) Str::uuid(),
            'event_type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ]]);

        $this->assertSame([], $result['errors'], json_encode($result['errors']));
    }
}
