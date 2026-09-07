<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingInventoryReportService;
use App\Support\PdfReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportController extends Controller
{
    public function __construct(
        private readonly AccountingInventoryReportService $inventoryReportService,
    ) {}

    public function index()
    {
        return view('pages.accounting-reports');
    }

    public function stockCard(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->inventoryReportService->stockCardRows($validated['month'], $validated['category']);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Accounting Stock Card',
            'month' => $validated['month'],
            'category' => $validated['category'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card',
            $data,
            'accounting-stock-card',
            'pdf.reports.accounting-stock-card'
        );
    }

    public function transaction(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->inventoryReportService->transactionRows(
            $validated['date_from'],
            $validated['date_to'],
            $validated['category'],
        );

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Transaction Report',
            'month' => Carbon::parse($validated['date_from'])->format('Y-m'),
            'category' => $validated['category'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card',
            $data,
            'accounting-transaction-report',
            'pdf.reports.accounting-stock-card'
        );
    }

    public function restatement(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->inventoryReportService->stockCardRows($validated['month'], $validated['category']);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Restatement Report',
            'month' => $validated['month'],
            'category' => $validated['category'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card',
            $data,
            'accounting-restatement',
            'pdf.reports.accounting-stock-card'
        );
    }

    public function stockCardCount(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->inventoryReportService->stockCardRows($validated['month'], $validated['category']);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Stock Card per Count Report',
            'month' => $validated['month'],
            'category' => $validated['category'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card',
            $data,
            'accounting-stock-card-count',
            'pdf.reports.accounting-stock-card'
        );
    }

    public function documentSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $groups = $this->inventoryReportService->documentSummaryGroups(
            $validated['date_from'],
            $validated['date_to'],
            $validated['category'],
        );

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Document Summary per Document',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $validated['category'],
            'groups' => $groups,
            'grand_total' => $groups->sum('total'),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-document-summary',
            $data,
            'accounting-document-summary',
            'pdf.reports.accounting-document-summary'
        );
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->inventoryReportService->purchaseRows(
            $validated['date_from'],
            $validated['date_to'],
            $validated['category'],
        );

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Report',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $validated['category'],
            'rows' => $rows,
            'total_quantity' => $rows->sum('quantity'),
            'total_amount' => $rows->sum('amount'),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-purchase-report',
            $data,
            'accounting-purchase-report',
            'pdf.reports.accounting-purchase-report'
        );
    }

    private function exportReport(
        string $format,
        string $excelView,
        array $data,
        string $filePrefix,
        string $pdfView
    ) {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $excelView, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return PdfReport::analytical($pdfView, $data, $filename);
    }

    private function streamExcel(string $filePrefix, string $view, array $data): StreamedResponse
    {
        $filename = sprintf('%s-%s.xls', $filePrefix, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($view, $data) {
            echo view($view, $data)->render();
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }
}
