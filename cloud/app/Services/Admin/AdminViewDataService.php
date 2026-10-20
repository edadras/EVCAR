<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AnprEvent;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeStation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\RepairOrder;
use App\Models\Settlement;
use App\Models\SyncInbox;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Support\Persian;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminViewDataService
{
    private const AVATAR_COLORS = ['#0E6BA8', '#16A34A', '#F97316', '#7C3AED', '#0891B2', '#DC2626', '#D97706'];

    public function __construct(
        private AdminMetricsService $metrics,
        private ReportsBundleService $reports,
        private ConnectorAvailabilityService $availability,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function dashboardChart(): array
    {
        return $this->metrics->hourlyChart();
    }

    /** @return array<string, mixed> */
    public function sessionsJs(): array
    {
        $active = ChargingSession::with(['station.edgeStation', 'charger', 'user', 'connector'])
            ->where('status', 'active')
            ->latest('started_at')
            ->limit(25)
            ->get();

        $history = ChargingSession::with(['station', 'charger', 'user'])
            ->where('status', '!=', 'active')
            ->latest('started_at')
            ->limit(25)
            ->get();

        return [
            'live' => $active->map(fn ($s) => $this->sessionRow($s, true))->values()->all(),
            'history' => $history->map(fn ($s) => $this->sessionRow($s, false))->values()->all(),
            'stats' => $this->metrics->sessionsIndex()['stats'],
        ];
    }

    /** @return array<string, mixed> */
    private function sessionRow(ChargingSession $s, bool $live): array
    {
        $prog = min(99, max(5, (int) ($s->soc_end ?? $s->soc_start ?? 50)));
        $mins = (int) floor(($s->duration_seconds ?? 0) / 60);

        $meta = $s->meta ?? [];

        return [
            'id' => $s->session_code ?? ('SX-'.$s->id),
            'session_id' => $s->id,
            'station_id' => $s->station_id,
            'connector_id' => $s->connector_id,
            'can_stop' => (bool) $s->station?->edgeStation,
            'user' => $s->user?->name ?? 'مهمان',
            'initials' => mb_substr($s->user?->name ?? 'م', 0, 1),
            'station' => $s->station?->name ?? '—',
            'charger' => $s->charger?->charge_point_id ?? '—',
            'connector' => $s->connector?->connector_number
                ? 'C'.$s->connector->connector_number
                : ($s->charger?->connector_type ?? 'DC CCS2'),
            'prog' => (int) ($meta['soc'] ?? $prog),
            'energy' => round((float) $s->energy_kwh, 1),
            'power' => (int) ($s->charger?->max_power_kw ?? 22),
            'duration' => $mins,
            'cost' => (int) ($s->cost_amount / 10),
            'status' => $live ? ($prog > 85 ? 'ending' : ($prog < 15 ? 'started' : 'charging')) : $s->status,
            'auth' => $s->auth_method ?? 'wallet',
            'started' => $s->started_at?->format('Y/m/d H:i'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function monitoringStationsJs(): array
    {
        $activeSessions = ChargingSession::where('status', 'active')
            ->whereNull('stopped_at')
            ->with('user')
            ->get()
            ->keyBy('connector_id');

        return Station::with(['chargers.connectors', 'edgeStation'])
            ->orderBy('name')
            ->get()
            ->map(function (Station $st) use ($activeSessions) {
                $conns = $st->chargers->flatMap(fn ($ch) => $ch->connectors);
                $total = max(1, $conns->count());
                $charging = $conns->where('ocpp_status', 'Charging')->count();
                $faulted = $conns->where('ocpp_status', 'Faulted')->count();
                $health = $faulted > 0 || $st->status !== 'active' ? 'bad' : ($charging / $total > 0.85 ? 'warn' : 'ok');
                $sync = $st->edgeStation?->sync_status === 'online' ? 'ok' : ($st->edgeStation ? 'lag' : 'off');
                $load = (int) min(100, round(($charging / $total) * 100));
                $onlineKw = 0;

                $connRows = $conns->values()->map(function ($c, $i) use ($activeSessions, &$onlineKw) {
                    $session = $activeSessions->get($c->id);
                    $chargerOnline = $c->charger?->is_online ?? true;
                    $state = match ($c->ocpp_status) {
                        'Charging' => 'charging',
                        'Faulted', 'Unavailable' => 'fault',
                        'Reserved' => 'reserved',
                        default => $chargerOnline ? 'free' : 'offline',
                    };
                    if (! $chargerOnline && $state === 'free') {
                        $state = 'offline';
                    }
                    $maxKw = (int) ($c->max_power_kw ?? $c->charger?->max_power_kw ?? 22);
                    $pw = $state === 'charging' ? $maxKw : 0;
                    if ($state === 'charging') {
                        $onlineKw += $pw;
                    }
                    $soc = 0;
                    if ($session) {
                        $meterStart = (float) ($session->meter_start_wh ?? 0);
                        $energyWh = (float) ($session->energy_kwh ?? 0) * 1000;
                        if ($meterStart > 0 && $energyWh > 0) {
                            $soc = (int) min(99, max(5, round(($energyWh / max(1, $meterStart + $energyWh)) * 100)));
                        } elseif ($session->energy_kwh > 0) {
                            $soc = (int) min(90, max(10, round((float) $session->energy_kwh * 2)));
                        }
                    }

                    return [
                        'id' => 'C'.($c->connector_number ?? ($i + 1)),
                        'type' => $c->charger?->connector_type ?? 'Type2',
                        'state' => $state,
                        'user' => $session?->user?->name,
                        'pw' => $pw,
                        'soc' => $soc,
                    ];
                })->all();

                return [
                    'id' => $st->id,
                    'name' => $st->name,
                    'loc' => ($st->city ?? '').' · '.$st->code,
                    'n' => $conns->count(),
                    'health' => $health,
                    'sync' => $sync,
                    'load' => $load,
                    'charging' => $charging,
                    'totalkw' => $onlineKw,
                    'remoteUrl' => route('admin.remote', ['station' => $st->id]),
                    'conns' => $connRows,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function remoteStationJs(Station $station): array
    {
        return app(\App\Services\Stations\RemoteStationService::class)->jsPayload($station);
    }

    /** @return Collection<int, SupportTicket|RepairOrder> */
    public function dashboardAlerts(): Collection
    {
        $tickets = SupportTicket::with('station')
            ->whereIn('priority', ['high', 'critical'])
            ->whereNot('status', 'resolved')
            ->latest()
            ->limit(3)
            ->get();

        $repairs = RepairOrder::with('station')
            ->where('status', 'open')
            ->latest()
            ->limit(2)
            ->get();

        return $tickets->concat($repairs);
    }

    public function connectorDcAcCounts(): array
    {
        $dcTypes = ['CCS2', 'CHAdeMO', 'CCS1'];
        $acTypes = ['Type2', 'Type1', 'AC'];

        $dc = Connector::whereHas('charger', fn ($q) => $q->whereIn('connector_type', $dcTypes))->count();
        $ac = Connector::whereHas('charger', fn ($q) => $q->whereIn('connector_type', $acTypes))->count();

        return ['dc' => $dc, 'ac' => $ac];
    }

    public function fa(mixed $v): string
    {
        return Persian::digits($v);
    }

    /** @return array<int, array<string, mixed>> */
    public function stationsPageJs(): array
    {
        $anpr = app(\App\Services\Stations\StationAnprService::class);

        return Station::with(['chargers.connectors', 'edgeStation'])
            ->withCount('chargers')
            ->orderBy('name')
            ->get()
            ->map(function (Station $st) use ($anpr) {
                $conns = $st->chargers->flatMap(fn ($ch) => $ch->connectors);
                $charging = $conns->where('ocpp_status', 'Charging')->count();
                $total = max(1, $conns->count());
                $load = (int) min(100, round(($charging / $total) * 100));
                $fault = $conns->where('ocpp_status', 'Faulted')->count();
                $status = $fault > 0 ? 'error' : ($load > 85 ? 'busy' : 'online');
                $pill = $fault > 0 ? ['pill-red', 'خطا'] : ($load > 85 ? ['pill-amber', 'پرتراکم'] : ['pill-green', 'آنلاین']);

                $todayQuery = ChargingSession::where('station_id', $st->id)
                    ->whereDate('started_at', today());
                $todayCount = (clone $todayQuery)->count();
                $todayEnergy = (float) (clone $todayQuery)->sum('energy_kwh');
                $todayRevenue = (float) (clone $todayQuery)->sum('cost_amount');

                return [
                    'id' => $st->id,
                    'name' => $st->name,
                    'city' => $st->city,
                    'code' => $st->code,
                    'address' => $st->address,
                    'chargers' => $st->chargers_count,
                    'load' => $load,
                    'charging' => $charging,
                    'free' => max(0, $total - $charging),
                    'status' => $status,
                    'pill' => $pill,
                    'today_energy' => Persian::kwh($todayEnergy),
                    'today_revenue' => Persian::digits(round($todayRevenue / 10_000_000, 1)),
                    'today_sessions' => Persian::digits($todayCount),
                    'lat' => (float) $st->latitude,
                    'lng' => (float) $st->longitude,
                    'remoteUrl' => route('admin.remote', ['station' => $st->id]),
                    'anpr' => $anpr->summaryForList($st),
                    'connectors' => $conns->values()->map(function ($c, $i) {
                        $stKey = match ($c->ocpp_status) {
                            'Charging' => 'busy',
                            'Faulted', 'Unavailable' => 'fault',
                            default => 'free',
                        };

                        return [
                            'id' => chr(65 + intdiv($i, 3)).(($i % 3) + 1),
                            'type' => ($c->charger?->connector_type ?? 'Type2').' · '.($c->charger?->max_power_kw ?? 22).'kW',
                            'st' => $stKey,
                            'pw' => $stKey === 'busy' ? (($c->charger?->max_power_kw ?? 22).' kW') : ($stKey === 'fault' ? 'خطا' : 'آماده'),
                            'u' => '',
                        ];
                    })->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    /** @return array<string, mixed> */
    public function walletBundle(): array
    {
        $index = $this->metrics->walletIndex();

        return [
            'kpis' => $index['kpis'],
            'paymentGateways' => $index['paymentGateways'],
            'topup' => $this->walletFlowSeries('topup'),
            'spend' => $this->walletFlowSeries('spend'),
            'wallets' => $this->walletUserRows(),
            'refunds' => $this->walletRefundRows(),
            'ranges' => [
                'today' => $this->walletRangeStats(0),
                'week' => $this->walletRangeStats(7),
                'month' => $this->walletRangeStats(30),
            ],
        ];
    }

    /** @return array<int, int> */
    private function walletFlowSeries(string $kind): array
    {
        $series = [];
        foreach (range(13, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();
            if ($kind === 'topup') {
                $series[] = (int) round((float) WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation'])->whereDate('occurred_at', $day)->sum('amount') / 10 / 1_000_000);
            } else {
                $charge = (float) WalletTransaction::where('type', 'charge')->whereDate('occurred_at', $day)->sum('amount');
                if ($charge <= 0) {
                    $charge = (float) ChargingSession::whereDate('started_at', $day)->sum('cost_amount');
                }
                $series[] = (int) round($charge / 10 / 1_000_000);
            }
        }

        return $series;
    }

    /** @return array<int, array<string, mixed>> */
    private function walletUserRows(): array
    {
        return Wallet::with(['user.organization', 'transactions' => fn ($q) => $q->latest('occurred_at')->limit(1)])
            ->orderByDesc('balance')
            ->limit(50)
            ->get()
            ->map(function (Wallet $w) {
                $user = $w->user;
                $isOrg = (bool) $user?->organization_id;
                $balToman = (int) round((float) $w->balance / 10);
                $bonusToman = (int) round((float) $w->bonus_balance / 10);
                $last = $w->transactions->first()?->occurred_at;

                return [
                    'uid' => $user?->id,
                    'n' => $user?->name ?? '—',
                    'm' => Persian::digits($user?->phone ?? '—'),
                    'ty' => [$isOrg ? 'سازمانی' : 'عادی', $isOrg ? 'pill-accent' : 'tag', $isOrg ? 'org' : 'normal'],
                    'bal' => Persian::money($balToman),
                    'rw' => Persian::money($bonusToman),
                    'last' => $this->timeAgoFa($last),
                    'st' => $w->status === 'blocked'
                        ? ['مسدود', 'pill-red']
                        : ($balToman < 100_000 ? ['کم‌موجودی', 'pill-amber'] : ['فعال', 'pill-green']),
                    'c' => $this->avatarColor($user?->name ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function walletRefundRows(): array
    {
        return SupportTicket::with(['user'])
            ->where('category', 'payment')
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (SupportTicket $t) {
                $amountToman = $this->refundAmountToman($t);
                $status = match ($t->status) {
                    'resolved' => ['تأیید شد', 'pill-green'],
                    'closed' => ['رد شد', 'pill-red'],
                    'in_progress' => ['در بررسی', 'pill-amber'],
                    default => ['در انتظار', 'pill-amber'],
                };

                return [
                    'tid' => $t->id,
                    'id' => 'RF‑'.Persian::digits(substr((string) $t->ticket_number, -3)),
                    'user' => $t->user?->name ?? '—',
                    'uid' => $t->user_id,
                    'amount' => Persian::money($amountToman),
                    'amount_raw' => $amountToman,
                    'reason' => $t->subject ?? 'درخواست استرداد',
                    'when' => $this->timeAgoFa($t->created_at),
                    'st' => $status,
                    'can_action' => in_array($t->status, ['open', 'in_progress'], true),
                ];
            })
            ->values()
            ->all();
    }

    public function refundAmountToman(SupportTicket $ticket): int
    {
        $text = ($ticket->subject ?? '').' '.($ticket->body ?? '');
        if (preg_match('/([\d۰-۹][\d۰-۹,\.]*)/u', $text, $m)) {
            $digits = str_replace(
                ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', ',', '.'],
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '', ''],
                $m[1]
            );
            $parsed = (int) $digits;
            if ($parsed >= 1000) {
                return $parsed;
            }
        }

        $charge = (float) WalletTransaction::query()
            ->where('type', 'charge')
            ->whereHas('wallet', fn ($q) => $q->where('user_id', $ticket->user_id))
            ->latest('occurred_at')
            ->value('amount');

        if ($charge > 0) {
            return (int) round($charge / 10);
        }

        return 500_000;
    }

    /** @return array{topup_million: int, topup_count: int, spend_million: int, topup_trend: float} */
    private function walletRangeStats(int $days): array
    {
        $topupQuery = WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation']);
        $spendQuery = ChargingSession::query();

        if ($days === 0) {
            $topupQuery->whereDate('occurred_at', today());
            $spendQuery->whereDate('started_at', today());
            $compareTopup = (float) WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation'])->whereDate('occurred_at', today()->subDay())->sum('amount');
        } else {
            $since = now()->subDays($days)->startOfDay();
            $topupQuery->where('occurred_at', '>=', $since);
            $spendQuery->where('started_at', '>=', $since);
            $compareTopup = (float) WalletTransaction::whereIn('type', ['topup', 'bonus', 'compensation'])
                ->whereBetween('occurred_at', [now()->subDays($days * 2)->startOfDay(), $since])
                ->sum('amount');
        }

        $topupSum = (float) $topupQuery->sum('amount');
        $spendSum = (float) $spendQuery->sum('cost_amount');
        $trend = $compareTopup > 0 ? round((($topupSum - $compareTopup) / $compareTopup) * 100, 1) : 0;

        return [
            'topup_million' => (int) round($topupSum / 10 / 1_000_000),
            'topup_count' => (int) $topupQuery->count(),
            'spend_million' => (int) round($spendSum / 10 / 1_000_000),
            'topup_trend' => $trend,
        ];
    }

    /** @return array<string, mixed> */
    /** @return array<string, mixed> */
    public function anprBundle(?int $stationId = null): array
    {
        $stations = Station::query()
            ->whereHas('edgeStation', fn ($q) => $q->whereJsonContains('capabilities', 'anpr'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $station = $stationId
            ? $stations->firstWhere('id', $stationId)
            : null;

        $eventQuery = AnprEvent::with(['station', 'user'])
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId));

        $latest = (clone $eventQuery)->latest('detected_at')->first();
        $demo = $this->anprDemoFromEvent($latest);

        return [
            'stations' => $stations->map(fn (Station $s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()->all(),
            'filters' => ['station_id' => $stationId],
            'station' => $station?->name,
            'kpis' => $this->metrics->anprKpis($stationId),
            'demo' => $demo,
            'events' => (clone $eventQuery)->latest('detected_at')->limit(20)->get()
                ->map(fn (AnprEvent $e) => $this->anprEventRow($e))
                ->values()
                ->all(),
            'plates' => $this->anprPlatesRows($stationId),
            'last_event_id' => $latest?->id,
        ];
    }

    /** @return array<string, mixed>|null */
    private function anprDemoFromEvent(?AnprEvent $latest): ?array
    {
        if (! $latest || ! $latest->plate_two) {
            return null;
        }

        $row = $this->anprEventRow($latest);

        return [
            'id' => $latest->id,
            'd2' => $row['p']['d2'],
            'let' => $row['p']['let'],
            'd3' => $row['p']['d3'],
            'prov' => $row['p']['prov'],
            'name' => $latest->user?->name ?? 'ثبت‌نشده',
            'station' => $latest->station?->name ?? '—',
            'camera' => $latest->meta['camera_source'] ?? 'CAM-01',
            'time' => Persian::digits($latest->detected_at?->format('H:i:s') ?? ''),
            'status' => $row['status_text'],
            'pill' => $row['s'],
            'cat' => $row['cat'],
        ];
    }

    /** @return array<string, mixed> */
    public function anprEventRow(AnprEvent $e): array
    {
        $conf = (float) ($e->confidence ?? 0);
        $outcome = $e->meta['outcome'] ?? null;
        $camera = $e->meta['camera_source'] ?? 'CAM-01';

        [$cat, $status, $act, $statusText] = match ($outcome) {
            'auto_start' => ['auto', ['موفق', 'pill-green'], 'شارژ خودکار آغاز شد', 'کیف پول معتبر · شارژ خودکار آغاز شد'],
            'manual_payment' => ['fail', ['ثبت‌نشده', 'pill-amber'], 'هدایت به پرداخت دستی', 'پلاک ثبت‌نشده · پرداخت دستی'],
            'low_confidence' => ['fail', ['ناموفق', 'pill-red'], 'دقت پایین — بررسی دستی', 'اطمینان پایین · نیاز به تأیید اپراتور'],
            'unknown_plate' => ['fail', ['ثبت‌نشده', 'pill-amber'], 'هدایت به پرداخت دستی', 'پلاک در سیستم یافت نشد'],
            default => $this->legacyAnprOutcome($e, $conf),
        };

        return [
            'id' => $e->id,
            't' => Persian::digits($e->detected_at?->format('H:i:s') ?? '—'),
            'p' => [
                'd2' => Persian::digits($e->plate_two),
                'let' => $e->plate_letter,
                'd3' => Persian::digits($e->plate_three),
                'prov' => Persian::digits($e->plate_province),
            ],
            'u' => $e->user?->name ?? '—',
            'st' => ($e->station?->name ?? '—').' · '.$camera,
            'act' => $act,
            'acc' => Persian::digits(round($conf * 100)).'٪',
            's' => $status,
            'cat' => $cat,
            'status_text' => $statusText,
        ];
    }

    /** @return array{0: string, 1: array{0: string, 1: string}, 2: string, 3: string} */
    private function legacyAnprOutcome(AnprEvent $e, float $conf): array
    {
        if (! $e->user_id) {
            return ['fail', ['ثبت‌نشده', 'pill-amber'], 'هدایت به پرداخت دستی', 'پلاک در سیستم یافت نشد'];
        }

        if ($conf < 0.92) {
            return ['fail', ['ناموفق', 'pill-red'], 'دقت پایین — بررسی دستی', 'اطمینان پایین · نیاز به تأیید اپراتور'];
        }

        return ['auto', ['موفق', 'pill-green'], 'شارژ خودکار آغاز شد', 'کیف پول معتبر · شارژ خودکار آغاز شد'];
    }

    /** @return array<int, array<string, mixed>> */
    private function anprPlatesRows(?int $stationId): array
    {
        return Vehicle::with(['user.wallet'])
            ->whereNotNull('plate_normalized')
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (Vehicle $v) use ($stationId) {
                $bal = (int) round((float) ($v->user?->wallet?->balance ?? 0) / 10);
                $lastEventQuery = AnprEvent::where('vehicle_id', $v->id);
                if ($stationId) {
                    $lastEventQuery->where('station_id', $stationId);
                }
                $lastEvent = $lastEventQuery->latest('detected_at')->first();

                return [
                    'p' => [
                        'd2' => Persian::digits($v->plate_two),
                        'let' => $v->plate_letter,
                        'd3' => Persian::digits($v->plate_three),
                        'prov' => Persian::digits($v->plate_province),
                    ],
                    'u' => $v->user?->name ?? '—',
                    'car' => trim(($v->brand ?? '').' '.($v->model ?? '')),
                    'w' => Persian::money($bal),
                    'auto' => $bal > 100_000,
                    'last' => $this->timeAgoFa($lastEvent?->detected_at ?? $v->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    /** @param  array<string, mixed>  $input */
    public function resolveReservationFilters(array $input): array
    {
        $stationId = ! empty($input['station_id']) ? (int) $input['station_id'] : null;
        $dateFrom = ! empty($input['date_from']) ? (string) $input['date_from'] : null;
        $dateTo = ! empty($input['date_to']) ? (string) $input['date_to'] : null;
        $dayOffset = ($dateFrom || $dateTo) ? 0 : max(0, min(6, (int) ($input['day'] ?? 0)));

        if ($dateFrom) {
            $rangeStart = Carbon::parse($dateFrom)->startOfDay();
            $rangeEnd = Carbon::parse($dateTo ?: $dateFrom)->endOfDay();
            $timelineDay = $rangeStart->copy();
        } else {
            $timelineDay = today()->addDays($dayOffset);
            $rangeStart = $timelineDay->copy()->startOfDay();
            $rangeEnd = $timelineDay->copy()->endOfDay();
        }

        if ($rangeEnd->lt($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
            $timelineDay = $rangeStart->copy();
        }

        $weekdays = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
        $dayOptions = [];
        foreach ([0, 1, 2] as $offset) {
            $d = today()->addDays($offset);
            $label = match ($offset) {
                0 => 'امروز',
                1 => 'فردا',
                default => $weekdays[$d->dayOfWeek],
            };
            $dayOptions[] = [
                'offset' => $offset,
                'label' => $label,
                'date' => Persian::digits($d->format('Y/m/d')),
            ];
        }

        $periodLabel = $dateFrom
            ? Persian::digits($rangeStart->format('Y/m/d')).($dateTo && $dateTo !== $dateFrom ? ' تا '.Persian::digits($rangeEnd->format('Y/m/d')) : '')
            : ($dayOptions[$dayOffset]['label'] ?? 'امروز');

        return [
            'station_id' => $stationId,
            'day' => $dayOffset,
            'date_from' => $dateFrom,
            'date_to' => $dateTo ?: ($dateFrom ? $dateFrom : null),
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'timeline_day' => $timelineDay,
            'period_label' => $periodLabel,
            'is_today' => $timelineDay->isToday(),
            'day_options' => $dayOptions,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function reservationsJs(?Station $station = null, array $filters = []): array
    {
        $filters = $this->resolveReservationFilters($filters);
        $station ??= Station::with(['chargers.connectors'])
            ->when($filters['station_id'], fn ($q) => $q->where('id', $filters['station_id']))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->first();
        if (! $station) {
            return [
                'station' => null,
                'timeline' => [], 'live' => [], 'reservations' => [], 'connectorOptions' => [],
                'stationName' => '—', 'stationStatus' => null, 'filters' => $filters,
                'kpis' => [
                    'total_connectors' => 0, 'free_connectors' => 0, 'charging_connectors' => 0,
                    'reserved_connectors' => 0, 'period_reservations' => 0, 'fulfillment_pct' => 0,
                ],
            ];
        }

        $timelineDay = $filters['timeline_day'];
        $rangeStart = $filters['range_start'];
        $rangeEnd = $filters['range_end'];
        $now = now();

        $connectors = $station->chargers->flatMap(fn ($ch) => $ch->connectors)->values();
        $connectorIds = $connectors->pluck('id');
        $windowStart = $timelineDay->copy()->setTime(12, 0);
        $windowEnd = $timelineDay->copy()->setTime(18, 0);

        $slotReservations = Reservation::query()
            ->where('station_id', $station->id)
            ->whereIn('connector_id', $connectorIds)
            ->where('reserved_from', '<', $windowEnd)
            ->where('reserved_until', '>', $windowStart)
            ->whereNot('status', 'cancelled')
            ->get();

        $activeSessions = $filters['is_today']
            ? ChargingSession::with('user')
                ->where('station_id', $station->id)
                ->whereIn('connector_id', $connectorIds)
                ->where('status', 'active')
                ->latest('started_at')
                ->get()
            : collect();

        $slotSessions = $filters['is_today']
            ? ChargingSession::query()
                ->where('station_id', $station->id)
                ->whereIn('connector_id', $connectorIds)
                ->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->where('status', 'active')
                        ->orWhere(function ($q2) use ($windowStart, $windowEnd) {
                            $q2->where('started_at', '<', $windowEnd)
                                ->where(function ($q3) use ($windowStart) {
                                    $q3->whereNull('stopped_at')->orWhere('stopped_at', '>', $windowStart);
                                });
                        });
                })
                ->get()
            : collect();

        $chargingIds = $activeSessions->pluck('connector_id')->unique();
        $reservedNowIds = $filters['is_today']
            ? $slotReservations
                ->filter(fn (Reservation $r) => $r->reserved_from <= $now && $r->reserved_until > $now)
                ->pluck('connector_id')
                ->unique()
            : collect();

        $totalConnectors = $connectors->count();
        $chargingCount = $connectors->filter(fn ($c) => $chargingIds->contains($c->id))->count();
        $reservedCount = $connectors->filter(
            fn ($c) => $reservedNowIds->contains($c->id) && ! $chargingIds->contains($c->id)
        )->count();
        $freeCount = $connectors->filter(function ($c) use ($chargingIds, $reservedNowIds) {
            $snap = $this->availability->snapshot($c);

            return $snap['reservable'] && ! $chargingIds->contains($c->id) && ! $reservedNowIds->contains($c->id);
        })->count();

        $periodQuery = Reservation::query()
            ->where('station_id', $station->id)
            ->whereNot('status', 'cancelled')
            ->where('reserved_from', '>=', $rangeStart)
            ->where('reserved_from', '<=', $rangeEnd);
        $periodTotal = (clone $periodQuery)->count();
        $periodFulfilled = (clone $periodQuery)
            ->whereIn('status', ['checked_in', 'confirmed'])
            ->count();

        $slotStarts = [];
        for ($h = 12; $h < 18; $h++) {
            foreach ([0, 30] as $m) {
                $slotStarts[] = $timelineDay->copy()->setTime($h, $m);
            }
        }

        $rows = [];
        foreach ($connectors as $idx => $connector) {
            $snap = $this->availability->snapshot($connector);
            $blocked = ! $snap['reservable'];
            $row = [];
            foreach ($slotStarts as $start) {
                $end = $start->copy()->addMinutes(30);
                if ($blocked) {
                    $row[] = 3;

                    continue;
                }
                $busy = $filters['is_today'] && $slotSessions->contains(function (ChargingSession $s) use ($connector, $start, $end, $now) {
                    if ($s->connector_id !== $connector->id) {
                        return false;
                    }
                    $sessionEnd = $s->status === 'active'
                        ? $now->copy()->addMinute()
                        : ($s->stopped_at ?? $now);

                    return $s->started_at < $end && $sessionEnd > $start;
                });
                $reserved = ! $busy && $slotReservations->contains(
                    fn (Reservation $r) => $r->connector_id === $connector->id
                        && $r->reserved_from < $end
                        && $r->reserved_until > $start
                );

                $row[] = $busy ? 1 : ($reserved ? 2 : 0);
            }
            $rows[] = [
                'id' => chr(65 + intdiv($idx, 3)).(($idx % 3) + 1),
                'cells' => $row,
                'blocked' => $blocked,
                'reason' => $snap['reason'],
            ];
        }

        $live = $activeSessions->map(function (ChargingSession $s, $i) use ($connectors) {
            $soc = min(99, max(5, (int) ($s->soc_end ?? $s->soc_start ?? 50)));
            $minsLeft = max(5, (int) ceil((100 - $soc) / 4));
            $connIdx = $connectors->search(fn ($c) => $c->id === $s->connector_id);
            $connLabel = $connIdx !== false
                ? chr(65 + intdiv($connIdx, 3)).(($connIdx % 3) + 1)
                : 'A'.($i + 1);

            return [
                'id' => $connLabel,
                'soc' => $soc,
                'eta' => Persian::digits($minsLeft).' دقیقه',
                'u' => $s->user?->name ?? 'مهمان',
            ];
        })->values()->all();

        $reservations = Reservation::with(['user', 'connector.charger'])
            ->where('station_id', $station->id)
            ->where('reserved_from', '>=', $rangeStart)
            ->where('reserved_from', '<=', $rangeEnd)
            ->orderBy('reserved_from')
            ->limit(50)
            ->get()
            ->map(function (Reservation $r, $i) use ($connectors, $station) {
                $status = match ($r->status) {
                    'cancelled' => ['لغوشده', 'pill-red'],
                    'pending' => ['در انتظار', 'pill-amber'],
                    default => ['تأییدشده', 'pill-green'],
                };
                $mins = max(20, (int) $r->reserved_from?->diffInMinutes($r->reserved_until));
                $vehicle = Vehicle::where('user_id', $r->user_id)->where('is_default', true)->first()
                    ?? Vehicle::where('user_id', $r->user_id)->first();

                $connIdx = $r->connector_id
                    ? $connectors->search(fn ($c) => $c->id === $r->connector_id)
                    : false;
                $connLabel = $connIdx !== false
                    ? chr(65 + intdiv($connIdx, 3)).(($connIdx % 3) + 1)
                    : chr(65 + ($i % 3)).(($i % 3) + 1);
                $code = 'RS‑'.Persian::digits(8800 + $r->id);
                $userName = $r->user?->name ?? '—';

                return [
                    'id' => $r->id,
                    'code' => $code,
                    'cancelled' => $r->status === 'cancelled',
                    'user' => $userName,
                    'phone' => Persian::digits($r->user?->phone ?? '—'),
                    'station' => $station->name,
                    'conn' => $connLabel,
                    'connType' => $r->connector?->charger?->connector_type ?? '—',
                    'date' => Persian::digits($r->reserved_from?->format('Y/m/d') ?? '—'),
                    'time' => Persian::digits($r->reserved_from?->format('H:i') ?? '—'),
                    'until' => Persian::digits($r->reserved_until?->format('H:i') ?? '—'),
                    'car' => $vehicle ? trim($vehicle->brand.' '.$vehicle->model) : '—',
                    'dur' => Persian::digits($mins).' دقیقه',
                    'st' => $status,
                    'search' => $code.' '.$userName.' '.($vehicle ? trim($vehicle->brand.' '.$vehicle->model) : ''),
                ];
            })
            ->values()
            ->all();

        $connectorOptions = Station::query()
            ->where('status', 'active')
            ->with(['chargers.connectors'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Station $st) {
                $options = $st->chargers->flatMap(function (Charger $ch) use ($st) {
                    return $ch->connectors->map(function (Connector $cn) use ($ch, $st) {
                        $snap = $this->availability->snapshot($cn);

                        return [
                            'id' => $cn->id,
                            'label' => $ch->charge_point_id.' · کانکتور '.$cn->connector_number,
                            'reservable' => $snap['reservable'],
                            'reason' => $snap['reason'],
                            'state' => $snap['state'],
                        ];
                    });
                })->values()->all();

                return [$st->id => $options];
            })
            ->all();

        return [
            'station' => $station,
            'timeline' => $rows,
            'live' => $live,
            'reservations' => $reservations,
            'connectorOptions' => $connectorOptions,
            'stationName' => $station->name,
            'stationStatus' => $station->status,
            'filters' => $filters,
            'kpis' => [
                'total_connectors' => $totalConnectors,
                'free_connectors' => $freeCount,
                'charging_connectors' => $chargingCount,
                'reserved_connectors' => $reservedCount,
                'period_reservations' => $periodTotal,
                'fulfillment_pct' => $periodTotal > 0 ? (int) round($periodFulfilled / $periodTotal * 100) : 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function usersJs(): array
    {
        $users = User::with(['wallet', 'organization', 'vehicles'])
            ->where('role', UserRole::Customer)
            ->withCount('chargingSessions')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (User $u) {
                $vehicle = $u->vehicles->firstWhere('is_default', true) ?? $u->vehicles->first();
                $isOrg = (bool) $u->organization_id;
                $bal = (int) round((float) ($u->wallet?->balance ?? 0) / 10);
                $active = $u->status === 'active';

                return [
                    'n' => $u->name,
                    'm' => Persian::digits($u->phone ?? '—'),
                    'r' => [$isOrg ? 'سازمانی' : 'عادی', $isOrg ? 'pill-accent' : 'tag'],
                    'pl' => $vehicle ? [
                        'd2' => Persian::digits($vehicle->plate_two),
                        'let' => $vehicle->plate_letter,
                        'd3' => Persian::digits($vehicle->plate_three),
                        'prov' => Persian::digits($vehicle->plate_province),
                    ] : null,
                    'auto' => $bal > 50_000,
                    'w' => Persian::money($bal),
                    's' => Persian::digits($u->charging_sessions_count),
                    'st' => [$active ? 'فعال' : 'غیرفعال', $active ? 'pill-green' : 'pill'],
                    'c' => $this->avatarColor($u->name),
                    'roleKey' => $isOrg ? 'org' : 'normal',
                ];
            })
            ->values()
            ->all();

        $orgColors = ['#0E6BA8', '#16A34A', '#7C3AED', '#F97316', '#0891B2'];
        $orgs = Organization::withCount(['users', 'members'])
            ->orderBy('name')
            ->get()
            ->map(function (Organization $o, $i) use ($orgColors) {
                $rfidCount = RfidCard::whereIn('user_id', $o->users()->pluck('id'))->count();
                $spent = (float) ChargingSession::whereIn('user_id', $o->users()->pluck('id'))
                    ->where('started_at', '>=', now()->startOfMonth())
                    ->sum('cost_amount');

                return [
                    'n' => $o->name,
                    't' => $o->type ?? 'سازمان',
                    'c' => $orgColors[$i % count($orgColors)],
                    'cars' => Persian::digits($o->users_count).' خودرو',
                    'cards' => Persian::digits(max($rfidCount, $o->users_count)).' کارت',
                    'cap' => Persian::digits((int) round((float) $o->credit_limit / 10 / 1_000_000)).' م/ماه',
                    'spent' => Persian::digits((int) round($spent / 10 / 1_000_000)).' م',
                ];
            })
            ->values()
            ->all();

        $roleLabels = [
            UserRole::SuperAdmin->value => 'مدیر کل (Super Admin)',
            UserRole::FinancialManager->value => 'مدیر مالی',
            UserRole::StationOperator->value => 'اپراتور ایستگاه',
            UserRole::Support->value => 'پشتیبانی',
            UserRole::CorporateManager->value => 'مدیر سازمان',
            UserRole::Partner->value => 'شریک',
            UserRole::Customer->value => 'کاربر نهایی',
        ];
        $roleDesc = [
            UserRole::SuperAdmin->value => 'دسترسی کامل به تمام بخش‌های سامانه، تنظیمات و کاربران.',
            UserRole::FinancialManager->value => 'دسترسی به دفتر کل، تسویه‌ها، فاکتورها و گزارش‌های مالیاتی.',
            UserRole::StationOperator->value => 'مدیریت و کنترل شارژرهای ایستگاه‌های تحت پوشش.',
            UserRole::Support->value => 'مشاهدهٔ خطاها، تیکت‌ها و کنترل از راه دور شارژرها.',
            UserRole::CorporateManager->value => 'مدیریت رانندگان، کارت‌ها و گزارش‌های سازمان مربوطه.',
            UserRole::Partner->value => 'دسترسی به پنل شریک و گزارش ایستگاه‌های تحت پوشش.',
            UserRole::Customer->value => 'استفاده از اپلیکیشن، کیف پول و شارژ خودرو.',
        ];

        $roles = collect(UserRole::cases())->map(function (UserRole $role) use ($roleLabels, $roleDesc) {
            $count = $role === UserRole::Customer
                ? User::where('role', UserRole::Customer)->count()
                : User::where('role', $role)->count();

            return [
                't' => $roleLabels[$role->value] ?? $role->value,
                'd' => $roleDesc[$role->value] ?? '',
                'n' => Persian::digits($count).' نفر',
            ];
        })->values()->all();

        return compact('users', 'orgs', 'roles');
    }

    /** @return array<string, mixed> */
    public function financeJs(): array
    {
        $net = [];
        $op = [];
        foreach (range(29, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();
            $rev = (float) ChargingSession::whereDate('started_at', $day)->sum('cost_amount');
            $m = (int) round($rev / 10 / 1_000_000);
            $net[] = max(0, $m);
            $platformFee = (float) ChargingSession::query()
                ->whereDate('charging_sessions.started_at', $day)
                ->join('stations', 'charging_sessions.station_id', '=', 'stations.id')
                ->leftJoin('partners', 'stations.partner_id', '=', 'partners.id')
                ->sum(DB::raw('charging_sessions.cost_amount * (100 - COALESCE(partners.revenue_share_percent, 70)) / 100'));
            $op[] = (int) round($platformFee / 10 / 1_000_000);
        }

        $settlements = Settlement::with('partner')
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (Settlement $s) {
                $status = match ($s->status) {
                    'approved', 'paid' => ['پرداخت‌شده', 'pill-green'],
                    'pending' => ['در انتظار', 'pill-amber'],
                    default => ['در حال بررسی', 'pill-accent'],
                };

                return [
                    'code' => $s->settlement_number ?? 'SET‑'.$s->id,
                    'partner' => $s->partner?->name ?? '—',
                    'period' => Persian::digits($s->period_start?->format('Y/m') ?? '—'),
                    'gross' => Persian::money((int) round((float) $s->gross_revenue / 10)),
                    'share' => Persian::money((int) round((float) $s->partner_share / 10)),
                    'fee' => Persian::money((int) round((float) $s->platform_fee / 10)),
                    'net' => Persian::money((int) round((float) $s->partner_share / 10)),
                    'status' => $status,
                    'search' => ($s->settlement_number ?? '').' '.($s->partner?->name ?? ''),
                ];
            })
            ->values()
            ->all();

        $ledger = collect()
            ->merge(
                WalletTransaction::with('wallet.user')
                    ->latest('occurred_at')
                    ->limit(6)
                    ->get()
                    ->map(function (WalletTransaction $t) {
                        $creditTypes = ['topup', 'bonus', 'refund'];
                        $isCredit = in_array($t->type, $creditTypes, true);
                        $amount = Persian::money((int) round(abs((float) $t->amount) / 10));

                        return [
                            'code' => 'JV‑'.Persian::digits($t->id),
                            'desc' => $t->description ?? $t->type,
                            'party' => $t->wallet?->user?->name ?? 'کیف پول',
                            'debit' => $isCredit ? $amount : '—',
                            'credit' => $isCredit ? '—' : $amount,
                            'balance' => Persian::money((int) round((float) ($t->balance_after ?? 0) / 10)),
                            'date' => Persian::digits($t->occurred_at?->format('Y/m/d') ?? '—'),
                        ];
                    })
            )
            ->merge(
                Payment::with('user')
                    ->latest()
                    ->limit(4)
                    ->get()
                    ->map(function (Payment $p) {
                        return [
                            'code' => 'JV‑P'.Persian::digits($p->id),
                            'desc' => 'دریافت وجه — '.($p->gateway ?? 'درگاه'),
                            'party' => $p->user?->name ?? 'بانک',
                            'debit' => Persian::money((int) round((float) $p->amount / 10)),
                            'credit' => '—',
                            'balance' => '—',
                            'date' => Persian::digits($p->created_at?->format('Y/m/d') ?? '—'),
                        ];
                    })
            )
            ->take(10)
            ->values()
            ->all();

        return compact('net', 'op', 'settlements', 'ledger');
    }

    /** @return array<string, mixed> */
    public function partnersJs(): array
    {
        $monthStart = now()->startOfMonth();
        $partners = Partner::withCount('stations')
            ->orderBy('name')
            ->get()
            ->map(function (Partner $p) use ($monthStart) {
                $stationIds = $p->stations()->pluck('id');
                $gross = (float) ChargingSession::whereIn('station_id', $stationIds)
                    ->where('started_at', '>=', $monthStart)
                    ->sum('cost_amount');
                $sharePct = (float) ($p->revenue_share_percent ?: 70);
                $share = $gross * ($sharePct / 100);
                $energy = (float) ChargingSession::whereIn('station_id', $stationIds)
                    ->where('started_at', '>=', $monthStart)
                    ->sum('energy_kwh');
                $pending = Settlement::where('partner_id', $p->id)
                    ->whereIn('status', ['draft', 'pending'])
                    ->exists();

                return [
                    'n' => $p->name,
                    't' => [$p->meta['type'] ?? 'شریک', 'tag'],
                    'st' => Persian::digits($p->stations_count),
                    'model' => 'اشتراک '.Persian::digits((int) $sharePct).'٪',
                    'en' => Persian::kwh($energy),
                    'gross' => Persian::money((int) round($gross / 10)),
                    'share' => Persian::money((int) round($share / 10)),
                    'set' => $pending ? ['در انتظار', 'pill-amber'] : ['تسویه‌شده', 'pill-green'],
                    'c' => $this->avatarColor($p->name),
                    'search' => $p->name.' '.$p->code,
                ];
            })
            ->values()
            ->all();

        $partnerNames = Partner::pluck('name', 'id');
        $invoices = Invoice::query()
            ->whereNotNull('partner_id')
            ->latest('issued_at')
            ->limit(10)
            ->get()
            ->map(function (Invoice $inv) use ($partnerNames) {
                $status = match ($inv->status) {
                    'paid' => ['پرداخت‌شده', 'pill-green'],
                    'issued' => ['صادرشده', 'pill-amber'],
                    default => ['پیش‌نویس', 'pill'],
                };
                $share = (float) $inv->subtotal;
                $fee = (float) $inv->tax_amount;

                return [
                    'code' => $inv->invoice_number ?? 'INV‑'.$inv->id,
                    'partner' => $partnerNames[$inv->partner_id] ?? '—',
                    'period' => Persian::digits($inv->issued_at?->format('Y/m') ?? '—'),
                    'share' => Persian::money((int) round($share / 10)),
                    'fee' => Persian::money((int) round($fee / 10)),
                    'net' => Persian::money((int) round((float) $inv->total_amount / 10)),
                    'status' => $status,
                    'search' => ($inv->invoice_number ?? '').' '.($partnerNames[$inv->partner_id] ?? ''),
                ];
            })
            ->values()
            ->all();

        $gross = [];
        $share = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();
            $g = (float) ChargingSession::whereBetween('started_at', [$start, $end])->sum('cost_amount');
            $partnerShare = (float) ChargingSession::query()
                ->whereBetween('charging_sessions.started_at', [$start, $end])
                ->join('stations', 'charging_sessions.station_id', '=', 'stations.id')
                ->leftJoin('partners', 'stations.partner_id', '=', 'partners.id')
                ->sum(DB::raw('charging_sessions.cost_amount * COALESCE(partners.revenue_share_percent, 70) / 100'));
            $gross[] = (int) round($g / 10 / 1_000_000);
            $share[] = (int) round($partnerShare / 10 / 1_000_000);
        }

        $topPartners = Partner::withCount('stations')
            ->get()
            ->map(function (Partner $p) use ($monthStart) {
                $rev = (float) ChargingSession::whereHas('station', fn ($q) => $q->where('partner_id', $p->id))
                    ->where('started_at', '>=', $monthStart)
                    ->sum('cost_amount');

                return ['n' => $p->name, 'v' => (int) round($rev / 10 / 1_000_000)];
            })
            ->sortByDesc('v')
            ->take(5)
            ->values()
            ->map(function (array $row, int $i) {
                $max = max(1, $row['v']);

                return [
                    'n' => $row['n'],
                    'v' => Persian::digits($row['v']).' میلیون',
                    'p' => (int) round(($row['v'] / $max) * 100),
                ];
            })
            ->all();

        $cooperationModels = Partner::query()
            ->selectRaw('ROUND(revenue_share_percent) as pct, COUNT(*) as c')
            ->groupBy('pct')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($row) => [
                'pct' => (int) $row->pct,
                'count' => (int) $row->c,
                'label' => $row->pct >= 80 ? 'اجارهٔ پلتفرم' : ($row->pct <= 55 ? 'سرمایه‌گذاری مشترک' : 'اشتراک درآمد'),
            ])
            ->values()
            ->all();

        $growth = [];
        for ($i = 5; $i >= 0; $i--) {
            $growth[] = Partner::where('created_at', '<=', now()->subMonths($i)->endOfMonth())->count();
        }

        $monthStart = now()->startOfMonth();
        $avgPartnerRevenue = Partner::count() > 0
            ? (int) round(
                ChargingSession::where('started_at', '>=', $monthStart)->sum('cost_amount')
                / Partner::count() / 10 / 1_000_000
            )
            : 0;
        $partnerStationIds = Station::whereNotNull('partner_id')->pluck('id');
        $partnerUtil = 0;
        if ($partnerStationIds->isNotEmpty()) {
            $conns = Connector::whereHas('charger', fn ($q) => $q->whereIn('station_id', $partnerStationIds))->count();
            $charging = Connector::whereHas('charger', fn ($q) => $q->whereIn('station_id', $partnerStationIds))
                ->where('ocpp_status', 'Charging')->count();
            $partnerUtil = $conns > 0 ? (int) round($charging / $conns * 100) : 0;
        }
        $onTimeSettlements = Settlement::where('status', 'approved')->count();
        $totalSettlements = max(1, Settlement::count());
        $onTimePct = (int) round($onTimeSettlements / $totalSettlements * 100);

        $sampleInvoice = Invoice::query()
            ->with('partner')
            ->whereNotNull('partner_id')
            ->latest('issued_at')
            ->first();

        return compact(
            'partners', 'invoices', 'gross', 'share', 'topPartners',
            'cooperationModels', 'growth', 'avgPartnerRevenue', 'partnerUtil', 'onTimePct', 'sampleInvoice'
        );
    }

    /** @return array<string, mixed> */
    public function inventoryJs(): array
    {
        $stock = InventoryItem::orderBy('name')->get()->map(function (InventoryItem $item) {
            $meta = $item->meta ?? [];
            $unitPrice = (int) ($meta['unit_price'] ?? match ($item->category) {
                'charger' => 185_000_000,
                'cable' => 14_000_000,
                default => 6_800_000,
            });
            $qty = (int) $item->quantity_on_hand;
            $total = $unitPrice * $qty;
            $kind = $item->category === 'charger' ? 'device' : 'part';
            $status = $qty <= 0
                ? ['ناموجود', 'pill-red']
                : ($qty < 10 ? ['کم‌موجودی', 'pill-amber'] : ['موجود', 'pill-green']);

            return [
                'sku' => $item->sku,
                'name' => $item->name,
                'kind' => $kind,
                'supplier' => $meta['supplier'] ?? '—',
                'qty' => Persian::digits($qty),
                'unit' => Persian::money((int) round($unitPrice / 10)),
                'total' => Persian::money((int) round($total / 10)),
                'date' => Persian::digits($item->created_at?->format('Y/m/d') ?? '—'),
                'status' => $status,
                'search' => $item->sku.' '.$item->name,
            ];
        })->values()->all();

        $assigns = Charger::with(['station.partner'])
            ->whereNotNull('serial_number')
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (Charger $ch) {
                $meta = $ch->meta ?? [];
                $purchase = (int) ($meta['purchase_price'] ?? 0);
                $sale = (int) ($meta['sale_price'] ?? 0);
                $status = match ($ch->status) {
                    'deployed', 'active' => ['نصب‌شده', 'pill-green'],
                    'shipping' => ['در حال ارسال', 'pill-amber'],
                    default => $ch->is_online ? ['نصب‌شده', 'pill-green'] : ['در انتظار نصب', 'pill-accent'],
                };

                return [
                    'code' => 'AS‑'.Persian::digits($ch->id),
                    'name' => ($ch->connector_type ?? 'شارژر').' '.$ch->max_power_kw.'kW',
                    'serial' => $ch->serial_number ?? '—',
                    'partner' => $ch->station?->partner?->name ?? $ch->station?->name ?? '—',
                    'purchase' => $purchase > 0 ? Persian::money((int) round($purchase / 10)) : '—',
                    'sale' => $sale > 0 ? Persian::money((int) round($sale / 10)) : '—',
                    'date' => Persian::digits($ch->created_at?->format('Y/m/d') ?? '—'),
                    'status' => $status,
                ];
            })
            ->values()
            ->all();

        $devices = Charger::with('station')
            ->orderBy('charge_point_id')
            ->limit(20)
            ->get()
            ->map(function (Charger $ch) {
                $st = match ($ch->status) {
                    'repair' => ['در تعمیر', 'pill-amber'],
                    'warehouse', 'stock' => ['در انبار', 'pill-accent'],
                    default => $ch->station_id ? ['واگذارشده', 'pill-green'] : ['در انبار', 'pill-accent'],
                };

                return [
                    'n' => 'شارژر '.($ch->charge_point_id ?? 'CP‑'.$ch->id),
                    's' => ($ch->connector_type ?? 'Type2').' · SN‑'.($ch->serial_number ?? $ch->id),
                    'st' => $st,
                    'id' => $ch->id,
                ];
            })
            ->values()
            ->all();

        $histories = [];
        foreach (Charger::with(['station.partner'])->orderBy('id')->limit(5)->get() as $ch) {
            $events = [];
            $movements = InventoryMovement::where('station_id', $ch->station_id)->with('item')->latest()->limit(3)->get();
            foreach ($movements as $mv) {
                $events[] = [
                    'ic' => $mv->movement_type === 'in' ? 'box' : 'truck',
                    'c' => 'ic-blue',
                    't' => $mv->movement_type === 'in' ? 'ورود به انبار' : 'خروج / واگذاری',
                    'd' => ($mv->item?->name ?? '—').' · '.($mv->notes ?? ''),
                    'date' => Persian::digits($mv->created_at?->format('Y/m/d') ?? '—'),
                    'cost' => null,
                ];
            }
            $repairs = RepairOrder::where('charger_id', $ch->id)->orderBy('opened_at')->get();
            foreach ($repairs as $rp) {
                $events[] = [
                    'ic' => 'wrench',
                    'c' => 'ic-orange',
                    't' => Str::limit($rp->issue_description ?? 'تعمیر', 40),
                    'd' => $rp->resolution_notes ?? '—',
                    'date' => Persian::digits($rp->opened_at?->format('Y/m/d') ?? '—'),
                    'cost' => null,
                ];
            }
            if ($ch->station_id) {
                $events[] = [
                    'ic' => 'plug',
                    'c' => 'ic-green',
                    't' => 'نصب در ایستگاه',
                    'd' => ($ch->station?->name ?? '—').' · '.($ch->station?->partner?->name ?? ''),
                    'date' => Persian::digits($ch->created_at?->format('Y/m/d') ?? '—'),
                    'cost' => null,
                ];
            }
            $histories[$ch->id] = array_reverse($events);
        }

        $chargerOptions = Charger::with('station')
            ->where(function ($q) {
                $q->whereNull('station_id')->orWhere('status', 'warehouse');
            })
            ->orderBy('charge_point_id')
            ->limit(30)
            ->get()
            ->map(fn (Charger $ch) => [
                'id' => $ch->id,
                'label' => ($ch->connector_type ?? 'شارژر').' '.$ch->max_power_kw.'kW · '.($ch->serial_number ?? $ch->id),
            ])
            ->values()
            ->all();

        $partners = Partner::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->name])
            ->values()
            ->all();

        return compact('stock', 'assigns', 'devices', 'histories', 'chargerOptions', 'partners');
    }

    /** @return array<string, mixed> */
    public function repairsJs(): array
    {
        $orders = RepairOrder::with(['charger', 'station', 'assignee'])
            ->latest('opened_at')
            ->limit(30)
            ->get()
            ->map(function (RepairOrder $r) {
                $status = match ($r->status) {
                    'open' => ['باز', 'pill-red', 'open'],
                    'in_progress' => ['در حال انجام', 'pill-amber', 'prog'],
                    default => ['تکمیل‌شده', 'pill-green', 'done'],
                };
                $parts = $r->resolution_notes
                    ? array_filter(array_map('trim', explode(',', $r->resolution_notes)))
                    : [];

                return [
                    'code' => 'RP‑'.Persian::digits(8800 + $r->id),
                    'charger' => $r->charger?->charge_point_id ?? '—',
                    'station' => $r->station?->name ?? '—',
                    'issue' => Str::limit($r->issue_description ?? '—', 40),
                    'parts' => $parts ?: ['—'],
                    'tech' => $r->assignee?->name ?? '—',
                    'date' => Persian::digits($r->opened_at?->format('Y/m/d') ?? '—'),
                    'cost' => Persian::money((int) round((float) (($r->meta['cost'] ?? 0) ?: 0) / 10)) ?: '—',
                    'status' => $status,
                    'search' => ($r->charger?->charge_point_id ?? '').' '.($r->station?->name ?? '').' '.($r->issue_description ?? ''),
                ];
            })
            ->values()
            ->all();

        $partsCatalog = InventoryItem::whereIn('category', ['cable', 'accessory', 'part'])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'sku' => $item->sku,
                'name' => $item->name,
                'price' => (int) round((float) (($item->meta['unit_price'] ?? 0) ?: 0) / 10),
            ])
            ->values()
            ->all();

        $preventive = RepairOrder::with(['charger', 'station'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('opened_at')
            ->limit(6)
            ->get()
            ->map(function (RepairOrder $r) {
                $dueMins = (int) ($r->opened_at?->diffInDays(now()) ?? 0);
                $col = $dueMins > 1 ? 'var(--red)' : ($dueMins > 0 ? 'var(--amber)' : 'var(--green)');
                $due = $dueMins > 1 ? 'معوق' : Persian::digits(max(1, 7 - $dueMins)).' روز';

                return [
                    't' => Str::limit($r->issue_description ?? 'سرویس دوره‌ای', 36),
                    's' => ($r->charger?->charge_point_id ?? '—').' · '.($r->station?->name ?? '—'),
                    'due' => $due,
                    'col' => $col,
                ];
            })
            ->values()
            ->all();

        $chargers = Charger::with('station')
            ->orderBy('charge_point_id')
            ->get()
            ->map(fn (Charger $ch) => [
                'id' => $ch->id,
                'label' => ($ch->charge_point_id ?? 'CP-'.$ch->id).' · '.($ch->station?->name ?? '—'),
            ])
            ->values()
            ->all();

        $technicians = User::query()
            ->whereIn('role', [
                UserRole::SuperAdmin,
                UserRole::StationOperator,
                UserRole::Support,
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();

        return compact('orders', 'preventive', 'partsCatalog', 'chargers', 'technicians');
    }

    /** @return array<string, mixed> */
    public function supportJs(): array
    {
        $faults = Charger::with(['station', 'connectors'])
            ->where(function ($q) {
                $q->where('is_online', false)
                    ->orWhereHas('connectors', fn ($c) => $c->whereIn('ocpp_status', ['Faulted', 'Unavailable']));
            })
            ->limit(15)
            ->get()
            ->map(function (Charger $ch) {
                $faulted = $ch->connectors->firstWhere('ocpp_status', 'Faulted')
                    ?? $ch->connectors->firstWhere('ocpp_status', 'Unavailable');
                $meta = $faulted?->meta ?? [];
                $code = $meta['error_code'] ?? ($ch->is_online ? 'Faulted' : 'ConnectionLost');
                $desc = $meta['error_info'] ?? ($ch->is_online ? 'خطای کانکتور' : 'قطع ارتباط شارژر با سرور');
                $crit = in_array($code, ['ConnectionLost', 'GroundFailure', 'PowerMeterFailure'], true)
                    || ! $ch->is_online;

                return [
                    'charger' => $ch->charge_point_id ?? 'CP‑'.$ch->id,
                    'station' => $ch->station?->name ?? '—',
                    'code' => $code,
                    'desc' => $desc,
                    'when' => $this->timeAgoFa($ch->updated_at),
                    'label' => [$crit ? 'بحرانی' : 'هشدار', $crit ? 'pill-red' : 'pill-amber'],
                    'sev' => $crit ? 'crit' : 'warn',
                    'search' => ($ch->charge_point_id ?? '').' '.($ch->station?->name ?? '').' '.$code,
                ];
            })
            ->values()
            ->all();

        $tickets = SupportTicket::with(['user', 'assignee'])
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(function (SupportTicket $t) {
                $priority = match ($t->priority) {
                    'high', 'critical' => ['بالا', 'pill-red'],
                    'medium' => ['متوسط', 'pill-amber'],
                    default => ['پایین', 'pill'],
                };
                $status = match ($t->status) {
                    'resolved', 'closed' => ['بسته شد', 'pill'],
                    'in_progress' => ['در حال بررسی', 'pill-accent'],
                    default => ['باز', 'pill-amber'],
                };

                return [
                    'id' => 'TK‑'.Persian::digits(substr((string) $t->ticket_number, -3)),
                    'user' => $t->user?->name ?? '—',
                    'subject' => $t->subject ?? '—',
                    'category' => $t->category ?? 'عمومی',
                    'assignee' => $t->assignee?->name ?? '—',
                    'when' => $this->timeAgoFa($t->updated_at),
                    'priority' => $priority,
                    'status' => $status,
                    'search' => ($t->ticket_number ?? '').' '.($t->user?->name ?? '').' '.($t->subject ?? ''),
                ];
            })
            ->values()
            ->all();

        return compact('faults', 'tickets');
    }

    /** @param  array<string, mixed>  $input */
    public function reportsBundle(array $input = []): array
    {
        $filters = $this->reports->resolveFilters($input);

        return $this->reports->build($filters);
    }

    /** @return array<string, mixed> */
    public function reportsJs(): array
    {
        return $this->reportsBundle();
    }

    private function avatarColor(string $name): string
    {
        return self::AVATAR_COLORS[crc32($name) % count(self::AVATAR_COLORS)];
    }

    private function timeAgoFa(?Carbon $dt): string
    {
        if (! $dt) {
            return '—';
        }
        $mins = (int) $dt->diffInMinutes(now());
        if ($mins < 1) {
            return 'لحظاتی پیش';
        }
        if ($mins < 60) {
            return Persian::digits($mins).' دقیقه پیش';
        }
        if ($mins < 1440) {
            return Persian::digits((int) floor($mins / 60)).' ساعت پیش';
        }
        if ($mins < 2880) {
            return 'دیروز';
        }

        return Persian::digits((int) floor($mins / 1440)).' روز پیش';
    }
}
