<?php

namespace App\Services\App;

use App\Models\EdgeStation;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Str;

class ConnectivityService
{
    /** @return array<string, mixed> */
    public function build(?User $user = null): array
    {
        $cloudBase = rtrim(url('/'), '/');

        return [
            'cloudBase' => $cloudBase,
            'contextUrl' => route('app.api.context'),
            'userUuid' => $user?->uuid,
            'edgeToken' => $user ? $this->edgeUserToken($user) : null,
            'askWhenMultiple' => (bool) config('safircharge.connectivity.ask_when_multiple'),
            'timeoutMs' => (int) config('safircharge.connectivity.probe_timeout_ms', 2500),
            'candidates' => $this->candidates($cloudBase),
        ];
    }

    public function edgeUserToken(User $user): string
    {
        $payload = $user->uuid.'|'.now()->format('Y-m-d');

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    public function verifyEdgeUserToken(string $userUuid, string $token): bool
    {
        $expected = hash_hmac('sha256', $userUuid.'|'.now()->format('Y-m-d'), config('app.key'));
        if (hash_equals($expected, $token)) {
            return true;
        }

        $yesterday = hash_hmac('sha256', $userUuid.'|'.now()->subDay()->format('Y-m-d'), config('app.key'));

        return hash_equals($yesterday, $token);
    }

    /** @return list<array<string, mixed>> */
    private function candidates(string $cloudBase): array
    {
        $items = [
            [
                'id' => 'cloud',
                'kind' => 'cloud',
                'label' => 'مرکز ابری SafirCharge',
                'base' => $cloudBase,
                'healthPath' => '/up',
            ],
        ];

        $seen = [];
        foreach (config('safircharge.connectivity.edge_probe_urls', []) as $url) {
            $base = rtrim($url, '/');
            if (isset($seen[$base])) {
                continue;
            }
            $seen[$base] = true;
            $edgeMeta = $this->edgeMetaForProbeUrl($base);
            $items[] = [
                'id' => 'edge-'.Str::slug($edgeMeta['edge_code'] ?? parse_url($base, PHP_URL_HOST) ?: $base),
                'kind' => 'edge',
                'label' => $edgeMeta['label'],
                'base' => $base,
                'healthPath' => '/api/v1/health',
                'edgeCode' => $edgeMeta['edge_code'],
                'wifiSsid' => $edgeMeta['wifi_ssid'],
            ];
        }

        return $items;
    }

    /** @return array{label:string,edge_code:?string,wifi_ssid:?string} */
    private function edgeMetaForProbeUrl(string $base): array
    {
        $host = parse_url($base, PHP_URL_HOST) ?: '';
        $edge = EdgeStation::query()
            ->where('hostname', $host)
            ->orWhere('hostname', 'station.local')
            ->with('station')
            ->first();

        if (! $edge) {
            $station = Station::where('local_hostname', $host)->first();

            return [
                'label' => 'لبه محلی'.($station ? ' — '.$station->name : ' ('.$host.')'),
                'edge_code' => null,
                'wifi_ssid' => $station?->wifi_ssid,
            ];
        }

        return [
            'label' => 'لبه محلی — '.($edge->station?->name ?? $edge->edge_code),
            'edge_code' => $edge->edge_code,
            'wifi_ssid' => $edge->station?->wifi_ssid,
        ];
    }
}
