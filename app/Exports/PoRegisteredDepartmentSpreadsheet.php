<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoRegisteredDepartmentSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PO Register Dept';
    }

    protected function lastColumn(): string
    {
        return 'X';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 8, 'B' => 12, 'C' => 12,
            'D' => 14, 'E' => 12, 'F' => 8, 'G' => 18,
            'H' => 12, 'I' => 20, 'J' => 10, 'K' => 8,
            'L' => 11, 'M' => 10, 'N' => 10, 'O' => 10, 'P' => 12,
            'Q' => 11, 'R' => 11, 'S' => 11, 'T' => 11, 'U' => 11, 'V' => 11,
            'W' => 14, 'X' => 16,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'C', 'Purchase Requisition Slip'],
            ['D', 'G', 'Purchase Order'],
            ['H', 'K', 'Item'],
            ['L', 'L', 'Unit Price'],
            ['M', 'M', 'Disc'],
            ['N', 'N', 'PPH'],
            ['O', 'O', 'PPN'],
            ['P', 'P', 'Amount'],
            ['Q', 'V', 'Currency'],
            ['W', 'W', 'Canvasser Name'],
            ['X', 'X', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'ID',
            'B' => 'Number',
            'C' => 'Date',
            'D' => 'Number',
            'E' => 'Date',
            'F' => 'Curr',
            'G' => 'Supplier',
            'H' => 'Code',
            'I' => 'Description',
            'J' => 'Quantity',
            'K' => 'Unit',
            'L' => 'Unit Price',
            'M' => 'Disc',
            'N' => 'PPH',
            'O' => 'PPN',
            'P' => 'Amount',
            'Q' => 'IDR',
            'R' => 'PHP',
            'S' => 'EUR',
            'T' => 'GBP',
            'U' => 'USD',
            'V' => 'YEN',
            'W' => 'Canvasser Name',
            'X' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['C', 'E'];
    }

    protected function qtyColumns(): array
    {
        return ['J'];
    }

    protected function moneyColumns(): array
    {
        return ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function rows(): array
    {
        $flat = [];

        foreach ($this->data['groups'] ?? [] as $group) {
            $code = $group['department_code'] ?? 'UNKNOWN';
            $name = $group['department_name'] ?? '';
            $flat[] = [
                '_type' => 'group',
                'label' => "Department: [{$code}] {$name}",
            ];

            foreach ($group['rows'] ?? [] as $row) {
                $flat[] = $row;
            }

            $totals = $group['totals'] ?? [];
            [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($totals);
            $flat[] = [
                '_type' => 'total',
                'label' => 'TOTAL',
                'amount' => $idr + $php + $eur + $gbp + $usd + $yen,
                'currency_buckets' => $totals,
            ];
        }

        return $flat;
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        if (($row['_type'] ?? null) === 'total') {
            $sheet->setCellValue('A'.$rowIndex, $row['label'] ?? 'TOTAL');
            $sheet->setCellValue('P'.$rowIndex, (float) ($row['amount'] ?? 0));

            [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['currency_buckets'] ?? null);
            $sheet->setCellValue('Q'.$rowIndex, $idr);
            $sheet->setCellValue('R'.$rowIndex, $php);
            $sheet->setCellValue('S'.$rowIndex, $eur);
            $sheet->setCellValue('T'.$rowIndex, $gbp);
            $sheet->setCellValue('U'.$rowIndex, $usd);
            $sheet->setCellValue('V'.$rowIndex, $yen);

            return;
        }

        $sheet->setCellValue('A'.$rowIndex, $row['prs_id'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $this->excelDateValue($row['prs_date'] ?? null));
        $sheet->setCellValue('D'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $this->excelDateValue($row['po_date'] ?? null));
        $sheet->setCellValue('F'.$rowIndex, $row['currency'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['supplier'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('K'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('L'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('M'.$rowIndex, (float) ($row['discount'] ?? 0));
        $sheet->setCellValue('N'.$rowIndex, (float) ($row['pph'] ?? 0));
        $sheet->setCellValue('O'.$rowIndex, (float) ($row['ppn'] ?? 0));
        $sheet->setCellValue('P'.$rowIndex, (float) ($row['amount'] ?? 0));

        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['currency_buckets'] ?? null);
        $sheet->setCellValue('Q'.$rowIndex, $idr);
        $sheet->setCellValue('R'.$rowIndex, $php);
        $sheet->setCellValue('S'.$rowIndex, $eur);
        $sheet->setCellValue('T'.$rowIndex, $gbp);
        $sheet->setCellValue('U'.$rowIndex, $usd);
        $sheet->setCellValue('V'.$rowIndex, $yen);

        $sheet->setCellValue('W'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('X'.$rowIndex, $row['remarks'] ?? '');
    }
}
