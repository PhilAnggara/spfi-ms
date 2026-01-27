<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::all()->sortDesc();

        return view('pages.supplier', compact('suppliers'));
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

        // Flag for error-handling to reopen the correct modal
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
