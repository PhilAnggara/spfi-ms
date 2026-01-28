<?php

namespace App\Http\Controllers;

use App\Models\FishSupplier;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FishSupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = FishSupplier::with('creator')->orderByDesc('id')->get();
        return view('pages.fish-supplier', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', Rule::unique('fish_suppliers', 'code')],
            'name' => ['required', 'string'],
        ]);

        FishSupplier::create([
            'code' => $request->code,
            'name' => $request->name,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Fish Supplier has been created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $supplier = FishSupplier::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_supplier_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('fish_suppliers', 'code')->ignore($id)],
            'name' => ['required', 'string'],
        ]);

        $supplier->update([
            'code' => $request->code,
            'name' => $request->name,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Fish Supplier has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $supplier = FishSupplier::findOrFail($id);
        $name = $supplier->name;
        $supplier->delete();

        return redirect()->back()->with('success', 'Fish Supplier ' . $name . ' has been deleted successfully.');
    }
}
