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

        $session = ChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $edge->station_id,
            'charger_id' => $charger?->id,
            'connector_id' => $connector?->id,
            'user_id' => $user?->id,
            'tariff_id' => Tariff::where('status', 'active')->value('id'),
            'edge_station_uuid' => $edge->uuid,
            'edge_session_uuid' => $uuid,
            'auth_method' => $payload['auth_method'] ?? 'wallet',
            'status' => 'active',
            'started_at' => $payload['started_at'] ?? now(),
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'synced_from_edge' => true,
            'meta' => $payload['meta'] ?? [],
        ]);

        if ($connector) {
            $connector->update([
                'ocpp_status' => 'Charging',
                'status_updated_at' => now(),
            ]);
        }
        if ($charger) {
            $charger->update([
                'is_online' => true,
                'last_heartbeat_at' => now(),
            ]);
        }

        return $session;
    }
}
