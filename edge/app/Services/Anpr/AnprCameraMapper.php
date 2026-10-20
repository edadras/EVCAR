<?php

namespace App\Services\Anpr;

use App\Models\EdgeConnector;
use App\Services\Station\StationConfigResolver;

class AnprCameraMapper
{
    public function __construct(private StationConfigResolver $config) {}

    public function connectorForCamera(?string $cameraId): ?EdgeConnector
    {
        if (! $cameraId) {
            return null;
        }

        foreach ($this->config->anprCameras() as $camera) {
            if (($camera['id'] ?? '') !== $cameraId) {
                continue;
            }

            if (! ($camera['enabled'] ?? true)) {
                return null;
            }

            return $this->resolveConnector($camera);
        }

        return null;
    }

    /** @param array<string, mixed> $camera */
    private function resolveConnector(array $camera): ?EdgeConnector
    {
        if (! empty($camera['connector_id'])) {
            return EdgeConnector::with('charger')->find((int) $camera['connector_id']);
        }

        $uuid = $camera['connector_uuid'] ?? $camera['cloud_connector_uuid'] ?? null;
        if ($uuid) {
            return EdgeConnector::with('charger')
                ->where('uuid', $uuid)
                ->orWhere('cloud_connector_uuid', $uuid)
                ->first();
        }

        return null;
    }
}
