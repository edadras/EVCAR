<?php

namespace App\Services\Finance;

use App\Models\ChargingSession;
use App\Models\Partner;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تسویهٔ شرکا: فقط جلسه‌های پایان‌یافته (stopped/completed) با هزینهٔ واقعی محاسبه می‌شوند.
 */
class SettlementService
{
    public const FINISHED = ['stopped', 'completed'];

    public function generate(Partner $partner, string $periodStart, string $periodEnd, float $maintenanceFeeRial = 0, ?string $reference = null): Settlement
    {
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');
        $range = [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'];
        $base = fn () => ChargingSession::whereIn('station_id', $stationIds)
            ->whereIn('status', self::FINISHED)
            ->whereBetween('started_at', $range);

        return DB::transaction(function () use ($partner, $periodStart, $periodEnd, $maintenanceFeeRial, $reference, $stationIds, $base) {
            $gross = (float) $base()->sum('cost_amount');
            $share = round($gross * ((float) $partner->revenue_share_percent / 100), 2);

            $settlement = Settlement::create([
                'uuid' => (string) Str::uuid(),
                'settlement_number' => 'STL-'.now()->format('ym').'-'.Str::upper(Str::random(4)),
                'partner_id' => $partner->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'pending',
                'gross_revenue' => $gross,
                'platform_fee' => round($gross - $share, 2),
                'partner_share' => max(0, $share - $maintenanceFeeRial),
                'energy_kwh' => (float) $base()->sum('energy_kwh'),
                'session_count' => (int) $base()->count(),
                'payment_reference' => $reference,
                'meta' => ['maintenance_fee' => $maintenanceFeeRial],
            ]);

            foreach ($stationIds as $stationId) {
                $q = fn () => $base()->where('station_id', $stationId);
                $stGross = (float) $q()->sum('cost_amount');
                if ($stGross <= 0) {
                    continue;
                }
                SettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'station_id' => $stationId,
                    'gross_revenue' => $stGross,
                    'partner_share' => round($stGross * ((float) $partner->revenue_share_percent / 100), 2),
                    'energy_kwh' => (float) $q()->sum('energy_kwh'),
                    'session_count' => (int) $q()->count(),
                ]);
            }

            return $settlement;
        });
    }

    /** تسویهٔ خودکار ماه قبل برای شرکایی که چرخهٔ ماهانه دارند (تکراری ساخته نمی‌شود). */
    public function generateMonthly(?string $month = null): int
    {
        $start = $month ? Carbon::parse($month.'-01') : now()->subMonthNoOverflow()->startOfMonth();
        $periodStart = $start->toDateString();
        $periodEnd = $start->copy()->endOfMonth()->toDateString();
        $created = 0;

        foreach (Partner::where('status', 'active')->where('settlement_cycle', 'monthly')->get() as $partner) {
            if (Settlement::where('partner_id', $partner->id)->whereDate('period_start', $periodStart)->exists()) {
                continue;
            }
            $this->generate($partner, $periodStart, $periodEnd);
            $created++;
        }

        return $created;
    }
}
