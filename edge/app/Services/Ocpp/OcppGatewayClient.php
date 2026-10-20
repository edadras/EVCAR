<?php

namespace App\Services\Ocpp;

use App\Services\Station\StationConfigResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OcppGatewayClient
{
    public function __construct(private StationConfigResolver $stationConfig) {}

    public function configured(): bool
    {
        $ocpp = $this->stationConfig->ocpp();

        return (bool) ($ocpp['api_url'] ?? '')
            && (bool) ($ocpp['email'] ?? '')
            && (bool) ($ocpp['password'] ?? '');
    }

    /** @return array<string, mixed> */
    private function ocpp(): array
    {
        return $this->stationConfig->ocpp();
    }

    /** @return array<string, mixed> */
    public function send(string $chargePointId, string $action, array $payload = []): array
    {
        return $this->request('post', '/ocpp/send', [
            'charge_point_id' => $chargePointId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }

    /** @return array<string, mixed> */
    public function testAction(string $action, int $chargerId, array $payload = []): array
    {
        return $this->request('post', '/tests/'.$action, [
            'charger_id' => $chargerId,
            'payload' => $payload,
        ]);
    }

    /** @return array<string, mixed> */
    public function remoteStopCharger(int $chargerId): array
    {
        return $this->request('post', '/chargers/'.$chargerId.'/remote-stop');
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, array $body = []): array
    {
        if (! $this->configured()) {
            throw new \RuntimeException('OCPP Gateway پیکربندی نشده (OCPP_API_URL).');
        }

        $ocpp = $this->ocpp();
        $base = rtrim((string) ($ocpp['api_url'] ?? ''), '/');
        $url = $base.$path;

        try {
            $response = Http::timeout(20)
                ->withToken($this->token())
                ->acceptJson()
                ->{$method}($url, $body);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('اتصال به OCPP Gateway محلی برقرار نشد: '.$e->getMessage());
        }

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
            throw new \RuntimeException('احراز هویت OCPP Gateway ناموفق بود.');
        }

        if (! $response->successful()) {
            $msg = $response->json('message') ?? $response->body();
            throw new \RuntimeException('OCPP Gateway: HTTP '.$response->status().' — '.$msg);
        }

        return $response->json() ?? [];
    }

    private function token(): string
    {
        return Cache::remember($this->cacheKey(), 3000, function () {
            $ocpp = $this->ocpp();
            $base = rtrim((string) ($ocpp['api_url'] ?? ''), '/');
            $response = Http::timeout(10)->post($base.'/auth/login', [
                'email' => $ocpp['email'] ?? '',
                'password' => $ocpp['password'] ?? '',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('ورود به OCPP Gateway ناموفق: HTTP '.$response->status());
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('توکن OCPP Gateway دریافت نشد.');
            }

            return $token;
        });
    }

    private function cacheKey(): string
    {
        return 'ocpp_gateway_token:'.md5((string) ($this->ocpp()['api_url'] ?? ''));
    }
}
