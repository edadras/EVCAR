<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\DeviceToken;
use App\Models\Station;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->station = Station::create(['code' => 'ST-N', 'name' => 'Notify station', 'status' => 'active', 'latitude' => 35.7, 'longitude' => 51.4]);
        $this->user = User::create([
            'name' => 'Ali', 'email' => 'n@example.test', 'phone' => '09120000055',
            'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 300_000, 'currency' => 'IRR']);
    }

    private function auth(): array
    {
        $t = $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000055', 'password' => 'secret-password'])->json('access_token');

        return ['Authorization' => 'Bearer '.$t];
    }

    private function activeSession(): ChargingSession
    {
        $charger = Charger::firstOrCreate(['charge_point_id' => 'CP-N'], ['station_id' => $this->station->id, 'max_power_kw' => 22, 'status' => 'active']);
        $connector = Connector::firstOrCreate(['charger_id' => $charger->id, 'connector_number' => 1], ['ocpp_status' => 'Charging', 'operational_status' => 'active']);

        return ChargingSession::create([
            'uuid' => (string) Str::uuid(), 'station_id' => $this->station->id, 'charger_id' => $charger->id,
            'connector_id' => $connector->id, 'user_id' => $this->user->id, 'auth_method' => 'qr', 'status' => 'active', 'started_at' => now()->subMinutes(20),
        ]);
    }

    public function test_finished_session_creates_notification_low_balance_and_fcm_push(): void
    {
        // حساب سرویس آزمایشی با کلید RSA واقعی برای امضای JWT
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'sc-test', 'client_email' => 'svc@sc-test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.fcm.credentials' => $path]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/sc-test/messages/1']),
        ]);

        $auth = $this->auth();
        $this->postJson('/api/v1/mobile/devices', ['token' => 'device-token-1', 'platform' => 'android'], $auth)
            ->assertOk()->assertJsonPath('push_enabled', true);
        $this->assertSame(1, DeviceToken::count());

        $session = $this->activeSession();
        $session->update(['status' => 'stopped', 'stopped_at' => now(), 'energy_kwh' => 7.5, 'cost_amount' => 225_000]);

        $types = UserNotification::where('user_id', $this->user->id)->pluck('type')->all();
        $this->assertContains('session_complete', $types);
        $this->assertContains('low_balance', $types); // ۳۰ هزار تومان < ۵۰ هزار تومان
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/sc-test/messages:send')
            && $r['message']['token'] === 'device-token-1'
            && $r->hasHeader('Authorization', 'Bearer ya29.test'));

        $res = $this->getJson('/api/v1/mobile/notifications/since?after=0', $auth)->assertOk();
        $this->assertCount(2, $res->json('items'));
        $this->getJson('/api/v1/mobile/notifications/since?after='.$res->json('latest_id'), $auth)->assertJsonCount(0, 'items');

        // هشدار کمبود موجودی در ۲۴ ساعت تکرار نمی‌شود
        $this->activeSession()->update(['status' => 'stopped', 'energy_kwh' => 1, 'cost_amount' => 10_000]);
        $this->assertSame(1, UserNotification::where('type', 'low_balance')->count());

        $this->deleteJson('/api/v1/mobile/devices', ['token' => 'device-token-1'], $auth)->assertOk();
        $this->assertSame(0, DeviceToken::count());
        @unlink($path);
    }

    public function test_push_disabled_by_preference_still_stores_inbox_notification(): void
    {
        $this->user->update(['meta' => ['app_prefs' => ['notify_push' => false, 'notify_low_balance' => false]]]);
        Http::fake();
        $this->activeSession()->update(['status' => 'stopped', 'energy_kwh' => 3, 'cost_amount' => 90_000]);

        $this->assertSame(['session_complete'], UserNotification::pluck('type')->all());
        Http::assertNothingSent();
    }

    public function test_location_updates_distances_and_favorites_toggle(): void
    {
        $auth = $this->auth();
        $this->postJson('/api/v1/mobile/location', ['lat' => 10, 'lng' => 10], $auth)->assertStatus(422);
        $this->postJson('/api/v1/mobile/location', ['lat' => 35.7001, 'lng' => 51.4001], $auth)->assertOk();
        $this->assertEqualsWithDelta(35.7001, $this->user->fresh()->meta['lat'], 0.0001);

        $this->postJson('/api/v1/mobile/favorites/ST-N', [], $auth)->assertOk()->assertJsonPath('favorite', true);
        $this->getJson('/api/v1/mobile/home', $auth)->assertJsonPath('nearby_stations.0.favorite', true);
        $this->getJson('/api/v1/mobile/charger?station=ST-N', $auth)->assertJsonPath('station.favorite', true);
        $this->postJson('/api/v1/mobile/favorites/ST-N', [], $auth)->assertJsonPath('favorite', false);
        $this->postJson('/api/v1/mobile/favorites/NOPE', [], $auth)->assertNotFound();
    }
}
