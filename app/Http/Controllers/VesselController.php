<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VesselController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vessels = Vessel::with('creator')->orderByDesc('id')->get();
        return view('pages.vessel', [
            'vessels' => $vessels,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', Rule::unique('vessels', 'code')],
            'name' => ['required', 'string'],
        ]);

        Vessel::create([
            'code' => $request->code,
            'name' => $request->name,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Vessel has been created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $vessel = Vessel::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_vessel_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('vessels', 'code')->ignore($id)],
            'name' => ['required', 'string'],
        ]);

        $vessel->update([
            'code' => $request->code,
            'name' => $request->name,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Vessel has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $vessel = Vessel::findOrFail($id);
        $name = $vessel->name;
        $vessel->delete();

        return redirect()->back()->with('success', 'Vessel ' . $name . ' has been deleted successfully.');
    }
}
