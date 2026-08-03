<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImSwsRegisterSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'SWS Register';
    }

    protected function lastColumn(): string
    {
        return 'J';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 22,
            'D' => 12, 'E' => 22, 'F' => 10,
            'G' => 12, 'H' => 12, 'I' => 16, 'J' => 20,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'B', 'Stores Withdrawal Slip'],
            ['C', 'C', 'Department'],
            ['D', 'F', 'Item'],
            ['G', 'H', 'Quantity'],
            ['I', 'I', 'Creator'],
            ['J', 'J', 'Info'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'Date',
            'C' => 'Department',
            'D' => 'Code',
            'E' => 'Description',
            'F' => 'Unit',
            'G' => 'Stock On Hand',
            'H' => 'Request',
            'I' => 'Creator',
            'J' => 'Info',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B'];
    }

    protected function qtyColumns(): array
    {
        return ['G', 'H'];
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('E3', 'Department');
        $sheet->setCellValue('F3', $this->data['department'] ?? 'All departments');
        $sheet->mergeCells("F3:{$lastCol}3");
        $sheet->getStyle('E3')->getFont()->setBold(true);
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['sws_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['sws_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $row['department'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['unit'] ?? '-');
        $sheet->setCellValue('G'.$rowIndex, (float) ($row['stock_on_hand'] ?? 0));
        $sheet->setCellValue('H'.$rowIndex, (float) ($row['request_qty'] ?? 0));
        $sheet->setCellValue('I'.$rowIndex, $row['creator'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['info'] ?? '');
    }
}
