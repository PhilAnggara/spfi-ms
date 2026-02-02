<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingCode;
use App\Models\AccountingGroupCode;
use App\Models\BsGrouping;
use App\Models\Grouping;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BsGroupingController extends Controller
{
    public function index()
    {
        // Eager load relationships untuk menghindari N+1 query
        $balanceSheets = BsGrouping::with([
                'groupCode:id,group_code,group_desc',
                'accountingCode:id,code,desc',
                'grouping:id,code,desc'
            ])
            ->select('id', 'group_code_id', 'accounting_code_id', 'grouping_id', 'major')
            ->orderBy('id', 'desc')
            ->get();

        // Cache reference data untuk select options
        $groupCodes = AccountingGroupCode::select('id', 'group_code', 'group_desc')
            ->orderBy('group_code')
            ->get();

        $accountingCodes = AccountingCode::select('id', 'code', 'desc')
            ->orderBy('code')
            ->get();

        $groupings = Grouping::select('id', 'code', 'desc')
            ->orderBy('code')
            ->get();

        return view('pages.accounting.balance-sheet', [
            'balanceSheets' => $balanceSheets,
            'groupCodes' => $groupCodes,
            'accountingCodes' => $accountingCodes,
            'groupings' => $groupings,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_code_id' => ['required', 'exists:accounting_group_codes,id'],
            'accounting_code_id' => ['required', 'exists:accounting_codes,id'],
            'grouping_id' => ['nullable', 'exists:groupings,id'],
            'major' => ['nullable', 'string', 'max:2'],
        ]);

        BsGrouping::updateOrCreate(
            [
                'group_code_id' => $validated['group_code_id'],
                'accounting_code_id' => $validated['accounting_code_id'],
            ],
            [
                'grouping_id' => $validated['grouping_id'] ?? null,
                'major' => $validated['major'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Balance sheet mapping saved successfully.');
    }

    public function update(Request $request, BsGrouping $balanceSheet)
    {
        $request->session()->flash('editing_balance_sheet_id', $balanceSheet->id);

        $validated = $request->validate([
            'group_code_id' => ['required', 'exists:accounting_group_codes,id'],
            'accounting_code_id' => ['required', 'exists:accounting_codes,id'],
            'grouping_id' => ['nullable', 'exists:groupings,id'],
            'major' => ['nullable', 'string', 'max:2'],
        ]);

        $balanceSheet->update($validated);

        return redirect()->back()->with('success', 'Balance sheet mapping updated successfully.');
    }

    public function destroy(BsGrouping $balanceSheet)
    {
        $balanceSheet->delete();

        return redirect()->back()->with('success', 'Balance sheet mapping deleted successfully.');
    }
}
