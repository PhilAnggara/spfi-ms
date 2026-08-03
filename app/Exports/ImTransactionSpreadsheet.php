<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImTransactionSpreadsheet
{
    private const LAST_COLUMN = 'G';

    private const TABLE_DATE_FORMAT = 'dd-mmm-yyyy';

    private const QTY_FORMAT = '#,##0.00';

    private const COLUMN_WIDTHS = [
        'A' => 14,
        'B' => 28,
        'C' => 12,
        'D' => 8,
        'E' => 16,
        'F' => 12,
        'G' => 10,
    ];

    public function __construct(
        private readonly array $data,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        return response()->streamDownload(function () {
            $writer = new Xlsx($this->build());
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        $lastCol = self::LAST_COLUMN;

        $sheet->setCellValue('A1', $this->data['company'] ?? '');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', $this->data['title'] ?? 'Transaction Report per Category');
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->setCellValue('A3', 'Date From');
        $dateFrom = $this->data['date_from'] ?? null;
        if (filled($dateFrom)) {
            $sheet->setCellValue('B3', Date::PHPToExcel(Carbon::parse($dateFrom)->startOfDay()));
            $sheet->getStyle('B3')->getNumberFormat()->setFormatCode(self::TABLE_DATE_FORMAT);
        }

        $sheet->setCellValue('C3', 'Date To');
        $dateTo = $this->data['date_to'] ?? null;
        if (filled($dateTo)) {
            $sheet->setCellValue('D3', Date::PHPToExcel(Carbon::parse($dateTo)->startOfDay()));
            $sheet->getStyle('D3')->getNumberFormat()->setFormatCode(self::TABLE_DATE_FORMAT);
        }

        $sheet->setCellValue('E3', 'Category');
        $sheet->setCellValue('F3', $this->data['category'] ?? '');
        $sheet->mergeCells("F3:{$lastCol}3");

        $sheet->setCellValue(
            $lastCol.'4',
            'Printed: '.($this->data['printed_at'] ?? now()->format('d-m-Y H:i'))
        );

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->getStyle('C3')->getFont()->setBold(true);
        $sheet->getStyle('E3')->getFont()->setBold(true);
        $sheet->getStyle($lastCol.'4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $headerRow = 6;
        $dataStartRow = 7;

        $columns = [
            'A' => 'Item Code',
            'B' => 'Item Name',
            'C' => 'Date',
            'D' => 'Type',
            'E' => 'Document No.',
            'F' => 'Quantity',
            'G' => 'Unit',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue($col.$headerRow, $label);
        }

        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = collect($this->data['groups'] ?? []);
        $rowIndex = $dataStartRow;
        $qtyRows = [];

        if ($groups->isEmpty()) {
            $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
            $sheet->setCellValue("A{$rowIndex}", 'No transaction records found for the selected filters.');
            $sheet->getStyle("A{$rowIndex}")->getFont()->getColor()->setRGB('6B7280');
            $lastDataRow = $rowIndex;
        } else {
            foreach ($groups as $group) {
                $txnRows = collect($group['rows'] ?? []);
                $groupStart = $rowIndex;
                $groupCount = max(1, $txnRows->count());

                $sheet->setCellValue('A'.$groupStart, $group['item_code'] ?? '');
                $sheet->setCellValue('B'.$groupStart, $group['item_name'] ?? '');
                $sheet->getStyle("A{$groupStart}:B{$groupStart}")->getFont()->setBold(true);
                $sheet->getStyle("A{$groupStart}:B{$groupStart}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);

                if ($groupCount > 1) {
                    $groupEnd = $groupStart + $groupCount - 1;
                    $sheet->mergeCells("A{$groupStart}:A{$groupEnd}");
                    $sheet->mergeCells("B{$groupStart}:B{$groupEnd}");
                }

                foreach ($txnRows as $row) {
                    $docDate = $row['doc_date'] ?? null;
                    if (filled($docDate)) {
                        $sheet->setCellValue('C'.$rowIndex, Date::PHPToExcel(Carbon::parse($docDate)->startOfDay()));
                        $sheet->getStyle('C'.$rowIndex)->getNumberFormat()->setFormatCode(self::TABLE_DATE_FORMAT);
                    }

                    $sheet->setCellValue('D'.$rowIndex, $row['doc_type'] ?? '');
                    $sheet->setCellValue('E'.$rowIndex, $row['doc_number'] ?? '');
                    $sheet->setCellValue('F'.$rowIndex, (float) ($row['quantity'] ?? 0));
                    $sheet->setCellValue('G'.$rowIndex, $group['unit'] ?? '-');
                    $qtyRows[] = $rowIndex;
                    $rowIndex++;
                }
            }
            $lastDataRow = $rowIndex - 1;
        }

        $tableRange = "A{$headerRow}:{$lastCol}{$lastDataRow}";
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";

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

        if ($qtyRows !== []) {
            $firstQty = min($qtyRows);
            $lastQty = max($qtyRows);
            $sheet->getStyle("C{$firstQty}:{$lastCol}{$lastQty}")->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ]);
            $sheet->getStyle("F{$firstQty}:F{$lastQty}")
                ->getNumberFormat()
                ->setFormatCode(self::QTY_FORMAT);
            $sheet->getStyle("F{$firstQty}:F{$lastQty}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        if ($groups->isNotEmpty()) {
            $sheet->freezePane('A'.$dataStartRow);
        }

        $this->writeSignatures($sheet, $lastDataRow + 3);

        return $spreadsheet;
    }

    private function writeSignatures(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow): void
    {
        $labelRow = $startRow;
        $nameRow = $startRow + 3;
        $lineRow = $startRow + 4;
        $titleRow = $startRow + 5;

        $blocks = [
            ['A', 'B', 'Prepared by', $this->data['prepared_by_name'] ?? '', $this->data['prepared_by_title'] ?? ''],
            ['D', 'E', 'Checked by', $this->data['checked_by_name'] ?? '', $this->data['checked_by_title'] ?? ''],
            ['F', 'G', 'Approved by', $this->data['approved_by_name'] ?? '', $this->data['approved_by_title'] ?? ''],
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
}
