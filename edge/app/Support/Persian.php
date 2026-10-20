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

    public static function num(int|float|string|null $value): string
    {
        return self::digits($value);
    }

    public static function money(float|int $amount, int $decimals = 0): string
    {
        return self::digits(number_format($amount, $decimals));
    }
}
