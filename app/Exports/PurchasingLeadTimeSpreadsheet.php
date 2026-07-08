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

class PurchasingLeadTimeSpreadsheet
{
    private const LAST_COLUMN = 'N';

    private const TABLE_DATE_FORMAT = 'dd-mmm-yyyy';

    private const COLUMN_WIDTHS = [
        'A' => 8,
        'B' => 14,
        'C' => 12,
        'D' => 12,
        'E' => 21,
        'F' => 14,
        'G' => 16,
        'H' => 14,
        'I' => 14,
        'J' => 13,
        'K' => 21,
        'L' => 14,
        'M' => 12,
        'N' => 10,
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
        $sheet->setTitle('Lead Time');

        $lastCol = self::LAST_COLUMN;
        $fmtDate = fn ($value) => $value ? Carbon::parse($value)->format('d M Y') : '';
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');

        $setDateCell = function (string $coordinate, mixed $value) use ($sheet): void {
            if (blank($value)) {
                $sheet->setCellValue($coordinate, null);

                return;
            }

            $sheet->setCellValue(
                $coordinate,
                Date::PHPToExcel(Carbon::parse($value)->startOfDay()),
            );
        };

        $sheet->setCellValue('A1', $this->data['company'] ?? '');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', $this->data['title'] ?? 'Purchasing Lead Time Report');
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A3', sprintf(
            'Period (RR date): %s - %s',
            $fmtDate($this->data['date_from'] ?? null),
            $fmtDate($this->data['date_to'] ?? null),
        ));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A4', 'Canvasser: '.($this->data['canvasser'] ?? 'All'));
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->setCellValue(
            'A5',
            'Lead Time (days) = RR Date - Assigned Canvasser Date. Each receiving report is listed separately.'
        );
        $sheet->mergeCells("A5:{$lastCol}5");
        $sheet->setCellValue($lastCol.'6', 'Printed: '.($this->data['printed_at'] ?? now()->format('d-m-Y H:i')));

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A5')->getFont()->setSize(9)->getColor()->setRGB('6B7280');
        $sheet->getStyle($lastCol.'6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $groupRow = 8;
        $columnRow = 9;
        $dataStartRow = 10;

        $groups = [
            ['A', 'C', 'Purchase Requisition Slip'],
            ['D', 'F', 'Item'],
            ['G', 'H', 'Canvasser'],
            ['I', 'J', 'Purchase Order'],
            ['K', 'K', 'Supplier'],
            ['L', 'M', 'Receiving Report'],
            ['N', 'N', 'Lead Time'],
        ];

        foreach ($groups as [$from, $to, $label]) {
            $range = $from === $to ? $from.$groupRow : $from.$groupRow.':'.$to.$groupRow;
            $sheet->setCellValue($from.$groupRow, $label);
            if ($from !== $to) {
                $sheet->mergeCells($range);
            }
        }

        $columns = [
            'A' => 'ID',
            'B' => 'Number',
            'C' => 'Date',
            'D' => 'Code',
            'E' => 'Description',
            'F' => 'Qty',
            'G' => 'Name',
            'H' => 'Assigned',
            'I' => 'Number',
            'J' => 'Date',
            'K' => 'Name',
            'L' => 'Number',
            'M' => 'Date',
            'N' => 'Days',
        ];

        foreach ($columns as $col => $label) {
            $sheet->setCellValue($col.$columnRow, $label);
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $this->data['rows'] ?? collect();
        $rowIndex = $dataStartRow;

        if ($rows->isEmpty()) {
            $sheet->mergeCells("A{$rowIndex}:{$lastCol}{$rowIndex}");
            $sheet->setCellValue("A{$rowIndex}", 'No lead time records found for this period.');
            $sheet->getStyle("A{$rowIndex}")->getFont()->getColor()->setRGB('6B7280');
            $lastDataRow = $rowIndex;
        } else {
            foreach ($rows as $row) {
                $sheet->setCellValue('A'.$rowIndex, $row['prs_id'] ?? '');
                $sheet->setCellValue('B'.$rowIndex, $row['prs_number'] ?? '');
                $setDateCell('C'.$rowIndex, $row['prs_date'] ?? null);
                $sheet->setCellValue('D'.$rowIndex, $row['item_code'] ?? '');
                $sheet->setCellValue('E'.$rowIndex, $row['item_name'] ?? '');
                $sheet->setCellValue(
                    'F'.$rowIndex,
                    trim($fmtQty($row['quantity'] ?? 0).' '.($row['unit'] ?? ''))
                );
                $sheet->setCellValue('G'.$rowIndex, $row['canvasser'] ?? '-');
                $setDateCell('H'.$rowIndex, $row['assigned_canvasser_at'] ?? null);
                $sheet->setCellValue('I'.$rowIndex, $row['po_number'] ?? '-');
                $setDateCell('J'.$rowIndex, $row['po_date'] ?? null);
                $sheet->setCellValue('K'.$rowIndex, $row['supplier_name'] ?? '-');
                $sheet->setCellValue('L'.$rowIndex, $row['rr_number'] ?? '-');
                $setDateCell('M'.$rowIndex, $row['rr_date'] ?? null);
                $sheet->setCellValue('N'.$rowIndex, $row['lead_time_days'] ?? '');
                $rowIndex++;
            }
            $lastDataRow = $rowIndex - 1;
        }

        $tableRange = "A{$groupRow}:{$lastCol}{$lastDataRow}";
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

        $sheet->getStyle("F{$dataStartRow}:F{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("N{$dataStartRow}:N{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('N'.$groupRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($rows->isNotEmpty()) {
            foreach (['C', 'H', 'J', 'M'] as $column) {
                $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(self::TABLE_DATE_FORMAT);
            }
        }

        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension($groupRow)->setRowHeight(18);
        $sheet->getRowDimension($columnRow)->setRowHeight(20);

        if ($rows->isNotEmpty()) {
            $sheet->setAutoFilter("A{$columnRow}:{$lastCol}{$columnRow}");
            $sheet->freezePane('A'.$dataStartRow);
        }

        return $spreadsheet;
    }
}
