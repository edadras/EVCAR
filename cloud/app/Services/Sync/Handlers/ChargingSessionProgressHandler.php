<?php

namespace App\Services\Sync\Handlers;

use App\Models\ChargingSession;
use App\Models\EdgeStation;

/**
 * به‌روزرسانی زندهٔ انرژی/هزینهٔ جلسهٔ در حال شارژ از روی کنتور شارژر (MeterValues در Edge).
 */
class ChargingSessionProgressHandler
{
    /** @param  array<string, mixed>  $payload */
    public function handle(EdgeStation $edge, array $payload): ?ChargingSession
    {
        $session = ChargingSession::where('edge_session_uuid', (string) ($payload['uuid'] ?? ''))->first();
        if (! $session || $session->status !== 'active') {
            return $session;
        }

        $session->update([
            'energy_kwh' => $payload['energy_kwh'] ?? $session->energy_kwh,
            'cost_amount' => $payload['cost_amount'] ?? $session->cost_amount,
            'ocpp_transaction_id' => $payload['ocpp_transaction_id'] ?? $session->ocpp_transaction_id,
            'meta' => array_merge($session->meta ?? [], $payload['meta'] ?? [], ['edge_progress_at' => now()->toIso8601String()]),
        ]);

        return $session;
    }
}
