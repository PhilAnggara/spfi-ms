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
        // get only item category names and item unit names
        $itemCategories = ItemCategory::query()->pluck('name');
        $itemUnits = UnitOfMeasure::query()->pluck('name');
        $types = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];
        $items = Item::all()->sortDesc();
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
        // Build allowed lists for validation
        $allowedCategories = ItemCategory::query()->pluck('name')->toArray();
        $allowedUnits = UnitOfMeasure::query()->pluck('name')->toArray();
        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'alpha_num', 'size:7', Rule::unique('items', 'code')],
            'name' => ['required', 'string'],
            'unit' => ['required', 'string', Rule::in($allowedUnits)],
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'type' => ['required', 'string', Rule::in($allowedTypes)],
        ]);

        Item::create([
            'code' => $request->code,
            'name' => $request->name,
            'unit' => $request->unit,
            'category' => $request->category,
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

        // Build allowed lists for validation
        $allowedCategories = ItemCategory::query()->pluck('name')->toArray();
        $allowedUnits = UnitOfMeasure::query()->pluck('name')->toArray();
        $allowedTypes = ['Raw Material', 'Capital Goods', 'Finished Goods', 'Wastes'];

        $request->validate([
            'code' => ['required', 'string', 'alpha_num', 'size:7', Rule::unique('items', 'code')->ignore($id)],
            'name' => ['required', 'string'],
            'unit' => ['required', 'string', Rule::in($allowedUnits)],
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'type' => ['required', 'string', Rule::in($allowedTypes)],
        ]);

        $item->update([
            'code' => $request->code,
            'name' => $request->name,
            'unit' => $request->unit,
            'category' => $request->category,
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
