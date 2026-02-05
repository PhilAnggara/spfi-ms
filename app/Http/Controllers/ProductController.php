<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $itemCategories = ItemCategory::query()->orderBy('name')->get();
        $itemUnits = UnitOfMeasure::query()->orderBy('name')->get();
        $types = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];
        $editingItem = null;
        $editingProductId = session('editing_product_id');

        if ($editingProductId) {
            $editingItem = Item::query()->find($editingProductId);
        }

        return view('pages.product', [
            'itemCategories' => $itemCategories,
            'itemUnits' => $itemUnits,
            'types' => $types,
            'editingItem' => $editingItem,
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
        ];

        // Base query untuk kebutuhan paging + join relasi
        $baseQuery = Item::query()
            ->leftJoin('unit_of_measures', 'items.unit_of_measure_id', '=', 'unit_of_measures.id')
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->select([
                'items.id',
                'items.code',
                'items.name',
                'items.type',
                'items.unit_of_measure_id',
                'items.category_id',
                'unit_of_measures.name as unit_name',
                'item_categories.name as category_name',
            ]);

        // Total data tanpa filter
        $recordsTotal = Item::query()->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            // Filter pencarian global (search box DataTables)
            $baseQuery->where(function ($query) use ($searchValue) {
                $likeValue = '%' . $searchValue . '%';
                $query->where('items.code', 'like', $likeValue)
                    ->orWhere('items.name', 'like', $likeValue)
                    ->orWhere('unit_of_measures.name', 'like', $likeValue)
                    ->orWhere('item_categories.name', 'like', $likeValue)
                    ->orWhere('items.type', 'like', $likeValue);
            });
        }

        // Total data setelah filter
        $recordsFiltered = (clone $baseQuery)->count();

        // Sorting yang dikirim DataTables (default id desc di sisi client)
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'items.id';

        // Paging
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;

        $data = $baseQuery
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        // Format JSON sesuai kebutuhan DataTables
        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'max:8', Rule::unique('items', 'code')],
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
        $item = Item::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_product_id', $id);

        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'max:8', Rule::unique('items', 'code')->ignore($id)],
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
    public function destroy(string $id)
    {
        $item = Item::findOrFail($id);
        $itemName = $item->name;
        $item->delete();

        return redirect()->back()->with('success', 'Product ' . $itemName . ' has been deleted successfully.');
    }
}
