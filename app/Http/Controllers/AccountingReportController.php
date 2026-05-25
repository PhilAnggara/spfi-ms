<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ReceivingReportItem;
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
            'accounting-stock-card',
            'portrait'
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

        $groups = collect([
            [
                'type' => 'RR',
                'title' => 'Receiving Report',
                'rows' => $this->receivingReportSummaryRows($validated),
            ],
            [
                'type' => 'TS',
                'title' => 'Transfer Slip',
                'rows' => $this->transferSlipSummaryRows($validated),
            ],
            [
                'type' => 'DR',
                'title' => 'Delivery Receipt',
                'rows' => $this->deliverySummaryRows($validated),
            ],
        ])->map(function (array $group) {
            $group['total'] = $group['rows']->sum('amount');

            return $group;
        });

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
            'portrait'
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

        $rows = ReceivingReportItem::with([
            'receivingReport',
            'purchaseOrderItem.purchaseOrder.supplier',
            'purchaseOrderItem.purchaseOrder.currency',
            'purchaseOrderItem.item.unit',
            'purchaseOrderItem.item.category',
        ])
            ->whereHas('receivingReport', function ($query) use ($validated) {
                $query->whereDate('received_date', '>=', $validated['date_from'])
                    ->whereDate('received_date', '<=', $validated['date_to']);
            })
            ->whereHas('purchaseOrderItem.item.category', function ($query) use ($validated) {
                $query->where('name', $validated['category']);
            })
            ->orderBy('receiving_report_id')
            ->orderBy('id')
            ->get()
            ->map(function (ReceivingReportItem $receivedItem) {
                $poItem = $receivedItem->purchaseOrderItem;
                $po = $poItem?->purchaseOrder;
                $rr = $receivedItem->receivingReport;
                $quantity = (float) $receivedItem->qty_good;
                $unitPrice = (float) ($poItem?->unit_price ?? 0);
                $amount = $quantity * $unitPrice;

                return [
                    'supplier_name' => $po?->supplier?->name,
                    'po_number' => $po?->po_number ?? ($po ? '#' . $po->id : ''),
                    'rr_number' => $rr?->rr_number,
                    'date' => $rr?->received_date?->toDateString(),
                    'currency' => $po?->currency?->code ?? 'IDR',
                    'item_code' => $poItem?->item?->code,
                    'item_name' => $poItem?->item?->name,
                    'unit' => $poItem?->item?->unit?->name ?? '',
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
            'total_quantity' => $rows->sum('quantity'),
            'total_amount' => $rows->sum('amount'),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.accounting-purchase-report',
            $data,
            'accounting-purchase-report'
        );
    }

    private function exportReport(string $format, string $view, array $data, string $filePrefix, string $orientation = 'landscape')
    {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $view, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
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

    private function receivingReportSummaryRows(array $filters)
    {
        return DB::table('receiving_reports as rr')
            ->join('receiving_report_items as rri', 'rri.receiving_report_id', '=', 'rr.id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->whereNull('rr.deleted_at')
            ->whereDate('rr.received_date', '>=', $filters['date_from'])
            ->whereDate('rr.received_date', '<=', $filters['date_to'])
            ->where('ic.name', $filters['category'])
            ->groupBy('rr.id', 'rr.rr_number', 'rr.received_date')
            ->orderBy('rr.received_date')
            ->orderBy('rr.rr_number')
            ->select([
                'rr.rr_number as number',
                'rr.received_date as date',
                DB::raw('SUM(COALESCE(rri.qty_good, 0) * COALESCE(poi.unit_price, 0)) as amount'),
            ])
            ->get()
            ->map(fn ($row) => [
                'number' => $row->number,
                'date' => $row->date,
                'amount' => (float) $row->amount,
            ]);
    }

    private function transferSlipSummaryRows(array $filters)
    {
        return DB::table('transfer_slips as ts')
            ->join('transfer_slip_items as tsi', 'tsi.transfer_slip_id', '=', 'ts.id')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('stock_inventories as si', function ($join) {
                $join->on('si.item_id', '=', 'tsi.item_id')
                    ->where('si.is_active', true)
                    ->where('si.is_delete', false);
            })
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereDate('ts.ts_date', '>=', $filters['date_from'])
            ->whereDate('ts.ts_date', '<=', $filters['date_to'])
            ->where('ic.name', $filters['category'])
            ->groupBy('ts.id', 'ts.ts_number', 'ts.ts_date')
            ->orderBy('ts.ts_date')
            ->orderBy('ts.ts_number')
            ->select([
                'ts.ts_number as number',
                'ts.ts_date as date',
                DB::raw('SUM(COALESCE(tsi.quantity, 0) * COALESCE(si.average_price, 0)) as amount'),
            ])
            ->get()
            ->map(fn ($row) => [
                'number' => $row->number,
                'date' => $row->date,
                'amount' => (float) $row->amount,
            ]);
    }

    private function deliverySummaryRows(array $filters)
    {
        return DB::table('deliveries as d')
            ->join('delivery_items as di', 'di.delivery_id', '=', 'd.id')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('stock_inventories as si', function ($join) {
                $join->on('si.item_id', '=', 'di.item_id')
                    ->where('si.is_active', true)
                    ->where('si.is_delete', false);
            })
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->whereDate('d.dr_date', '>=', $filters['date_from'])
            ->whereDate('d.dr_date', '<=', $filters['date_to'])
            ->where('ic.name', $filters['category'])
            ->groupBy('d.id', 'd.dr_number', 'd.dr_date')
            ->orderBy('d.dr_date')
            ->orderBy('d.dr_number')
            ->select([
                'd.dr_number as number',
                'd.dr_date as date',
                DB::raw('SUM(COALESCE(di.quantity, 0) * COALESCE(si.average_price, 0)) as amount'),
            ])
            ->get()
            ->map(fn ($row) => [
                'number' => $row->number,
                'date' => $row->date,
                'amount' => (float) $row->amount,
            ]);
    }
}
