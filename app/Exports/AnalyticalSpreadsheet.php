<?php

namespace App\Exports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AnalyticalSpreadsheet
{
    protected const TABLE_DATE_FORMAT = 'dd-mmm-yyyy';

    protected const QTY_FORMAT = '#,##0.00';

    protected const MONEY_FORMAT = '#,##0.00';

    public function __construct(
        protected readonly array $data,
    ) {}

    abstract protected function sheetTitle(): string;

    abstract protected function lastColumn(): string;

    /**
     * @return array<string, float|int>
     */
    abstract protected function columnWidths(): array;

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    abstract protected function groupHeaders(): array;

    /**
     * @return array<string, string>
     */
    abstract protected function columnHeaders(): array;

    /**
     * @return list<string>
     */
    abstract protected function dateColumns(): array;

    /**
     * @return list<string>
     */
    abstract protected function qtyColumns(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function rows(): array;

    /**
     * @param  array<string, mixed>  $row
     */
    abstract protected function writeDataRow(Worksheet $sheet, int $rowIndex, array $row): void;

    /**
     * @return list<string>
     */
    protected function moneyColumns(): array
    {
        return [];
    }

    protected function includeSignatures(): bool
    {
        return true;
    }

    public function download(string $filename): StreamedResponse
    {
        return response()->streamDownload(function () {
            $writer = new Xlsx($this->build());
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle());

        $lastCol = $this->lastColumn();

        $sheet->setCellValue('A1', $this->data['company'] ?? '');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', $this->data['title'] ?? '');
        $sheet->mergeCells("A2:{$lastCol}2");

        $this->writePeriodFilters($sheet, $lastCol);
        $this->writeExtraFilters($sheet, $lastCol);

        $sheet->setCellValue(
            $lastCol.'4',
            'Printed: '.($this->data['printed_at'] ?? now()->format('d-m-Y H:i'))
        );

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($lastCol.'4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $groupRow = 6;
        $columnRow = 7;
        $dataStartRow = 8;

        foreach ($this->groupHeaders() as [$from, $to, $label]) {
            if ($from === $to) {
                continue;
            }

            $sheet->setCellValue($from.$groupRow, $label);
            $sheet->mergeCells("{$from}{$groupRow}:{$to}{$groupRow}");
        }

        foreach ($this->columnHeaders() as $col => $label) {
            $sheet->setCellValue($col.$columnRow, $label);
        }

        $rows = $this->rows();
        $rowIndex = $dataStartRow;
        $groupTitleRows = [];

        if ($rows === []) {
            $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
            $sheet->setCellValue("A{$rowIndex}", 'No records found for the selected filters.');
            $sheet->getStyle("A{$rowIndex}")->getFont()->getColor()->setRGB('6B7280');
            $lastDataRow = $rowIndex;
        } else {
            foreach ($rows as $row) {
                if (($row['_type'] ?? null) === 'group') {
                    $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
                    $sheet->setCellValue("A{$rowIndex}", $row['label'] ?? '');
                    $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")->getFont()->setBold(true);
                    $groupTitleRows[] = $rowIndex;
                    $rowIndex++;

                    continue;
                }

                if (($row['_type'] ?? null) === 'total') {
                    $this->writeDataRow($sheet, $rowIndex, $row);
                    $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")->getFont()->setBold(true);
                    $rowIndex++;

                    continue;
                }

                $this->writeDataRow($sheet, $rowIndex, $row);
                $rowIndex++;
            }
            $lastDataRow = $rowIndex - 1;
        }

        $tableRange = "A{$groupRow}:{$lastCol}{$lastDataRow}";
        $headerRange = "A{$groupRow}:{$lastCol}{$columnRow}";

        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '9CA3AF'],
                ],
            ],
        ]);

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
        ]);

        $sheet->getStyle("A{$columnRow}:{$lastCol}{$columnRow}")->getFont()->setSize(9);

        if ($rows !== []) {
            $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);

            foreach ($this->qtyColumns() as $col) {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(self::QTY_FORMAT);
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            foreach ($this->moneyColumns() as $col) {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(self::MONEY_FORMAT);
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            foreach ($this->dateColumns() as $col) {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(self::TABLE_DATE_FORMAT);
            }

            foreach ($groupTitleRows as $groupRowIndex) {
                $sheet->getStyle("A{$groupRowIndex}:{$lastCol}{$groupRowIndex}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'],
                    ],
                ]);
            }

            $sheet->freezePane('A'.$dataStartRow);
        }

        foreach ($this->columnWidths() as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension($groupRow)->setRowHeight(18);
        $sheet->getRowDimension($columnRow)->setRowHeight(20);

        if ($this->includeSignatures()) {
            $this->writeSignatures($sheet, $lastDataRow + 3);
        }

        return $spreadsheet;
    }

    protected function writePeriodFilters(Worksheet $sheet, string $lastCol): void
    {
        if (array_key_exists('date_from', $this->data) || array_key_exists('date_to', $this->data)) {
            $sheet->setCellValue('A3', 'Date From');
            $this->writeExcelDate($sheet, 'B3', $this->data['date_from'] ?? null);
            $sheet->setCellValue('C3', 'Date To');
            $this->writeExcelDate($sheet, 'D3', $this->data['date_to'] ?? null);
            $sheet->getStyle('A3')->getFont()->setBold(true);
            $sheet->getStyle('C3')->getFont()->setBold(true);

            return;
        }

        if (array_key_exists('as_of', $this->data)) {
            $sheet->setCellValue('A3', 'As Of');
            $this->writeExcelDate($sheet, 'B3', $this->data['as_of'] ?? null);
            $sheet->getStyle('A3')->getFont()->setBold(true);
        }
    }

    protected function writeExtraFilters(Worksheet $sheet, string $lastCol): void
    {
        //
    }

    protected function writeExcelDate(Worksheet $sheet, string $cell, mixed $value): void
    {
        if (! filled($value)) {
            return;
        }

        $sheet->setCellValue($cell, Date::PHPToExcel(Carbon::parse($value)->startOfDay()));
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::TABLE_DATE_FORMAT);
    }

    protected function excelDateValue(mixed $value): ?float
    {
        if (! filled($value)) {
            return null;
        }

        return Date::PHPToExcel(Carbon::parse($value)->startOfDay());
    }

    /**
     * @param  array<string, float|int>|null  $buckets
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    protected function currencyValues(?array $buckets): array
    {
        $buckets ??= [];

        return [
            (float) ($buckets['IDR'] ?? 0),
            (float) ($buckets['PHP'] ?? 0),
            (float) ($buckets['EUR'] ?? 0),
            (float) ($buckets['GBP'] ?? 0),
            (float) ($buckets['USD'] ?? 0),
            (float) ($buckets['YEN'] ?? 0),
        ];
    }

    private function writeSignatures(Worksheet $sheet, int $startRow): void
    {
        $labelRow = $startRow;
        $nameRow = $startRow + 3;
        $lineRow = $startRow + 4;
        $titleRow = $startRow + 5;
        $lastCol = $this->lastColumn();

        $blocks = [
            ['A', 'B', 'Prepared by', $this->data['prepared_by_name'] ?? '', $this->data['prepared_by_title'] ?? ''],
            ['C', 'D', 'Checked by', $this->data['checked_by_name'] ?? '', $this->data['checked_by_title'] ?? ''],
            [$this->signatureApprovedFrom($lastCol), $lastCol, 'Approved by', $this->data['approved_by_name'] ?? '', $this->data['approved_by_title'] ?? ''],
        ];

        foreach ($blocks as [$from, $to, $label, $name, $title]) {
            $sheet->mergeCells("{$from}{$labelRow}:{$to}{$labelRow}");
            $sheet->setCellValue("{$from}{$labelRow}", $label);
            $sheet->getStyle("{$from}{$labelRow}")->getFont()->setBold(true);
            $sheet->getStyle("{$from}{$labelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("{$from}{$nameRow}:{$to}{$nameRow}");
            $sheet->setCellValue("{$from}{$nameRow}", $name);
            $sheet->getStyle("{$from}{$nameRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("{$from}{$lineRow}:{$to}{$lineRow}");
            $sheet->getStyle("{$from}{$lineRow}:{$to}{$lineRow}")->applyFromArray([
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '111827'],
                    ],
                ],
            ]);

            $sheet->mergeCells("{$from}{$titleRow}:{$to}{$titleRow}");
            $sheet->setCellValue("{$from}{$titleRow}", $title);
            $sheet->getStyle("{$from}{$titleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$from}{$titleRow}")->getFont()->setSize(9);
        }
    }

    private function signatureApprovedFrom(string $lastCol): string
    {
        $map = [
            'J' => 'I',
            'L' => 'K',
            'O' => 'N',
            'W' => 'V',
            'X' => 'W',
            'Z' => 'Y',
        ];

        return $map[$lastCol] ?? 'E';
    }
}
