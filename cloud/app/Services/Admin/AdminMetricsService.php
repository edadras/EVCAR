<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AnprEvent;
use App\Models\Campaign;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\QrToken;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Models\RepairOrder;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\Settlement;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\SyncInbox;
use App\Models\SystemSetting;
use App\Models\Tariff;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Persian;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminMetricsService
{
    public function __construct(
        private ConnectorAvailabilityService $availability,
    ) {}
    public function networkKpis(): array
    {
        $totalChargers = Charger::count();
        $onlineChargers = Charger::where('is_online', true)->count();
        $totalConnectors = Connector::count();
        $chargingConnectors = Connector::where('ocpp_status', 'Charging')->count();
        $activeSessions = ChargingSession::where('status', 'active')->count();

        $todayEnergy = ChargingSession::whereDate('started_at', today())->sum('energy_kwh');
        $yesterdayEnergy = ChargingSession::whereDate('started_at', today()->subDay())->sum('energy_kwh');
        $todayRevenue = ChargingSession::whereDate('started_at', today())->sum('cost_amount');
        $yesterdayRevenue = ChargingSession::whereDate('started_at', today()->subDay())->sum('cost_amount');

        $energyTrend = $yesterdayEnergy > 0
            ? round((($todayEnergy - $yesterdayEnergy) / $yesterdayEnergy) * 100, 1)
            : 0;
        $revenueTrend = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;

        $utilization = $totalConnectors > 0
            ? round(($chargingConnectors / $totalConnectors) * 100, 1)
            : 0;

        return [
            'chargers_online' => $onlineChargers,
            'chargers_total' => $totalChargers,
            'charger_uptime' => $totalChargers > 0 ? round(($onlineChargers / $totalChargers) * 100, 1) : 0,
            'today_energy_kwh' => round($todayEnergy, 1),
            'energy_trend' => $energyTrend,
            'today_revenue' => $todayRevenue,
            'today_revenue_million' => round($todayRevenue / 10_000_000, 1),
            'revenue_trend' => $revenueTrend,
            'active_sessions' => $activeSessions,
            'utilization_percent' => $utilization,
            'stations_count' => Station::where('status', 'active')->count(),
            'cities_count' => Station::distinct('city')->count('city'),
        ];
    }

    /** @return array<int, array{hour:int, energy:float, revenue:float}> */
    public function hourlyChart(int $hours = 24): array
    {
        $rows = ChargingSession::query()
            ->where('started_at', '>=', now()->subHours($hours))
            ->selectRaw('HOUR(started_at) as h, SUM(energy_kwh) as e, SUM(cost_amount) as r')
            ->groupBy('h')
            ->orderBy('h')
            ->get()
            ->keyBy('h');

        $data = [];
        for ($i = 0; $i < $hours; $i++) {
            $h = (int) now()->subHours($hours - 1 - $i)->format('G');
            $row = $rows->get($h);
            $data[] = [
                'hour' => $h,
                'energy' => round((float) ($row->e ?? 0), 1),
                'revenue' => round((float) ($row->r ?? 0) / 10_000_000, 2),
            ];
        }

        return $data;
    }

    public function connectorBreakdown(): array
    {
        $counts = Connector::query()
            ->selectRaw('ocpp_status, COUNT(*) as c')
            ->groupBy('ocpp_status')
            ->pluck('c', 'ocpp_status');

        return [
            'charging' => (int) ($counts['Charging'] ?? 0),
            'available' => (int) ($counts['Available'] ?? 0) + (int) ($counts['Preparing'] ?? 0),
            'reserved' => (int) ($counts['Reserved'] ?? 0) + (int) ($counts['Finishing'] ?? 0),
            'faulted' => (int) ($counts['Faulted'] ?? 0) + (int) ($counts['Unavailable'] ?? 0),
        ];
    }

    public function recentSessions(int $limit = 8): Collection
    {
        return ChargingSession::with(['station', 'charger', 'user', 'connector'])
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }

    public function activeSessionsTable(int $limit = 20): Collection
    {
        return ChargingSession::with(['station', 'charger', 'user', 'connector'])
            ->where('status', 'active')
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    public function stationMapMarkers(): array
    {
        return Station::withCount(['chargers', 'chargingSessions'])
            ->with(['chargers' => fn ($q) => $q->select('id', 'station_id', 'is_online')])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (Station $s) {
                $online = $s->chargers->where('is_online', true)->count();
                $total = $s->chargers_count;
                $status = $s->status !== 'active' ? 'error' : ($online === $total ? 'online' : ($online > 0 ? 'busy' : 'offline'));

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'city' => $s->city,
                    'lat' => (float) $s->latitude,
                    'lng' => (float) $s->longitude,
                    'chargers' => $total,
                    'online' => $online,
                    'status' => $status,
                    'url' => route('admin.stations.show', $s),
                ];
            })
            ->values()
            ->all();
    }

    public function stationsIndex(): array
    {
        $stations = Station::withCount('chargers')
            ->with(['chargers.connectors', 'partner'])
            ->latest()
            ->get();

        $online = $stations->filter(fn ($s) => $s->status === 'active' && $s->chargers->where('is_online', true)->count() > 0)->count();
        $faulty = $stations->where('status', '!=', 'active')->count();

        return [
            'stations' => $stations,
            'stats' => [
                'total' => $stations->count(),
                'online' => $online,
                'faulty' => $faulty,
                'connectors' => Connector::count(),
                'cities' => $stations->pluck('city')->unique()->count(),
            ],
            'mapMarkers' => $this->stationMapMarkers(),
        ];
    }

    public function monitoring(): array
    {
        $onlineConnectors = Connector::whereHas('charger', fn ($q) => $q->where('is_online', true))
            ->whereIn('ocpp_status', ['Available', 'Charging', 'Preparing', 'Finishing'])
            ->count();
        $charging = Connector::where('ocpp_status', 'Charging')->count();
        $activePowerMw = ChargingSession::where('status', 'active')
            ->with('charger:id,max_power_kw')
            ->get()
            ->sum(fn ($s) => (int) ($s->charger?->max_power_kw ?? 0));

        $edgesOnline = EdgeStation::where('sync_status', 'online')->count();
        $edgesTotal = EdgeStation::count();
        $syncHealth = $edgesTotal > 0 ? round(($edgesOnline / $edgesTotal) * 100, 1) : 100;
        $faults = Connector::where('ocpp_status', 'Faulted')->count()
            + RepairOrder::where('status', 'open')->count();

        return array_merge($this->networkKpis(), [
            'online_connectors' => $onlineConnectors,
            'charging_connectors' => $charging,
            'load_mw' => round($activePowerMw / 1000, 1),
            'sync_health' => $syncHealth,
            'active_faults' => $faults,
            'loadChart' => $this->hourlyChart(12),
            'stations' => Station::orderBy('name')->get(['id', 'name']),
            'activeSessions' => $this->activeSessionsTable(15),
        ]);
    }

    public function sessionsIndex(): array
    {
        $completedScope = ChargingSession::where('status', 'completed')
            ->where('started_at', '>=', now()->subDays(30));

        return [
            'sessions' => ChargingSession::with(['station', 'charger', 'user'])
                ->latest('started_at')
                ->paginate(25),
            'stats' => [
                'active' => ChargingSession::where('status', 'active')->count(),
                'today' => ChargingSession::whereDate('started_at', today())->count(),
                'today_energy' => ChargingSession::whereDate('started_at', today())->sum('energy_kwh'),
                'today_revenue' => ChargingSession::whereDate('started_at', today())->sum('cost_amount'),
                'avg_duration_min' => (int) round((float) (clone $completedScope)->avg('duration_seconds') / 60),
                'avg_energy_kwh' => round((float) (clone $completedScope)->avg('energy_kwh'), 1),
                'history_total' => ChargingSession::where('status', '!=', 'active')->count(),
            ],
        ];
    }

    public function walletIndex(): array
    {
        $totalBalance = (float) Wallet::sum('balance');
        $walletCount = Wallet::count();
        $todayTopupQuery = WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation'])->whereDate('occurred_at', today());
        $todayTopupSum = (float) $todayTopupQuery->sum('amount');
        $todaySpend = (float) ChargingSession::whereDate('started_at', today())->sum('cost_amount');
        $pendingRefunds = SupportTicket::where('category', 'payment')
            ->whereIn('status', ['open', 'in_progress'])
            ->get();
        $pendingRefundAmount = 0.0;
        foreach ($pendingRefunds as $ticket) {
            $amt = WalletTransaction::where('type', 'refund')
                ->whereHas('wallet', fn ($q) => $q->where('user_id', $ticket->user_id))
                ->latest('occurred_at')
                ->value('amount');
            $pendingRefundAmount += (float) ($amt ?? 0);
        }
        $yesterdayTopup = (float) WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation'])->whereDate('occurred_at', today()->subDay())->sum('amount');
        $topupTrend = $yesterdayTopup > 0
            ? round((($todayTopupSum - $yesterdayTopup) / $yesterdayTopup) * 100, 1)
            : 0;

        return [
            'wallets' => Wallet::with('user')->orderByDesc('balance')->paginate(20),
            'stats' => [
                'total_balance' => $totalBalance,
                'total_bonus' => Wallet::sum('bonus_balance'),
                'active_wallets' => Wallet::where('status', 'active')->count(),
                'today_topups' => $todayTopupSum,
            ],
            'kpis' => [
                'total_balance_billion' => round($totalBalance / 10 / 1_000_000_000, 2),
                'wallet_count' => $walletCount,
                'today_topup_million' => (int) round($todayTopupSum / 10 / 1_000_000),
                'today_topup_count' => $todayTopupQuery->count(),
                'today_spend_million' => (int) round($todaySpend / 10 / 1_000_000),
                'pending_refunds' => $pendingRefunds->count(),
                'pending_refund_million' => (int) round($pendingRefundAmount / 10 / 1_000_000, 1),
                'topup_trend' => $topupTrend,
            ],
            'paymentGateways' => $this->paymentGatewayBreakdown(),
            'recentTransactions' => WalletTransaction::with('wallet.user')->latest('occurred_at')->limit(15)->get(),
        ];
    }

    public function financeIndex(): array
    {
        $monthRevenue = ChargingSession::where('started_at', '>=', now()->startOfMonth())->sum('cost_amount');
        $pendingSettlements = Settlement::whereIn('status', ['draft', 'pending'])->sum('partner_share');
        $paidSettlements = Settlement::where('status', 'approved')->sum('partner_share');

        return [
            'monthRevenue' => $monthRevenue,
            'pendingSettlements' => $pendingSettlements,
            'paidSettlements' => $paidSettlements,
            'settlements' => Settlement::with('partner')->latest()->limit(10)->get(),
            'recentPayments' => Payment::with('user')->latest()->limit(10)->get(),
            'invoices' => Invoice::latest()->limit(10)->get(),
            'revenueSources' => $this->revenueSourceBreakdown(),
            'taxRate' => 9,
        ];
    }

    /** @return array<int, array{label: string, pct: int, amount_million: int, icon: string, color: string, status: string}> */
    public function paymentGatewayBreakdown(): array
    {
        $since = now()->subDays(30);
        $byGateway = Payment::where('created_at', '>=', $since)
            ->selectRaw("COALESCE(gateway, 'unknown') as gw, COUNT(*) as c")
            ->groupBy('gw')
            ->get();
        $byAuth = ChargingSession::where('started_at', '>=', $since)
            ->selectRaw('auth_method, COUNT(*) as c')
            ->groupBy('auth_method')
            ->get()
            ->keyBy('auth_method');
        $total = max(1, $byGateway->sum('c') + $byAuth->sum('c'));

        $rows = [];
        foreach ($byGateway as $row) {
            $pct = (int) round($row->c / $total * 100);
            $label = match ($row->gw) {
                'mellat', 'behpardakht' => 'درگاه بانک ملت',
                'saman', 'sep' => 'درگاه سامان',
                'zarinpal' => 'زرین‌پال',
                default => $row->gw ?: 'درگاه پرداخت',
            };
            $rows[] = ['label' => $label, 'pct' => $pct, 'sub' => $pct.'٪ تراکنش‌ها', 'icon' => 'card', 'color' => 'ic-blue', 'status' => 'pill-green', 'statusLabel' => 'سالم'];
        }
        $rfidCount = (int) ($byAuth['rfid']?->c ?? 0);
        if ($rfidCount > 0) {
            $rows[] = ['label' => 'کیف پول RFID', 'pct' => (int) round($rfidCount / $total * 100), 'sub' => round($rfidCount / $total * 100).'% تراکنش‌ها', 'icon' => 'chip', 'color' => 'ic-orange', 'status' => 'pill-green', 'statusLabel' => 'سالم'];
        }
        $orgCount = (int) ChargingSession::where('started_at', '>=', $since)
            ->whereHas('user', fn ($q) => $q->whereNotNull('organization_id'))
            ->count();
        if ($orgCount > 0) {
            $rows[] = ['label' => 'اعتبار سازمانی', 'pct' => (int) round($orgCount / $total * 100), 'sub' => round($orgCount / $total * 100).'% تراکنش‌ها', 'icon' => 'users', 'color' => 'ic-violet', 'status' => 'pill-amber', 'statusLabel' => 'فعال'];
        }

        return $rows ?: [['label' => 'بدون تراکنش', 'pct' => 0, 'sub' => '۰٪', 'icon' => 'card', 'color' => 'ic-blue', 'status' => 'pill', 'statusLabel' => '—']];
    }

    /** @return array<int, array{label: string, pct: int, amount: string}> */
    public function revenueSourceBreakdown(): array
    {
        $since = now()->subDays(30);
        $total = (float) ChargingSession::where('started_at', '>=', $since)->sum('cost_amount');
        if ($total <= 0) {
            return [];
        }
        $dcTypes = ['CCS2', 'CHAdeMO', 'CCS1'];
        $acTypes = ['Type2', 'Type1', 'AC'];
        $dc = (float) ChargingSession::where('started_at', '>=', $since)
            ->whereHas('charger', fn ($q) => $q->whereIn('connector_type', $dcTypes))
            ->sum('cost_amount');
        $ac = (float) ChargingSession::where('started_at', '>=', $since)
            ->whereHas('charger', fn ($q) => $q->whereIn('connector_type', $acTypes))
            ->sum('cost_amount');
        $org = (float) ChargingSession::where('started_at', '>=', $since)
            ->whereHas('user', fn ($q) => $q->whereNotNull('organization_id'))
            ->sum('cost_amount');
        $other = max(0, $total - $dc - $ac - $org);
        $fmt = fn (float $v) => Persian::digits((int) round($v / 10 / 1_000_000)).' میلیون';
        $pct = fn (float $v) => (int) round($v / $total * 100);

        return [
            ['label' => 'شارژ DC سریع', 'pct' => $pct($dc), 'amount' => $fmt($dc)],
            ['label' => 'شارژ AC', 'pct' => $pct($ac), 'amount' => $fmt($ac)],
            ['label' => 'حساب‌های سازمانی', 'pct' => $pct($org), 'amount' => $fmt($org)],
            ['label' => 'سایر', 'pct' => $pct($other), 'amount' => $fmt($other)],
        ];
    }

    public function reportsIndex(): array
    {
        $daily = ChargingSession::query()
            ->where('started_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(started_at) as d, SUM(energy_kwh) as e, SUM(cost_amount) as r, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return [
            'daily' => $daily,
            'byCity' => Station::query()
                ->join('charging_sessions', 'charging_sessions.station_id', '=', 'stations.id')
                ->selectRaw('stations.city, SUM(charging_sessions.energy_kwh) as energy, SUM(charging_sessions.cost_amount) as revenue, COUNT(charging_sessions.id) as sessions')
                ->groupBy('stations.city')
                ->orderByDesc('revenue')
                ->get(),
            'byAuth' => ChargingSession::selectRaw('auth_method, COUNT(*) as c, SUM(cost_amount) as r')
                ->groupBy('auth_method')
                ->get(),
        ];
    }

    public function tariffsIndex(): array
    {
        $qrTokens = QrToken::with(['station', 'charger', 'connector'])->latest()->limit(100)->get();
        $connectorIds = $qrTokens->pluck('connector_id')->filter()->values();
        $qrScanCounts = $connectorIds->isEmpty()
            ? collect()
            : ChargingSession::query()
                ->whereIn('connector_id', $connectorIds)
                ->where('auth_method', 'qr')
                ->where('started_at', '>=', now()->subDays(30))
                ->groupBy('connector_id')
                ->selectRaw('connector_id, count(*) as scan_count')
                ->pluck('scan_count', 'connector_id');

        return [
            'tariffs' => Tariff::withCount('chargingSessions')->get(),
            'rfidCards' => RfidCard::with(['user.wallet', 'organization'])->latest()->limit(50)->get(),
            'qrTokens' => $qrTokens,
            'qrScanCounts' => $qrScanCounts,
            'stats' => [
                'active_tariffs' => Tariff::where('status', 'active')->count(),
                'active_rfid' => RfidCard::where('status', 'active')->count(),
                'active_qr' => QrToken::where('status', 'active')->count(),
            ],
        ];
    }

    public function usersIndex(): array
    {
        $customers = User::where('role', UserRole::Customer);
        $avgBalance = (float) Wallet::avg('balance');

        return [
            'users' => User::with(['wallet', 'organization', 'vehicles'])
                ->where('role', UserRole::Customer)
                ->withCount('chargingSessions')
                ->latest()
                ->paginate(20),
            'staff' => User::whereNot('role', UserRole::Customer)->get(),
            'stats' => [
                'customers' => $customers->count(),
                'organizations' => DB::table('organizations')->count(),
                'active_today' => ChargingSession::whereDate('started_at', today())->distinct('user_id')->count('user_id'),
            ],
            'kpis' => [
                'total' => $customers->count(),
                'active_30d' => ChargingSession::where('started_at', '>=', now()->subDays(30))
                    ->distinct('user_id')
                    ->count('user_id'),
                'new_month' => User::where('role', UserRole::Customer)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'avg_balance_million' => round($avgBalance / 10 / 1_000_000, 1),
            ],
        ];
    }

    public function partnersIndex(): array
    {
        return [
            'partners' => Partner::withCount('stations')->get(),
            'stats' => [
                'total' => Partner::count(),
                'active' => Partner::where('status', 'active')->count(),
            ],
        ];
    }

    public function supportIndex(): array
    {
        return [
            'tickets' => SupportTicket::with(['user', 'station', 'assignee'])->latest()->paginate(20),
            'stats' => [
                'open' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
                'high' => SupportTicket::where('priority', 'high')->whereNot('status', 'resolved')->count(),
            ],
        ];
    }

    public function inventoryIndex(): array
    {
        return [
            'items' => InventoryItem::orderBy('name')->get(),
            'stats' => [
                'skus' => InventoryItem::count(),
                'total_qty' => InventoryItem::sum('quantity_on_hand'),
                'low_stock' => InventoryItem::where('quantity_on_hand', '<', 10)->count(),
            ],
        ];
    }

    public function repairsIndex(): array
    {
        return [
            'orders' => RepairOrder::with(['station', 'charger', 'assignee'])->latest()->paginate(20),
            'stats' => [
                'open' => RepairOrder::where('status', 'open')->count(),
                'in_progress' => RepairOrder::where('status', 'in_progress')->count(),
                'closed' => RepairOrder::where('status', 'closed')->count(),
            ],
        ];
    }

    public function syncIndex(): array
    {
        return [
            'edges' => EdgeStation::with('station')->get(),
            'inbox' => SyncInbox::latest('received_at')->limit(20)->get(),
            'stats' => [
                'online' => EdgeStation::where('sync_status', 'online')->count(),
                'total' => EdgeStation::count(),
                'pending' => SyncInbox::where('status', 'pending')->count(),
            ],
        ];
    }

    public function notificationsIndex(): array
    {
        return [
            'notifications' => UserNotification::with('user')->latest('sent_at')->paginate(20),
            'stats' => [
                'today' => UserNotification::whereDate('sent_at', today())->count(),
                'unread' => UserNotification::whereNull('read_at')->count(),
            ],
        ];
    }

    public function campaignsIndex(): array
    {
        return ['campaigns' => Campaign::orderByDesc('starts_at')->get()];
    }

    public function teamIndex(): array
    {
        $audit = app(\App\Services\Audit\AuditService::class);
        $presenter = app(\App\Services\App\MobileAppPresenter::class);

        return [
            'staff' => User::whereNot('role', UserRole::Customer)->orderBy('name')->get(),
            'auditRows' => $presenter->auditRows($audit->recentForAdmin(40)),
        ];
    }

    public function settingsIndex(): array
    {
        return [
            'settings' => SystemSetting::orderBy('group')->orderBy('key')->get()->groupBy('group'),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function reservationsIndex(array $filters): array
    {
        $station = Station::with(['chargers.connectors'])
            ->when($filters['station_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->orderBy('name')
            ->first();

        $connectors = $station?->chargers->flatMap(fn ($ch) => $ch->connectors) ?? collect();
        $free = $connectors->filter(fn ($c) => $this->availability->snapshot($c)['reservable'])->count();
        $charging = $connectors->where('ocpp_status', 'Charging')->count();

        $periodQuery = Reservation::query()
            ->when($station, fn ($q) => $q->where('station_id', $station->id))
            ->where('reserved_from', '>=', $filters['range_start'])
            ->where('reserved_from', '<=', $filters['range_end']);

        $periodTotal = (clone $periodQuery)->count();
        $periodFulfilled = (clone $periodQuery)
            ->whereIn('status', ['checked_in', 'confirmed'])
            ->count();

        return [
            'station' => $station,
            'stations' => Station::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'name', 'status']),
            'filters' => $filters,
            'kpis' => [
                'free_connectors' => $free,
                'charging_connectors' => $charging,
                'period_reservations' => $periodTotal,
                'fulfillment_pct' => $periodTotal > 0 ? (int) round($periodFulfilled / $periodTotal * 100) : 0,
            ],
        ];
    }

    public function anprIndex(?int $stationId = null): array
    {
        return [
            'events' => AnprEvent::with(['station', 'user', 'vehicle'])
                ->when($stationId, fn ($q) => $q->where('station_id', $stationId))
                ->latest('detected_at')
                ->paginate(25),
            'stats' => [
                'today' => AnprEvent::when($stationId, fn ($q) => $q->where('station_id', $stationId))
                    ->whereDate('detected_at', today())->count(),
                'matched' => AnprEvent::when($stationId, fn ($q) => $q->where('station_id', $stationId))
                    ->whereNotNull('user_id')->whereDate('detected_at', today())->count(),
            ],
            'kpis' => $this->anprKpis($stationId),
        ];
    }

    /** @return array{cameras: int, registered_plates: int, auto_today: int, accuracy_pct: float} */
    public function anprKpis(?int $stationId = null): array
    {
        $eventBase = AnprEvent::query()
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId));

        $avgConfidence = (float) (clone $eventBase)
            ->where('detected_at', '>=', now()->subDays(7))
            ->avg('confidence');

        $edgeQuery = EdgeStation::query()
            ->whereJsonContains('capabilities', 'anpr')
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId));

        $cameraCount = 0;
        foreach ($edgeQuery->get() as $edge) {
            $cams = $edge->meta['anpr_cameras'] ?? [];
            $cameraCount += is_array($cams) && $cams !== [] ? count($cams) : 1;
        }

        return [
            'cameras' => max(0, $cameraCount),
            'registered_plates' => \App\Models\Vehicle::count(),
            'auto_today' => (clone $eventBase)
                ->whereDate('detected_at', today())
                ->where(function ($q) {
                    $q->whereNotNull('user_id')
                        ->orWhere('meta->outcome', 'auto_start');
                })
                ->count(),
            'accuracy_pct' => round($avgConfidence * 100, 1) ?: 96.8,
        ];
    }

    public function settlementShow(): array
    {
        return [
            'settlement' => Settlement::with(['partner', 'items.station'])->latest()->first(),
            'settlements' => Settlement::with('partner')->latest()->limit(20)->get(),
        ];
    }

    public function syncSettingsIndex(): array
    {
        $base = $this->syncIndex();
        $engine = SystemSetting::where('group', 'sync')->where('key', 'engine')->first();
        $config = $engine?->value ?? [
            'sync_interval_seconds' => 30,
            'batch_size' => 500,
            'retry_enabled' => true,
            'offline_queue_enabled' => true,
        ];

        return array_merge($base, [
            'syncConfig' => $config,
            'pendingQueue' => SyncInbox::where('status', 'pending')->count(),
            'offlineEdges' => EdgeStation::where('sync_status', '!=', 'online')->count(),
        ]);
    }
}
