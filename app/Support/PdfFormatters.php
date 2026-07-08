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
        return number_format((float) $value, 2, ',', '.');
    }
}
