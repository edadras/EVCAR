<?php

namespace App\Support;

class Persian
{
    private const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public static function digits(int|float|string|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return str_replace(range(0, 9), self::DIGITS, (string) $value);
    }

    /** @alias digits() */
    public static function num(int|float|string|null $value): string
    {
        return self::digits($value);
    }

    public static function money(float|int $amount, int $decimals = 0): string
    {
        return self::digits(number_format($amount, $decimals));
    }

    public static function kwh(float|int $amount, int $decimals = 1): string
    {
        return self::digits(number_format($amount, $decimals));
    }

    /** Jalali date/time in the app timezone, e.g. ۱۴۰۵/۰۸/۱۲ ۱۴:۳۵ */
    public static function dateTime(?\DateTimeInterface $at, string $pattern = 'yyyy/MM/dd HH:mm'): string
    {
        if (! $at) {
            return '—';
        }
        if (class_exists(\IntlDateFormatter::class)) {
            $fmt = new \IntlDateFormatter('fa_IR@calendar=persian', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
                config('app.timezone'), \IntlDateFormatter::TRADITIONAL, $pattern);
            $out = $fmt->format($at);
            if ($out !== false) {
                return self::digits(self::toAsciiDigits($out));
            }
        }

        $php = strtr($pattern, ['yyyy' => 'Y', 'MMMM' => 'F', 'MM' => 'm', 'dd' => 'd', 'HH' => 'H', 'mm' => 'i']);

        return self::digits(\Carbon\Carbon::instance($at)->setTimezone(config('app.timezone'))->format($php));
    }

    public static function date(?\DateTimeInterface $at): string
    {
        return self::dateTime($at, 'yyyy/MM/dd');
    }

    public static function time(?\DateTimeInterface $at): string
    {
        return $at ? self::digits(\Carbon\Carbon::instance($at)->setTimezone(config('app.timezone'))->format('H:i')) : '—';
    }

    public static function toAsciiDigits(string $value): string
    {
        return str_replace(
            array_merge(self::DIGITS, ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩']),
            array_merge(range(0, 9), range(0, 9)),
            $value
        );
    }
}
