<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PurchaseOrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportController extends Controller
{
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

        $selectedMonth = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $selectedMonth->year;
        $month = $selectedMonth->month;

        $legacyRows = DB::connection('legacy_sqlsrv_2')
            ->table('tbl_InventoryMonthly')
            ->selectRaw('ItemCode, UCost, SUM(Ending) AS Ending, SUM(Begining) AS Beginning')
            ->where('Category', $validated['category'])
            ->whereRaw('YEAR(TranDate) = ? AND MONTH(TranDate) = ?', [$year, $month])
            ->groupBy('ItemCode', 'UCost')
            ->orderBy('ItemCode')
            ->get();

        $itemCodes = $legacyRows->pluck('ItemCode')
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $localItems = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
            ->with('unit')
            ->get()
            ->keyBy(fn (Item $item) => strtoupper($item->code));

        $rows = $legacyRows->map(function ($row) use ($localItems) {
            $itemCode = strtoupper((string) $row->ItemCode);
            $item = $localItems->get($itemCode);
            $ending = (float) $row->Ending;
            $beginning = (float) $row->Beginning;
            $unitCost = (float) $row->UCost;
            $amount = $ending * $unitCost;
            $beginningAmount = $beginning * $unitCost;
            $transaction = $amount - $beginningAmount;

            return [
                'item_code' => $row->ItemCode,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $ending,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $transaction,
            ];
        });

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
            'accounting-stock-card'
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

        $selectedMonth = Carbon::createFromFormat('Y-m', Carbon::parse($validated['date_from'])->format('Y-m'));
        $year = $selectedMonth->year;
        $month = $selectedMonth->month;

        $legacyRows = DB::connection('legacy_sqlsrv_2')
            ->table('tbl_InventoryMonthly')
            ->selectRaw('ItemCode, UCost, SUM(Ending) AS Ending, SUM(Begining) AS Beginning')
            ->where('Category', $validated['category'])
            ->whereRaw('YEAR(TranDate) = ? AND MONTH(TranDate) = ?', [$year, $month])
            ->groupBy('ItemCode', 'UCost')
            ->orderBy('ItemCode')
            ->get();

        $itemCodes = $legacyRows->pluck('ItemCode')
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $localItems = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
            ->with('unit')
            ->get()
            ->keyBy(fn (Item $item) => strtoupper($item->code));

        $rows = $legacyRows->map(function ($row) use ($localItems) {
            $itemCode = strtoupper((string) $row->ItemCode);
            $item = $localItems->get($itemCode);
            $ending = (float) $row->Ending;
            $beginning = (float) $row->Beginning;
            $unitCost = (float) $row->UCost;
            $amount = $ending * $unitCost;
            $beginningAmount = $beginning * $unitCost;
            $transaction = $amount - $beginningAmount;

            return [
                'item_code' => $row->ItemCode,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $ending,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $transaction,
            ];
        });

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
            'accounting-transaction-report'
        );

        // Implementation will be added later
        return response()->json(['message' => 'Transaction report - Implementation pending']);
    }

    public function restatement(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $selectedMonth = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $selectedMonth->year;
        $month = $selectedMonth->month;

        $legacyRows = DB::connection('legacy_sqlsrv_2')
            ->table('tbl_InventoryMonthly')
            ->selectRaw('ItemCode, UCost, SUM(Ending) AS Ending, SUM(Begining) AS Beginning')
            ->where('Category', $validated['category'])
            ->whereRaw('YEAR(TranDate) = ? AND MONTH(TranDate) = ?', [$year, $month])
            ->groupBy('ItemCode', 'UCost')
            ->orderBy('ItemCode')
            ->get();

        $itemCodes = $legacyRows->pluck('ItemCode')
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $localItems = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
            ->with('unit')
            ->get()
            ->keyBy(fn (Item $item) => strtoupper($item->code));

        $rows = $legacyRows->map(function ($ros) use ($localItems) {
            $itemCode = strtoupper((string) $ros->ItemCode);
            $item = $localItems->get($itemCode);
            $ending = (float) $ros->Ending;
            $beginning = (float) $ros->Beginning;
            $unitCost = (float) $ros->UCost;
            $amount = $ending * $unitCost;
            $beginningAmount = $beginning * $unitCost;
            $transaction = $amount - $beginningAmount;

            return [
                'item_code' => $ros->ItemCode,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $ending,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $transaction,
            ];
        });

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
            'accounting-restatement'
        );

        // Implementation will be added later
        return response()->json(['message' => 'Restatement report - Implementation pending']);
    }

    public function stockCardCount(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $selectedMonth = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $selectedMonth->year;
        $month = $selectedMonth->month;

        $legacyRows = DB::connection('legacy_sqlsrv_2')
            ->table('tbl_InventoryMonthly')
            ->selectRaw('ItemCode, UCost, SUM(Ending) AS Ending, SUM(Begining) AS Beginning')
            ->where('Category', $validated['category'])
            ->whereRaw('YEAR(TranDate) = ? AND MONTH(TranDate) = ?', [$year, $month])
            ->groupBy('ItemCode', 'UCost')
            ->orderBy('ItemCode')
            ->get();

        $itemCodes = $legacyRows->pluck('ItemCode')
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $localItems = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
            ->with('unit')
            ->get()
            ->keyBy(fn (Item $item) => strtoupper($item->code));

        $rows = $legacyRows->map(function ($ros) use ($localItems) {
            $itemCode = strtoupper((string) $ros->ItemCode);
            $item = $localItems->get($itemCode);
            $ending = (float) $ros->Ending;
            $beginning = (float) $ros->Beginning;
            $unitCost = (float) $ros->UCost;
            $amount = $ending * $unitCost;
            $beginningAmount = $beginning * $unitCost;
            $transaction = $amount - $beginningAmount;

            return [
                'item_code' => $ros->ItemCode,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $ending,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $transaction,
            ];
        });

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
            'accounting-stock-card-count'
        );

        return response()->json([
            'message' => 'Stock Card per Count report - Implementation pending',
            'month' => $validated['month'],
            'year' => $year,
            'category' => $validated['category'],
            'data' => $legacyRows,
            'item_codes' => $itemCodes,
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Stock Card per Count report - Implementation pending']);
    }

    public function documentSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $selectedMonth = Carbon::createFromFormat('Y-m', Carbon::parse($validated['date_from'])->format('Y-m'));
        $year = $selectedMonth->year;
        $month = $selectedMonth->month;

        $legacyRows = DB::connection('legacy_sqlsrv_2')
            ->table('tbl_InventoryMonthly')
            ->selectRaw('ItemCode, UCost, SUM(Ending) AS Ending, SUM(Begining) AS Beginning')
            ->where('Category', $validated['category'])
            ->whereRaw('YEAR(TranDate) = ? AND MONTH(TranDate) = ?', [$year, $month])
            ->groupBy('ItemCode', 'UCost')
            ->orderBy('ItemCode')
            ->get();

        $itemCodes = $legacyRows->pluck('ItemCode')
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();

        $localItems = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
            ->with('unit')
            ->get()
            ->keyBy(fn (Item $item) => strtoupper($item->code));

        $rows = $legacyRows->map(function ($row) use ($localItems) {
            $itemCode = strtoupper((string) $row->ItemCode);
            $item = $localItems->get($itemCode);
            $ending = (float) $row->Ending;
            $beginning = (float) $row->Beginning;
            $unitCost = (float) $row->UCost;
            $amount = $ending * $unitCost;
            $beginningAmount = $beginning * $unitCost;
            $transaction = $amount - $beginningAmount;

            return [
                'item_code' => $row->ItemCode,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $ending,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $transaction,
            ];
        });

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Document Summary per Doc',
            'month' => Carbon::parse($validated['date_from'])->format('Y-m'),
            'category' => $validated['category'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-stock-card',
            $data,
            'accounting-document-summary'
        );

        // Implementation will be added later
        return response()->json(['message' => 'Document Summary per Doc report - Implementation pending']);
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = PurchaseOrderItem::with([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'receivingReportItems.receivingReport',
            'item.unit',
            'item.category',
        ])
            ->whereHas('purchaseOrder', function ($query) use ($validated) {
                $query->whereDate('created_at', '>=', $validated['date_from'])
                    ->whereDate('created_at', '<=', $validated['date_to']);
            })
            ->whereHas('item.category', function ($query) use ($validated) {
                $query->where('name', $validated['category']);
            })
            ->orderBy('purchase_order_id')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseOrderItem $item) {
                $po = $item->purchaseOrder;
                $quantity = (float) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $amount = $item->line_subtotal !== null
                    ? (float) $item->line_subtotal
                    : $quantity * $unitPrice;

                return [
                    'supplier_name' => $po?->supplier?->name,
                    'po_number' => $po?->po_number ?? ($po ? '#' . $po->id : ''),
                    'rr_number' => $item->receivingReportItems
                        ->pluck('receivingReport.rr_number')
                        ->filter()
                        ->unique()
                        ->implode(', '),
                    'date' => $po?->created_at?->toDateString(),
                    'currency' => $po?->currency?->code ?? 'IDR',
                    'item_code' => $item->item?->code,
                    'item_name' => $item->item?->name,
                    'unit' => $item->item?->unit?->name ?? '',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ];
            });

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Report',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $validated['category'],
            'rows' => $rows,
            'total_amount' => $rows->sum('amount'),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-purchase-report',
            $data,
            'accounting-purchase-report'
        );
    }

    private function exportReport(string $format, string $view, array $data, string $filePrefix)
    {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $view, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->stream($filename);
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
