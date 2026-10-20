<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\OcppCall;
use App\Models\OperationalAlert;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\Tariff;
use App\Models\TariffRule;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\TariffCalculator;
use App\Services\Ocpp\CloudChargePointHandler;
use App\Services\Ocpp\CloudOcppCommander;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectOcppTest extends TestCase
{
    use RefreshDatabase;

    private Charger $charger;

    private Connector $connector;

    private User $user;

    private CloudChargePointHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $station = Station::create(['code' => 'ST-D', 'name' => 'Direct station', 'status' => 'active']);
        $this->charger = Charger::create([
            'station_id' => $station->id, 'charge_point_id' => 'CP-D1', 'max_power_kw' => 60,
            'status' => 'active', 'is_online' => true, 'connected_at' => now(),
        ]);
        $this->connector = Connector::create([
            'charger_id' => $this->charger->id, 'connector_number' => 1,
            'ocpp_status' => 'Available', 'operational_status' => 'active',
        ]);
        $this->user = User::create([
            'name' => 'Driver', 'email' => 'd@example.test', 'phone' => '09120000001',
            'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 1_000_000, 'currency' => 'IRR']);
        RfidCard::create(['id_tag' => 'CARD-D', 'user_id' => $this->user->id, 'status' => 'active']);
        Tariff::create(['name' => 'Std', 'code' => 'STD', 'pricing_model' => 'per_kwh', 'price_per_kwh' => 10000, 'session_fee' => 0, 'is_default' => true, 'status' => 'active']);

        $this->handler = app(CloudChargePointHandler::class);
    }

    protected function tearDown(): void
    {
        CloudOcppCommander::markInsideServer(false);
        parent::tearDown();
    }

    public function test_station_without_edge_charges_directly_through_cloud_ocpp(): void
    {
        $this->assertTrue($this->charger->connectsDirectly());
        CloudOcppCommander::markInsideServer(); // enqueue RemoteStart without waiting for a socket

        $session = app(CloudChargingSessionService::class)->requestStart($this->user, $this->connector, 'qr', null, null, 80, 30);
        $this->assertTrue($session->meta['direct_ocpp']);
        $this->assertSame(['connectorId' => 1, 'idTag' => 'CARD-D'], OcppCall::where('action', 'RemoteStartTransaction')->first()->payload);

        $start = $this->handler->handle('CP-D1', 'StartTransaction', ['connectorId' => 1, 'idTag' => 'CARD-D', 'meterStart' => 1000, 'timestamp' => now()->toIso8601String()]);
        $this->assertSame('Accepted', $start['idTagInfo']['status']);
        $this->handler->handle('CP-D1', 'MeterValues', ['connectorId' => 1, 'transactionId' => $start['transactionId'], 'meterValue' => [[
            'timestamp' => now()->toIso8601String(),
            'sampledValue' => [['value' => '6000'], ['value' => '50000', 'measurand' => 'Power.Active.Import', 'unit' => 'W']],
        ]]]);
        $this->assertEquals(5.0, (float) $session->fresh()->energy_kwh);

        $this->handler->handle('CP-D1', 'StopTransaction', ['transactionId' => $start['transactionId'], 'meterStop' => 21000, 'timestamp' => now()->toIso8601String(), 'reason' => 'EVDisconnected']);

        $session->refresh();
        $this->assertSame('stopped', $session->status);
        $this->assertEquals(20.0, (float) $session->energy_kwh);
        $this->assertEquals(200_000.0, (float) $session->cost_amount);
        $this->assertEquals(800_000.0, (float) $this->user->wallet()->first()->balance);
        $this->assertSame(1, ChargingSession::count());
    }

    public function test_rfid_on_charger_starts_session_and_unknown_tag_is_rejected(): void
    {
        $ok = $this->handler->handle('CP-D1', 'StartTransaction', ['connectorId' => 1, 'idTag' => 'card-d', 'meterStart' => 0, 'timestamp' => now()->toIso8601String()]);
        $this->assertSame('Accepted', $ok['idTagInfo']['status']);
        $this->assertSame('rfid', ChargingSession::first()->auth_method);

        $bad = $this->handler->handle('CP-D1', 'Authorize', ['idTag' => 'NOPE']);
        $this->assertSame('Invalid', $bad['idTagInfo']['status']);
    }

    public function test_offline_direct_charger_refuses_start(): void
    {
        config(['safircharge.charging.allow_without_edge' => false]);
        $this->charger->update(['is_online' => false, 'connected_at' => null]);

        $this->expectExceptionMessage('شارژر به سرور متصل نیست');
        app(CloudChargingSessionService::class)->requestStart($this->user, $this->connector->fresh(), 'qr', null, null, 80, 30);
    }

    public function test_faulted_connector_raises_alert_and_recovery_resolves_it(): void
    {
        $this->handler->handle('CP-D1', 'StatusNotification', ['connectorId' => 1, 'errorCode' => 'GroundFailure', 'status' => 'Faulted']);
        $this->assertSame(1, OperationalAlert::where('status', 'open')->where('type', 'connector_faulted')->count());

        $this->handler->handle('CP-D1', 'StatusNotification', ['connectorId' => 1, 'errorCode' => 'NoError', 'status' => 'Available']);
        $this->assertSame(0, OperationalAlert::where('status', 'open')->count());
    }

    public function test_off_peak_rule_and_idle_fee(): void
    {
        $tariff = Tariff::first();
        $tariff->update(['meta' => ['idle_fee_per_minute' => 1000, 'idle_grace_minutes' => 5]]);
        TariffRule::create(['tariff_id' => $tariff->id, 'rule_type' => 'off_peak', 'start_time' => '00:00', 'end_time' => '06:00', 'price_per_kwh' => 6000, 'multiplier' => 1, 'priority' => 1]);
        $calc = app(TariffCalculator::class);

        $night = CarbonImmutable::parse('2026-10-20 02:00', 'Asia/Tehran');
        $day = CarbonImmutable::parse('2026-10-20 14:00', 'Asia/Tehran');

        $this->assertEquals(60_000.0, $calc->cost($tariff->fresh(), 10, 1800, $night));
        $this->assertEquals(100_000.0, $calc->cost($tariff->fresh(), 10, 1800, $day));
        // ۱۵ دقیقه اشغال، ۵ دقیقه مجاز → ۱۰ دقیقه × ۱۰۰۰
        $this->assertEquals(110_000.0, $calc->cost($tariff->fresh(), 10, 1800, $day, 15 * 60));
        $this->assertTrue(TariffCalculator::inWindow('23:30:00', '22:00', '06:00'));
    }
}
