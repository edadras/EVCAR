<?php

namespace App\Services\Sync\Handlers;

use App\Models\Charger;
use App\Models\Connector;
use App\Models\EdgeStation;

/**
 * وضعیت زندهٔ شارژر/کانکتور که Edge مستقیماً از شارژر (OCPP) دریافت کرده است.
 */
class DeviceStatusHandler
{
    /** @param  array<string, mixed>  $payload */
    public function connector(EdgeStation $edge, array $payload): void
    {
        $connector = $this->resolveConnector($edge, $payload);
        if (! $connector) {
            return;
        }

        $at = $payload['at'] ?? now();
        if ($connector->status_updated_at && $connector->status_updated_at->gt($at)) {
            return; // رویداد قدیمی‌تر از وضعیت فعلی
        }

        $connector->update([
            'ocpp_status' => (string) ($payload['ocpp_status'] ?? $connector->ocpp_status),
            'status_updated_at' => $at,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function charger(EdgeStation $edge, array $payload): void
    {
        $charger = $this->resolveCharger($edge, $payload);
        if (! $charger) {
            return;
        }

        $updates = [
            'is_online' => (bool) ($payload['is_online'] ?? false),
            'last_heartbeat_at' => $payload['at'] ?? now(),
        ];
        foreach (['vendor', 'model', 'firmware_version'] as $field) {
            if (! empty($payload[$field]) && $charger->isFillable($field)) {
                $updates[$field] = $payload[$field];
            }
        }

        $charger->update($updates);
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveCharger(EdgeStation $edge, array $payload): ?Charger
    {
        if (! empty($payload['cloud_charger_uuid'])) {
            $charger = Charger::where('uuid', $payload['cloud_charger_uuid'])->first();
            if ($charger) {
                return $charger;
            }
        }

        return Charger::where('station_id', $edge->station_id)
            ->where('charge_point_id', (string) ($payload['charge_point_id'] ?? ''))
            ->first();
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveConnector(EdgeStation $edge, array $payload): ?Connector
    {
        if (! empty($payload['cloud_connector_uuid'])) {
            $connector = Connector::where('uuid', $payload['cloud_connector_uuid'])->first();
            if ($connector) {
                return $connector;
            }
        }

        $charger = $this->resolveCharger($edge, $payload);

        return $charger
            ? Connector::where('charger_id', $charger->id)->where('connector_number', (int) ($payload['connector_number'] ?? 0))->first()
            : null;
    }
}
