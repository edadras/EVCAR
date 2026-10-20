<?php

namespace App\Services\Sync\Handlers;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;

class ChargingSessionStoppedHandler
{
    /** @param  array<string, mixed>  $payload */
    public function handle(EdgeStation $edge, array $payload): ChargingSession
    {
        $uuid = (string) ($payload['uuid'] ?? '');
        $session = ChargingSession::where('edge_session_uuid', $uuid)->first();

        if (! $session) {
            throw new \RuntimeException("جلسه edge یافت نشد: {$uuid}");
        }

        $session->update([
            'status' => 'stopped',
            'stopped_at' => $payload['stopped_at'] ?? now(),
            'duration_seconds' => (int) ($payload['duration_seconds'] ?? 0),
            'energy_kwh' => $payload['energy_kwh'] ?? $session->energy_kwh,
            'cost_amount' => $payload['cost_amount'] ?? $session->cost_amount,
            'synced_from_edge' => true,
            'meta' => array_merge($session->meta ?? [], $payload['meta'] ?? []),
        ]);

        if ($session->connector_id) {
            Connector::where('id', $session->connector_id)->update([
                'ocpp_status' => 'Available',
                'status_updated_at' => now(),
            ]);
        }

        return $session->fresh();
    }
}
