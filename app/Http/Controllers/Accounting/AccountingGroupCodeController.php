<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingGroupCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingGroupCodeController extends Controller
{
    public function index()
    {
        $groupCodes = AccountingGroupCode::orderBy('group_code')->get();

        return view('pages.accounting.group-codes', [
            'groupCodes' => $groupCodes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:10', Rule::unique('accounting_group_codes', 'group_code')],
            'group_desc' => ['required', 'string', 'max:255'],
        ]);

        AccountingGroupCode::create($validated);

        return redirect()->back()->with('success', 'Group code created successfully.');
    }

    public function update(Request $request, AccountingGroupCode $groupCode)
    {
        $request->session()->flash('editing_group_code_id', $groupCode->id);

        $validated = $request->validate([
            'group_code' => ['required', 'string', 'max:10', Rule::unique('accounting_group_codes', 'group_code')->ignore($groupCode->id)],
            'group_desc' => ['required', 'string', 'max:255'],
        ]);

        $groupCode->update($validated);

        return redirect()->back()->with('success', 'Group code updated successfully.');
    }

    public function destroy(AccountingGroupCode $groupCode)
    {
        $groupCode->delete();

        return redirect()->back()->with('success', 'Group code deleted successfully.');
    }
}
