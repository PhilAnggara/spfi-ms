<?php

use App\Support\PdfFormatters;

it('formats whole quantities without trailing decimals', function () {
    expect(PdfFormatters::qty(10))->toBe('10')
        ->and(PdfFormatters::qty(0))->toBe('0')
        ->and(PdfFormatters::qty('1000'))->toBe('1.000');
});

it('keeps meaningful decimal places and trims trailing zeros', function () {
    expect(PdfFormatters::qty(10.5))->toBe('10,5')
        ->and(PdfFormatters::qty(1.25000))->toBe('1,25')
        ->and(PdfFormatters::qty(0.00001))->toBe('0,00001')
        ->and(PdfFormatters::qty(1234.56789))->toBe('1.234,56789');
});
