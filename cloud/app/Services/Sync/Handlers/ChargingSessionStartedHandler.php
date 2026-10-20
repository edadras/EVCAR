<?php

namespace App\Services\Sync\Handlers;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Str;

class ChargingSessionStartedHandler
{
    /** @param  array<string, mixed>  $payload */
    public function handle(EdgeStation $edge, array $payload): ChargingSession
    {
        $uuid = (string) ($payload['uuid'] ?? '');
        if ($uuid === '') {
            throw new \InvalidArgumentException('session uuid الزامی است.');
        }

        $existing = ChargingSession::where('edge_session_uuid', $uuid)->first();
        if ($existing) {
            return $existing;
        }

        $user = User::where('uuid', $payload['cloud_user_uuid'] ?? '')->first();
        $connector = Connector::where('uuid', $payload['cloud_connector_uuid'] ?? '')->first();
        $charger = $connector?->charger
            ?? Charger::where('uuid', $payload['cloud_charger_uuid'] ?? '')->first();

        $edgeMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        // جلسه‌ای که اپ آنلاین از ابر درخواست کرده بود (remote_start) — همان رکورد تکمیل می‌شود.
        if (! empty($payload['cloud_session_uuid'])) {
            $requested = ChargingSession::where('uuid', $payload['cloud_session_uuid'])->first();
            if ($requested) {
                $requested->update([
                    'edge_station_uuid' => $edge->uuid,
                    'edge_session_uuid' => $uuid,
                    'ocpp_transaction_id' => $payload['ocpp_transaction_id'] ?? $requested->ocpp_transaction_id,
                    'started_at' => $payload['started_at'] ?? $requested->started_at,
                    'synced_from_edge' => true,
                    'meta' => array_merge($requested->meta ?? [], $edgeMeta, ['edge_state' => $edgeMeta['ocpp_state'] ?? 'charging']),
                ]);
                $this->markCharging($connector, $charger);

                return $requested->fresh();
            }
        }

        $session = ChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $edge->station_id,
            'charger_id' => $charger?->id,
            'connector_id' => $connector?->id,
            'user_id' => $user?->id,
            'tariff_id' => Tariff::where('status', 'active')->orderByDesc('is_default')->value('id'),
            'edge_station_uuid' => $edge->uuid,
            'edge_session_uuid' => $uuid,
            'ocpp_transaction_id' => $payload['ocpp_transaction_id'] ?? null,
            'auth_method' => $payload['auth_method'] ?? 'wallet',
            'status' => 'active',
            'started_at' => $payload['started_at'] ?? now(),
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'synced_from_edge' => true,
            'meta' => array_merge($edgeMeta, ['edge_backed' => true]),
        ]);

        $this->markCharging($connector, $charger);

        return $session;
    }

    private function markCharging(?Connector $connector, ?Charger $charger): void
    {
        $connector?->update([
            'ocpp_status' => 'Charging',
            'status_updated_at' => now(),
        ]);
        $charger?->update([
            'is_online' => true,
            'last_heartbeat_at' => now(),
        ]);
    }
}
