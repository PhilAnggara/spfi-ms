<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = ItemCategory::all()->sortDesc();
        return view('pages.category', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', Rule::unique('item_categories', 'code')],
            'name' => ['required', 'string'],
        ]);

        ItemCategory::create([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'Category has been created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = ItemCategory::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_category_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('item_categories', 'code')->ignore($id)],
            'name' => ['required', 'string'],
        ]);

        $category->update([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'Category has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = ItemCategory::findOrFail($id);
        $name = $category->name;
        $category->delete();

        return redirect()->back()->with('success', 'Category ' . $name . ' has been deleted successfully.');
    }
}
