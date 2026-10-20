<?php

namespace App\Services\Ocpp;

use App\Models\CachedTariff;
use Carbon\CarbonInterface;

/**
 * محاسبهٔ هزینهٔ جلسه بر اساس تعرفهٔ کش‌شده از ابر (همان منطق TariffCalculator ابر):
 * مدل انرژی/زمانی/ترکیبی، قوانین ساعتی (peak/off_peak/weekend)، هزینهٔ اشغال و هزینهٔ ثابت.
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

    public function cost(?CachedTariff $tariff, float $energyKwh, int $durationSeconds, ?CarbonInterface $startedAt = null, int $idleSeconds = 0): float
    {
        $energyKwh = max(0.0, $energyKwh);
        $minutes = $durationSeconds > 0 ? (int) ceil($durationSeconds / 60) : 0;
        $base = $tariff?->price_per_kwh !== null ? (float) $tariff->price_per_kwh : null;
        $perKwh = $this->effectiveKwhPrice($tariff, $base, $startedAt);
        $perMinute = (float) ($tariff?->price_per_minute ?? 0);

        $model = $this->model($tariff);
        $amount = match ($model) {
            'time' => $minutes * $perMinute,
            'mixed' => $energyKwh * (float) ($perKwh ?? 0) + $minutes * $perMinute,
            default => $energyKwh * ($perKwh ?? self::FALLBACK_PRICE_PER_KWH),
        };

        $meta = $tariff?->meta ?? [];
        $idleFee = (float) ($meta['idle_fee_per_minute'] ?? 0);
        $idleMinutes = max(0, (int) ceil($idleSeconds / 60) - (int) ($meta['idle_grace_minutes'] ?? 10));
        if ($idleFee > 0) {
            $amount += $idleMinutes * $idleFee;
        }

        return round($amount + (float) ($tariff?->session_fee ?? 0), 2);
    }

    public function effectiveKwhPrice(?CachedTariff $tariff, ?float $base, ?CarbonInterface $at): ?float
    {
        if (! $tariff || ! $at || ! is_array($tariff->rules) || $tariff->rules === []) {
            return $base;
        }

        $local = $at->copy()->setTimezone(config('safircharge.timezone', 'Asia/Tehran'));
        $time = $local->format('H:i:s');
        $dow = strtolower($local->format('l'));
        $rules = $tariff->rules;
        usort($rules, fn ($a, $b) => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0));

        foreach ($rules as $rule) {
            if (! in_array($rule['rule_type'] ?? '', ['peak', 'off_peak', 'weekend'], true)) {
                continue;
            }
            if (! empty($rule['day_of_week']) && ! in_array($dow, array_map('trim', explode(',', strtolower((string) $rule['day_of_week']))), true)) {
                continue;
            }
            if (! empty($rule['start_time']) && ! empty($rule['end_time']) && ! self::inWindow($time, (string) $rule['start_time'], (string) $rule['end_time'])) {
                continue;
            }

            return isset($rule['price_per_kwh']) && $rule['price_per_kwh'] !== null
                ? (float) $rule['price_per_kwh']
                : ($base !== null ? $base * (float) (($rule['multiplier'] ?? 1) ?: 1) : null);
        }

        return $base;
    }

    public static function inWindow(string $time, string $start, string $end): bool
    {
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return $start <= $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
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
