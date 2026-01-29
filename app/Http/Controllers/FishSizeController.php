<?php

namespace App\Http\Controllers;

use App\Models\FishSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FishSizeController extends Controller
{
    /**
     * Store a newly created size range in storage.
     */
    public function store(Request $request)
    {
        $request->session()->flash('size_modal_fish_id', $request->fish_id);

        $request->validate([
            'fish_id' => ['required', 'exists:fish,id'],
            'code' => [
                'required',
                'string',
                Rule::unique('fish_sizes', 'code')
                    ->where(fn ($q) => $q->where('fish_id', $request->fish_id)->whereNull('deleted_at')),
            ],
            'size_range' => [
                'required',
                'string',
                'max:50',
            ],
        ]);

        FishSize::create([
            'fish_id' => $request->fish_id,
            'code' => $request->fish_code .$request->code,
            'size_range' => $request->size_range,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Size range added successfully.');
    }

    /**
     * Remove the specified size range from storage.
     */
    public function destroy(FishSize $fishSize)
    {
        $fishSize->delete();

        return redirect()->back()->with('success', 'Size range deleted successfully.');
    }
}
