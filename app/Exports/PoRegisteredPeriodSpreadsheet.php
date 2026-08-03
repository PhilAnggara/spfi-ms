<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoRegisteredPeriodSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PO Register Period';
    }

    protected function lastColumn(): string
    {
        return 'Z';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 8, 'B' => 12, 'C' => 12,
            'D' => 10, 'E' => 14,
            'F' => 14, 'G' => 12, 'H' => 8, 'I' => 18,
            'J' => 12, 'K' => 20, 'L' => 10, 'M' => 8,
            'N' => 11, 'O' => 10, 'P' => 10, 'Q' => 10, 'R' => 12,
            'S' => 11, 'T' => 11, 'U' => 11, 'V' => 11, 'W' => 11, 'X' => 11,
            'Y' => 14, 'Z' => 16,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'C', 'Purchase Requisition Slip'],
            ['D', 'E', 'Department'],
            ['F', 'I', 'Purchase Order'],
            ['J', 'M', 'Item'],
            ['N', 'N', 'Unit Price'],
            ['O', 'O', 'Disc'],
            ['P', 'P', 'PPH'],
            ['Q', 'Q', 'PPN'],
            ['R', 'R', 'Amount'],
            ['S', 'X', 'Currency'],
            ['Y', 'Y', 'Canvasser Name'],
            ['Z', 'Z', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'ID',
            'B' => 'Number',
            'C' => 'Date',
            'D' => 'Code',
            'E' => 'Name',
            'F' => 'Number',
            'G' => 'Date',
            'H' => 'Curr',
            'I' => 'Supplier',
            'J' => 'Code',
            'K' => 'Description',
            'L' => 'Quantity',
            'M' => 'Unit',
            'N' => 'Unit Price',
            'O' => 'Disc',
            'P' => 'PPH',
            'Q' => 'PPN',
            'R' => 'Amount',
            'S' => 'IDR',
            'T' => 'PHP',
            'U' => 'EUR',
            'V' => 'GBP',
            'W' => 'USD',
            'X' => 'YEN',
            'Y' => 'Canvasser Name',
            'Z' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['C', 'G'];
    }

    protected function qtyColumns(): array
    {
        return ['L'];
    }

    protected function moneyColumns(): array
    {
        return ['N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function rows(): array
    {
        $rows = collect($this->data['rows'] ?? [])->all();

        if ($rows === []) {
            return [];
        }

        $totals = $this->data['totals'] ?? [];
        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($totals);
        $grand = $idr + $php + $eur + $gbp + $usd + $yen;

        $rows[] = [
            '_type' => 'total',
            'label' => 'GRAND TOTAL',
            'amount' => $grand,
            'currency_buckets' => $totals,
        ];

        return $rows;
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        if (($row['_type'] ?? null) === 'total') {
            $sheet->setCellValue('A'.$rowIndex, $row['label'] ?? 'GRAND TOTAL');
            $sheet->setCellValue('R'.$rowIndex, (float) ($row['amount'] ?? 0));

            [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['currency_buckets'] ?? null);
            $sheet->setCellValue('S'.$rowIndex, $idr);
            $sheet->setCellValue('T'.$rowIndex, $php);
            $sheet->setCellValue('U'.$rowIndex, $eur);
            $sheet->setCellValue('V'.$rowIndex, $gbp);
            $sheet->setCellValue('W'.$rowIndex, $usd);
            $sheet->setCellValue('X'.$rowIndex, $yen);

            return;
        }

        $sheet->setCellValue('A'.$rowIndex, $row['prs_id'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
        $sheet->setCellValue('C'.$rowIndex, $this->excelDateValue($row['prs_date'] ?? null));
        $sheet->setCellValue('D'.$rowIndex, $row['department_code'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['department_name'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $this->excelDateValue($row['po_date'] ?? null));
        $sheet->setCellValue('H'.$rowIndex, $row['currency'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, $row['supplier'] ?? '');
        $sheet->setCellValue('J'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('K'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('L'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('M'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('N'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('O'.$rowIndex, (float) ($row['discount'] ?? 0));
        $sheet->setCellValue('P'.$rowIndex, (float) ($row['pph'] ?? 0));
        $sheet->setCellValue('Q'.$rowIndex, (float) ($row['ppn'] ?? 0));
        $sheet->setCellValue('R'.$rowIndex, (float) ($row['amount'] ?? 0));

        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['currency_buckets'] ?? null);
        $sheet->setCellValue('S'.$rowIndex, $idr);
        $sheet->setCellValue('T'.$rowIndex, $php);
        $sheet->setCellValue('U'.$rowIndex, $eur);
        $sheet->setCellValue('V'.$rowIndex, $gbp);
        $sheet->setCellValue('W'.$rowIndex, $usd);
        $sheet->setCellValue('X'.$rowIndex, $yen);

        $sheet->setCellValue('Y'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('Z'.$rowIndex, $row['remarks'] ?? '');
    }
}
