<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrsNotYetPoSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PRS Not Yet PO';
    }

    protected function lastColumn(): string
    {
        return 'J';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 14, 'C' => 12,
            'D' => 12, 'E' => 28, 'F' => 12, 'G' => 12, 'H' => 10,
            'I' => 10, 'J' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'C', 'Purchase Requisition Slip'],
            ['D', 'H', 'Item'],
            ['I', 'J', 'Department'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'ID',
            'B' => 'Number',
            'C' => 'Date',
            'D' => 'Code',
            'E' => 'Description',
            'F' => 'Stock on Hand',
            'G' => 'Quantity',
            'H' => 'Unit',
            'I' => 'Code',
            'J' => 'Name',
        ];
    }

    protected function dateColumns(): array
    {
        return ['C'];
    }

    protected function qtyColumns(): array
    {
        return ['F', 'G'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('A4', 'Canvasser: '.($this->data['canvasser'] ?? 'All'));
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['prs_id'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $this->excelDateValue($row['prs_date'] ?? null));
        $sheet->setCellValue('D'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, (float) ($row['stock_on_hand'] ?? 0));
        $sheet->setCellValue('G'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('H'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, $row['department_code'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['department_name'] ?? '');
    }
}
