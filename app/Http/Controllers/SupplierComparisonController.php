<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'canvasser',
            'canvassingItems.supplier',
            'selectedCanvassingItem.supplier',
        ])
            ->whereNull('purchase_order_id')
            ->where('is_direct_purchase', false)
            ->whereHas('canvassingItems')
            ->orderByDesc('id')
            ->get();

        return view('pages.procurement.supplier-comparison', [
            'prsItems' => $prsItems,
        ]);
    }

    /**
     * Select the supplier quote for a PRS item.
     */
    public function select(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->purchase_order_id) {
            return redirect()->back()->withErrors(['canvassing_item_id' => 'Supplier selection is locked because a PO has been created.']);
        }

        $validated = $request->validate([
            'canvassing_item_id' => ['required', 'exists:prs_canvassing_items,id'],
            'selection_reason' => ['nullable', 'string'],
        ]);

        $canvassing = $prsItem->canvassingItems()->whereKey($validated['canvassing_item_id'])->first();
        if (! $canvassing) {
            return redirect()->back()->withErrors(['canvassing_item_id' => 'Invalid supplier for this item.']);
        }

        $prsItem->update([
            'selected_canvassing_item_id' => $canvassing->id,
            'selection_reason' => $validated['selection_reason'] ?? null,
        ]);

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'SELECT_SUPPLIER',
            'message' => 'Supplier selected for PRS item.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'supplier_id' => $canvassing->supplier_id,
                'canvassing_item_id' => $canvassing->id,
            ],
        ]);

        return redirect()->back()->with('success', 'Supplier selected for this item.');
    }

    /**
     * Generate supplier selection report PDF.
     */
    public function report(PrsItem $prsItem, Request $request)
    {
        $prsItem->load([
            'prs.department',
            'prs.user',
            'item.unit',
            'canvassingItems.supplier',
            'selectedCanvassingItem.supplier',
        ]);

        $canvassingItems = $prsItem->canvassingItems
            ->sortBy('unit_price')
            ->values();

        if ($canvassingItems->isEmpty()) {
            return redirect()
                ->back()
                ->withErrors(['message' => 'Selection report cannot be generated because no supplier data is available.']);
        }

        if (!$prsItem->selected_canvassing_item_id) {
            return redirect()
                ->back()
                ->withErrors(['message' => 'Selection report cannot be generated because no supplier has been selected yet.']);
        }

        $filename = sprintf(
            'supplier-selection-report-%s-%s.pdf',
            $prsItem->item?->code ?? ('item-' . $prsItem->item_id),
            now()->format('YmdHis')
        );

        return Pdf::loadView('pdf.selection-report', [
            'prsItem' => $prsItem,
            'canvassingItems' => $canvassingItems,
            'generatedBy' => $request->user(),
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
