<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImTransferRegisterSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'TS Register';
    }

    protected function lastColumn(): string
    {
        return 'L';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 14, 'D' => 10,
            'E' => 22, 'F' => 12, 'G' => 22, 'H' => 10,
            'I' => 12, 'J' => 12, 'K' => 16, 'L' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'D', 'Transfer Slip'],
            ['E', 'E', 'To Department'],
            ['F', 'H', 'Item'],
            ['I', 'J', 'Quantity'],
            ['K', 'K', 'Creator'],
            ['L', 'L', 'Info'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'Date',
            'C' => 'SWS Number',
            'D' => 'Type',
            'E' => 'To Department',
            'F' => 'Code',
            'G' => 'Description',
            'H' => 'Unit',
            'I' => 'Request',
            'J' => 'Transfer',
            'K' => 'Creator',
            'L' => 'Info',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B'];
    }

    protected function qtyColumns(): array
    {
        return ['I', 'J'];
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('E3', 'TS Type');
        $sheet->setCellValue('F3', $this->data['ts_type_label'] ?? '');
        $sheet->mergeCells("F3:{$lastCol}3");
        $sheet->getStyle('E3')->getFont()->setBold(true);
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['ts_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['ts_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $row['sws_number'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['ts_type'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['to_department'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['unit'] ?? '-');
        $sheet->setCellValue('I'.$rowIndex, (float) ($row['request_qty'] ?? 0));
        $sheet->setCellValue('J'.$rowIndex, (float) ($row['transfer_qty'] ?? 0));
        $sheet->setCellValue('K'.$rowIndex, $row['creator'] ?? '');
        $sheet->setCellValue('L'.$rowIndex, $row['info'] ?? '');
    }
}
