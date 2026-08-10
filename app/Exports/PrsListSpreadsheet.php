<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrsListSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PRS List';
    }

    protected function lastColumn(): string
    {
        return 'J';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 4,
            'B' => 14,
            'C' => 12,
            'D' => 12,
            'E' => 18,
            'F' => 12,
            'G' => 28,
            'H' => 10,
            'I' => 8,
            'J' => 24,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'D', 'Purchase Requisition Slip'],
            ['E', 'E', 'Department'],
            ['F', 'I', 'Items'],
            ['J', 'J', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => '#',
            'B' => 'Number',
            'C' => 'PRS Date',
            'D' => 'Date Needed',
            'E' => 'Department',
            'F' => 'Code',
            'G' => 'Description',
            'H' => 'Qty',
            'I' => 'Unit',
            'J' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['C', 'D'];
    }

    protected function qtyColumns(): array
    {
        return ['H'];
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
        $sheet->setCellValue('A'.$rowIndex, $row['row_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $this->excelDateValue($row['prs_date'] ?? null));
        $sheet->setCellValue('D'.$rowIndex, $this->excelDateValue($row['date_needed'] ?? null));
        $sheet->setCellValue('E'.$rowIndex, $row['department_name'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('I'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['remarks'] ?? '');
    }
}
