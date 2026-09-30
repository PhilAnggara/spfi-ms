<?php

namespace App\Support;

use Carbon\Carbon;

class PdfFormatters
{
    public static function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('d M Y') : '';
    }

    public static function tableDate(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('d-M-Y') : '';
    }

    public static function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    public static function qty(float|int|string $value): string
    {
        return self::trimmedDecimal($value, 5, ',', '.');
    }

    public static function trimmedDecimal(
        float|int|string $value,
        int $maxDecimals,
        string $decimalSeparator = ',',
        string $thousandsSeparator = '.',
    ): string {
        $formatted = number_format((float) $value, $maxDecimals, $decimalSeparator, $thousandsSeparator);
        $trimmed = rtrim(rtrim($formatted, '0'), $decimalSeparator);

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @param  array{should_convert?: bool, multiplier?: float|int|string|null}  $currencyConversion
     */
    public static function convertedUnitCost(float $unitCost, array $currencyConversion, int $decimals = 3): float
    {
        return round(self::convertedRaw($unitCost, $currencyConversion), $decimals);
    }

    /**
     * @param  array{should_convert?: bool, multiplier?: float|int|string|null}  $currencyConversion
     */
    public static function convertedAmount(float $amount, array $currencyConversion, int $decimals = 2): float
    {
        return round(self::convertedRaw($amount, $currencyConversion), $decimals);
    }

    public static function lineAmountFromUnit(float $unitCost, float $qty): float
    {
        return round($unitCost * $qty, 4);
    }

    /**
     * @param  array{should_convert?: bool, multiplier?: float|int|string|null}  $currencyConversion
     */
    private static function convertedRaw(float $amount, array $currencyConversion): float
    {
        if (! ($currencyConversion['should_convert'] ?? false)) {
            return $amount;
        }

        return $amount * (float) ($currencyConversion['multiplier'] ?? 1);
    }
}
