<?php

namespace App\Services\Charging;

use App\Models\Tariff;
use Carbon\CarbonInterface;

/**
 * محاسبهٔ هزینهٔ جلسه:
 *  - مدل انرژی (per_kwh/energy)، زمانی (per_minute/time) یا ترکیبی (mixed/combined)
 *  - قوانین ساعتی (peak / off_peak / weekend) بر اساس زمان شروع جلسه
 *  - هزینهٔ اشغال (idle) پس از پایان شارژ و گذشت زمان مجاز
 *  - هزینهٔ ثابت جلسه
 */
class TariffCalculator
{
    public const FALLBACK_PRICE_PER_KWH = 32000;

    /**
     * @param  array<string, mixed>|null  $rules  قوانین سریال‌شده (برای Edge) — در ابر از رابطه خوانده می‌شود
     * @return array{energy:float,time:float,idle:float,fee:float,total:float,price_per_kwh:float,rule:?string}
     */
    public function breakdown(?Tariff $tariff, float $energyKwh, int $durationSeconds, ?CarbonInterface $startedAt = null, int $idleSeconds = 0, bool $member = false): array
    {
        $energyKwh = max(0.0, $energyKwh);
        $minutes = $durationSeconds > 0 ? (int) ceil($durationSeconds / 60) : 0;
        $basePerKwh = $tariff?->price_per_kwh !== null ? (float) $tariff->price_per_kwh : null;
        $perMinute = (float) ($tariff?->price_per_minute ?? 0);

        [$perKwh, $ruleName] = $this->effectiveKwhPrice($tariff, $basePerKwh, $startedAt);

        $model = $this->model($tariff);
        $energyCost = match ($model) {
            'time' => 0.0,
            'mixed' => $energyKwh * (float) ($perKwh ?? 0),
            default => $energyKwh * ($perKwh ?? self::FALLBACK_PRICE_PER_KWH),
        };
        $timeCost = in_array($model, ['time', 'mixed'], true) ? $minutes * $perMinute : 0.0;

        // تخفیف اعضای سازمانی روی هزینهٔ انرژی/زمان (نه هزینه‌های ثابت و اشغال)
        $discount = $member ? max(0, min(100, (float) ($tariff?->member_discount_percent ?? 0))) : 0.0;
        $energyCost *= (1 - $discount / 100);
        $timeCost *= (1 - $discount / 100);

        $meta = $tariff?->meta ?? [];
        $idleFee = (float) ($meta['idle_fee_per_minute'] ?? 0);
        $grace = (int) ($meta['idle_grace_minutes'] ?? 10);
        $idleMinutes = max(0, (int) ceil($idleSeconds / 60) - $grace);
        $idleCost = $idleFee > 0 ? $idleMinutes * $idleFee : 0.0;

        $fee = (float) ($tariff?->session_fee ?? 0);
        $total = round($energyCost + $timeCost + $idleCost + $fee, 2);

        return [
            'energy' => round($energyCost, 2),
            'time' => round($timeCost, 2),
            'idle' => round($idleCost, 2),
            'fee' => $fee,
            'total' => $total,
            'price_per_kwh' => (float) ($perKwh ?? self::FALLBACK_PRICE_PER_KWH),
            'rule' => $ruleName,
        ];
    }

    public function cost(?Tariff $tariff, float $energyKwh, int $durationSeconds, ?CarbonInterface $startedAt = null, int $idleSeconds = 0, bool $member = false): float
    {
        return $this->breakdown($tariff, $energyKwh, $durationSeconds, $startedAt, $idleSeconds, $member)['total'];
    }

    /** @return array{0:?float,1:?string} */
    public function effectiveKwhPrice(?Tariff $tariff, ?float $basePerKwh, ?CarbonInterface $at): array
    {
        if (! $tariff || ! $at) {
            return [$basePerKwh, null];
        }

        $rules = $tariff->relationLoaded('rules') ? $tariff->rules : $tariff->rules()->get();
        $local = $at->copy()->setTimezone(config('safircharge.timezone', 'Asia/Tehran'));
        $time = $local->format('H:i:s');
        $dow = strtolower($local->format('l'));

        foreach ($rules->sortByDesc('priority') as $rule) {
            if (! in_array($rule->rule_type, ['peak', 'off_peak', 'weekend'], true)) {
                continue;
            }
            if ($rule->day_of_week && ! in_array($dow, array_map('trim', explode(',', strtolower($rule->day_of_week))), true)) {
                continue;
            }
            if ($rule->start_time && $rule->end_time && ! self::inWindow($time, (string) $rule->start_time, (string) $rule->end_time)) {
                continue;
            }

            $price = $rule->price_per_kwh !== null
                ? (float) $rule->price_per_kwh
                : ($basePerKwh !== null ? $basePerKwh * (float) ($rule->multiplier ?: 1) : null);

            return [$price, $rule->rule_type];
        }

        return [$basePerKwh, null];
    }

    public static function inWindow(string $time, string $start, string $end): bool
    {
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return $start <= $end
            ? $time >= $start && $time < $end
            : $time >= $start || $time < $end; // بازهٔ عبوری از نیمه‌شب
    }

    private function model(?Tariff $tariff): string
    {
        return match (strtolower((string) ($tariff?->pricing_model ?? ''))) {
            'per_minute', 'time', 'minute' => 'time',
            'mixed', 'combined', 'hybrid' => 'mixed',
            default => 'energy',
        };
    }
}
