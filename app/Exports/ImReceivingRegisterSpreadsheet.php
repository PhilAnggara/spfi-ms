<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImReceivingRegisterSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'RR Register';
    }

    protected function lastColumn(): string
    {
        return 'O';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 18,
            'D' => 12, 'E' => 22, 'F' => 14, 'G' => 10,
            'H' => 10, 'I' => 10,
            'J' => 14, 'K' => 12, 'L' => 14, 'M' => 16,
            'N' => 10, 'O' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'C', 'Receiving Report'],
            ['D', 'G', 'Item'],
            ['H', 'I', 'Quantity'],
            ['J', 'M', 'Purchase Order'],
            ['N', 'N', 'End User'],
            ['O', 'O', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'Date',
            'C' => 'From',
            'D' => 'Code',
            'E' => 'Description',
            'F' => 'Category',
            'G' => 'Unit',
            'H' => 'Good',
            'I' => 'Bad',
            'J' => 'Code',
            'K' => 'Date',
            'L' => 'Payment Term',
            'M' => 'Canvasser',
            'N' => 'End User',
            'O' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B', 'K'];
    }

    protected function qtyColumns(): array
    {
        return ['H', 'I'];
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['rr_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['rr_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $row['from'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['item_category'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['unit'] ?? '-');
        $sheet->setCellValue('H'.$rowIndex, (float) ($row['qty_good'] ?? 0));
        $sheet->setCellValue('I'.$rowIndex, (float) ($row['qty_bad'] ?? 0));
        $sheet->setCellValue('J'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('K'.$rowIndex, $this->excelDateValue($row['po_date'] ?? null));
        $sheet->setCellValue('L'.$rowIndex, $row['payment_term'] ?? '');
        $sheet->setCellValue('M'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValueExplicit('N'.$rowIndex, (string) ($row['end_user'] ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue('O'.$rowIndex, $row['remarks'] ?? '');
    }
}
