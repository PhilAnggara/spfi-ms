<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use App\Models\PrsCanvasingItem;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $supplierTermsById = $rows
            ->mapWithKeys(function (array $row) {
                return [
                    (int) $row['supplier_id'] => [
                        'term_of_payment_type' => $this->sanitizeTermValue($row['term_of_payment_type'] ?? null),
                        'term_of_payment' => $this->sanitizeTermValue($row['term_of_payment'] ?? null),
                        'term_of_delivery' => $this->sanitizeTermValue($row['term_of_delivery'] ?? null),
                    ],
                ];
            })
            ->all();

        DB::transaction(function () use ($prsItem, $rows, $keepIds, $request, $supplierTermsById) {
            foreach ($supplierTermsById as $supplierId => $terms) {
                Supplier::whereKey($supplierId)->update($terms);

                PrsCanvasingItem::where('supplier_id', $supplierId)->update($terms);
            }

            if ($keepIds->isEmpty()) {
                $prsItem->canvasingItems()->delete();
                if ($prsItem->selected_canvasing_item_id) {
                    $prsItem->update(['selected_canvasing_item_id' => null]);
                }
            } else {
                $prsItem->canvasingItems()->whereNotIn('id', $keepIds)->delete();
            }

            foreach ($rows as $row) {
                $terms = $supplierTermsById[(int) $row['supplier_id']] ?? [
                    'term_of_payment_type' => null,
                    'term_of_payment' => null,
                    'term_of_delivery' => null,
                ];

                $payload = [
                    'prs_id' => $prsItem->prs_id,
                    'supplier_id' => $row['supplier_id'],
                    'unit_price' => $row['unit_price'],
                    'lead_time_days' => $row['lead_time_days'] ?? null,
                    'term_of_payment_type' => $terms['term_of_payment_type'],
                    'term_of_payment' => $terms['term_of_payment'],
                    'term_of_delivery' => $terms['term_of_delivery'],
                    'notes' => $row['notes'] ?? null,
                    'canvased_by' => $request->user()->id,
                ];

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

        // return redirect()->route('canvasing.index')->with('success', 'Canvasing data saved.');
        return redirect()->back()->with('success', 'Canvasing data saved.');
    }

    /**
     * Download canvasing report per PRS item.
     */
    public function report(PrsItem $prsItem, Request $request)
    {
        if ($prsItem->canvaser_id !== $request->user()->id) {
            abort(403);
        }

        $prsItem->load([
            'prs.department',
            'prs.user',
            'item.unit',
            'canvasingItems.supplier',
            'selectedCanvasingItem.supplier',
        ]);

        $canvasingItems = $prsItem->canvasingItems
            ->sortBy('unit_price')
            ->values();

        if ($canvasingItems->isEmpty()) {
            return redirect()
                ->route('canvasing.show', $prsItem)
                ->withErrors(['message' => 'Canvasing report cannot be generated because no supplier data is available yet.']);
        }

        $filename = sprintf(
            'canvasing-report-%s-%s.pdf',
            $prsItem->item?->code ?? ('item-' . $prsItem->item_id),
            now()->format('YmdHis')
        );

        return Pdf::loadView('pdf.canvasing-report', [
            'prsItem' => $prsItem,
            'canvasingItems' => $canvasingItems,
            'maxUnitPrice' => (float) max($canvasingItems->max('unit_price') ?? 0, 1),
            'generatedBy' => $request->user(),
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    private function sanitizeTermValue(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
