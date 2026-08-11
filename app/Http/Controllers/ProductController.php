<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use PaginatesLegacySqlServer;

    /** @var list<string> */
    private const ROLES_CREATE_PRODUCTS = [
        'administrator',
        'it-staff',
        'engineering-manager',
        'im-manager',
        'im-supervisor',
    ];

    /** @var list<string> */
    private const ROLES_MANAGE_PRODUCTS = [
        'administrator',
        'it-staff',
        'im-manager',
        'im-supervisor',
    ];

    /** @var list<string> */
    private const ALLOWED_SORTS = [
        'name_asc',
        'name_desc',
        'code_asc',
        'code_desc',
        'category_asc',
        'category_desc',
        'avg_unit_price_asc',
        'avg_unit_price_desc',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $itemCategories = ItemCategory::query()->orderBy('name')->get();
        $itemUnits = UnitOfMeasure::query()->orderBy('name')->get();
        $types = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];
        $editingItem = null;
        $editingProductId = session('editing_product_id');

        if ($editingProductId) {
            $editingItem = Item::query()->find($editingProductId);
        }

        $sort = mb_strtolower(trim((string) request('sort', 'name_asc')));

        return view('pages.product', [
            'itemCategories' => $itemCategories,
            'itemUnits' => $itemUnits,
            'types' => $types,
            'editingItem' => $editingItem,
            'filters' => [
                'keyword' => request('keyword', ''),
                'category_id' => request('category_id', ''),
                'unit_id' => request('unit_id', ''),
                'type' => request('type', ''),
                'sort' => in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'name_asc',
            ],
            'canCreateProducts' => $this->userCanCreateProducts($user),
            'canManageProducts' => $this->userCanManageProducts($user),
            'canViewPurchaseOrders' => $user?->hasAnyRole([
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
        // Mapping kolom agar sorting sesuai urutan kolom di DataTables
        $columns = [
            'items.id',
            'items.code',
            'items.name',
            'unit_of_measures.name',
            'item_categories.name',
            'items.type',
            'item_avg_price.avg_unit_price',
        ];

        $rankedAvgSql = '
            SELECT poi.item_id,
                   c.code AS currency_code,
                   SUM(poi.quantity * poi.unit_price) / NULLIF(SUM(poi.quantity), 0) AS avg_unit_price,
                   SUM(poi.quantity) AS total_qty,
                   ROW_NUMBER() OVER (PARTITION BY poi.item_id ORDER BY SUM(poi.quantity) DESC) AS rn
            FROM purchase_order_items poi
            INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id AND po.deleted_at IS NULL
            INNER JOIN currencies c ON c.id = po.currency_id
            GROUP BY poi.item_id, c.code
        ';

        $avgPriceSubquery = DB::table(DB::raw("({$rankedAvgSql}) as ranked_avg"))
            ->where('rn', 1)
            ->select('item_id', 'currency_code', 'avg_unit_price');

        // Base query untuk kebutuhan paging + join relasi
        $baseQuery = Item::query()
            ->leftJoin('unit_of_measures', 'items.unit_of_measure_id', '=', 'unit_of_measures.id')
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->leftJoinSub($avgPriceSubquery, 'item_avg_price', 'item_avg_price.item_id', '=', 'items.id')
            ->select([
                'items.id',
                'items.code',
                'items.name',
                'items.type',
                'items.unit_of_measure_id',
                'items.category_id',
                'unit_of_measures.name as unit_name',
                'item_categories.name as category_name',
                'item_avg_price.avg_unit_price',
                'item_avg_price.currency_code as avg_price_currency',
            ]);

        // Total data tanpa filter
        $recordsTotal = Item::query()->count();

        $searchValue = trim((string) ($request->input('keyword') ?: $request->input('search.value', '')));
        if ($searchValue !== '') {
            $baseQuery->where(function ($query) use ($searchValue) {
                $likeValue = '%'.$searchValue.'%';
                $query->where('items.code', 'like', $likeValue)
                    ->orWhere('items.name', 'like', $likeValue)
                    ->orWhere('unit_of_measures.name', 'like', $likeValue)
                    ->orWhere('item_categories.name', 'like', $likeValue)
                    ->orWhere('items.type', 'like', $likeValue);
            });
        }

        if ($request->filled('category_id')) {
            $baseQuery->where('items.category_id', $request->integer('category_id'));
        }

        if ($request->filled('unit_id')) {
            $baseQuery->where('items.unit_of_measure_id', $request->integer('unit_id'));
        }

        if ($request->filled('type')) {
            $baseQuery->where('items.type', $request->input('type'));
        }

        // Total data setelah filter
        $recordsFiltered = (clone $baseQuery)->reorder()->count();

        // Sorting yang dikirim DataTables (default id desc di sisi client)
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'items.id';

        // Paging
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;

        $orderBySql = $this->buildDataTableOrderBySql($orderColumn, $orderDirection, 'items.id');

        if (! $this->isSqlServerConnection()) {
            $baseQuery->orderBy($orderColumn, $orderDirection);
        }

        $data = $this->sliceEloquentQueryForDataTables(
            $baseQuery,
            'items.id',
            $orderBySql,
            $start,
            $length
        )
            ->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'unit_of_measure_id' => $row->unit_of_measure_id,
                'category_id' => $row->category_id,
                'unit_name' => $row->unit_name,
                'category_name' => $row->category_name,
                'avg_unit_price' => $row->avg_unit_price !== null ? round((float) $row->avg_unit_price, 2) : null,
                'avg_price_currency' => $row->avg_price_currency,
            ]);

        // Format JSON sesuai kebutuhan DataTables
        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function purchaseHistory(Request $request, Item $item)
    {
        $columns = [
            'po.po_number',
            'po.created_at',
            'currencies.code',
            'suppliers.code',
            'suppliers.name',
            'purchase_order_items.quantity',
            'purchase_order_items.unit_price',
            'canvasser',
        ];

        $baseQuery = PurchaseOrderItem::query()
            ->where('purchase_order_items.item_id', $item->id)
            ->join('purchase_orders as po', 'po.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereNull('po.deleted_at')
            ->leftJoin('prs_items as pi', 'pi.id', '=', 'purchase_order_items.prs_item_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'po.supplier_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'po.currency_id')
            ->leftJoin('users as prs_canvasser', 'prs_canvasser.id', '=', 'pi.canvasser_id')
            ->leftJoin('users as po_creator', 'po_creator.id', '=', 'po.created_by')
            ->select([
                'purchase_order_items.id',
                'po.id as purchase_order_id',
                'po.po_number',
                'po.created_at as po_date',
                'currencies.code as currency',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'purchase_order_items.quantity',
                'purchase_order_items.unit_price',
                DB::raw('COALESCE(prs_canvasser.name, po_creator.name) as canvasser'),
            ]);

        $recordsTotal = PurchaseOrderItem::query()
            ->where('purchase_order_items.item_id', $item->id)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereNull('deleted_at'))
            ->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $likeValue = '%'.$searchValue.'%';
            $baseQuery->where(function ($query) use ($likeValue) {
                $query->where('po.po_number', 'like', $likeValue)
                    ->orWhere('suppliers.code', 'like', $likeValue)
                    ->orWhere('suppliers.name', 'like', $likeValue)
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
            ])
            ->groupBy('currencies.code')
            ->orderBy('currencies.code')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'avg_unit_price' => $row->avg_unit_price !== null ? (float) $row->avg_unit_price : null,
                'total_qty' => (float) $row->total_qty,
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
                'supplier_code' => $row->supplier_code,
                'supplier_name' => $row->supplier_name,
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
     * Check whether a product code is available for create or edit.
     */
    public function checkCode(Request $request): JsonResponse
    {
        abort_unless($this->userCanCreateProducts($request->user()), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha_num'],
            'ignore_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
        ]);

        $code = $validated['code'];
        $ignoreId = $validated['ignore_id'] ?? null;

        $exists = Item::query()
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
        abort_unless($this->userCanCreateProducts($request->user()), 403);

        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha_num', Rule::unique('items', 'code')],
            'name' => ['required', 'string'],
            'unit_of_measure_id' => ['required', 'integer', Rule::exists('unit_of_measures', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'type' => ['nullable', 'string', Rule::in($allowedTypes)],
        ]);

        Item::create([
            'code' => $request->code,
            'name' => $request->name,
            'unit_of_measure_id' => $request->unit_of_measure_id,
            'category_id' => $request->category_id,
            'type' => $request->type,
        ]);

        return redirect()->back()->with('success', 'Product has been created successfully.');
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
        abort_unless($this->userCanManageProducts($request->user()), 403);

        $item = Item::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_product_id', $id);

        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha_num', Rule::unique('items', 'code')->ignore($id)],
            'name' => ['required', 'string'],
            'unit_of_measure_id' => ['required', 'integer', Rule::exists('unit_of_measures', 'id')],
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'type' => ['nullable', 'string', Rule::in($allowedTypes)],
        ]);

        $item->update([
            'code' => $request->code,
            'name' => $request->name,
            'unit_of_measure_id' => $request->unit_of_measure_id,
            'category_id' => $request->category_id,
            'type' => $request->type,
        ]);

        return redirect()->back()->with('success', 'Product has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        abort_unless($this->userCanManageProducts($request->user()), 403);

        $item = Item::findOrFail($id);
        $itemName = $item->name;
        $item->delete();

        return redirect()->back()->with('success', 'Product '.$itemName.' has been deleted successfully.');
    }

    private function userCanCreateProducts(?User $user): bool
    {
        return $user?->hasAnyRole(self::ROLES_CREATE_PRODUCTS) ?? false;
    }

    private function userCanManageProducts(?User $user): bool
    {
        return $user?->hasAnyRole(self::ROLES_MANAGE_PRODUCTS) ?? false;
    }
}
