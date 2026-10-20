<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\OcppCall;
use App\Models\QrToken;
use App\Models\Station;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Services\Ocpp\CloudOcppCommander;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Connector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $station = Station::create(['code' => 'ST-M', 'name' => 'Mobile station', 'status' => 'active', 'latitude' => 35.7, 'longitude' => 51.4]);
        $charger = Charger::create([
            'station_id' => $station->id, 'charge_point_id' => 'CP-M1', 'max_power_kw' => 22,
            'status' => 'active', 'is_online' => true, 'connected_at' => now(),
        ]);
        $this->connector = Connector::create([
            'charger_id' => $charger->id, 'connector_number' => 1,
            'ocpp_status' => 'Available', 'operational_status' => 'active',
        ]);
        QrToken::create([
            'uuid' => (string) Str::uuid(), 'token' => 'QR-M1', 'station_id' => $station->id,
            'charger_id' => $charger->id, 'connector_id' => $this->connector->id, 'status' => 'active',
        ]);
        $this->user = User::create([
            'name' => 'علی رضایی', 'email' => 'm@example.test', 'phone' => '09120000009',
            'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 2_000_000, 'currency' => 'IRR']);
        Tariff::create(['name' => 'Std', 'code' => 'STD', 'pricing_model' => 'per_kwh', 'price_per_kwh' => 30000, 'session_fee' => 0, 'is_default' => true, 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        CloudOcppCommander::markInsideServer(false);
        parent::tearDown();
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000009', 'password' => 'secret-password'])
            ->assertOk()->json('access_token');
    }

    public function test_login_and_read_screens(): void
    {
        $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000009', 'password' => 'wrong'])->assertStatus(422);
        $this->getJson('/api/v1/mobile/home')->assertUnauthorized();

        $auth = ['Authorization' => 'Bearer '.$this->token()];
        $this->getJson('/api/v1/mobile/home', $auth)->assertOk()
            ->assertJsonPath('first_name', 'علی')
            ->assertJsonPath('user.balance', 200_000)
            ->assertJsonPath('nearby_stations.0.code', 'ST-M');

        foreach (['map', 'wallet', 'account', 'history', 'charging', 'notifications', 'vehicles', 'rfid',
            'reservations', 'scan', 'support', 'tickets/form', 'settings', 'offers', 'legal/terms', 'navigator', 'context', 'me', 'charging/status'] as $screen) {
            $this->getJson('/api/v1/mobile/'.$screen, $auth)->assertOk();
        }

        $this->getJson('/api/v1/mobile/charger?station=ST-M', $auth)->assertOk()
            ->assertJsonPath('connectors.0.authorized', false)
            ->assertJsonPath('tariff.energy', 3000);
    }

    public function test_actions_share_web_app_logic_and_return_json(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->token()];

        $this->postJson('/api/v1/mobile/vehicles', ['brand_model' => 'MVM'], $auth)
            ->assertStatus(422)->assertJsonValidationErrors('plate_two');

        $this->postJson('/api/v1/mobile/vehicles', [
            'brand_model' => 'BYD Atto3', 'plate_two' => '12', 'plate_letter' => 'ب',
            'plate_three' => '345', 'plate_province' => '11', 'battery_kwh' => 60,
        ], $auth)->assertOk()->assertJsonPath('ok', true)->assertJsonPath('redirect', 'vehicle');
        $this->assertSame(1, Vehicle::where('user_id', $this->user->id)->count());

        $this->postJson('/api/v1/mobile/topup', ['amount' => 50000, 'gateway' => 'mellat'], $auth)
            ->assertOk()->assertJsonPath('redirect', 'topup/result');
        $this->getJson('/api/v1/mobile/wallet', $auth)->assertJsonPath('balance', 250_000);

        $this->postJson('/api/v1/mobile/settings', ['charge_target_soc' => 90], $auth)->assertOk()->assertJsonPath('prefs.charge_target_soc', 90);

        // بدون اسکن QR شروع شارژ مجاز نیست
        $this->postJson('/api/v1/mobile/charging/start', ['connector_id' => $this->connector->id], $auth)
            ->assertStatus(422)->assertJsonValidationErrors('charging');

        // اسکن QR → مجوز (به کاربر گره خورده، نه به نشست مرورگر) → شروع مستقیم از طریق OCPP ابری
        $this->getJson('/api/v1/mobile/charger?qr=QR-M1', $auth)
            ->assertOk()->assertJsonPath('qr_grant.type', 'qr')->assertJsonPath('connectors.0.authorized', true);

        CloudOcppCommander::markInsideServer();
        $this->postJson('/api/v1/mobile/charging/start', ['connector_id' => $this->connector->id, 'start_soc' => 30], $auth)
            ->assertOk()->assertJsonPath('redirect', 'charging');

        $session = ChargingSession::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('active', $session->status);
        $this->assertTrue(OcppCall::where('action', 'RemoteStartTransaction')->exists());
        $this->getJson('/api/v1/mobile/charging', $auth)->assertOk()
            ->assertJsonPath('session.station', 'Mobile station')
            ->assertJsonPath('live.goal', 90);

        $this->postJson('/api/v1/mobile/charging/stop', [], $auth)->assertOk()->assertJsonPath('ok', true);

        $this->postJson('/api/v1/mobile/auth/logout', [], $auth)->assertOk();
    }

    public function test_staff_cannot_use_mobile_api(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@example.test', 'password' => 'secret-password', 'role' => UserRole::SuperAdmin, 'status' => 'active']);
        $token = $admin->createToken('x')->plainTextToken;

        $this->getJson('/api/v1/mobile/home', ['Authorization' => 'Bearer '.$token])->assertForbidden();
    }
}
