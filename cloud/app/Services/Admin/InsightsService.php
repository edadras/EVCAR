<?php

namespace App\Services\Admin;

use App\Models\ChargingSession;
use App\Models\OperationalAlert;
use App\Models\Station;
use Illuminate\Support\Collection;

/**
 * داشبورد تحلیلی: عملکرد ایستگاه‌ها، پیش‌بینی ساعات شلوغ و نشانه‌های خرابی.
 */
class InsightsService
{
    public const FINISHED = ['stopped', 'completed'];

    /** دلایل توقف غیرعادی OCPP 1.6 که نشانهٔ مشکل دستگاه است. */
    public const ABNORMAL_STOPS = ['EmergencyStop', 'PowerLoss', 'Reboot', 'HardReset', 'SoftReset', 'Other', 'UnlockCommand', 'ChargerOffline'];

    /** @return list<array<string, mixed>> */
    public function stationPerformance(int $days = 30): array
    {
        $since = now()->subDays($days);
        $rows = ChargingSession::query()
            ->whereIn('status', self::FINISHED)
            ->where('started_at', '>=', $since)
            ->selectRaw('station_id, COUNT(*) as sessions, SUM(energy_kwh) as kwh, SUM(cost_amount) as revenue, SUM(duration_seconds) as secs, AVG(duration_seconds) as avg_secs')
            ->groupBy('station_id')
            ->get()
            ->keyBy('station_id');

        return Station::withCount('chargers')->with('chargers.connectors')->get()->map(function (Station $st) use ($rows, $days) {
            $r = $rows->get($st->id);
            $connectors = max(1, $st->chargers->sum(fn ($c) => $c->connectors->count()));
            $utilization = $r ? min(100, (float) $r->secs / ($connectors * $days * 86400) * 100) : 0;

            return [
                'station' => $st->name,
                'city' => $st->city,
                'sessions' => (int) ($r->sessions ?? 0),
                'kwh' => round((float) ($r->kwh ?? 0), 1),
                'revenue' => (float) ($r->revenue ?? 0),
                'avg_minutes' => (int) round((float) ($r->avg_secs ?? 0) / 60),
                'utilization' => round($utilization, 1),
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    /**
     * پیش‌بینی تعداد جلسه در هر ساعت ۲۴ ساعت آینده: میانگین همان ساعتِ همان روز هفته در ۸ هفتهٔ گذشته.
     *
     * @return array<int, array{hour:int, expected:float}>
     */
    public function busyHoursForecast(?int $stationId = null, int $weeks = 8): array
    {
        $tz = config('safircharge.timezone', 'Asia/Tehran');
        $sessions = ChargingSession::query()
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId))
            ->where('started_at', '>=', now()->subWeeks($weeks))
            ->pluck('started_at');

        $buckets = [];
        foreach ($sessions as $at) {
            $local = $at->copy()->setTimezone($tz);
            $key = $local->dayOfWeek.'-'.$local->hour;
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        $result = [];
        $now = now()->setTimezone($tz)->startOfHour();
        for ($i = 0; $i < 24; $i++) {
            $slot = $now->copy()->addHours($i);
            $result[] = [
                'hour' => $slot->hour,
                'expected' => round(($buckets[$slot->dayOfWeek.'-'.$slot->hour] ?? 0) / $weeks, 2),
            ];
        }

        return $result;
    }

    /** شارژرهایی با بیشترین توقف غیرعادی یا هشدار خرابی — کاندید سرویس پیشگیرانه. */
    public function maintenanceCandidates(int $days = 30): Collection
    {
        $stops = ChargingSession::with('charger.station')
            ->where('started_at', '>=', now()->subDays($days))
            ->whereIn('stop_reason', self::ABNORMAL_STOPS)
            ->get()
            ->groupBy('charger_id');

        $alerts = OperationalAlert::where('opened_at', '>=', now()->subDays($days))
            ->whereIn('type', ['connector_faulted', 'charger_offline'])
            ->get()
            ->groupBy(fn ($a) => explode('#', $a->subject_key)[0]);

        $candidates = collect();
        foreach ($stops as $chargerId => $list) {
            $cp = $list->first()->charger?->charge_point_id;
            $candidates[$cp] = [
                'charge_point_id' => $cp,
                'station' => $list->first()->charger?->station?->name,
                'abnormal_stops' => $list->count(),
                'alerts' => ($alerts[$cp] ?? collect())->sum('occurrences'),
            ];
        }
        foreach ($alerts as $cp => $list) {
            if (! isset($candidates[$cp])) {
                $candidates[$cp] = ['charge_point_id' => $cp, 'station' => $list->first()->station?->name, 'abnormal_stops' => 0, 'alerts' => $list->sum('occurrences')];
            }
        }

        return $candidates->map(fn ($c) => $c + ['score' => $c['abnormal_stops'] * 2 + $c['alerts']])
            ->sortByDesc('score')->take(10)->values();
    }
}
