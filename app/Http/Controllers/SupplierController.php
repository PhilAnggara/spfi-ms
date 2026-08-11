<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    use PaginatesLegacySqlServer;

    /** @var list<string> */
    private const ALLOWED_SORTS = [
        'name_asc',
        'name_desc',
        'code_asc',
        'code_desc',
        'po_count_asc',
        'po_count_desc',
        'total_amount_asc',
        'total_amount_desc',
        'last_po_date_asc',
        'last_po_date_desc',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $editingSupplier = null;
        $editingSupplierId = session('editing_supplier_id');

        if ($editingSupplierId) {
            $editingSupplier = Supplier::query()->find($editingSupplierId);
        }

        $sort = mb_strtolower(trim((string) request('sort', 'name_asc')));

        return view('pages.supplier', [
            'editingSupplier' => $editingSupplier,
            'filters' => [
                'keyword' => request('keyword', ''),
                'has_po' => request('has_po', ''),
                'sort' => in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'name_asc',
            ],
            'canManageSuppliers' => auth()->user()?->hasAnyRole([
                'administrator',
                'it-staff',
                'purchasing-staff',
                'purchasing-manager',
            ]) ?? false,
            'canDeleteSuppliers' => auth()->user()?->hasAnyRole([
                'administrator',
                'it-staff',
            ]) ?? false,
            'canViewPurchaseOrders' => auth()->user()?->hasAnyRole([
                'administrator',
                'purchasing-staff',
                'purchasing-manager',
                'general-manager',
            ]) ?? false,
        ]);
    }

    /**
     * Server-side datatable data.
     */
    public function datatable(Request $request)
    {
        $columns = [
            'suppliers.id',
            'suppliers.code',
            'suppliers.name',
            'suppliers.address',
            'po_stats.po_count',
            'amount_stats.total_amount',
            'po_stats.last_po_date',
        ];

        $poStatsSubquery = DB::table('purchase_orders as po')
            ->whereNull('po.deleted_at')
            ->select([
                'po.supplier_id',
                DB::raw('COUNT(DISTINCT po.id) as po_count'),
                DB::raw('MAX(po.created_at) as last_po_date'),
            ])
            ->groupBy('po.supplier_id');

        $rankedAmountSql = '
            SELECT po.supplier_id,
                   c.code AS currency_code,
                   SUM(poi.quantity * poi.unit_price) AS total_amount,
                   ROW_NUMBER() OVER (PARTITION BY po.supplier_id ORDER BY SUM(poi.quantity * poi.unit_price) DESC) AS rn
            FROM purchase_order_items poi
            INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id AND po.deleted_at IS NULL
            INNER JOIN currencies c ON c.id = po.currency_id
            GROUP BY po.supplier_id, c.code
        ';

        $amountStatsSubquery = DB::table(DB::raw("({$rankedAmountSql}) as ranked_amounts"))
            ->where('rn', 1)
            ->select('supplier_id', 'currency_code', 'total_amount');

        $baseQuery = Supplier::query()
            ->leftJoinSub($poStatsSubquery, 'po_stats', 'po_stats.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub($amountStatsSubquery, 'amount_stats', 'amount_stats.supplier_id', '=', 'suppliers.id')
            ->select([
                'suppliers.id',
                'suppliers.code',
                'suppliers.name',
                'suppliers.address',
                'suppliers.phone',
                'suppliers.fax',
                'suppliers.email',
                'suppliers.contact_person',
                'suppliers.remarks',
                'po_stats.po_count',
                'po_stats.last_po_date',
                'amount_stats.total_amount as primary_total_amount',
                'amount_stats.currency_code as primary_total_currency',
            ]);

        $recordsTotal = Supplier::query()->count();

        $searchValue = trim((string) ($request->input('keyword') ?: $request->input('search.value', '')));
        if ($searchValue !== '') {
            $baseQuery->where(function ($query) use ($searchValue) {
                $likeValue = '%'.$searchValue.'%';
                $query->where('suppliers.code', 'like', $likeValue)
                    ->orWhere('suppliers.name', 'like', $likeValue)
                    ->orWhere('suppliers.address', 'like', $likeValue)
                    ->orWhere('suppliers.phone', 'like', $likeValue)
                    ->orWhere('suppliers.email', 'like', $likeValue)
                    ->orWhere('suppliers.contact_person', 'like', $likeValue);
            });
        }

        if ($request->input('has_po') === '1') {
            $baseQuery->where('po_stats.po_count', '>', 0);
        } elseif ($request->input('has_po') === '0') {
            $baseQuery->where(function ($query) {
                $query->whereNull('po_stats.po_count')
                    ->orWhere('po_stats.po_count', '=', 0);
            });
        }

        $recordsFiltered = (clone $baseQuery)->reorder()->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'suppliers.id';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;

        $orderBySql = $this->buildDataTableOrderBySql($orderColumn, $orderDirection, 'suppliers.id');

        if (! $this->isSqlServerConnection()) {
            $baseQuery->orderBy($orderColumn, $orderDirection);
        }

        $rows = $this->sliceEloquentQueryForDataTables(
            $baseQuery,
            'suppliers.id',
            $orderBySql,
            $start,
            $length
        );

        $supplierIds = $rows->pluck('id');

        $purchaseTotalsBySupplier = $supplierIds->isEmpty()
            ? collect()
            : DB::table('purchase_order_items as poi')
                ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->join('currencies as c', 'c.id', '=', 'po.currency_id')
                ->whereNull('po.deleted_at')
                ->whereIn('po.supplier_id', $supplierIds)
                ->groupBy('po.supplier_id', 'c.code')
                ->orderBy('c.code')
                ->select([
                    'po.supplier_id',
                    'c.code as currency',
                    DB::raw('ROUND(SUM(poi.quantity * poi.unit_price), 2) as total_amount'),
                ])
                ->get()
                ->groupBy('supplier_id');

        $data = $rows->map(function ($row) use ($purchaseTotalsBySupplier) {
            $purchaseTotals = ($purchaseTotalsBySupplier[$row->id] ?? collect())
                ->map(fn ($totalRow) => [
                    'currency' => $totalRow->currency,
                    'total_amount' => $totalRow->total_amount !== null ? (float) $totalRow->total_amount : null,
                ])
                ->values();

            return [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'address' => $row->address,
                'phone' => $row->phone,
                'fax' => $row->fax,
                'email' => $row->email,
                'contact_person' => $row->contact_person,
                'remarks' => $row->remarks,
                'po_count' => (int) ($row->po_count ?? 0),
                'last_po_date' => $row->last_po_date,
                'primary_total_amount' => $row->primary_total_amount !== null ? round((float) $row->primary_total_amount, 2) : null,
                'primary_total_currency' => $row->primary_total_currency,
                'purchase_totals' => $purchaseTotals,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function purchaseHistory(Request $request, Supplier $supplier)
    {
        $columns = [
            'po.po_number',
            'po.created_at',
            'currencies.code',
            'items.code',
            'items.name',
            'purchase_order_items.quantity',
            'purchase_order_items.unit_price',
            'canvasser',
        ];

        $baseQuery = PurchaseOrderItem::query()
            ->join('purchase_orders as po', 'po.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('po.supplier_id', $supplier->id)
            ->whereNull('po.deleted_at')
            ->leftJoin('prs_items as pi', 'pi.id', '=', 'purchase_order_items.prs_item_id')
            ->leftJoin('items', 'items.id', '=', 'purchase_order_items.item_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'po.currency_id')
            ->leftJoin('users as prs_canvasser', 'prs_canvasser.id', '=', 'pi.canvasser_id')
            ->leftJoin('users as po_creator', 'po_creator.id', '=', 'po.created_by')
            ->select([
                'purchase_order_items.id',
                'po.id as purchase_order_id',
                'po.po_number',
                'po.created_at as po_date',
                'currencies.code as currency',
                'items.code as item_code',
                'items.name as item_name',
                'purchase_order_items.quantity',
                'purchase_order_items.unit_price',
                DB::raw('COALESCE(prs_canvasser.name, po_creator.name) as canvasser'),
            ]);

        $recordsTotal = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($query) => $query
                ->where('supplier_id', $supplier->id)
                ->whereNull('deleted_at'))
            ->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $likeValue = '%'.$searchValue.'%';
            $baseQuery->where(function ($query) use ($likeValue) {
                $query->where('po.po_number', 'like', $likeValue)
                    ->orWhere('items.code', 'like', $likeValue)
                    ->orWhere('items.name', 'like', $likeValue)
                    ->orWhere('prs_canvasser.name', 'like', $likeValue)
                    ->orWhere('po_creator.name', 'like', $likeValue);
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        $avgUnitPrices = (clone $baseQuery)
            ->reorder()
            ->select([
                'currencies.code as currency',
                DB::raw('ROUND(SUM(purchase_order_items.quantity * purchase_order_items.unit_price) / NULLIF(SUM(purchase_order_items.quantity), 0), 2) as avg_unit_price'),
                DB::raw('SUM(purchase_order_items.quantity) as total_qty'),
                DB::raw('ROUND(SUM(purchase_order_items.quantity * purchase_order_items.unit_price), 2) as total_amount'),
            ])
            ->groupBy('currencies.code')
            ->orderBy('currencies.code')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'avg_unit_price' => $row->avg_unit_price !== null ? (float) $row->avg_unit_price : null,
                'total_qty' => (float) $row->total_qty,
                'total_amount' => $row->total_amount !== null ? (float) $row->total_amount : null,
            ])
            ->values();

        $orderColumnIndex = (int) $request->input('order.0.column', 1);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'po.created_at';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;

        if ($orderColumn === 'canvasser') {
            $baseQuery->orderByRaw('COALESCE(prs_canvasser.name, po_creator.name) '.$orderDirection);
        } else {
            $baseQuery->orderBy($orderColumn, $orderDirection);
        }

        $data = $baseQuery
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'purchase_order_id' => $row->purchase_order_id,
                'po_number' => $row->po_number,
                'po_date' => $row->po_date,
                'currency' => $row->currency,
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'quantity' => $row->quantity,
                'unit_price' => $row->unit_price,
                'canvasser' => $row->canvasser,
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'avgUnitPrices' => $avgUnitPrices,
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Check whether a supplier code is available for create or edit.
     */
    public function checkCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'ignore_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
        ]);

        $code = $validated['code'];
        $ignoreId = $validated['ignore_id'] ?? null;

        $exists = Supplier::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            return response()->json([
                'available' => false,
                'message' => 'This code has already been used.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Code is available.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', Rule::unique('suppliers', 'code')],
            'name' => ['required', 'string'],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'fax' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'contact_person' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        Supplier::create([
            'code' => $request->code,
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'fax' => $request->fax,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'remarks' => $request->remarks,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Supplier has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplier = Supplier::findOrFail($id);

        $request->session()->flash('editing_supplier_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('suppliers', 'code')->ignore($id)],
            'name' => ['required', 'string'],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'fax' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'contact_person' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $supplier->update([
            'code' => $request->code,
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'fax' => $request->fax,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'remarks' => $request->remarks,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Supplier has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Supplier::findOrFail($id);
        $title = $item->name;
        $item->delete();

        return redirect()->back()->with('success', "{$title} has been deleted successfully.");
    }
}
