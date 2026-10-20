<?php

namespace App\Services\Ocpp;

use App\Models\CachedTariff;

/**
 * محاسبهٔ هزینهٔ جلسه بر اساس تعرفهٔ کش‌شده از ابر.
 * مدل‌ها: انرژی (per_kwh/energy)، زمان (per_minute/time)، ترکیبی (mixed/combined).
 */
class TariffCalculator
{
    public const FALLBACK_PRICE_PER_KWH = 32000;

    public function current(): ?CachedTariff
    {
        return CachedTariff::query()
            ->orderByDesc('station_assigned')
            ->orderByDesc('is_default')
            ->orderByDesc('synced_at')
            ->first();
    }

    public function cost(?CachedTariff $tariff, float $energyKwh, int $durationSeconds): float
    {
        $energyKwh = max(0.0, $energyKwh);
        $minutes = $durationSeconds > 0 ? (int) ceil($durationSeconds / 60) : 0;
        $perKwh = $tariff?->price_per_kwh !== null ? (float) $tariff->price_per_kwh : null;
        $perMinute = (float) ($tariff?->price_per_minute ?? 0);
        $fee = (float) ($tariff?->session_fee ?? 0);

        $amount = match ($this->model($tariff)) {
            'time' => $minutes * $perMinute,
            'mixed' => $energyKwh * (float) ($perKwh ?? 0) + $minutes * $perMinute,
            default => $energyKwh * ($perKwh ?? self::FALLBACK_PRICE_PER_KWH),
        };

        return round($amount + $fee, 2);
    }

    private function model(?CachedTariff $tariff): string
    {
        return match (strtolower((string) ($tariff?->pricing_model ?? ''))) {
            'per_minute', 'time', 'minute' => 'time',
            'mixed', 'combined', 'hybrid' => 'mixed',
            default => 'energy',
        };
    }
}
