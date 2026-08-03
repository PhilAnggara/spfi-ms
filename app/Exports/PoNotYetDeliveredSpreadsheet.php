<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoNotYetDeliveredSpreadsheet extends AnalyticalSpreadsheet
{
    protected function sheetTitle(): string
    {
        return 'PO Not Yet Delivered';
    }

    protected function lastColumn(): string
    {
        return 'W';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 12, 'C' => 10, 'D' => 8,
            'E' => 10, 'F' => 18,
            'G' => 12, 'H' => 22, 'I' => 10, 'J' => 8,
            'K' => 11, 'L' => 10, 'M' => 10, 'N' => 10, 'O' => 12,
            'P' => 11, 'Q' => 11, 'R' => 11, 'S' => 11, 'T' => 11, 'U' => 11,
            'V' => 16, 'W' => 18,
        ];
    }

    protected function groupHeaders(): array
    {
        return [
            ['A', 'D', 'Purchase Order'],
            ['E', 'F', 'Supplier'],
            ['G', 'J', 'Item'],
            ['K', 'K', 'Unit Price'],
            ['L', 'L', 'Disc'],
            ['M', 'M', 'PPH'],
            ['N', 'N', 'PPN'],
            ['O', 'O', 'Amount'],
            ['P', 'U', 'Currency'],
            ['V', 'V', 'Canvasser Name'],
            ['W', 'W', 'Remarks'],
        ];
    }

    protected function columnHeaders(): array
    {
        return [
            'A' => 'Number',
            'B' => 'Date',
            'C' => 'Type',
            'D' => 'Curr',
            'E' => 'Code',
            'F' => 'Name',
            'G' => 'Code',
            'H' => 'Description',
            'I' => 'Quantity',
            'J' => 'Unit',
            'K' => 'Unit Price',
            'L' => 'Disc',
            'M' => 'PPH',
            'N' => 'PPN',
            'O' => 'Amount',
            'P' => 'IDR',
            'Q' => 'PHP',
            'R' => 'EUR',
            'S' => 'GBP',
            'T' => 'USD',
            'U' => 'YEN',
            'V' => 'Canvasser Name',
            'W' => 'Remarks',
        ];
    }

    protected function dateColumns(): array
    {
        return ['B'];
    }

    protected function qtyColumns(): array
    {
        return ['I'];
    }

    protected function moneyColumns(): array
    {
        return ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
    }

    protected function includeSignatures(): bool
    {
        return false;
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        $sheet->setCellValue('A4', 'Canvasser: '.($this->data['canvasser'] ?? 'All'));
        $sheet->setCellValue('C4', 'PO Type: '.strtoupper((string) ($this->data['po_type'] ?? '')));
    }

    protected function rows(): array
    {
        return collect($this->data['rows'] ?? [])->all();
    }

    protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void
    {
        $sheet->setCellValue('A'.$rowIndex, $row['po_number'] ?? '');
        $sheet->setCellValue('B'.$rowIndex, $this->excelDateValue($row['po_date'] ?? null));
        $sheet->setCellValue('C'.$rowIndex, strtoupper((string) ($row['po_type'] ?? '')));
        $sheet->setCellValue('D'.$rowIndex, $row['currency'] ?? '');
        $sheet->setCellValue('E'.$rowIndex, $row['supplier_code'] ?? '');
        $sheet->setCellValue('F'.$rowIndex, $row['supplier_name'] ?? '');
        $sheet->setCellValue('G'.$rowIndex, $row['item_code'] ?? '');
        $sheet->setCellValue('H'.$rowIndex, $row['item_name'] ?? '');
        $sheet->setCellValue('I'.$rowIndex, (float) ($row['quantity'] ?? 0));
        $sheet->setCellValue('J'.$rowIndex, $row['unit'] ?? '');
        $sheet->setCellValue('K'.$rowIndex, (float) ($row['unit_price'] ?? 0));
        $sheet->setCellValue('L'.$rowIndex, (float) ($row['discount'] ?? 0));
        $sheet->setCellValue('M'.$rowIndex, (float) ($row['pph'] ?? 0));
        $sheet->setCellValue('N'.$rowIndex, (float) ($row['ppn'] ?? 0));
        $sheet->setCellValue('O'.$rowIndex, (float) ($row['amount'] ?? 0));

        [$idr, $php, $eur, $gbp, $usd, $yen] = $this->currencyValues($row['currency_buckets'] ?? null);
        $sheet->setCellValue('P'.$rowIndex, $idr);
        $sheet->setCellValue('Q'.$rowIndex, $php);
        $sheet->setCellValue('R'.$rowIndex, $eur);
        $sheet->setCellValue('S'.$rowIndex, $gbp);
        $sheet->setCellValue('T'.$rowIndex, $usd);
        $sheet->setCellValue('U'.$rowIndex, $yen);

        $sheet->setCellValue('V'.$rowIndex, $row['canvasser'] ?? '');
        $sheet->setCellValue('W'.$rowIndex, $row['remarks'] ?? '');
    }
}
