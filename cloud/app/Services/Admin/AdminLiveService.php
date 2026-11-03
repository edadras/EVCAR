<?php

namespace App\Services\Admin;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\OperationalAlert;
use App\Models\RepairOrder;
use App\Models\SessionMeterValue;
use App\Models\StationReview;
use App\Models\SupportTicket;
use App\Models\SyncInbox;
use App\Models\UserNotification;
use App\Support\Persian;

/** JSON snapshots polled by admin pages so numbers update without a page reload. */
class AdminLiveService
{
    public function __construct(
        private AdminMetricsService $metrics,
        private AdminViewDataService $viewData,
    ) {}

    /** @return array<string, int> */
    public function nav(): array
    {
        return [
            'sessions' => ChargingSession::where('status', 'active')->count(),
            'support' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'alerts' => OperationalAlert::where('status', 'open')->count(),
            'reviews' => StationReview::where('rating', '<=', 2)->whereNull('reply')->where('is_hidden', false)->count(),
            'online' => Charger::where('is_online', true)->count(),
            'chargers' => Charger::count(),
        ];
    }

    public function dashboard(): array
    {
        $kpis = $this->metrics->networkKpis();
        $kpis['load_kw'] = round($this->metrics->liveLoadKw(), 1);

        return [
            'kpis' => $kpis,
            'breakdown' => $this->metrics->connectorBreakdown(),
            'chart' => $this->metrics->hourlyChart(),
            'connectorCounts' => $this->connectorTypeCounts(),
            'sessions' => $this->activeSessionRows(20),
            'alerts' => $this->alertRows(6),
            'nav' => $this->nav(),
            'at' => now()->toIso8601String(),
        ];
    }

    public function monitoring(): array
    {
        $m = $this->metrics->monitoring();
        $edgeTotal = EdgeStation::count();
        $edgeOffline = EdgeStation::where('sync_status', '!=', 'online')->count();
        $notifToday = UserNotification::whereDate('sent_at', today())->count();
        $notifRead = UserNotification::whereDate('sent_at', today())->whereNotNull('read_at')->count();

        return [
            'kpis' => [
                'online_connectors' => $m['online_connectors'],
                'charging_connectors' => $m['charging_connectors'],
                'load_kw' => $m['load_kw'],
                'sync_health' => $m['sync_health'],
                'active_faults' => $m['active_faults'],
                'chargers_online' => $m['chargers_online'],
                'chargers_total' => $m['chargers_total'],
            ],
            'health' => [
                'pending_queue' => SyncInbox::where('status', 'pending')->count(),
                'edge_total' => $edgeTotal,
                'edge_offline' => $edgeOffline,
                'notif_today' => $notifToday,
                'notif_read_pct' => $notifToday > 0 ? (int) round($notifRead / $notifToday * 100) : null,
            ],
            'loadSeries' => $this->loadSeries(60),
            'stations' => $this->viewData->monitoringStationsJs(),
            'nav' => $this->nav(),
            'at' => now()->toIso8601String(),
        ];
    }

    public function sessions(): array
    {
        return array_merge($this->viewData->sessionsJs(), ['nav' => $this->nav()]);
    }

