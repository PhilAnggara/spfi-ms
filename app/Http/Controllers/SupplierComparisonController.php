<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use Illuminate\Http\Request;

class SupplierComparisonController extends Controller
{
    /**
     * Show supplier comparison per PRS item.
     */
    public function index()
    {
        $prsItems = PrsItem::with([
            'prs.department',
            'item.unit',
            'canvaser',
            'canvasingItems.supplier',
            'selectedCanvasingItem.supplier',
        ])
            ->whereNull('purchase_order_id')
            ->whereHas('canvasingItems')
            ->orderByDesc('id')
            ->get();

        return view('pages.procurement.supplier-comparison', [
            'prsItems' => $prsItems,
        ]);
    }

    /**
     * Print supplier comparison.
     */
    public function print(Request $request)
    {
        $prsItems = PrsItem::with([
            'prs.department',
            'prs.user',
            'item.unit',
            'canvaser',
            'canvasingItems.supplier',
            'selectedCanvasingItem.supplier',
        ])
            ->whereNull('purchase_order_id')
            ->whereHas('canvasingItems')
            ->orderByDesc('id')
            ->get();

        return view('pages.procurement.supplier-comparison-print', [
            'prsItems' => $prsItems,
        ]);
    }

    /**
     * Add feedback/notes for a PRS item.
     */
    public function addFeedback(Request $request, PrsItem $prsItem)
    {
        $validated = $request->validate([
            'feedback' => ['required', 'string', 'max:1000'],
        ]);

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'MANAGER_FEEDBACK',
            'message' => 'Manager added feedback for supplier selection.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'feedback' => $validated['feedback'],
            ],
        ]);

        return redirect()->back()->with('success', 'Feedback added successfully.');
    }

    /**
     * Select the supplier quote for a PRS item.
     */
    public function select(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->purchase_order_id) {
            return redirect()->back()->withErrors(['canvasing_item_id' => 'Supplier selection is locked because a PO has been created.']);
        }

        $validated = $request->validate([
            'canvasing_item_id' => ['required', 'exists:prs_canvasing_items,id'],
        ]);

        $canvasing = $prsItem->canvasingItems()->whereKey($validated['canvasing_item_id'])->first();
        if (! $canvasing) {
            return redirect()->back()->withErrors(['canvasing_item_id' => 'Invalid supplier for this item.']);
        }

        $prsItem->update([
            'selected_canvasing_item_id' => $canvasing->id,
        ]);

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'SELECT_SUPPLIER',
            'message' => 'Supplier selected for PRS item.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'supplier_id' => $canvasing->supplier_id,
                'canvasing_item_id' => $canvasing->id,
            ],
        ]);

        return redirect()->back()->with('success', 'Supplier selected for this item.');
    }
}
