<?php

namespace App\Http\Controllers;

use App\Models\UnitOfMeasure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitOfMeasureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $units = UnitOfMeasure::all()->sortDesc();
        return view('pages.uom', [
            'units' => $units,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', Rule::unique('unit_of_measures', 'code')],
            'name' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        UnitOfMeasure::create([
            'code' => $request->code,
            'name' => $request->name,
            'remarks' => $request->remarks,
        ]);

        return redirect()->back()->with('success', 'Unit of Measurement has been created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $uom = UnitOfMeasure::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_uom_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('unit_of_measures', 'code')->ignore($id)],
            'name' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $uom->update([
            'code' => $request->code,
            'name' => $request->name,
            'remarks' => $request->remarks,
        ]);

        return redirect()->back()->with('success', 'Unit of Measurement has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $uom = UnitOfMeasure::findOrFail($id);
        $name = $uom->name;
        $uom->delete();

        return redirect()->back()->with('success', 'Unit of Measurement ' . $name . ' has been deleted successfully.');
    }
}
