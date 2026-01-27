<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BuyerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $buyers = Buyer::all()->sortDesc();
        return view('pages.buyer', [
            'buyers' => $buyers,
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
            'name' => ['required', 'string'],
            'address' => ['required', 'string'],
        ]);

        Buyer::create([
            'name' => $request->name,
            'address' => $request->address,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Buyer has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Buyer $buyer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Buyer $buyer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $buyer = Buyer::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_buyer_id', $id);

        $request->validate([
            'name' => ['required', 'string'],
            'address' => ['required', 'string'],
        ]);

        $buyer->update([
            'name' => $request->name,
            'address' => $request->address,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Buyer has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Buyer $buyer)
    {
        $name = $buyer->name;
        $buyer->update([
            'updated_by' => Auth::id(),
        ]);
        $buyer->delete();

        return redirect()->back()->with('success', 'Buyer ' . $name . ' has been deleted successfully.');
    }
}
