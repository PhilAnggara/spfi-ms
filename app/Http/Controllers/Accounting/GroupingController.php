<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Grouping;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupingController extends Controller
{
    public function index()
    {
        $groupings = Grouping::orderBy('code')->get();

        return view('pages.accounting.groupings', [
            'groupings' => $groupings,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('groupings', 'code')],
            'desc' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:2'],
            'grp' => ['nullable', 'integer', 'min:0'],
            'tab' => ['nullable', 'integer', 'min:0'],
            'other' => ['nullable', 'boolean'],
            'selection' => ['nullable', 'boolean'],
        ]);

        Grouping::create([
            'code' => $validated['code'],
            'desc' => $validated['desc'],
            'major' => $validated['major'] ?? null,
            'grp' => $validated['grp'] ?? 0,
            'tab' => $validated['tab'] ?? 0,
            'other' => $request->boolean('other'),
            'selection' => $request->boolean('selection'),
        ]);

        return redirect()->back()->with('success', 'Grouping created successfully.');
    }

    public function update(Request $request, Grouping $grouping)
    {
        $request->session()->flash('editing_grouping_id', $grouping->id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('groupings', 'code')->ignore($grouping->id)],
            'desc' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:2'],
            'grp' => ['nullable', 'integer', 'min:0'],
            'tab' => ['nullable', 'integer', 'min:0'],
            'other' => ['nullable', 'boolean'],
            'selection' => ['nullable', 'boolean'],
        ]);

        $grouping->update([
            'code' => $validated['code'],
            'desc' => $validated['desc'],
            'major' => $validated['major'] ?? null,
            'grp' => $validated['grp'] ?? 0,
            'tab' => $validated['tab'] ?? 0,
            'other' => $request->boolean('other'),
            'selection' => $request->boolean('selection'),
        ]);

        return redirect()->back()->with('success', 'Grouping updated successfully.');
    }

    public function destroy(Grouping $grouping)
    {
        $grouping->delete();

        return redirect()->back()->with('success', 'Grouping deleted successfully.');
    }
}
