<?php

namespace App\Http\Controllers;

use App\Models\PrsCanvasingItem;
use App\Models\PrsItem;
use App\Models\Supplier;
use Illuminate\Http\Request;

class CanvasingController extends Controller
{
    /**
     * List items assigned to the current canvasser.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $prsItems = PrsItem::with([
            'prs',
            'item',
            'canvasingItem',
        ])
            ->where('canvaser_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return view('pages.canvasing', [
            'prsItems' => $prsItems,
        ]);
    }

    /**
     * Show PRS detail for canvasing input.
     */
    public function show(PrsItem $prsItem, Request $request)
    {
        if ($prsItem->canvaser_id !== $request->user()->id) {
            abort(403);
        }

        $prsItem->load([
            'prs.department',
            'prs.user',
            'item',
            'canvasingItem.supplier',
        ]);

        $suppliers = Supplier::orderBy('name')->get();

        return view('pages.canvasing-detail', [
            'prsItem' => $prsItem,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Save canvasing results per item.
     */
    public function store(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->canvaser_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'term_of_payment_type' => ['nullable', 'in:cash,credit'],
            'term_of_payment' => ['nullable', 'string', 'max:255'],
            'term_of_delivery' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        PrsCanvasingItem::updateOrCreate(
            ['prs_item_id' => $prsItem->id],
            [
                'prs_id' => $prsItem->prs_id,
                'supplier_id' => $validated['supplier_id'],
                'unit_price' => $validated['unit_price'],
                'lead_time_days' => $validated['lead_time_days'] ?? null,
                'term_of_payment_type' => $validated['term_of_payment_type'] ?? null,
                'term_of_payment' => $validated['term_of_payment'] ?? null,
                'term_of_delivery' => $validated['term_of_delivery'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'canvased_by' => $request->user()->id,
            ]
        );

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'CANVASE',
            'message' => 'Canvasing data saved per item.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
            ],
        ]);

        return redirect()->route('canvasing.show', $prsItem)->with('success', 'Canvasing data saved.');
    }
}
