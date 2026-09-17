<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Symfony\Component\HttpFoundation\Response;

class PdfReport
{
    public const DEFAULT_COMPANY = 'PT. SINAR PURE FOODS INTERNATIONAL';

    public static function withDefaults(array $data): array
    {
        return array_merge([
            'company' => self::DEFAULT_COMPANY,
            'logo_path' => public_path('assets/images/sinar.png'),
            'printed_at' => now()->format('d M Y H:i'),
        ], $data);
    }

    public static function formal(
        string $view,
        array $data,
        string $filename,
        bool $landscape = false
    ): Response {
        $data = self::withDefaults($data);
        $data['landscape'] = $landscape;

        return self::build($view, $data)
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->stream($filename);
    }

    public static function analytical(string $view, array $data, string $filename, bool $landscape = true): Response
    {
        $currentLimit = ini_get('memory_limit');
        if (self::memoryLimitBytes($currentLimit) < 1024 * 1024 * 1024) {
            ini_set('memory_limit', '1024M');
        }

        $data = self::withDefaults($data);
        $data['landscape'] = $landscape;

        return self::build($view, $data)
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption('isPhpEnabled', true)
            ->stream($filename);
    }

    private static function memoryLimitBytes(string|false $limit): int
    {
        if ($limit === false || $limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }

    public static function build(string $view, array $data): DomPdfDocument
    {
        return Pdf::loadView($view, self::withDefaults($data));
    }
}
