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
        $editingBalanceSheet = null;
        $editingBalanceSheetId = session('editing_balance_sheet_id');

        if ($editingBalanceSheetId) {
            $editingBalanceSheet = BsGrouping::query()->find($editingBalanceSheetId);
        }

        return view('pages.accounting.balance-sheet', [
            'groupCodes' => AccountingGroupCode::orderBy('group_code')->get(),
            'accountingCodes' => AccountingCode::orderBy('code')->get(),
            'groupings' => Grouping::orderBy('code')->get(),
            'editingBalanceSheet' => $editingBalanceSheet,
        ]);
    }

    public function datatable(Request $request)
    {
        $columns = [
            'bs_groupings.id',
            'accounting_group_codes.group_code',
            'accounting_codes.code',
            'groupings.code',
            'bs_groupings.major',
        ];

        $baseQuery = BsGrouping::query()
            ->leftJoin('accounting_group_codes', 'bs_groupings.group_code_id', '=', 'accounting_group_codes.id')
            ->leftJoin('accounting_codes', 'bs_groupings.accounting_code_id', '=', 'accounting_codes.id')
            ->leftJoin('groupings', 'bs_groupings.grouping_id', '=', 'groupings.id')
            ->select([
                'bs_groupings.id',
                'bs_groupings.group_code_id',
                'bs_groupings.accounting_code_id',
                'bs_groupings.grouping_id',
                'bs_groupings.major',
                'accounting_group_codes.group_code as group_code',
                'accounting_group_codes.group_desc as group_desc',
                'accounting_codes.code as accounting_code',
                'accounting_codes.desc as accounting_desc',
                'groupings.code as grouping_code',
                'groupings.desc as grouping_desc',
            ]);

        $recordsTotal = BsGrouping::query()->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $baseQuery->where(function ($query) use ($searchValue) {
                $likeValue = '%' . $searchValue . '%';
                $query->where('accounting_group_codes.group_code', 'like', $likeValue)
                    ->orWhere('accounting_group_codes.group_desc', 'like', $likeValue)
                    ->orWhere('accounting_codes.code', 'like', $likeValue)
                    ->orWhere('accounting_codes.desc', 'like', $likeValue)
                    ->orWhere('groupings.code', 'like', $likeValue)
                    ->orWhere('groupings.desc', 'like', $likeValue)
                    ->orWhere('bs_groupings.major', 'like', $likeValue);
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'bs_groupings.id';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? $length : 10;

        $data = $baseQuery
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
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
