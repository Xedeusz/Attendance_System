<?php

namespace App\Support;

class LeaveDays
{
    public static function format(int|float|string|null $value): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '—';
        }

        $days = (float) $value;

        if (! is_finite($days)) {
            return '—';
        }

        if (abs($days) < 0.005) {
            $days = 0;
        }

        return rtrim(rtrim(number_format($days, 2, '.', ','), '0'), '.');
    }

    public static function withUnit(int|float|string|null $value): string
    {
        $formatted = self::format($value);

        if ($formatted === '—') {
            return $formatted;
        }

        return $formatted.' '.(abs((float) $value) === 1.0 ? 'day' : 'days');
    }
}
