<?php

namespace App\Services\Partner;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Partner;
use App\Models\Settlement;
use App\Models\Station;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PartnerMetricsService
{
    public function forPartner(Partner $partner): array
    {
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');
        $monthStart = now()->startOfMonth();
        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd = now()->subMonth()->endOfMonth();

        $monthRevenue = ChargingSession::whereIn('station_id', $stationIds)
            ->where('started_at', '>=', $monthStart)
            ->sum('cost_amount');
        $prevRevenue = ChargingSession::whereIn('station_id', $stationIds)
            ->whereBetween('started_at', [$prevStart, $prevEnd])
            ->sum('cost_amount');
        $partnerShare = round($monthRevenue * ($partner->revenue_share_percent / 100), 2);
        $prevShare = round($prevRevenue * ($partner->revenue_share_percent / 100), 2);
        $shareTrend = $prevShare > 0 ? round((($partnerShare - $prevShare) / $prevShare) * 100, 1) : 0;

        $monthEnergy = ChargingSession::whereIn('station_id', $stationIds)
            ->where('started_at', '>=', $monthStart)
            ->sum('energy_kwh');
        $monthSessions = ChargingSession::whereIn('station_id', $stationIds)
            ->where('started_at', '>=', $monthStart)
            ->count();

        $chargers = Charger::whereIn('station_id', $stationIds)->get();
        $online = $chargers->where('is_online', true)->count();

        return [
            'partner' => $partner,
            'partner_share' => $partnerShare,
            'partner_share_million' => round($partnerShare / 10_000_000, 1),
            'share_trend' => $shareTrend,
            'month_energy' => round($monthEnergy, 0),
            'month_sessions' => $monthSessions,
            'chargers_total' => $chargers->count(),
            'chargers_online' => $online,
            'stations_count' => $stationIds->count(),
            'next_settlement' => Settlement::where('partner_id', $partner->id)->latest()->first(),
        ];
    }

    public function dashboard(Partner $partner): array
    {
        $base = $this->forPartner($partner);
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');

        $daily = ChargingSession::whereIn('station_id', $stationIds)
            ->where('started_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(started_at) as d, SUM(cost_amount) as r, SUM(energy_kwh) as e')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return array_merge($base, [
            'daily' => $daily,
            'recentSessions' => ChargingSession::with(['station', 'charger'])
                ->whereIn('station_id', $stationIds)
                ->latest('started_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function stations(Partner $partner): array
    {
        return [
            'partner' => $partner,
            'stations' => Station::where('partner_id', $partner->id)
                ->withCount('chargers')
                ->with(['chargers.connectors'])
                ->get(),
            'mapMarkers' => array_values(array_filter(
                app(\App\Services\Admin\AdminMetricsService::class)->stationMapMarkers(),
                fn ($m) => in_array($m['id'], Station::where('partner_id', $partner->id)->pluck('id')->all(), true)
            )),
        ];
    }

    public function payments(Partner $partner): array
    {
        return [
            'partner' => $partner,
            'settlements' => Settlement::where('partner_id', $partner->id)
                ->with('items.station')
                ->latest()
                ->get(),
        ];
    }

    public function reports(Partner $partner): array
    {
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');

        return [
            'partner' => $partner,
            'byStation' => Station::where('partner_id', $partner->id)
                ->withCount('chargers')
                ->get()
                ->map(function (Station $s) use ($partner) {
                    $revenue = $s->chargingSessions()->sum('cost_amount');

                    return [
                        'station' => $s,
                        'sessions' => $s->chargingSessions()->count(),
                        'energy' => $s->chargingSessions()->sum('energy_kwh'),
                        'revenue' => $revenue,
                        'share' => round($revenue * ($partner->revenue_share_percent / 100), 2),
                    ];
                }),
            'daily' => ChargingSession::whereIn('station_id', $stationIds)
                ->where('started_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(started_at) as d, COUNT(*) as c, SUM(energy_kwh) as e')
                ->groupBy('d')
                ->orderBy('d')
                ->get(),
        ];
    }

    public function support(Partner $partner): Collection
    {
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');

        return SupportTicket::with(['station', 'assignee'])
            ->whereIn('station_id', $stationIds)
            ->latest()
            ->get();
    }
}
