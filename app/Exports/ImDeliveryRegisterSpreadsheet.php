<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImDeliveryRegisterSpreadsheet extends ImAnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'DR Register';
    }

    protected function lastColumn(): string
    {
        return 'J';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 18, 'D' => 18,
            'E' => 12, 'F' => 22, 'G' => 10,
            'H' => 12, 'I' => 16, 'J' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'D', 'Delivery Receipt'],
            ['E', 'G', 'Item'],
            ['H', 'H', 'Quantity'],
            ['I', 'I', 'Creator'],
            ['J', 'J', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'Date',
            'C' => 'From',
            'D' => 'To',
            'E' => 'Code',
            'F' => 'Description',
            'G' => 'Unit',
            'H' => 'Quantity',
            'I' => 'Creator',
            'J' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B'];
    }

    protected function qtyColumns(): array
    {
        return ['H'];
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['dr_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['dr_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $row['from'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['to'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['unit'] ?? '-');
        $sheet->setCellValue('H'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('I'.$rowIndex, $row['creator'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['remarks'] ?? '');
    }
}
