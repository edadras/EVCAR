<?php

namespace App\Services\Stations;

use App\Models\AnprEvent;
use App\Models\EdgeStation;
use App\Models\Station;
use App\Support\Persian;
use Illuminate\Support\Str;

class StationAnprService
{
    /** @return list<array<string, mixed>> */
    public function defaultCameras(string $stationName = 'ایستگاه'): array
    {
        return [
            [
                'id' => 'CAM-01',
                'name' => 'دوربین ورودی — '.$stationName,
                'rtsp_url' => '',
                'role' => 'entry',
                'enabled' => true,
                'plpr_profile' => 'default',
            ],
        ];
    }

    public function isEnabled(?EdgeStation $edge): bool
    {
        return $edge !== null && $edge->hasCapability('anpr');
    }

    /** @return list<array<string, mixed>> */
    public function camerasFor(EdgeStation $edge): array
    {
        $meta = $edge->meta ?? [];
        $cameras = $meta['anpr_cameras'] ?? null;

        if (is_array($cameras) && $cameras !== []) {
            return array_values($cameras);
        }

        return $this->defaultCameras($edge->station?->name ?? 'ایستگاه');
    }

    /**
     * @param  list<array<string, mixed>>  $cameras
     */
    public function saveConfig(Station $station, bool $enabled, array $cameras = []): void
    {
        $edge = $station->edgeStation;
        if (! $edge) {
            $edge = EdgeStation::create([
                'uuid' => (string) Str::uuid(),
                'station_id' => $station->id,
                'edge_code' => 'EDGE-'.Str::upper(Str::substr($station->code ?? 'ST', -6)),
                'api_token_hash' => hash('sha256', 'edge-'.$station->id.'-'.Str::random(16)),
                'hostname' => $station->local_hostname ?? 'station.local',
                'sync_status' => 'offline',
                'capabilities' => [],
                'meta' => [],
            ]);
        }

        $capabilities = collect($edge->capabilities ?? []);
        if ($enabled) {
            $capabilities = $capabilities->push('anpr')->unique()->values();
        } else {
            $capabilities = $capabilities->reject(fn ($c) => $c === 'anpr')->values();
        }

        if (! $capabilities->contains('ocpp')) {
            $capabilities->push('ocpp');
        }

        $normalizedCameras = $this->normalizeCameras($cameras, $station->name);

        $meta = $edge->meta ?? [];
        $meta['anpr_cameras'] = $normalizedCameras;
        $meta['anpr'] = [
            'engine' => 'plpr_lite',
            'plpr_url' => config('safircharge.anpr.plpr_url'),
            'min_confidence' => config('safircharge.anpr.min_confidence', 0.92),
            'auto_start_charging' => true,
        ];

        $edge->update([
            'capabilities' => $capabilities->values()->all(),
            'meta' => $meta,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $cameras
     * @return list<array<string, mixed>>
     */
    private function normalizeCameras(array $cameras, string $stationName): array
    {
        if ($cameras === []) {
            return $this->defaultCameras($stationName);
        }

        $out = [];
        foreach ($cameras as $i => $cam) {
            $id = trim((string) ($cam['id'] ?? ''));
            if ($id === '') {
                $id = 'CAM-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            }
            $out[] = [
                'id' => $id,
                'name' => trim((string) ($cam['name'] ?? 'دوربین '.($i + 1))) ?: 'دوربین '.($i + 1),
                'rtsp_url' => trim((string) ($cam['rtsp_url'] ?? '')),
                'role' => $cam['role'] ?? 'entry',
                'enabled' => (bool) ($cam['enabled'] ?? true),
                'plpr_profile' => $cam['plpr_profile'] ?? 'default',
                'cloud_connector_uuid' => $cam['cloud_connector_uuid'] ?? $cam['connector_uuid'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function summaryForList(Station $station): array
    {
        $edge = $station->edgeStation;
        $enabled = $this->isEnabled($edge);
        $today = AnprEvent::where('station_id', $station->id)->whereDate('detected_at', today())->count();
        $latest = AnprEvent::where('station_id', $station->id)->latest('detected_at')->first();

        return [
            'enabled' => $enabled,
            'cameras' => $enabled ? count($this->camerasFor($edge)) : 0,
            'today' => $today,
            'last_plate' => $latest ? Persian::digits($latest->plate_two).$latest->plate_letter.Persian::digits($latest->plate_three) : null,
            'last_time' => $latest?->detected_at ? Persian::digits($latest->detected_at->format('H:i')) : null,
            'anpr_url' => route('admin.anpr', ['station_id' => $station->id]),
        ];
    }

    /** @return array<string, mixed> */
    public function panelData(Station $station): array
    {
        $station->loadMissing('edgeStation');
        $edge = $station->edgeStation;
        $enabled = $this->isEnabled($edge);
        $cameras = $enabled && $edge ? $this->camerasFor($edge) : [];

        $events = AnprEvent::with(['user', 'vehicle'])
            ->where('station_id', $station->id)
            ->latest('detected_at')
            ->limit(8)
            ->get();

        $latest = $events->first();
        $presenter = app(\App\Services\Admin\AdminViewDataService::class);
        $bundle = app(\App\Services\Admin\AdminMetricsService::class)->anprKpis($station->id);

        $cameraRows = [];
        foreach ($cameras as $cam) {
            $lastForCam = AnprEvent::where('station_id', $station->id)
                ->where('meta->camera_source', $cam['id'])
                ->latest('detected_at')
                ->first();

            $cameraRows[] = [
                'id' => $cam['id'],
                'name' => $cam['name'],
                'rtsp_url' => $cam['rtsp_url'] ?? '',
                'enabled' => (bool) ($cam['enabled'] ?? true),
                'status' => $this->cameraStatus($edge, $cam, $lastForCam),
                'last_detection' => $lastForCam?->detected_at
                    ? Persian::digits($lastForCam->detected_at->format('Y/m/d H:i'))
                    : '—',
            ];
        }

        $demo = null;
        if ($latest && $latest->plate_two) {
            $row = $presenter->anprEventRow($latest);
            $demo = [
                'd2' => $row['p']['d2'],
                'let' => $row['p']['let'],
                'd3' => $row['p']['d3'],
                'prov' => $row['p']['prov'],
                'name' => $latest->user?->name ?? 'ثبت‌نشده',
                'station' => $station->name,
                'camera' => $latest->meta['camera_source'] ?? 'CAM-01',
                'time' => Persian::digits($latest->detected_at?->format('H:i:s') ?? ''),
                'status' => $row['status_text'],
                'pill' => $row['s'],
            ];
        }

        return [
            'enabled' => $enabled,
            'engine' => 'PLPR Lite (Edge)',
            'plpr_url' => config('safircharge.anpr.plpr_url'),
            'edge_code' => $edge?->edge_code,
            'cameras' => $cameraRows,
            'kpis' => [
                'today' => $bundle['auto_today'],
                'accuracy_pct' => $bundle['accuracy_pct'],
                'registered_plates' => $bundle['registered_plates'],
                'camera_count' => count($cameraRows),
            ],
            'events' => $events->map(fn (AnprEvent $e) => $presenter->anprEventRow($e))->values()->all(),
            'demo' => $demo,
            'last_event_id' => $latest?->id,
            'anpr_page_url' => route('admin.anpr', ['station_id' => $station->id]),
            'edit_url' => route('admin.stations.edit', $station),
        ];
    }

    /** @param  array<string, mixed>  $cam */
    private function cameraStatus(?EdgeStation $edge, array $cam, ?AnprEvent $last): string
    {
        if (! ($cam['enabled'] ?? true)) {
            return 'disabled';
        }
        if ($edge?->last_heartbeat_at && $edge->last_heartbeat_at->gt(now()->subMinutes(15))) {
            return 'online';
        }
        if ($last && $last->detected_at?->gt(now()->subHours(24))) {
            return 'idle';
        }

        return 'offline';
    }
}
