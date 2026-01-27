<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currencies = Currency::all()->sortDesc();
        return view('pages.currency', [
            'currencies' => $currencies,
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
            'code' => ['required', 'string', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string'],
            'symbol' => ['nullable', 'string'],
        ]);

        Currency::create([
            'code' => $request->code,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Currency has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Currency $currency)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Currency $currency)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $currency = Currency::findOrFail($id);

        // Flag for error-handling to reopen the correct modal
        $request->session()->flash('editing_currency_id', $id);

        $request->validate([
            'code' => ['required', 'string', Rule::unique('currencies', 'code')->ignore($id)],
            'name' => ['required', 'string'],
            'symbol' => ['nullable', 'string'],
        ]);

        $currency->update([
            'code' => $request->code,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Currency has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        $name = $currency->name;
        $currency->delete();

        return redirect()->back()->with('success', 'Currency ' . $name . ' has been deleted successfully.');
    }
}
