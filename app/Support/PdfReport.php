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

    public static function analytical(string $view, array $data, string $filename): Response
    {
        $data = self::withDefaults($data);

        return self::build($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', true)
            ->stream($filename);
    }

    public static function build(string $view, array $data): DomPdfDocument
    {
        return Pdf::loadView($view, self::withDefaults($data));
    }
}
