<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductPurchaseHistorySpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'Purchase History';
    }

    protected function lastColumn(): string
    {
        return 'H';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 12,
            'C' => 12,
            'D' => 28,
            'E' => 10,
            'F' => 12,
            'G' => 8,
            'H' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'B', 'Purchase Order'],
            ['C', 'D', 'Supplier'],
            ['E', 'H', 'Line'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'PO Number',
            'B' => 'PO Date',
            'C' => 'Supplier Code',
            'D' => 'Supplier Name',
            'E' => 'Qty',
            'F' => 'Unit Price',
            'G' => 'Curr',
            'H' => 'Canvasser',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B'];
    }

    protected function qtyColumns(): array
    {
        return ['E'];
    }

    protected function moneyColumns(): array
    {
        return ['F'];
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
                'label' => "[{$code}] {$name} ({$count} line(s))",
            ];

            foreach ($group['rows'] ?? [] as $row) {
                $flat[] = $row;
            }
        }

        return $flat;
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['po_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $row['supplier_code'] ?? '');
        $sheet->setCellValue('D'.$rowIndex, $row['supplier_name'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('F'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('G'.$rowIndex, $row['currency'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['canvasser'] ?? '');
    }
}
