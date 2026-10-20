<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Settlement;
use App\Models\Station;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Persian;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ReportsBundleService
{
    private const DC_CONNECTORS = ['CCS2', 'CHAdeMO', 'CCS1'];

    private const AC_CONNECTORS = ['Type2', 'Type1', 'AC'];

    /** @param  array<string, mixed>  $input */
    public function resolveFilters(array $input): array
    {
        $range = in_array($input['range'] ?? '', ['week', 'month', 'quarter', 'year', 'custom'], true)
            ? $input['range']
            : 'month';

        $dateFrom = ! empty($input['date_from']) ? Carbon::parse($input['date_from'])->startOfDay() : null;
        $dateTo = ! empty($input['date_to']) ? Carbon::parse($input['date_to'])->endOfDay() : null;

        if ($range === 'custom' && $dateFrom && $dateTo) {
            if ($dateTo->lt($dateFrom)) {
                [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
            }
            $periodStart = $dateFrom;
            $periodEnd = $dateTo;
        } else {
            [$periodStart, $periodEnd] = $this->periodBounds($range);
        }

        $connector = in_array($input['connector'] ?? '', ['dc', 'ac'], true) ? $input['connector'] : null;

        return [
            'range' => $range,
            'city' => ! empty($input['city']) ? (string) $input['city'] : null,
            'connector' => $connector,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),
            'period_start' => $periodStart->toIso8601String(),
            'period_end' => $periodEnd->toIso8601String(),
            'range_label' => match ($range) {
                'week' => 'هفته اخیر',
                'quarter' => 'فصل اخیر',
                'year' => 'سال اخیر',
                'custom' => 'بازه دلخواه',
                default => 'ماه اخیر',
            },
            'connector_label' => match ($connector) {
                'dc' => 'DC سریع',
                'ac' => 'AC',
                default => 'همه کانکتورها',
            },
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function build(array $filters): array
    {
        $periodStart = Carbon::parse($filters['period_start']);
        $periodEnd = Carbon::parse($filters['period_end']);
        $range = $filters['range'] ?? 'month';
        $rangeLabel = $filters['range_label'] ?? 'ماه اخیر';

        $baseQuery = $this->sessionQuery($filters, $periodStart, $periodEnd);
        $buckets = $this->chartBuckets($range, $periodStart, $periodEnd);

        $energyVals = [];
        $revenueVals = [];
        $sessionVals = [];
        $stationVals = [];
        $userVals = [];
        $energyRows = [];
        $revenueRows = [];
        $sessionRows = [];
        $userRows = [];

        foreach ($buckets as $i => $bucket) {
            $stats = $this->bucketStats($baseQuery, $bucket['start'], $bucket['end']);
            $energyVals[] = (int) round($stats['energy']);
            $revenueVals[] = (int) round($stats['revenue'] / 10 / 1_000_000);
            $sessionVals[] = $stats['sessions'];
            $stationVals[] = $stats['sessions'];
            $userVals[] = $stats['new_users'];

            if ($i >= count($buckets) - 8) {
                $label = $bucket['label'];
                $energyRows[] = [
                    $label,
                    Persian::digits($stats['sessions']),
                    Persian::kwh($stats['energy']),
                    Persian::kwh($stats['sessions'] > 0 ? $stats['energy'] / $stats['sessions'] : 0),
                    Persian::digits((int) round($stats['peak_kw'])).' kW',
                ];
                $grossM = (int) round($stats['revenue'] / 10 / 1_000_000);
                $feeM = (int) round($stats['fee'] / 10 / 1_000_000);
                $revenueRows[] = [
                    $label,
                    Persian::digits($stats['sessions']),
                    Persian::digits($grossM).' م',
                    Persian::digits($feeM).' م',
                    Persian::digits(max(0, $grossM - $feeM)).' م',
                ];
                $sessionRows[] = [
                    $label,
                    Persian::digits($stats['sessions']),
                    Persian::digits($stats['completed']),
                    Persian::digits($stats['failed']),
                    Persian::digits($stats['avg_duration_min']).' دق',
                ];
                $userRows[] = [
                    $label,
                    Persian::digits($stats['new_users']),
                    Persian::digits($stats['active_users']),
                    Persian::digits($stats['sessions']),
                    Persian::money($stats['avg_wallet_toman']),
                ];
            }
        }

        $scoped = fn () => $this->sessionQuery($filters, $periodStart, $periodEnd);
        $totalEnergy = max(1, (float) (clone $scoped())->sum('energy_kwh'));
        $dcEnergy = (float) (clone $scoped())->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::DC_CONNECTORS))->sum('energy_kwh');
        $acEnergy = (float) (clone $scoped())->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::AC_CONNECTORS))->sum('energy_kwh');
        $dcPct = (int) round($dcEnergy / $totalEnergy * 100);
        $acPct = (int) round($acEnergy / $totalEnergy * 100);
        $otherPct = max(0, 100 - $dcPct - $acPct);

        $stationRows = $this->stationRows($filters, $periodStart, $periodEnd);

        $totalUsers = User::where('role', UserRole::Customer)->count();
        $activeUsers = (clone $scoped())->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $newUsers = User::where('role', UserRole::Customer)->whereBetween('created_at', [$periodStart, $periodEnd])->count();

        $totalRev = (float) (clone $scoped())->sum('cost_amount');
        $dcRev = (float) (clone $scoped())->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::DC_CONNECTORS))->sum('cost_amount');
        $acRev = (float) (clone $scoped())->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::AC_CONNECTORS))->sum('cost_amount');
        $orgRev = (float) (clone $scoped())->whereHas('user', fn ($q) => $q->whereNotNull('organization_id'))->sum('cost_amount');
        $otherRev = max(0, $totalRev - $dcRev - $acRev - $orgRev);
        $revPct = fn (float $v) => $totalRev > 0 ? (int) round($v / $totalRev * 100) : 0;

        $partnerShareTotal = (float) Settlement::whereBetween('created_at', [$periodStart, $periodEnd])->sum('partner_share');
        $platformFeeTotal = (float) Settlement::whereBetween('created_at', [$periodStart, $periodEnd])->sum('platform_fee');
        if ($platformFeeTotal <= 0) {
            $platformFeeTotal = $totalRev * 0.09;
        }

        $sessionTotal = (int) (clone $scoped())->count();
        $completedSessions = (clone $scoped())->whereIn('status', ['completed', 'stopped'])->count();
        $stoppedSessions = (clone $scoped())->whereIn('status', ['stopped', 'cancelled'])->count();
        $failedSessions = (clone $scoped())->whereIn('status', ['failed', 'interrupted'])->count();
        $sessDenom = max(1, $completedSessions + $stoppedSessions + $failedSessions);
        $avgDurationMin = (int) round((float) (clone $scoped())->whereIn('status', ['completed', 'stopped'])->avg('duration_seconds') / 60);
        $successPct = (int) round($completedSessions / $sessDenom * 100);

        $orgUserCount = User::where('role', UserRole::Customer)->whereNotNull('organization_id')->count();
        $normalUsers = max(0, $totalUsers - $orgUserCount);
        $userDenom = max(1, $totalUsers);
        $orgUserPct = (int) round($orgUserCount / $userDenom * 100);
        $normalPct = (int) round($normalUsers / $userDenom * 100);
        $guestPct = max(0, 100 - $orgUserPct - $normalPct);

        $utils = collect($stationRows)->map(fn ($r) => (int) preg_replace('/\D/', '', (string) ($r[3] ?? '0')))->filter();
        $avgUtil = $utils->isNotEmpty() ? (int) round($utils->avg()) : 0;
        $highUtil = $utils->filter(fn ($u) => $u >= 70)->count();
        $midUtil = $utils->filter(fn ($u) => $u >= 40 && $u < 70)->count();
        $lowUtil = $utils->filter(fn ($u) => $u < 40)->count();
        $utilDenom = max(1, $highUtil + $midUtil + $lowUtil);

        $totalEnergySum = array_sum($energyVals);
        $totalRevenueM = array_sum($revenueVals);
        $chartSubtitle = match ($range) {
            'week' => '۷ روز گذشته',
            'quarter' => '۱۳ هفته گذشته',
            'year' => '۱۲ ماه گذشته',
            'custom' => Persian::digits($periodStart->format('Y/m/d')).' تا '.Persian::digits($periodEnd->format('Y/m/d')),
            default => '۳۰ روز گذشته',
        };

        return [
            'filters' => $filters,
            'chartPoints' => count($buckets),
            'chartSubtitle' => $chartSubtitle,
            'rangeLabel' => $rangeLabel,
            'energy' => [
                'vals' => $energyVals,
                'rows' => $energyRows,
                'donut' => [['DC سریع', $dcPct, 'var(--accent)'], ['AC', $acPct, 'var(--green)'], ['سایر', $otherPct, 'var(--orange)']],
                'kpis' => [
                    ['انرژی کل', Persian::kwh($totalEnergySum), 'ic-blue', 'bolt', $rangeLabel],
                    ['میانگین دوره', Persian::kwh($totalEnergySum / max(1, count($energyVals))), 'ic-green', 'leaf', ''],
                    ['اوج روزانه', Persian::kwh(max($energyVals ?: [0])), 'ic-orange', 'activity', ''],
                    ['سهم DC', Persian::digits($dcPct).'٪', 'ic-violet', 'plug', 'از کل'],
                ],
            ],
            'revenue' => [
                'vals' => $revenueVals,
                'rows' => $revenueRows,
                'donut' => [
                    ['شارژ DC', $revPct($dcRev), 'var(--accent)'],
                    ['شارژ AC', $revPct($acRev), 'var(--green)'],
                    ['سازمانی', $revPct($orgRev), 'var(--orange)'],
                    ['سایر', $revPct($otherRev), 'var(--violet)'],
                ],
                'kpis' => [
                    ['درآمد کل', Persian::digits($totalRevenueM).' میلیون', 'ic-green', 'coins', $rangeLabel],
                    ['میانگین دوره', Persian::digits((int) round($totalRevenueM / max(1, count($revenueVals)))).' م', 'ic-blue', 'chart', ''],
                    ['سهم شرکا', Persian::digits((int) round($partnerShareTotal / 10 / 1_000_000)).' م', 'ic-orange', 'users', ''],
                    ['کارمزد پلتفرم', Persian::digits((int) round($platformFeeTotal / 10 / 1_000_000)).' م', 'ic-violet', 'receipt', ''],
                ],
            ],
            'sessions' => [
                'vals' => $sessionVals,
                'rows' => $sessionRows,
                'donut' => [
                    ['تکمیل‌شده', (int) round($completedSessions / $sessDenom * 100), 'var(--green)'],
                    ['متوقف‌شده', (int) round($stoppedSessions / $sessDenom * 100), 'var(--amber)'],
                    ['ناموفق', (int) round($failedSessions / $sessDenom * 100), 'var(--red)'],
                ],
                'kpis' => [
                    ['کل جلسات', Persian::digits($sessionTotal), 'ic-violet', 'activity', $rangeLabel],
                    ['میانگین مدت', Persian::digits($avgDurationMin).' دقیقه', 'ic-blue', 'clock', ''],
                    ['موفق', Persian::digits($successPct).'٪', 'ic-green', 'bolt', ''],
                    ['ناموفق', Persian::digits($failedSessions), 'ic-orange', 'alert', ''],
                ],
            ],
            'users' => [
                'vals' => $userVals,
                'rows' => $userRows,
                'donut' => [
                    ['عادی', $normalPct, 'var(--accent)'],
                    ['سازمانی', $orgUserPct, 'var(--violet)'],
                    ['سایر', $guestPct, 'var(--orange)'],
                ],
                'kpis' => [
                    ['کل کاربران', Persian::digits($totalUsers), 'ic-blue', 'users', ''],
                    ['فعال (دوره)', Persian::digits($activeUsers), 'ic-green', 'activity', $rangeLabel],
                    ['جدید (دوره)', Persian::digits($newUsers), 'ic-orange', 'plus', ''],
                    ['سازمانی', Persian::digits($orgUserCount), 'ic-violet', 'building', ''],
                ],
            ],
            'stations' => [
                'vals' => $stationVals,
                'rows' => $stationRows,
                'donut' => [
                    ['بهره‌برداری بالا', (int) round($highUtil / $utilDenom * 100), 'var(--green)'],
                    ['متوسط', (int) round($midUtil / $utilDenom * 100), 'var(--accent)'],
                    ['پایین', (int) round($lowUtil / $utilDenom * 100), 'var(--amber)'],
                ],
                'kpis' => [
                    ['ایستگاه فعال', Persian::digits(Station::where('status', 'active')->count()), 'ic-orange', 'station', ''],
                    ['میانگین بهره‌برداری', Persian::digits($avgUtil).'٪', 'ic-blue', 'chart', ''],
                    ['پرکارترین', $stationRows[0][0] ?? '—', 'ic-green', 'bolt', ''],
                    ['کم‌بازده', $stationRows[count($stationRows) - 1][0] ?? '—', 'ic-violet', 'activity', ''],
                ],
            ],
            'cities' => Station::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->values()
                ->all(),
            'recordCount' => Persian::digits($sessionTotal),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodBounds(string $range): array
    {
        return match ($range) {
            'week' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'quarter' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'year' => [now()->subMonths(11)->startOfMonth(), now()->endOfDay()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };
    }

    /** @param  array<string, mixed>  $filters */
    private function sessionQuery(array $filters, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        $query = ChargingSession::query()->whereBetween('started_at', [$periodStart, $periodEnd]);

        if (! empty($filters['city'])) {
            $query->whereHas('station', fn ($q) => $q->where('city', $filters['city']));
        }

        if (($filters['connector'] ?? null) === 'dc') {
            $query->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::DC_CONNECTORS));
        } elseif (($filters['connector'] ?? null) === 'ac') {
            $query->whereHas('charger', fn ($q) => $q->whereIn('connector_type', self::AC_CONNECTORS));
        }

        return $query;
    }

    /** @return array<int, array{start: Carbon, end: Carbon, label: string}> */
    private function chartBuckets(string $range, Carbon $periodStart, Carbon $periodEnd): array
    {
        $buckets = [];

        if ($range === 'year') {
            foreach (range(11, 0) as $monthsAgo) {
                $start = now()->subMonths($monthsAgo)->startOfMonth();
                $end = now()->subMonths($monthsAgo)->endOfMonth();
                if ($end->lt($periodStart) || $start->gt($periodEnd)) {
                    continue;
                }
                $buckets[] = [
                    'start' => $start->lt($periodStart) ? $periodStart->copy() : $start,
                    'end' => $end->gt($periodEnd) ? $periodEnd->copy() : $end,
                    'label' => Persian::digits($start->format('Y/m')),
                ];
            }

            return $buckets;
        }

        if ($range === 'quarter') {
            foreach (range(12, 0) as $weeksAgo) {
                $start = now()->subWeeks($weeksAgo)->startOfWeek();
                $end = now()->subWeeks($weeksAgo)->endOfWeek();
                if ($end->lt($periodStart) || $start->gt($periodEnd)) {
                    continue;
                }
                $buckets[] = [
                    'start' => $start->lt($periodStart) ? $periodStart->copy() : $start,
                    'end' => $end->gt($periodEnd) ? $periodEnd->copy() : $end,
                    'label' => 'هفته '.Persian::digits($start->format('m/d')),
                ];
            }

            return $buckets;
        }

        $days = $range === 'week' ? 6 : max(0, (int) $periodStart->diffInDays($periodEnd));
        foreach (range($days, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo);
            if ($day->lt($periodStart) || $day->gt($periodEnd)) {
                continue;
            }
            $buckets[] = [
                'start' => $day->copy()->startOfDay(),
                'end' => $day->copy()->endOfDay(),
                'label' => Persian::digits($day->format('Y/m/d')),
            ];
        }

        return $buckets;
    }

    /** @return array{energy: float, revenue: float, sessions: int, completed: int, failed: int, avg_duration_min: int, peak_kw: float, new_users: int, active_users: int, avg_wallet_toman: int, fee: float} */
    private function bucketStats(Builder $baseQuery, Carbon $start, Carbon $end): array
    {
        $q = (clone $baseQuery)->whereBetween('started_at', [$start, $end]);
        $sessions = (int) (clone $q)->count();
        $energy = (float) (clone $q)->sum('energy_kwh');
        $revenue = (float) (clone $q)->sum('cost_amount');

        return [
            'energy' => $energy,
            'revenue' => $revenue,
            'sessions' => $sessions,
            'completed' => (int) (clone $q)->whereIn('status', ['completed', 'stopped'])->count(),
            'failed' => (int) (clone $q)->whereIn('status', ['failed', 'cancelled', 'interrupted'])->count(),
            'avg_duration_min' => (int) round((float) (clone $q)->whereIn('status', ['completed', 'stopped'])->avg('duration_seconds') / 60),
            'peak_kw' => (float) (clone $q)->join('chargers', 'chargers.id', '=', 'charging_sessions.charger_id')->max('chargers.max_power_kw'),
            'new_users' => (int) User::where('role', UserRole::Customer)->whereBetween('created_at', [$start, $end])->count(),
            'active_users' => (int) (clone $q)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'avg_wallet_toman' => (int) round((float) Wallet::avg('balance') / 10),
            'fee' => $revenue * 0.09,
        ];
    }

    /** @param  array<string, mixed>  $filters
     * @return array<int, array<int, string>>
     */
    private function stationRows(array $filters, Carbon $periodStart, Carbon $periodEnd): array
    {
        $stationQuery = Station::query();
        if (! empty($filters['city'])) {
            $stationQuery->where('city', $filters['city']);
        }

        return $stationQuery
            ->with(['chargers.connectors'])
            ->get()
            ->map(function (Station $st) use ($filters, $periodStart, $periodEnd) {
                $sessionsQ = $this->sessionQuery($filters, $periodStart, $periodEnd)->where('station_id', $st->id);
                $sessionCount = (int) (clone $sessionsQ)->count();
                $energy = (float) (clone $sessionsQ)->sum('energy_kwh');
                $revenue = (float) (clone $sessionsQ)->sum('cost_amount');
                $conns = $st->chargers->sum(fn (Charger $c) => $c->connectors->count());
                $charging = $st->chargers->sum(fn (Charger $c) => $c->connectors->where('ocpp_status', 'Charging')->count());
                $util = $conns > 0 ? (int) round($charging / $conns * 100) : 0;

                return [
                    'sessions' => $sessionCount,
                    'row' => [
                        $st->name.($st->city ? ' · '.$st->city : ''),
                        Persian::digits($sessionCount),
                        Persian::kwh($energy),
                        Persian::digits($util).'٪',
                        Persian::digits((int) round($revenue / 10 / 1_000_000)).' م',
                    ],
                ];
            })
            ->filter(fn ($item) => $item['sessions'] > 0)
            ->sortByDesc('sessions')
            ->take(12)
            ->pluck('row')
            ->values()
            ->all();
    }
}
