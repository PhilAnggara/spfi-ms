<?php

namespace App\Services\Reconcile;

use Illuminate\Support\Carbon;

class DocumentFingerprint
{
    public static function normalizeKey(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    public static function normalizeDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }

    /**
     * @param  array<int, array{code: string, qty: float|int|string}>  $lines
     */
    public static function linesSignature(array $lines): string
    {
        $parts = [];

        foreach ($lines as $line) {
            $code = self::normalizeKey($line['code'] ?? '');
            $qty = round((float) ($line['qty'] ?? 0), 5);
            if ($code === '') {
                continue;
            }
            $parts[] = $code.':'.$qty;
        }

        sort($parts);

        return implode('|', $parts);
    }

    public static function hash(string $signature): string
    {
        return hash('sha256', $signature);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function compose(array $parts): string
    {
        $normalized = [];

        foreach ($parts as $key => $value) {
            $normalized[$key] = is_string($value) || is_numeric($value)
                ? self::normalizeKey((string) $value)
                : (string) $value;
        }

        ksort($normalized);

        return self::hash(json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
