<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoRegisteredSupplierSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PO Register Supplier';
    }

    protected function lastColumn(): string
    {
        return 'N';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 28,
            'C' => 11, 'D' => 11, 'E' => 11, 'F' => 11, 'G' => 11, 'H' => 11,
            'I' => 11, 'J' => 11, 'K' => 11, 'L' => 11, 'M' => 11, 'N' => 11,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'B', 'Supplier'],
            ['C', 'H', 'Current Month'],
            ['I', 'N', 'Year to Date'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Code',
            'B' => 'Description',
            'C' => 'IDR',
            'D' => 'PHP',
            'E' => 'EUR',
            'F' => 'GBP',
            'G' => 'USD',
            'H' => 'YEN',
            'I' => 'IDR',
            'J' => 'PHP',
            'K' => 'EUR',
            'L' => 'GBP',
            'M' => 'USD',
            'N' => 'YEN',
        ];
    }

    protected function dateColumns(): array
    {
        return [];
    }

    protected function qtyColumns(): array
    {
        return [];
    }

    protected function moneyColumns(): array
    {
        return ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['supplier_code'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['supplier_name'] ?? '');

        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['current_currency'] ?? null);
        $sheet->setCellValue('C'.$rowIndex, $idr);
        $sheet->setCellValue('D'.$rowIndex, $php);
        $sheet->setCellValue('E'.$rowIndex, $eur);
        $sheet->setCellValue('F'.$rowIndex, $gbp);
        $sheet->setCellValue('G'.$rowIndex, $usd);
        $sheet->setCellValue('H'.$rowIndex, $yen);

        [$yIdr, $yPhp, $yEur, $yGbp, $yUsd, $yYen] = $this->currencyValues($row['ytd_currency'] ?? null);
        $sheet->setCellValue('I'.$rowIndex, $yIdr);
        $sheet->setCellValue('J'.$rowIndex, $yPhp);
        $sheet->setCellValue('K'.$rowIndex, $yEur);
        $sheet->setCellValue('L'.$rowIndex, $yGbp);
        $sheet->setCellValue('M'.$rowIndex, $yUsd);
        $sheet->setCellValue('N'.$rowIndex, $yYen);
    }
}
