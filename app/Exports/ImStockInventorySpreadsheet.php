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

class ImStockInventorySpreadsheet
{
    private const LAST_COLUMN = 'H';

    private const TABLE_DATE_FORMAT = 'dd-mmm-yyyy';

    private const QTY_FORMAT = '#,##0.00';

    private const COLUMN_WIDTHS = [
        'A' => 28,
        'B' => 14,
        'C' => 10,
        'D' => 12,
        'E' => 12,
        'F' => 12,
        'G' => 12,
        'H' => 12,
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
        $sheet->setTitle('Stock Inventory');

        $lastCol = self::LAST_COLUMN;

        $sheet->setCellValue('A1', $this->data['company'] ?? '');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', $this->data['title'] ?? 'Stock Inventory per Category');
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->setCellValue('A3', 'As of');
        $asOf = $this->data['as_of'] ?? null;
        if (filled($asOf)) {
            $sheet->setCellValue('B3', Date::PHPToExcel(Carbon::parse($asOf)->startOfDay()));
            $sheet->getStyle('B3')->getNumberFormat()->setFormatCode(self::TABLE_DATE_FORMAT);
        }
        $sheet->setCellValue('C3', 'Category');
        $sheet->setCellValue('D3', $this->data['category'] ?? '');
        $sheet->mergeCells("D3:{$lastCol}3");

        $sheet->setCellValue(
            $lastCol.'4',
            'Printed: '.($this->data['printed_at'] ?? now()->format('d-m-Y H:i'))
        );

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->getStyle('C3')->getFont()->setBold(true);
        $sheet->getStyle($lastCol.'4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $groupRow = 6;
        $columnRow = 7;
        $dataStartRow = 8;

        $groups = [
            ['A', 'C', 'Item'],
            ['D', 'D', 'Beginning'],
            ['E', 'E', 'Receipt'],
            ['F', 'G', 'Issuances'],
            ['H', 'H', 'Ending'],
        ];

        foreach ($groups as [$from, $to, $label]) {
            $range = $from === $to ? $from.$groupRow : $from.$groupRow.':'.$to.$groupRow;
            $sheet->setCellValue($from.$groupRow, $label);
            if ($from !== $to) {
                $sheet->mergeCells($range);
            }
        }

        $columns = [
            'A' => 'Name',
            'B' => 'Code',
            'C' => 'Unit',
            'D' => 'Balance',
            'E' => 'RR',
            'F' => 'TS',
            'G' => 'DR',
            'H' => 'Balance',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue($col.$columnRow, $label);
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($this->data['rows'] ?? []);
        $rowIndex = $dataStartRow;

        if ($rows->isEmpty()) {
            $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
            $sheet->setCellValue("A{$rowIndex}", 'No stock inventory records found for the selected filters.');
            $sheet->getStyle("A{$rowIndex}")->getFont()->getColor()->setRGB('6B7280');
            $lastDataRow = $rowIndex;
            $totalRow = null;
        } else {
            foreach ($rows as $row) {
                $sheet->setCellValue('A'.$rowIndex, $row['name'] ?? '');
                $sheet->setCellValue('B'.$rowIndex, $row['code'] ?? '');
                $sheet->setCellValue('C'.$rowIndex, $row['unit'] ?? '-');
                $sheet->setCellValue('D'.$rowIndex, (float) ($row['beginning'] ?? 0));
                $sheet->setCellValue('E'.$rowIndex, (float) ($row['rr'] ?? 0));
                $sheet->setCellValue('F'.$rowIndex, (float) ($row['ts'] ?? 0));
                $sheet->setCellValue('G'.$rowIndex, (float) ($row['dr'] ?? 0));
                $sheet->setCellValue('H'.$rowIndex, (float) ($row['ending'] ?? 0));
                $rowIndex++;
            }
            $lastDataRow = $rowIndex - 1;

            $totalRow = $rowIndex;
            $sheet->setCellValue('A'.$totalRow, 'GRAND TOTAL');
            $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
            $sheet->setCellValue('D'.$totalRow, (float) $rows->sum('beginning'));
            $sheet->setCellValue('E'.$totalRow, (float) $rows->sum('rr'));
            $sheet->setCellValue('F'.$totalRow, (float) $rows->sum('ts'));
            $sheet->setCellValue('G'.$totalRow, (float) $rows->sum('dr'));
            $sheet->setCellValue('H'.$totalRow, (float) $rows->sum('ending'));
            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
        }

        $tableEndRow = $totalRow ?? $lastDataRow;
        $tableRange = "A{$groupRow}:{$lastCol}{$tableEndRow}";
        $headerRange = "A{$groupRow}:{$lastCol}{$columnRow}";
        $bodyRange = "A{$dataStartRow}:{$lastCol}{$lastDataRow}";

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

        $sheet->getStyle($bodyRange)->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        $qtyEndRow = $totalRow ?? $lastDataRow;
        $sheet->getStyle("D{$dataStartRow}:H{$qtyEndRow}")
            ->getNumberFormat()
            ->setFormatCode(self::QTY_FORMAT);
        $sheet->getStyle("D{$dataStartRow}:H{$qtyEndRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension($groupRow)->setRowHeight(18);
        $sheet->getRowDimension($columnRow)->setRowHeight(20);

        if ($rows->isNotEmpty()) {
            $sheet->setAutoFilter("A{$columnRow}:{$lastCol}{$columnRow}");
            $sheet->freezePane('A'.$dataStartRow);
        }

        $signatureStart = $tableEndRow + 3;
        $this->writeSignatures($sheet, $signatureStart);

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
            ['G', 'H', 'Approved by', $this->data['approved_by_name'] ?? '', $this->data['approved_by_title'] ?? ''],
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
