<?php

namespace App\Services\Panel;

use App\Models\CachedUser;
use App\Models\EdgeCharger;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Models\StationIdentity;
use App\Services\Charging\EdgeChargingService;
use App\Services\Sync\CloudConnectivity;
use App\Support\Persian;
use Illuminate\Support\Collection;

class PanelLiveService
{
    public function __construct(
        private EdgeChargingService $charging,
        private CloudConnectivity $cloud,
    ) {}

    /** @return array<string, mixed> */
    public function jsPayload(): array
    {
        $live = $this->liveConnectors();
        $station = StationIdentity::first();

        return [
            'devs' => $live['connectors'],
            'stationName' => $station?->name ?? 'ایستگاه',
            'stationCode' => $station?->edge_code ?? '—',
            'hasEdge' => true,
            'edgeOnline' => true,
            'online' => $live['online'],
            'cloudOnline' => $this->cloudReachable(),
            'offlineMode' => (bool) ($station?->offline_mode_enabled ?? true),
            'commandUrl' => route('panel.api.commands.store'),
            'commandsUrl' => route('panel.api.commands.recent'),
            'syncFeedUrl' => route('panel.api.sync.feed'),
            'liveUrl' => route('panel.api.live'),
        ];
    }

    /** @return array{online:bool,connectors:list<array<string,mixed>>} */
    public function liveConnectors(): array
    {
        $chargers = EdgeCharger::with('connectors')->orderBy('id')->get();
        $connectorIds = $chargers->flatMap(fn ($ch) => $ch->connectors->pluck('id'));
        $activeSessions = EdgeChargingSession::query()
            ->where('status', 'active')
            ->whereIn('connector_id', $connectorIds)
            ->with(['charger', 'connector'])
            ->get()
            ->keyBy('connector_id');

        $devs = [];
        foreach ($chargers as $charger) {
            foreach ($charger->connectors as $connector) {
                $devs[] = $this->connectorRow($charger, $connector, $activeSessions->get($connector->id));
            }
        }

        $anyOnline = $chargers->contains(fn ($ch) => $ch->is_online === true)
            || $activeSessions->isNotEmpty();

        return [
            'online' => $anyOnline || $devs !== [],
            'connectors' => $devs,
        ];
    }

    /** @return array<string, mixed> */
    private function connectorRow(EdgeCharger $charger, EdgeConnector $connector, ?EdgeChargingSession $session): array
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
        $typeLabel = $charger->meta['connector_type'] ?? 'Type2';
        $cpLabel = $charger->charge_point_id ?? ('CP-'.$charger->id);
        $userName = null;
        if ($session?->cached_user_uuid) {
            $userName = CachedUser::where('uuid', $session->cached_user_uuid)->value('name');
        }

        return [
            'id' => $cpLabel.' · C'.($connector->connector_number ?? $connector->id),
            'connectorId' => $connector->id,
            'chargePointId' => $charger->charge_point_id,
            'connectorNumber' => (int) ($connector->connector_number ?? 1),
            't' => $typeLabel.' · '.Persian::num($maxKw).' kW · '.$cpLabel,
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
            'user' => $userName,
            'sessionUuid' => $session?->uuid,
        ];
    }

    /** @return array{power:int,voltage:int,current:int,temp:?int,energy:float,duration_min:int} */
    private function metrics(?EdgeChargingSession $session, int $maxKw, bool $isCharging): array
    {
        if (! $session || ! $isCharging) {
            return ['power' => 0, 'voltage' => 0, 'current' => 0, 'temp' => null, 'energy' => 0, 'duration_min' => 0];
        }

        $live = $this->charging->liveMetrics($session);
        $power = (int) ($live['power'] ?: $maxKw);
        $energy = (float) $live['energy'];
        $secs = (int) $live['secs'];
        $voltage = $power > 0 ? 400 : 0;
        $current = $power > 0 && $voltage > 0 ? (int) round(($power * 1000) / $voltage) : 0;

        return [
            'power' => $power,
            'voltage' => $voltage,
            'current' => $current,
            'temp' => $session->meta['connector_temp'] ?? null,
            'energy' => $energy,
            'duration_min' => (int) ceil($secs / 60),
        ];
    }

    public function cloudReachable(): bool
    {
        return $this->cloud->isReachable();
    }

    /** @return Collection<int, EdgeChargingSession> */
    public function recentSessions(int $limit = 30): Collection
    {
        return EdgeChargingSession::with(['charger', 'connector'])
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }
}
