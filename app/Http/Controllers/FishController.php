<?php

namespace App\Http\Controllers;

use App\Models\Fish;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FishController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fishes = Fish::all()->sortDesc();

        return view('pages.fish', [
            'fishes' => $fishes,
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
            'code' => ['required', 'string', Rule::unique('fish', 'code')],
            'name' => ['required', 'string'],
        ]);

        Fish::create([
            'code' => $request->code,
            'name' => $request->name,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Fish has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fish $fish)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fish $fish)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fish $fish)
    {
        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_fish_id', $fish->id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('fish', 'code')->ignore($fish->id)],
            'name' => ['required', 'string'],
        ]);

        $fish->update([
            'code' => $request->code,
            'name' => $request->name,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Fish has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fish $fish)
    {
        $name = $fish->name;
        $fish->delete();

        return redirect()->back()->with('success', 'Fish ' . $name . ' has been deleted successfully.');
    }
}
