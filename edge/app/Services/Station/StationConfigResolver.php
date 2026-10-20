<?php

namespace App\Services\Station;

use App\Models\StationIdentity;

class StationConfigResolver
{
    public function station(): ?StationIdentity
    {
        return StationIdentity::first();
    }

    /** @return array<string, mixed> */
    public function anpr(): array
    {
        $defaults = config('safircharge.anpr', []);
        $meta = $this->station()?->meta['anpr'] ?? [];

        return array_merge($defaults, is_array($meta) ? $meta : []);
    }

    /** @return list<array<string, mixed>> */
    public function anprCameras(): array
    {
        $cameras = $this->station()?->meta['anpr_cameras'] ?? [];

        if (is_array($cameras) && $cameras !== []) {
            return array_values($cameras);
        }

        return [[
            'id' => config('safircharge.anpr.default_camera', 'CAM-01'),
            'name' => 'دوربین ورودی',
            'rtsp_url' => '',
            'role' => 'entry',
            'enabled' => true,
            'plpr_profile' => 'default',
        ]];
    }

    /** @return array<string, mixed> */
    public function ocpp(): array
    {
        $defaults = config('safircharge.ocpp', []);
        $meta = $this->station()?->meta['ocpp'] ?? [];

        return array_merge($defaults, is_array($meta) ? $meta : []);
    }

    public function plprUrl(): string
    {
        $default = config('safircharge.anpr.plpr_url', 'http://host.docker.internal:8502');

        return rtrim((string) ($this->anpr()['plpr_url'] ?? $default), '/');
    }

    /** آدرس ابر: تنظیم پنل ایستگاه، در غیر این صورت CLOUD_API_URL. */
    public function cloudApiUrl(): string
    {
        return rtrim((string) ($this->station()?->cloud_api_url ?: config('services.cloud.api_url', '')), '/');
    }

    public function cloudSyncToken(): ?string
    {
        return $this->station()?->cloud_sync_token ?: config('services.cloud.sync_token');
    }
}
