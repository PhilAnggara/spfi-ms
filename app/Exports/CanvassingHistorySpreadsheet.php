<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CanvassingHistorySpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'Canvassing History';
    }

    protected function lastColumn(): string
    {
        return 'J';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 14,
            'C' => 12,
            'D' => 28,
            'E' => 12,
            'F' => 12,
            'G' => 16,
            'H' => 14,
            'I' => 18,
            'J' => 28,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'B', 'Document'],
            ['C', 'E', 'Supplier Quote'],
            ['F', 'H', 'Terms'],
            ['I', 'J', 'Meta'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Canvass Date',
            'B' => 'PRS Number',
            'C' => 'Supplier Code',
            'D' => 'Supplier Name',
            'E' => 'Unit Price',
            'F' => 'Status',
            'G' => 'TOP',
            'H' => 'TOD',
            'I' => 'Canvasser',
            'J' => 'Notes',
        ];
    }

    protected function dateColumns(): array
    {
        return ['A'];
    }

    protected function qtyColumns(): array
    {
        return [];
    }

    protected function moneyColumns(): array
    {
        return ['E'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('A3', 'Product: '.($this->data['item_code'] ?? '').' — '.($this->data['item_name'] ?? ''));
        $sheet->mergeCells("A3:{$lastCol}3");

        $summary = $this->data['summary'] ?? [];
        $avg = $summary['avg_unit_price'] ?? null;
        $min = $summary['min_unit_price'] ?? null;
        $quotes = $summary['quote_count'] ?? 0;
        $suppliers = $summary['supplier_count'] ?? 0;

        $sheet->setCellValue(
            'A4',
            sprintf(
                'Summary: Avg %s | Min %s | %s quote(s) | %s supplier(s)',
                $avg !== null ? number_format((float) $avg, 2) : '-',
                $min !== null ? number_format((float) $min, 2) : '-',
                $quotes,
                $suppliers
            )
        );
        $sheet->mergeCells('A4:E4');
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $this->excelDateValue($row['canvass_date'] ?? null));
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $row['supplier_code'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['supplier_name'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('F'.$rowIndex, ! empty($row['is_selected']) ? 'Selected' : 'Not selected');
        $sheet->setCellValue('G'.$rowIndex, $row['term_of_payment'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['term_of_delivery'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['notes'] ?? '');
    }
}
