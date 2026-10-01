<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountTagNonFgController extends Controller
{
    public function index(): View
    {
        return view('pages.count-tags.non-fg.index');
    }

    public function scan(): View
    {
        return view('pages.count-tags.non-fg.scan', [
            'lookupUrl' => route('count-tags.non-fg.lookup'),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = trim($validated['code']);

        $item = Item::query()
            ->with([
                'unit:id,name',
                'category:id,name',
            ])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $item) {
            return response()->json([
                'message' => 'Product not found for code: '.$code,
            ], 404);
        }

        return response()->json([
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'unit_name' => $item->unit?->name,
            'category_name' => $item->category?->name,
        ]);
    }
}
