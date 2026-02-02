<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingCodeController extends Controller
{
    public function index()
    {
        $codes = AccountingCode::orderBy('code')->get();

        return view('pages.accounting.codes', [
            'codes' => $codes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('accounting_codes', 'code')],
            'desc' => ['required', 'string', 'max:255'],
        ]);

        AccountingCode::create($validated);

        return redirect()->back()->with('success', 'Accounting code created successfully.');
    }

    public function update(Request $request, AccountingCode $code)
    {
        $request->session()->flash('editing_code_id', $code->id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('accounting_codes', 'code')->ignore($code->id)],
            'desc' => ['required', 'string', 'max:255'],
        ]);

        $code->update($validated);

        return redirect()->back()->with('success', 'Accounting code updated successfully.');
    }

    public function destroy(AccountingCode $code)
    {
        $code->delete();

        return redirect()->back()->with('success', 'Accounting code deleted successfully.');
    }
}
