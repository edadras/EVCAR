<?php

namespace App\Services\Stations;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Station;
use App\Support\Persian;

class RemoteStationService
{
    public function __construct(private StationAnprService $anpr) {}

    /** @return array<string, mixed> */
    public function jsPayload(Station $station): array
    {
        $station->loadMissing(['chargers.connectors', 'edgeStation']);
        $live = $this->liveConnectors($station);

        return [
            'devs' => $live['connectors'],
            'stationName' => $station->name,
            'stationCode' => $station->code,
            'stationId' => $station->id,
            'hasEdge' => (bool) $station->edgeStation,
            'edgeOnline' => $live['edge_online'],
            'commandUrl' => route('admin.api.remote-command.store', $station),
            'commandsUrl' => route('admin.api.remote-command.recent', $station),
            'liveUrl' => route('admin.api.remote-command.live', $station),
            'anprLiveUrl' => route('admin.api.anpr.live'),
            'online' => $live['online'],
            'anpr' => $this->anpr->panelData($station),
        ];
    }

    /** @return array{online:bool,edge_online:bool,connectors:list<array<string,mixed>>} */
    public function liveConnectors(Station $station): array
    {
        $station->loadMissing(['chargers.connectors', 'edgeStation']);

        $connectorIds = $station->chargers->flatMap(fn ($ch) => $ch->connectors->pluck('id'));
        $activeSessions = ChargingSession::query()
            ->where('status', 'active')
            ->whereNull('stopped_at')
            ->whereIn('connector_id', $connectorIds)
            ->with('user')
            ->get()
            ->keyBy('connector_id');

        $edge = $station->edgeStation;
        $lastSeen = $edge?->last_heartbeat_at ?? $edge?->last_sync_at;
        $edgeOnline = $edge
            && in_array($edge->sync_status, ['online', 'synced'], true)
            && $lastSeen
            && $lastSeen->gt(now()->subMinutes(10));

        $devs = [];
        foreach ($station->chargers as $charger) {
            foreach ($charger->connectors as $connector) {
                $devs[] = $this->connectorRow($charger, $connector, $activeSessions->get($connector->id));
            }
        }

        $anyChargerOnline = $station->chargers->contains(fn ($ch) => $ch->is_online === true);

        return [
            'online' => $edgeOnline || $anyChargerOnline,
            'edge_online' => (bool) $edgeOnline,
            'connectors' => $devs,
        ];
    }

    /** @return array<string, mixed> */
    private function connectorRow($charger, Connector $connector, ?ChargingSession $session): array
    {
        $sessionActive = $session !== null;
        $ocpp = $sessionActive ? 'Charging' : ($connector->ocpp_status ?? 'Available');
        $chargerOnline = $charger->is_online === true;

        $st = match ($ocpp) {
            'Charging' => ['در حال شارژ', 'pill-green', 'ic-green'],
            'Faulted', 'Unavailable' => ['خطا', 'pill-red', 'ic-red'],
            'Reserved' => ['رزرو', 'pill-amber', 'ic-amber'],
            'Preparing', 'Finishing' => ['آماده‌سازی', 'pill-amber', 'ic-amber'],
            default => $chargerOnline ? ['آماده', 'pill-accent', 'ic-blue'] : ['آفلاین', 'pill', 'ic-amber'],
        };

        $maxKw = (int) ($connector->max_power_kw ?? $charger->max_power_kw ?? 22);
        $metrics = $this->metrics($session, $maxKw, $ocpp === 'Charging');
        $typeLabel = $charger->connector_type ?? 'Type2';
        if ($typeLabel === 'CCS2') {
            $typeLabel = 'DC CCS2';
        }

        $cpLabel = $charger->charge_point_id ?? ('CP-'.$charger->id);

        return [
            'id' => $cpLabel.' · C'.($connector->connector_number ?? $connector->id),
            'connectorId' => $connector->id,
            'chargePointId' => $charger->charge_point_id,
            'connectorNumber' => (int) ($connector->connector_number ?? 1),
            't' => $typeLabel.' · '.Persian::num($maxKw).' kW · '.$charger->charge_point_id,
            'st' => $st,
            'ocppStatus' => $ocpp,
            'chargerOnline' => $chargerOnline,
            'maxKw' => $maxKw,
            'pw' => $metrics['power'] > 0 ? Persian::num($metrics['power']).' kW' : '—',
            'power' => $metrics['power'],
            'voltage' => $metrics['voltage'],
            'current' => $metrics['current'],
            'temp' => $metrics['temp'],
            'energy' => $metrics['energy'],
            'duration_min' => $metrics['duration_min'],
            'user' => $session?->user?->name,
            'transactionId' => $session?->ocpp_transaction_id,
        ];
    }

    /** @return array{power:int,voltage:int,current:int,temp:?int,energy:float,duration_min:int} */
    private function metrics(?ChargingSession $session, int $maxKw, bool $isCharging): array
    {
        if (! $isCharging) {
            return [
                'power' => 0,
                'voltage' => 0,
                'current' => 0,
                'temp' => null,
                'energy' => 0,
                'duration_min' => 0,
            ];
        }

        $meta = $session?->meta ?? [];
        $power = (int) ($meta['power_kw'] ?? $maxKw);
        $durationMin = $session?->started_at ? (int) $session->started_at->diffInMinutes(now()) : 0;
        $energy = $session
            ? (float) (($session->energy_kwh > 0)
                ? $session->energy_kwh
                : round($power * ($durationMin / 60) * 0.85, 2))
            : 0;
        $voltage = (int) ($meta['voltage'] ?? ($power > 50 ? 400 : 230));
        $current = $voltage > 0 ? (int) round(($power * 1000) / $voltage) : 0;
        $temp = isset($meta['temp_c']) ? (int) $meta['temp_c'] : null;

        return [
            'power' => $power,
            'voltage' => $voltage,
            'current' => $current,
            'temp' => $temp,
            'energy' => $energy,
            'duration_min' => $durationMin,
        ];
    }
}
