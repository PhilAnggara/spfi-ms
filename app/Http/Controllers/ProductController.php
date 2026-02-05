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
        $items = Item::query()->with(['unit', 'category'])->orderByDesc('id')->get();

        return view('pages.product', [
            'items' => $items,
            'itemCategories' => $itemCategories,
            'itemUnits' => $itemUnits,
            'types' => $types,
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
