<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Services\Accounting\AccountingInventoryReportService;
use App\Support\PdfReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportController extends Controller
{
    public function __construct(
        private readonly AccountingInventoryReportService $inventoryReportService,
    ) {}

    public function index()
    {
        return view('pages.accounting-reports', [
            'categories' => $this->inventoryReportService->reportCategories(),
        ]);
    }

    public function stockCard(Request $request)
    {
        $validated = $this->validateReportRequest($request, [
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $rows = $this->inventoryReportService->stockCardRows($validated['month'], $category->id);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Accounting Stock Card',
            'month' => $validated['month'],
            'category' => $category->name,
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
        $validated = $this->validateReportRequest($request, [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $groups = $this->inventoryReportService->transactionGroups(
            $validated['date_from'],
            $validated['date_to'],
            $category->id,
        );

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Transaction Report',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $category->name,
            'groups' => $groups,
            'grand_total_qty' => $groups->sum(fn (array $group): float => (float) collect($group['rows'])->sum(fn (array $row): float => abs((float) $row['qty']))),
            'grand_total_amount' => $groups->sum(fn (array $group): float => (float) collect($group['rows'])->sum(fn (array $row): float => abs((float) $row['amount']))),
            'grand_rr_minus_ts_qty' => $groups->sum(fn (array $group): float => (float) ($group['rr_minus_ts_qty'] ?? 0)),
            'grand_rr_minus_ts_amount' => $groups->sum(fn (array $group): float => (float) ($group['rr_minus_ts_amount'] ?? 0)),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-transaction-report',
            $data,
            'accounting-transaction-report',
            'pdf.reports.accounting-transaction-report'
        );
    }

    public function restatement(Request $request)
    {
        $validated = $this->validateReportRequest($request, [
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $rows = $this->inventoryReportService->restatementRows($validated['month'], $category->id);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Restatement Report',
            'month' => $validated['month'],
            'category' => $category->name,
            'rows' => $rows,
            'totals' => [
                'beg_qty' => $rows->sum('beg_qty'),
                'beg_amount' => $rows->sum('beg_amount'),
                'purchase_qty' => $rows->sum('purchase_qty'),
                'purchase_amount' => $rows->sum('purchase_amount'),
                'issuance_qty' => $rows->sum('issuance_qty'),
                'issuance_amount' => $rows->sum('issuance_amount'),
                'end_theoretical_qty' => $rows->sum('end_theoretical_qty'),
                'end_theoretical_amount' => $rows->sum('end_theoretical_amount'),
                'percount_qty' => $rows->sum(fn (array $row): float => (float) ($row['percount_qty'] ?? 0)),
                'percount_amount' => $rows->sum(fn (array $row): float => (float) ($row['percount_amount'] ?? 0)),
                'variance_qty' => $rows->sum(fn (array $row): float => (float) ($row['variance_qty'] ?? 0)),
                'variance_amount' => $rows->sum(fn (array $row): float => (float) ($row['variance_amount'] ?? 0)),
                'total_qty' => $rows->sum('total_qty'),
                'total_amount' => $rows->sum('total_amount'),
            ],
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-restatement',
            $data,
            'accounting-restatement',
            'pdf.reports.accounting-restatement'
        );
    }

    public function stockCardCount(Request $request)
    {
        $validated = $this->validateReportRequest($request, [
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $rows = $this->inventoryReportService->stockCardCountRows($validated['month'], $category->id);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Stock Card per Count Report',
            'month' => $validated['month'],
            'category' => $category->name,
            'rows' => $rows,
            'totals' => [
                'stock_card_qty' => $rows->sum('stock_card_qty'),
                'stock_card_amount' => $rows->sum('stock_card_amount'),
                'percount_qty' => $rows->sum(fn (array $row): float => (float) ($row['percount_qty'] ?? 0)),
                'percount_amount' => $rows->sum(fn (array $row): float => (float) ($row['percount_amount'] ?? 0)),
                'variance_qty' => $rows->sum(fn (array $row): float => (float) ($row['variance_qty'] ?? 0)),
                'variance_amount' => $rows->sum(fn (array $row): float => (float) ($row['variance_amount'] ?? 0)),
            ],
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card-count',
            $data,
            'accounting-stock-card-count',
            'pdf.reports.accounting-stock-card-count'
        );
    }

    public function documentSummary(Request $request)
    {
        $validated = $this->validateReportRequest($request, [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $groups = $this->inventoryReportService->documentSummaryGroups(
            $validated['date_from'],
            $validated['date_to'],
            $category->id,
        );

        $rrTotal = (float) ($groups->firstWhere('type', 'RR')['total'] ?? 0);
        $tsTotal = (float) ($groups->firstWhere('type', 'TS')['total'] ?? 0);

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Document Summary per Document',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $category->name,
            'groups' => $groups,
            'grand_total' => $rrTotal - $tsTotal,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-document-summary',
            $data,
            'accounting-document-summary',
            'pdf.reports.accounting-document-summary',
            landscape: false,
        );
    }

    public function purchase(Request $request)
    {
        $validated = $this->validateReportRequest($request, [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $category = $this->resolveCategory((int) $validated['category_id']);
        $rows = $this->inventoryReportService->purchaseRows(
            $validated['date_from'],
            $validated['date_to'],
            $category->id,
        );

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Report',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $category->name,
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

    /**
     * @param  array<string, list<string>>  $extraRules
     * @return array<string, mixed>
     */
    private function validateReportRequest(Request $request, array $extraRules): array
    {
        return $request->validate([
            ...$extraRules,
            'category_id' => [
                'required',
                'integer',
                Rule::exists('item_categories', 'id')->where(function ($query): void {
                    $query->whereIn('name', AccountingInventoryReportService::REPORT_CATEGORY_NAMES);
                }),
            ],
            'format' => ['required', 'in:pdf,excel'],
        ]);
    }

    private function resolveCategory(int $categoryId): ItemCategory
    {
        return ItemCategory::query()->findOrFail($categoryId);
    }

    private function exportReport(
        string $format,
        string $excelView,
        array $data,
        string $filePrefix,
        string $pdfView,
        bool $landscape = true,
    ) {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $excelView, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return PdfReport::analytical($pdfView, $data, $filename, $landscape);
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
