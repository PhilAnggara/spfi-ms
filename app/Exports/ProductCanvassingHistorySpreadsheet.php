<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductCanvassingHistorySpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'Canvassing History';
    }

    protected function lastColumn(): string
    {
        return 'K';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 14,
            'C' => 12,
            'D' => 28,
            'E' => 12,
            'F' => 8,
            'G' => 12,
            'H' => 16,
            'I' => 14,
            'J' => 18,
            'K' => 28,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'B', 'Document'],
            ['C', 'F', 'Supplier Quote'],
            ['G', 'I', 'Terms'],
            ['J', 'K', 'Meta'],
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
            'F' => 'Curr',
            'G' => 'Status',
            'H' => 'TOP',
            'I' => 'TOD',
            'J' => 'Canvasser',
            'K' => 'Notes',
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
        $sheet->setCellValue('A4', 'Selected products: '.($this->data['selected_count'] ?? 0));
        $sheet->mergeCells('A4:C4');
    }

    protected function rows(): array
    {
        $flat = [];

        foreach ($this->data['groups'] ?? [] as $group) {
            $code = $group['item_code'] ?? '';
            $name = $group['item_name'] ?? '';
            $count = count($group['rows'] ?? []);
            $flat[] = [
                '_type' => 'group',
                'label' => "[{$code}] {$name} ({$count} quote(s))",
            ];

            foreach ($group['rows'] ?? [] as $row) {
                $flat[] = $row;
            }
        }

        return $flat;
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $this->excelDateValue($row['canvass_date'] ?? null));
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $row['supplier_code'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['supplier_name'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('F'.$rowIndex, $row['currency'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, ! empty($row['is_selected']) ? 'Selected' : 'Not selected');
        $sheet->setCellValue('H'.$rowIndex, $row['term_of_payment'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, $row['term_of_delivery'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('K'.$rowIndex, $row['notes'] ?? '');
    }
}