    public function stations(): array
    {
        $kpis = $this->metrics->networkKpis();

        return [
            'stations' => $this->viewData->stationsPageJs(),
            'utilization' => $kpis['utilization_percent'],
            'nav' => $this->nav(),
            'at' => now()->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function activeSessionRows(int $limit): array
    {
        return ChargingSession::with(['station.edgeStation', 'charger', 'user', 'connector'])
            ->where('status', 'active')
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => $this->viewData->sessionRow($s, true))
            ->values()
            ->all();
    }

    /** @return list<array{title: string, sub: string, level: string, label: string, url: string}> */
    public function alertRows(int $limit): array
    {
        $rows = OperationalAlert::with('station')
            ->where('status', 'open')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->latest('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(fn (OperationalAlert $a) => [
                'title' => $a->message,
                'sub' => ($a->station?->name ?? '—').' · '.($a->last_seen_at ?? $a->opened_at)?->diffForHumans(),
                'level' => $a->severity === 'critical' ? 'red' : ($a->severity === 'warning' ? 'amber' : 'blue'),
                'label' => match ($a->severity) {
                    'critical' => 'بحرانی',
                    'warning' => 'هشدار',
                    default => 'اطلاع',
                },
                'url' => route('admin.alerts.index'),
            ]);

        $tickets = SupportTicket::with('station')
            ->whereIn('priority', ['high', 'critical'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'title' => $t->subject ?? 'تیکت پشتیبانی',
                'sub' => ($t->station?->name ?? '—').' · '.$t->created_at?->diffForHumans(),
                'level' => $t->priority === 'critical' ? 'red' : 'amber',
                'label' => $t->priority === 'critical' ? 'بحرانی' : 'تیکت مهم',
                'url' => route('admin.support.ticket', $t),
            ]);

        $repairs = RepairOrder::with('station')
            ->where('status', 'open')
            ->latest()
            ->limit(2)
            ->get()
            ->map(fn (RepairOrder $r) => [
                'title' => 'سفارش تعمیر #'.Persian::digits($r->id),
                'sub' => ($r->station?->name ?? '—').' · '.$r->created_at?->diffForHumans(),
                'level' => 'amber',
                'label' => 'تعمیر',
                'url' => route('admin.repairs'),
            ]);

        return $rows->concat($tickets)->concat($repairs)->take($limit)->values()->all();
    }

    /** @return array{dc: array{count: int, rated_kw: float}, ac: array{count: int, rated_kw: float}} */
    public function connectorTypeCounts(): array
    {
        $acTypes = ['Type2', 'Type1', 'AC', 'GBT-AC'];
        $connectors = Connector::with('charger:id,connector_type,max_power_kw')->get(['id', 'charger_id', 'max_power_kw']);
        $groups = $connectors->groupBy(fn ($c) => in_array($c->charger?->connector_type, $acTypes, true) ? 'ac' : 'dc');
        $out = [];
        foreach (['dc', 'ac'] as $k) {
            $g = $groups->get($k, collect());
            $out[$k] = [
                'count' => $g->count(),
                'rated_kw' => $g->isEmpty() ? 0.0 : round((float) $g->avg(fn ($c) => (float) ($c->max_power_kw ?: $c->charger?->max_power_kw ?: 0)), 1),
            ];
        }

        return $out;
    }

    /**
     * Network power (kW) per minute, summed over sessions, from reported meter values.
     *
     * @return list<array{t: string, kw: float}>
     */
    public function loadSeries(int $minutes): array
    {
        $since = now()->subMinutes($minutes - 1)->startOfMinute();
        $samples = SessionMeterValue::where('sampled_at', '>=', $since)
            ->whereNotNull('power_w')
            ->orderBy('sampled_at')
            ->get(['charging_session_id', 'sampled_at', 'power_w']);

        $bySession = [];
        foreach ($samples as $s) {
            $idx = (int) floor(($s->sampled_at->getTimestamp() - $since->getTimestamp()) / 60);
            if ($idx >= 0 && $idx < $minutes) {
                $bySession[$s->charging_session_id][$idx] = (float) $s->power_w;
            }
        }

        $totals = array_fill(0, $minutes, 0.0);
        foreach ($bySession as $points) {
            $last = null;
            $lastIdx = -100;
            for ($i = 0; $i < $minutes; $i++) {
                if (isset($points[$i])) {
                    $last = $points[$i];
                    $lastIdx = $i;
                }
                // Chargers sample every 1–3 min; carry the last reading across short gaps only.
                if ($last !== null && $i - $lastIdx <= 3) {
                    $totals[$i] += $last;
                }
            }
        }

        $series = [];
        foreach ($totals as $i => $w) {
            $series[] = ['t' => Persian::digits($since->copy()->addMinutes($i)->format('H:i')), 'kw' => round($w / 1000, 2)];
        }

        return $series;
    }
}
