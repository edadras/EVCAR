<?php

namespace Tests\Feature;

use App\Models\CachedQrToken;
use App\Models\EdgeChargingSession;
use App\Models\OcppCall;
use App\Services\Ocpp\ChargePointCommander;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Ocpp\OcppFixtures;
use Tests\TestCase;

/** اپ موبایل در حالت محلی (WiFi ایستگاه، بدون اینترنت) با هدرهای کاربر ابری. */
class MobileEdgeApiTest extends TestCase
{
    use OcppFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ChargePointCommander::markInsideServer(false);
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $key = config('services.cloud.app_key') ?: config('app.key');

        return [
            'X-SC-User-UUID' => $this->user->cloud_user_uuid,
            'X-SC-Edge-Token' => hash_hmac('sha256', $this->user->cloud_user_uuid.'|'.now()->format('Y-m-d'), $key),
            'Accept' => 'application/json',
        ];
    }

    public function test_offline_qr_charge_flow(): void
    {
        $this->seedStation();
        CachedQrToken::create([
            'uuid' => (string) Str::uuid(), 'token' => 'QR-EDGE-1', 'status' => 'active',
            'cloud_connector_uuid' => $this->connector->cloud_connector_uuid, 'connector_number' => 1,
        ]);

        $this->getJson('/api/v1/app/connectors')->assertUnauthorized();

        $this->getJson('/api/v1/app/connectors', $this->headers())->assertOk()
            ->assertJsonPath('connectors.0.free', true)
            ->assertJsonPath('connectors.0.authorized', false)
            ->assertJsonPath('wallet_balance', 100000);

        $this->postJson('/api/v1/app/charging/start', ['connector_uuid' => $this->connector->uuid], $this->headers())
            ->assertStatus(422);

        $this->postJson('/api/v1/app/authorize', ['method' => 'qr', 'qr_token' => 'NOPE'], $this->headers())->assertStatus(422);
        $this->postJson('/api/v1/app/authorize', ['method' => 'qr', 'qr_token' => 'QR-EDGE-1'], $this->headers())
            ->assertOk()->assertJsonPath('connector_uuid', $this->connector->uuid);

        $this->getJson('/api/v1/app/connectors', $this->headers())->assertJsonPath('connectors.0.authorized', true);

        ChargePointCommander::markInsideServer(); // enqueue RemoteStart without waiting for a socket
        $this->postJson('/api/v1/app/charging/start', ['connector_uuid' => $this->connector->uuid, 'start_soc' => 30], $this->headers())
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertTrue(OcppCall::where('action', 'RemoteStartTransaction')->exists());
        $this->assertSame(1, EdgeChargingSession::where('status', 'active')->count());

        $this->getJson('/api/v1/app/charging/status', $this->headers())->assertOk()->assertJsonPath('active', true)->assertJsonPath('charge_point', 'CP-T1')->assertJsonPath('station', 'Test station');
    }
}
