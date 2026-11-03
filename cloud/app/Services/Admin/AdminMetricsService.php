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
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\App\MobileAppPresenter;
use App\Services\Audit\AuditService;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Support\Persian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminMetricsService
{
    public const IN_USE_OCPP = ['Charging', 'SuspendedEV', 'SuspendedEVSE'];

    public function __construct(
        private ConnectorAvailabilityService $availability,
    ) {}

    public function networkKpis(): array
    {
        $totalChargers = Charger::count();
        $onlineChargers = Charger::where('is_online', true)->count();
        $totalConnectors = Connector::count();
        $chargingConnectors = Connector::whereIn('ocpp_status', self::IN_USE_OCPP)
            ->whereHas('charger', fn ($q) => $q->where('is_online', true))
            ->count();
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
        $hourExpr = ChargingSession::query()->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', started_at) AS INTEGER)"
            : 'HOUR(started_at)';

        $rows = ChargingSession::query()
            ->where('started_at', '>=', now()->subHours($hours))
            ->selectRaw($hourExpr.' as h, SUM(energy_kwh) as e, SUM(cost_amount) as r')
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
                'energy' => round((float) ($row->e ?? 0), 3),
                'revenue' => round((float) ($row->r ?? 0) / 10_000_000, 4),
                'revenue_rial' => round((float) ($row->r ?? 0)),
            ];
        }

        return $data;
    }

    public function connectorBreakdown(): array
    {
        $offline = Connector::whereHas('charger', fn ($q) => $q->where('is_online', false))->count();
        $counts = Connector::query()
            ->whereHas('charger', fn ($q) => $q->where('is_online', true))
            ->selectRaw('ocpp_status, COUNT(*) as c')
            ->groupBy('ocpp_status')
            ->pluck('c', 'ocpp_status');
        $sum = fn (array $keys) => (int) collect($keys)->sum(fn ($k) => (int) ($counts[$k] ?? 0));

        return [
            'charging' => $sum(self::IN_USE_OCPP),
            'available' => $sum(['Available', 'Preparing']),
            'reserved' => $sum(['Reserved', 'Finishing']),
            'faulted' => $sum(['Faulted', 'Unavailable']),
            'offline' => $offline,
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
        $faulty = $stations->count() - $online;

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
            ->whereIn('ocpp_status', ['Available', 'Preparing', 'Finishing', 'Reserved', ...self::IN_USE_OCPP])
            ->count();
        $charging = Connector::whereIn('ocpp_status', self::IN_USE_OCPP)
            ->whereHas('charger', fn ($q) => $q->where('is_online', true))
            ->count();
        $activePowerKw = $this->liveLoadKw();

        $edgesOnline = EdgeStation::where('sync_status', 'online')->count();
        $edgesTotal = EdgeStation::count();
        $syncHealth = $edgesTotal > 0 ? round(($edgesOnline / $edgesTotal) * 100, 1) : 100;
        $faults = Connector::where('ocpp_status', 'Faulted')
            ->whereHas('charger', fn ($q) => $q->where('is_online', true))
            ->count()
            + RepairOrder::where('status', 'open')->count();

        return array_merge($this->networkKpis(), [
            'online_connectors' => $onlineConnectors,
            'charging_connectors' => $charging,
            'load_kw' => round($activePowerKw, 1),
            'load_mw' => round($activePowerKw / 1000, 2),
            'sync_health' => $syncHealth,
            'active_faults' => $faults,
        ]);
    }

    /** Sum of the power currently reported by chargers for active sessions. */
    public function liveLoadKw(): float
    {
        return (float) ChargingSession::where('status', 'active')
            ->get(['id', 'status', 'meta', 'energy_kwh', 'started_at', 'cost_amount'])
            ->sum(fn (ChargingSession $s) => $s->liveReadings()['power_kw'] ?? 0);
    }

    public function sessionsIndex(): array
    {
        $completedScope = ChargingSession::successful()
            ->where('started_at', '>=', now()->subDays(30));

        return [
            'stats' => [
                'active' => ChargingSession::where('status', 'active')->count(),
                'today' => ChargingSession::whereDate('started_at', today())->count(),
                'today_energy' => ChargingSession::whereDate('started_at', today())->sum('energy_kwh'),
                'today_revenue' => ChargingSession::whereDate('started_at', today())->sum('cost_amount'),
                'avg_duration_min' => (int) round((float) (clone $completedScope)->avg('duration_seconds') / 60),
                'avg_energy_kwh' => round((float) (clone $completedScope)->avg('energy_kwh'), 2),
                'history_total' => ChargingSession::where('status', '!=', 'active')->count(),
            ],
        ];
    }

    public function walletIndex(): array
    {
        $totalBalance = (float) Wallet::sum('balance');
        $walletCount = Wallet::count();
        $todayTopupQuery = WalletTransaction::whereIn('type', WalletTransaction::TOPUP_TYPES)->whereDate('occurred_at', today());
        $todayTopupSum = (float) $todayTopupQuery->sum('amount');
        $todaySpend = (float) WalletTransaction::whereIn('type', WalletTransaction::DEBIT_TYPES)->whereDate('occurred_at', today())->sum('amount');
        $pendingRefunds = SupportTicket::where('category', 'payment')
            ->whereIn('status', ['open', 'in_progress'])
            ->get();
        $yesterdayTopup = (float) WalletTransaction::whereIn('type', WalletTransaction::TOPUP_TYPES)->whereDate('occurred_at', today()->subDay())->sum('amount');
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
                'total_balance_toman' => (int) round($totalBalance / 10),
                'wallet_count' => $walletCount,
                'today_topup_toman' => (int) round($todayTopupSum / 10),
                'today_topup_count' => $todayTopupQuery->count(),
                'today_spend_toman' => (int) round($todaySpend / 10),
                'pending_refunds' => $pendingRefunds->count(),
                'topup_trend' => $topupTrend,
            ],
            'paymentGateways' => $this->paymentGatewayBreakdown(),
            'recentTransactions' => WalletTransaction::with('wallet.user')->latest('occurred_at')->limit(15)->get(),
        ];
    }

    public function financeIndex(): array
    {
        $monthRevenue = ChargingSession::finished()->where('started_at', '>=', now()->startOfMonth())->sum('cost_amount');
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
            'taxRate' => (float) config('safircharge.finance.vat_percent', 10),
        ];
    }

    /** @return array<int, array{label: string, pct: int, amount_million: int, icon: string, color: string, status: string}> */
    public function paymentGatewayBreakdown(): array
    {
        $since = now()->subDays(30);
        $byGateway = Payment::where('created_at', '>=', $since)
            ->selectRaw("COALESCE(gateway, 'unknown') as gw, COUNT(*) as c, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as ok")
            ->groupBy('gw')
            ->get();
        $total = max(1, (int) $byGateway->sum('c'));

        $rows = [];
        foreach ($byGateway as $row) {
            $pct = (int) round($row->c / $total * 100);
            $success = $row->c > 0 ? (int) round($row->ok / $row->c * 100) : 0;
            $label = match ($row->gw) {
                'mellat', 'behpardakht' => 'درگاه بانک ملت',
                'saman', 'sep' => 'درگاه سامان',
                'zarinpal' => 'زرین‌پال',
                default => $row->gw ?: 'درگاه پرداخت',
            };
            $rows[] = [
                'label' => $label, 'pct' => $pct,
                'sub' => Persian::digits((int) $row->c).' تراکنش · موفق '.Persian::digits($success).'٪',
                'icon' => 'card', 'color' => 'ic-blue',
                'status' => $success >= 80 ? 'pill-green' : ($success >= 50 ? 'pill-amber' : 'pill-red'),
                'statusLabel' => $success >= 80 ? 'سالم' : ($success >= 50 ? 'متوسط' : 'ضعیف'),
            ];
        }

        return $rows ?: [['label' => 'بدون تراکنش درگاه در ۳۰ روز اخیر', 'pct' => 0, 'sub' => '—', 'icon' => 'card', 'color' => 'ic-blue', 'status' => 'pill', 'statusLabel' => '—']];
    }

    /** @return array<int, array{label: string, pct: int, amount: string}> */
    public function revenueSourceBreakdown(): array
    {
        $since = now()->subDays(30);
        $base = fn () => ChargingSession::finished()->where('started_at', '>=', $since);
        $total = (float) $base()->sum('cost_amount');
        if ($total <= 0) {
            return [];
        }
        $acTypes = ['Type2', 'Type1', 'AC', 'GBT-AC'];
        $org = (float) $base()
            ->whereHas('user', fn ($q) => $q->whereNotNull('organization_id'))
            ->sum('cost_amount');
        $ac = (float) $base()
            ->whereHas('user', fn ($q) => $q->whereNull('organization_id'))
            ->whereHas('charger', fn ($q) => $q->whereIn('connector_type', $acTypes))
            ->sum('cost_amount');
        $dc = (float) $base()
            ->whereHas('user', fn ($q) => $q->whereNull('organization_id'))
            ->whereHas('charger', fn ($q) => $q->whereNotIn('connector_type', $acTypes))
            ->sum('cost_amount');
        $other = max(0, $total - $dc - $ac - $org);
        $fmt = fn (float $v) => Persian::money((int) round($v / 10));
        $pct = fn (float $v) => (int) round($v / $total * 100);

        return [
            ['label' => 'شارژ DC', 'pct' => $pct($dc), 'amount' => $fmt($dc)],
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

        $tariffs = Tariff::withCount('chargingSessions')->with('rules')->get();
        $default = $tariffs->firstWhere('is_default', true) ?? $tariffs->firstWhere('status', 'active');
        $calc = app(\App\Services\Charging\TariffCalculator::class);
        $hourly = [];
        if ($default) {
            $day = now()->setTimezone(config('safircharge.timezone', 'Asia/Tehran'))->startOfDay();
            for ($h = 0; $h < 24; $h++) {
                [$price, $type] = $calc->effectiveKwhPrice($default, $default->price_per_kwh !== null ? (float) $default->price_per_kwh : null, $day->copy()->addHours($h)->addMinutes(30));
                $hourly[] = ['h' => $h, 'toman' => $price !== null ? (int) round($price / 10) : 0, 'type' => $type ?? 'normal'];
            }
        }

        return [
            'tariffs' => $tariffs,
            'hourlyPrices' => $hourly,
            'hourlyTariff' => $default,
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
        $avgBalance = (float) Wallet::whereHas('user', fn ($q) => $q->where('role', UserRole::Customer))->avg('balance');

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
        $audit = app(AuditService::class);
        $presenter = app(MobileAppPresenter::class);

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

        $avgConfidence = (clone $eventBase)
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
            'registered_plates' => Vehicle::count(),
            'auto_today' => (clone $eventBase)
                ->whereDate('detected_at', today())
                ->where(function ($q) {
                    $q->whereNotNull('user_id')
                        ->orWhere('meta->outcome', 'auto_start');
                })
                ->count(),
            'accuracy_pct' => $avgConfidence !== null ? round((float) $avgConfidence * 100, 1) : null,
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
