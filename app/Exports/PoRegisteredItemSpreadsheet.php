<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoRegisteredItemSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PO Register Item';
    }

    protected function lastColumn(): string
    {
        return 'Q';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 28, 'C' => 8,
            'D' => 10, 'E' => 11, 'F' => 11, 'G' => 11, 'H' => 11, 'I' => 11, 'J' => 11,
            'K' => 10, 'L' => 11, 'M' => 11, 'N' => 11, 'O' => 11, 'P' => 11, 'Q' => 11,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'C', 'Item'],
            ['D', 'J', 'Current Month'],
            ['K', 'Q', 'Year to Date'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Code',
            'B' => 'Description',
            'C' => 'Unit',
            'D' => 'Quantity',
            'E' => 'IDR',
            'F' => 'PHP',
            'G' => 'EUR',
            'H' => 'GBP',
            'I' => 'USD',
            'J' => 'YEN',
            'K' => 'Quantity',
            'L' => 'IDR',
            'M' => 'PHP',
            'N' => 'EUR',
            'O' => 'GBP',
            'P' => 'USD',
            'Q' => 'YEN',
        ];
    }

    protected function dateColumns(): array
    {
        return [];
    }

    protected function qtyColumns(): array
    {
        return ['D', 'K'];
    }

    protected function moneyColumns(): array
    {
        return ['E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O', 'P', 'Q'];
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
        $sheet->setCellValue('A'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, (float) ($row['current_qty'] ?? 0));

        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['current_currency'] ?? null);
        $sheet->setCellValue('E'.$rowIndex, $idr);
        $sheet->setCellValue('F'.$rowIndex, $php);
        $sheet->setCellValue('G'.$rowIndex, $eur);
        $sheet->setCellValue('H'.$rowIndex, $gbp);
        $sheet->setCellValue('I'.$rowIndex, $usd);
        $sheet->setCellValue('J'.$rowIndex, $yen);

        $sheet->setCellValue('K'.$rowIndex, (float) ($row['ytd_qty'] ?? 0));

        [$yIdr, $yPhp, $yEur, $yGbp, $yUsd, $yYen] = $this->currencyValues($row['ytd_currency'] ?? null);
        $sheet->setCellValue('L'.$rowIndex, $yIdr);
        $sheet->setCellValue('M'.$rowIndex, $yPhp);
        $sheet->setCellValue('N'.$rowIndex, $yEur);
        $sheet->setCellValue('O'.$rowIndex, $yGbp);
        $sheet->setCellValue('P'.$rowIndex, $yUsd);
        $sheet->setCellValue('Q'.$rowIndex, $yYen);
    }
}
