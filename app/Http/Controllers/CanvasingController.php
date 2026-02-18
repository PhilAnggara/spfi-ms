<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'canvasingItems.supplier',
            'selectedCanvasingItem.supplier',
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
            'canvasingItems.supplier',
            'selectedCanvasingItem.supplier',
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
            'suppliers' => ['required', 'array', 'min:1'],
            'suppliers.*.id' => ['nullable', 'integer', 'exists:prs_canvasing_items,id'],
            'suppliers.*.supplier_id' => ['required', 'distinct', 'exists:suppliers,id'],
            'suppliers.*.unit_price' => ['required', 'numeric', 'min:0'],
            'suppliers.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'suppliers.*.term_of_payment_type' => ['nullable', 'in:cash,credit'],
            'suppliers.*.term_of_payment' => ['nullable', 'string', 'max:255'],
            'suppliers.*.term_of_delivery' => ['nullable', 'string', 'max:255'],
            'suppliers.*.notes' => ['nullable', 'string'],
        ]);

        $rows = collect($validated['suppliers']);
        $keepIds = $rows->pluck('id')->filter()->values();

        DB::transaction(function () use ($prsItem, $rows, $keepIds, $request) {
            if ($keepIds->isEmpty()) {
                $prsItem->canvasingItems()->delete();
                if ($prsItem->selected_canvasing_item_id) {
                    $prsItem->update(['selected_canvasing_item_id' => null]);
                }
            } else {
                $prsItem->canvasingItems()->whereNotIn('id', $keepIds)->delete();
            }

            // Collect supplier updates to batch them
            $supplierUpdates = [];

            foreach ($rows as $row) {
                $payload = [
                    'prs_id' => $prsItem->prs_id,
                    'supplier_id' => $row['supplier_id'],
                    'unit_price' => $row['unit_price'],
                    'lead_time_days' => $row['lead_time_days'] ?? null,
                    'term_of_payment_type' => $row['term_of_payment_type'] ?? null,
                    'term_of_payment' => $row['term_of_payment'] ?? null,
                    'term_of_delivery' => $row['term_of_delivery'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'canvased_by' => $request->user()->id,
                ];

                // Collect supplier updates if terms are provided
                if (isset($row['term_of_payment_type']) || isset($row['term_of_payment']) || isset($row['term_of_delivery'])) {
                    $supplierUpdates[$row['supplier_id']] = [
                        'term_of_payment_type' => $row['term_of_payment_type'] ?? null,
                        'term_of_payment' => $row['term_of_payment'] ?? null,
                        'term_of_delivery' => $row['term_of_delivery'] ?? null,
                    ];
                }

                if (! empty($row['id'])) {
                    $existing = $prsItem->canvasingItems()->whereKey($row['id'])->first();
                    if (! $existing) {
                        throw ValidationException::withMessages([
                            'suppliers' => 'Invalid canvasing row for this PRS item.',
                        ]);
                    }
                    $existing->update($payload);
                } else {
                    $prsItem->canvasingItems()->create($payload);
                }
            }

            // Batch update suppliers
            foreach ($supplierUpdates as $supplierId => $updates) {
                Supplier::where('id', $supplierId)->update($updates);
            }

            if ($prsItem->selected_canvasing_item_id && $keepIds->isNotEmpty()) {
                if (! $keepIds->contains($prsItem->selected_canvasing_item_id)) {
                    $prsItem->update(['selected_canvasing_item_id' => null]);
                }
            }
        });

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'CANVASE',
            'message' => 'Canvasing data saved per item.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'supplier_ids' => $rows->pluck('supplier_id')->values()->all(),
            ],
        ]);

        return redirect()->route('canvasing.index')->with('success', 'Canvasing data saved.');
    }

    /**
     * Get supplier payment and delivery terms.
     */
    public function getSupplierTerms(Supplier $supplier)
    {
        return response()->json([
            'term_of_payment_type' => $supplier->term_of_payment_type,
            'term_of_payment' => $supplier->term_of_payment,
            'term_of_delivery' => $supplier->term_of_delivery,
        ]);
    }
}
