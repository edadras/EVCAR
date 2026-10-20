<?php

namespace App\Support;

/**
 * تخمین درصد باتری و زمان باقی‌ماندهٔ شارژ.
 *
 * - اگر شارژر SoC خودرو را گزارش کند (معمولاً شارژرهای DC)، همان مقدار مرجع است.
 * - در غیر این صورت: درصد شروع + انرژی تحویلی × راندمان ÷ ظرفیت باتری خودرو.
 * - زمان: انرژی باقی‌مانده تا هدف ÷ توان (بالای ۸۰٪ توان شارژ کاهش می‌یابد).
 */
final class ChargeEstimator
{
    /** سهم انرژی کنتور شارژر که واقعاً در باتری ذخیره می‌شود. */
    public const EFFICIENCY = 0.92;

    public const DEFAULT_BATTERY_KWH = 60.0;

    public const DEFAULT_START_SOC = 20;

    public static function socPercent(?float $reportedSoc, float $startSoc, float $energyKwh, float $batteryKwh): int
    {
        if ($reportedSoc !== null && $reportedSoc > 0) {
            return (int) max(0, min(100, round($reportedSoc)));
        }

        $battery = $batteryKwh > 0 ? $batteryKwh : self::DEFAULT_BATTERY_KWH;
        $soc = $startSoc + (max(0, $energyKwh) * self::EFFICIENCY / $battery) * 100;

        return (int) max(0, min(100, round($soc)));
    }

    /** دقیقه‌های باقی‌مانده تا رسیدن به goal، یا null اگر توان نامعلوم است یا هدف حاصل شده. */
    public static function etaMinutes(int $pct, int $goal, float $powerKw, float $batteryKwh): ?int
    {
        if ($pct >= $goal || $powerKw <= 0) {
            return null;
        }

        $battery = $batteryKwh > 0 ? $batteryKwh : self::DEFAULT_BATTERY_KWH;
        $fastKwh = max(0, min($goal, 80) - $pct) / 100 * $battery / self::EFFICIENCY;
        $slowKwh = max(0, $goal - max($pct, 80)) / 100 * $battery / self::EFFICIENCY;
        $hours = $fastKwh / $powerKw + $slowKwh / ($powerKw * 0.4);

        return max(1, (int) ceil($hours * 60));
    }
}
