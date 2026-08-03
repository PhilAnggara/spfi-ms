<?php

namespace App\Http\Controllers;

use App\Exports\ImStockInventorySpreadsheet;
use App\Exports\ImTransactionSpreadsheet;
use App\Models\Department;
use App\Models\Item;
use App\Support\PdfReport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImReportController extends Controller
{
    private const CATEGORIES = [
        'OFFICE SUPPLIES',
        'SPARE PARTS',
        'FACTORY SUPPLIES',
        'CHEMICAL',
        'FUEL',
        'LABEL',
        'CARTON',
        'CAN',
        'RAW MATERIALS',
        'SPICES AND INGREDIENTS',
        'COAL',
        'SLUDGE OIL',
        'LABELING SUPPLIES',
        'MATERIAL IN TRANSIT',
        'FINISHED GOODS',
        'FISH',
    ];

    private const TS_TYPES = [
        'Finished Goods',
        'Raw Materials',
        'Spare Parts',
        'Supplies',
        'Others',
    ];

    public function index(): View
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get();

        return view('pages.im-reports', [
            'departments' => $departments,
            'categories' => self::CATEGORIES,
            'tsTypes' => self::TS_TYPES,
        ]);
    }

    public function stockInventory(Request $request): Response
    {
        $validated = $request->validate([
            'as_of' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->stockInventoryRows($validated['as_of'], $validated['category']);

        $data = [
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Stock Inventory per Category',
            'as_of' => $validated['as_of'],
            'category' => $validated['category'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $rows,
            'prepared_by_name' => $request->user()?->name ?? '',
            'prepared_by_title' => '',
            'checked_by_name' => 'Daniel Watuna',
            'checked_by_title' => 'IM Supervisor',
            'approved_by_name' => 'Rommy Tendean',
            'approved_by_title' => 'IM Manager',
        ];

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-stock-inventory-%s.xlsx', now()->format('Ymd-His'));

            return (new ImStockInventorySpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-stock-inventory-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-stock-inventory', $data, $filename);
    }

    public function transaction(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $groups = $this->transactionGroups(
            $validated['date_from'],
            $validated['date_to'],
            $validated['category']
        );

        $data = [
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Transaction Report per Category',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $validated['category'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'groups' => $groups,
            'prepared_by_name' => $request->user()?->name ?? '',
            'prepared_by_title' => '',
            'checked_by_name' => 'Daniel Watuna',
            'checked_by_title' => 'IM Supervisor',
            'approved_by_name' => 'Rommy Tendean',
            'approved_by_title' => 'IM Manager',
        ];

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-transaction-%s.xlsx', now()->format('Ymd-His'));

            return (new ImTransactionSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-transaction-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-transaction', $data, $filename);
    }

    public function receivingRegister(Request $request): RedirectResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        return $this->reportNotReady();
    }

    public function swsRegister(Request $request): RedirectResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        return $this->reportNotReady();
    }

    public function transferRegister(Request $request): RedirectResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'ts_type' => ['required', 'in:'.implode(',', self::TS_TYPES)],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        return $this->reportNotReady();
    }

    public function deliveryRegister(Request $request): RedirectResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        return $this->reportNotReady();
    }

    /**
     * @return Collection<int, array{item_code: string, item_name: string, unit: string|null, rows: Collection<int, array{doc_date: string, doc_type: string, doc_number: string, quantity: float}>}>
     */
    private function transactionGroups(string $dateFrom, string $dateTo, string $category): Collection
    {
        $rr = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->whereDate('rr.received_date', '>=', $dateFrom)
            ->whereDate('rr.received_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('rri.qty_good', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'rr.received_date as doc_date',
                DB::raw("'RR' as doc_type"),
                'rr.rr_number as doc_number',
                'rri.qty_good as quantity',
                'u.name as unit',
            ]);

        $ts = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereDate('ts.ts_date', '>=', $dateFrom)
            ->whereDate('ts.ts_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('tsi.quantity', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'ts.ts_date as doc_date',
                DB::raw("'TS' as doc_type"),
                'ts.ts_number as doc_number',
                'tsi.quantity as quantity',
                'u.name as unit',
            ]);

        $dr = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->whereDate('d.dr_date', '>=', $dateFrom)
            ->whereDate('d.dr_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('di.quantity', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'd.dr_date as doc_date',
                DB::raw("'DR' as doc_type"),
                'd.dr_number as doc_number',
                'di.quantity as quantity',
                DB::raw('COALESCE(u.name, di.uom) as unit'),
            ]);

        $typeOrder = ['RR' => 1, 'TS' => 2, 'DR' => 3];

        return collect($rr->unionAll($ts)->unionAll($dr)->get())
            ->map(fn ($row) => [
                'item_code' => (string) $row->item_code,
                'item_name' => (string) $row->item_name,
                'doc_date' => Carbon::parse($row->doc_date)->toDateString(),
                'doc_type' => (string) $row->doc_type,
                'doc_number' => (string) $row->doc_number,
                'quantity' => (float) $row->quantity,
                'unit' => $row->unit ?: null,
            ])
            ->groupBy('item_code')
            ->sortKeys()
            ->map(function (Collection $itemRows) use ($typeOrder) {
                $first = $itemRows->first();
                $sorted = $itemRows
                    ->sortBy([
                        ['doc_date', 'asc'],
                        fn ($row) => $typeOrder[$row['doc_type']] ?? 99,
                        ['doc_number', 'asc'],
                    ])
                    ->values()
                    ->map(fn (array $row) => [
                        'doc_date' => $row['doc_date'],
                        'doc_type' => $row['doc_type'],
                        'doc_number' => $row['doc_number'],
                        'quantity' => $row['quantity'],
                    ]);

                return [
                    'item_code' => $first['item_code'],
                    'item_name' => $first['item_name'],
                    'unit' => $first['unit'],
                    'rows' => $sorted,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, code: string, unit: string|null, beginning: float, rr: float, ts: float, dr: float, ending: float}>
     */
    private function stockInventoryRows(string $asOf, string $category): Collection
    {
        $monthStart = Carbon::parse($asOf)->startOfMonth()->toDateString();

        $items = Item::query()
            ->with(['unit:id,name'])
            ->whereHas('category', function ($query) use ($category) {
                $query->where('name', $category);
            })
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'unit_of_measure_id']);

        if ($items->isEmpty()) {
            return collect();
        }

        $itemIds = $items->pluck('id');

        $beginnings = $this->stockBalanceBeginnings($itemIds, $monthStart, $asOf);
        $movements = $this->stockBalanceMovements($itemIds, $monthStart, $asOf);
        $endings = $this->stockBalanceEndings($itemIds, $asOf);
        $inventoryBalances = DB::table('stock_inventories')
            ->whereIn('item_id', $itemIds)
            ->where('is_delete', false)
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(balance), 0) as balance')
            ->pluck('balance', 'item_id');

        return $items
            ->map(function (Item $item) use ($beginnings, $movements, $endings, $inventoryBalances) {
                $movement = $movements->get($item->id);
                $beginning = (float) ($beginnings[$item->id] ?? 0);
                $rr = (float) ($movement->rr ?? 0);
                $ts = (float) ($movement->ts ?? 0);
                $dr = (float) ($movement->dr ?? 0);
                $ending = $endings->has($item->id)
                    ? (float) $endings->get($item->id)
                    : (float) ($inventoryBalances[$item->id] ?? 0);

                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit?->name,
                    'beginning' => $beginning,
                    'rr' => $rr,
                    'ts' => $ts,
                    'dr' => $dr,
                    'ending' => $ending,
                ];
            })
            ->filter(function (array $row) {
                return $row['beginning'] != 0
                    || $row['rr'] != 0
                    || $row['ts'] != 0
                    || $row['dr'] != 0
                    || $row['ending'] != 0;
            })
            ->values();
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, float>
     */
    private function stockBalanceBeginnings(Collection $itemIds, string $monthStart, string $asOf): Collection
    {
        $endColumn = DB::getQueryGrammar()->wrap('end');
        $beginColumn = DB::getQueryGrammar()->wrap('begin');

        $priorEnds = DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$endColumn} as ending_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date DESC, id DESC) as rn")
                    ->whereIn('item_id', $itemIds)
                    ->whereDate('date', '<', $monthStart),
                'ranked'
            )
            ->where('rn', 1)
            ->get();

        $beginnings = $priorEnds
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('ending_qty'));

        $missingItemIds = $itemIds->reject(fn ($id) => $beginnings->has($id))->values();

        if ($missingItemIds->isEmpty()) {
            return $beginnings;
        }

        $monthBegins = DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$beginColumn} as beginning_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date ASC, id ASC) as rn")
                    ->whereIn('item_id', $missingItemIds)
                    ->whereDate('date', '>=', $monthStart)
                    ->whereDate('date', '<=', $asOf),
                'ranked'
            )
            ->where('rn', 1)
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('beginning_qty'));

        return $beginnings->union($monthBegins);
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, object{rr: float|int|string, ts: float|int|string, dr: float|int|string}>
     */
    private function stockBalanceMovements(Collection $itemIds, string $monthStart, string $asOf): Collection
    {
        return DB::table('stock_balances')
            ->whereIn('item_id', $itemIds)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $asOf)
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(qty_in1), 0) as rr, COALESCE(SUM(qty_out1), 0) as ts, COALESCE(SUM(qty_out3), 0) as dr')
            ->get()
            ->keyBy('item_id');
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, float>
     */
    private function stockBalanceEndings(Collection $itemIds, string $asOf): Collection
    {
        $endColumn = DB::getQueryGrammar()->wrap('end');

        return DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$endColumn} as ending_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date DESC, id DESC) as rn")
                    ->whereIn('item_id', $itemIds)
                    ->whereDate('date', '<=', $asOf),
                'ranked'
            )
            ->where('rn', 1)
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('ending_qty'));
    }

    private function exportReport(
        string $format,
        string $excelView,
        array $data,
        string $filePrefix,
        string $pdfView
    ): Response {
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

    private function reportNotReady(): RedirectResponse
    {
        return back()->with('success', 'Report generation will be available soon.');
    }
}
