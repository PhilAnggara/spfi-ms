<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use App\Models\PrsCanvasingItem;
use App\Models\Supplier;
use Illuminate\Http\Request;

class CanvasingController extends Controller
{
    /**
     * List PRS assigned to the current canvasser.
     */
    public function index(Request $request)
    {
        $items = Prs::with(['department', 'items.item'])
            ->withCount([
                'items as items_count',
                'items as canvased_items_count' => function ($query) {
                    $query->whereHas('canvasingItem', function ($subQuery) {
                        $subQuery->whereNotNull('unit_price');
                    });
                },
            ])
            ->where('canvaser_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('pages.canvasing', [
            'items' => $items,
        ]);
    }

    /**
     * Show PRS detail for canvasing input.
     */
    public function show(Prs $prs, Request $request)
    {
        if ($prs->canvaser_id !== $request->user()->id) {
            abort(403);
        }

        $prs->load([
            'department',
            'user',
            'items.item',
            'items.canvasingItem.supplier',
        ]);

        $suppliers = Supplier::orderBy('name')->get();

        return view('pages.canvasing-detail', [
            'prs' => $prs,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Save canvasing results per item.
     */
    public function store(Request $request, Prs $prs)
    {
        if ($prs->canvaser_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.prs_item_id' => ['required', 'exists:prs_items,id'],
            'items.*.supplier_id' => ['required', 'exists:suppliers,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $prsItemIds = $prs->items()->pluck('id')->all();

        foreach ($validated['items'] as $row) {
            if (! in_array((int) $row['prs_item_id'], $prsItemIds, true)) {
                continue;
            }

            PrsCanvasingItem::updateOrCreate(
                ['prs_item_id' => $row['prs_item_id']],
                [
                    'prs_id' => $prs->id,
                    'supplier_id' => $row['supplier_id'],
                    'unit_price' => $row['unit_price'],
                    'lead_time_days' => $row['lead_time_days'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'canvased_by' => $request->user()->id,
                ]
            );
        }

        $prs->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'CANVASE',
            'message' => 'Canvasing data saved.',
            'meta' => [
                'items_count' => count($validated['items']),
            ],
        ]);

        return redirect()->route('canvasing.show', $prs)->with('success', 'Canvasing data saved.');
    }
}
