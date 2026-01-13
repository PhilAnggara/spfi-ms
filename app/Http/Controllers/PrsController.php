<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use Illuminate\Http\Request;

class PrsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Prs::all()->sortDesc();
        return view('pages.prs', [
            'items' => $items
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Prs::findOrFail($id);
        $tile = $item->prs_no;
        $item->delete();
        // session()->flash('delete', 'PRS ' . $tile . ' has been deleted successfully.');
        return redirect()->back()->with('success', 'PRS ' . $tile . ' has been deleted successfully.');
    }
}
