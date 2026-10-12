<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class CountTagPeriod
{
    /**
     * Normalize a period (YYYY-MM or any parseable date) to the last day of that month.
     */
    public static function toEndOfMonth(mixed $period): string
    {
        $normalized = self::parse($period);

        return $normalized->endOfMonth()->toDateString();
    }

    /**
     * Format a stored count_tag_date as YYYY-MM for month inputs / display.
     */
    public static function toMonthString(mixed $period): ?string
    {
        if ($period === null || trim((string) $period) === '') {
            return null;
        }

        try {
            return self::parse($period)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function currentMonthString(): string
    {
        return now()->format('Y-m');
    }

    public static function isFutureMonth(mixed $period): bool
    {
        try {
            $month = self::parse($period)->startOfMonth();
        } catch (\Throwable) {
            return false;
        }

        return $month->greaterThan(now()->startOfMonth());
    }

    public static function parse(mixed $period): Carbon
    {
        $value = trim((string) $period);

        if ($value === '') {
            throw new InvalidArgumentException('Period is required.');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }
}
