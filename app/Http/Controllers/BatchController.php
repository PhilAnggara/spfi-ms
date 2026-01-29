<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\FishSupplier;
use App\Models\Vessel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $batches = Batch::with(['fishSupplier', 'vessel'])->get()->sortDesc();
        $fishSuppliers = FishSupplier::all();
        $vessels = Vessel::all();
        return view('pages.batch', [
            'batches' => $batches,
            'fishSuppliers' => $fishSuppliers,
            'vessels' => $vessels,
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
        $request->validate([
            'code' => ['required', 'string', Rule::unique('batches', 'code')],
            'fish_supplier_id' => ['required', 'exists:fish_suppliers,id'],
            'vessel_id' => ['required', 'exists:vessels,id'],
            'fishing_method' => ['required', 'string'],
            'fish_type' => ['required', 'string'],
        ]);

        Batch::create([
            'code' => $request->code,
            'fish_supplier_id' => $request->fish_supplier_id,
            'vessel_id' => $request->vessel_id,
            'fishing_method' => $request->fishing_method,
            'fish_type' => $request->fish_type,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Batch has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Batch $batch)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Batch $batch)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $batch = Batch::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_batch_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('batches', 'code')->ignore($id)],
            'fish_supplier_id' => ['required', 'exists:fish_suppliers,id'],
            'vessel_id' => ['required', 'exists:vessels,id'],
            'fishing_method' => ['required', 'string'],
            'fish_type' => ['required', 'string'],
        ]);

        $batch->update([
            'code' => $request->code,
            'fish_supplier_id' => $request->fish_supplier_id,
            'vessel_id' => $request->vessel_id,
            'fishing_method' => $request->fishing_method,
            'fish_type' => $request->fish_type,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Batch has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Batch $batch)
    {
        $code = $batch->code;
        $batch->delete();

        return redirect()->back()->with('success', 'Batch ' . $code . ' has been deleted successfully.');
    }
}
