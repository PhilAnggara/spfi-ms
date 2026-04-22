<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use App\Models\PrsCanvassingItem;
use App\Models\Department;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CanvassingController extends Controller
{
    /**
     * List items assigned to the current canvasser.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'date_needed_start' => trim((string) $request->query('date_needed_start', '')),
            'date_needed_end' => trim((string) $request->query('date_needed_end', '')),
            'department' => trim((string) $request->query('department', '')),
        ];

        $prsItems = PrsItem::with([
            'prs',
            'prs.department',
            'item.unit',
            'canvassingItems.supplier',
            'selectedCanvassingItem.supplier',
        ])
            ->where('canvasser_id', $userId)
            ->when($filters['keyword'] !== '', function ($query) use ($filters) {
                $keyword = $filters['keyword'];

                $query->where(function ($innerQuery) use ($keyword) {
                    $innerQuery->whereHas('prs', function ($prsQuery) use ($keyword) {
                        $prsQuery->where('prs_number', 'like', "%{$keyword}%");
                    })->orWhereHas('item', function ($itemQuery) use ($keyword) {
                        $itemQuery->where('code', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%");
                    });
                });
            })
            ->when($filters['date_needed_start'] !== '', function ($query) use ($filters) {
                $query->whereHas('prs', function ($prsQuery) use ($filters) {
                    $prsQuery->whereDate('date_needed', '>=', $filters['date_needed_start']);
                });
            })
            ->when($filters['date_needed_end'] !== '', function ($query) use ($filters) {
                $query->whereHas('prs', function ($prsQuery) use ($filters) {
                    $prsQuery->whereDate('date_needed', '<=', $filters['date_needed_end']);
                });
            })
            ->when($filters['department'] !== '', function ($query) use ($filters) {
                $query->whereHas('prs.department', function ($departmentQuery) use ($filters) {
                    $departmentQuery->where('code', $filters['department']);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.canvassing', [
            'prsItems' => $prsItems,
            'departmentOptions' => $departmentOptions,
            'filters' => $filters,
        ]);
    }

    /**
     * Show PRS detail for canvassing input.
     */
    public function show(PrsItem $prsItem, Request $request)
    {
        if ($prsItem->canvasser_id !== $request->user()->id) {
            abort(403);
        }

        $prsItem->load([
            'prs.department',
            'prs.user',
            'item',
            'canvassingItems.supplier',
            'selectedCanvassingItem.supplier',
        ]);

        $suppliers = Supplier::orderBy('name')->get();

        return view('pages.canvassing-detail', [
            'prsItem' => $prsItem,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Save canvassing results per item.
     */
    public function store(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->canvasser_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'suppliers' => ['required', 'array', 'min:1'],
            'suppliers.*.id' => ['nullable', 'integer', 'exists:prs_canvassing_items,id'],
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

                PrsCanvassingItem::where('supplier_id', $supplierId)->update($terms);
            }

            if ($keepIds->isEmpty()) {
                $prsItem->canvassingItems()->delete();
                if ($prsItem->selected_canvassing_item_id) {
                    $prsItem->update(['selected_canvassing_item_id' => null]);
                }
            } else {
                $prsItem->canvassingItems()->whereNotIn('id', $keepIds)->delete();
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
                    $existing = $prsItem->canvassingItems()->whereKey($row['id'])->first();
                    if (! $existing) {
                        throw ValidationException::withMessages([
                            'suppliers' => 'Invalid canvassing row for this PRS item.',
                        ]);
                    }
                    $existing->update($payload);
                } else {
                    $prsItem->canvassingItems()->create($payload);
                }
            }

            if ($prsItem->selected_canvassing_item_id && $keepIds->isNotEmpty()) {
                if (! $keepIds->contains($prsItem->selected_canvassing_item_id)) {
                    $prsItem->update(['selected_canvassing_item_id' => null]);
                }
            }
        });

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'CANVASE',
            'message' => 'Canvassing data saved per item.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'supplier_ids' => $rows->pluck('supplier_id')->values()->all(),
            ],
        ]);

        // return redirect()->route('canvassing.index')->with('success', 'Canvassing data saved.');
        return redirect()->back()->with('success', 'Canvassing data saved.');
    }

    /**
     * Download canvassing report per PRS item.
     */
    public function report(PrsItem $prsItem, Request $request)
    {
        if ($prsItem->canvasser_id !== $request->user()->id) {
            abort(403);
        }

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
                ->route('canvassing.show', $prsItem)
                ->withErrors(['message' => 'Canvassing report cannot be generated because no supplier data is available yet.']);
        }

        $filename = sprintf(
            'canvassing-report-%s-%s.pdf',
            $prsItem->item?->code ?? ('item-' . $prsItem->item_id),
            now()->format('YmdHis')
        );

        return Pdf::loadView('pdf.canvassing-report', [
            'prsItem' => $prsItem,
            'canvassingItems' => $canvassingItems,
            'maxUnitPrice' => (float) max($canvassingItems->max('unit_price') ?? 0, 1),
            'generatedBy' => $request->user(),
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    /**
     * Toggle direct purchase status for a PRS item.
     */
    public function toggleDirectPurchase(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->canvasser_id !== $request->user()->id) {
            abort(403);
        }

        if ($prsItem->purchase_order_id) {
            return redirect()->back()->withErrors(['message' => 'Cannot change status because a PO has already been created for this item.']);
        }

        $validated = $request->validate([
            'is_direct_purchase' => ['required', 'boolean'],
        ]);

        $oldStatus = $prsItem->is_direct_purchase ? 'Direct Purchase' : 'Needs PO';
        $newStatus = $validated['is_direct_purchase'] ? 'Direct Purchase' : 'Needs PO';

        $prsItem->update([
            'is_direct_purchase' => $validated['is_direct_purchase'],
        ]);

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'TOGGLE_DIRECT_PURCHASE',
            'message' => "Status changed from {$oldStatus} to {$newStatus}.",
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'item_code' => $prsItem->item?->code,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'is_direct_purchase' => $validated['is_direct_purchase'],
            ],
        ]);

        return redirect()->back()->with('success', "Item marked as {$newStatus}.");
    }

    private function sanitizeTermValue(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
