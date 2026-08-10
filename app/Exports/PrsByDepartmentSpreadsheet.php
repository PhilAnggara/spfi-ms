<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrsByDepartmentSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PRS by Department';
    }

    protected function lastColumn(): string
    {
        return 'M';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 12,
            'C' => 12,
            'D' => 16,
            'E' => 12,
            'F' => 8,
            'G' => 12,
            'H' => 24,
            'I' => 10,
            'J' => 8,
            'K' => 14,
            'L' => 14,
            'M' => 20,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'F', 'Purchase Requisition Slip'],
            ['G', 'J', 'Item'],
            ['K', 'K', 'Canvasser'],
            ['L', 'L', 'PO No.'],
            ['M', 'M', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'PRS Date',
            'C' => 'Date Needed',
            'D' => 'Requestor',
            'E' => 'Status',
            'F' => 'CAPEX',
            'G' => 'Code',
            'H' => 'Description',
            'I' => 'Qty',
            'J' => 'Unit',
            'K' => 'Canvasser',
            'L' => 'PO No.',
            'M' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B', 'C'];
    }

    protected function qtyColumns(): array
    {
        return ['I'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        if (! empty($this->data['scoped_department_label'])) {
            $sheet->setCellValue('A4', 'Department: '.$this->data['scoped_department_label']);
        }
    }

    protected function rows(): array
    {
        $flat = [];

        foreach ($this->data['groups'] ?? [] as $group) {
            $code = $group['department_code'] ?? 'UNKNOWN';
            $name = $group['department_name'] ?? '';
            $count = count($group['rows'] ?? []);
            $flat[] = [
                '_type' => 'group',
                'label' => "Department: [{$code}] {$name} ({$count} line(s))",
            ];

            foreach ($group['rows'] ?? [] as $row) {
                $flat[] = $row;
            }
        }

        return $flat;
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['prs_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, $this->excelDateValue($row['date_needed'] ?? null));
        $sheet->setCellValue('D'.$rowIndex, $row['requestor'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['status'] ?? '');

        if (array_key_exists('is_capex', $row) && $row['is_capex'] !== null) {
            $sheet->setCellValue('F'.$rowIndex, $row['is_capex'] ? 'Yes' : '-');
        } else {
            $sheet->setCellValue('F'.$rowIndex, '');
        }

        $sheet->setCellValue('G'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('J'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('K'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('L'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('M'.$rowIndex, $row['remarks'] ?? '');
    }
}
