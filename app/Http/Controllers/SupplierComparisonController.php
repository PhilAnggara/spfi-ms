<?php

namespace App\Http\Controllers;

use App\Models\PrsItem;
use App\Services\NotificationRecipientService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SupplierComparisonController extends Controller
{
    use PaginatesLegacySqlServer;

    /**
     * Show supplier comparison per PRS item.
     */
    public function index(Request $request)
    {
        $prsItemId = (int) $request->query('prs_item', 0);

        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'prs_item' => $prsItemId,
        ];

        $prsItemsQuery = PrsItem::with([
            'prs.department',
            'item.unit',
            'canvasser',
            'canvassingItems.supplier',
            'selectedCanvassingItem.supplier',
        ])
            ->whereNull('purchase_order_id')
            ->where('is_direct_purchase', false)
            ->whereHas('canvassingItems')
            ->when($prsItemId > 0, function ($query) use ($prsItemId) {
                $query->whereKey($prsItemId);
            })
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
            ->orderByDesc('id');

        $prsItems = $this->paginateEloquentForCurrentConnection(
            $prsItemsQuery,
            'id DESC',
            10
        );

        return view('pages.procurement.supplier-comparison', [
            'prsItems' => $prsItems,
            'filters' => $filters,
            'highlightPrsItemId' => $prsItemId,
        ]);
    }

    /**
     * Select the supplier quote for a PRS item.
     */
    public function select(Request $request, PrsItem $prsItem)
    {
        if ($prsItem->purchase_order_id) {
            return $this->selectResponse(
                $request,
                'Supplier selection is locked because a PO has been created.',
                ['canvassing_item_id' => 'Supplier selection is locked because a PO has been created.'],
                422
            );
        }

        $validated = $request->validate([
            'canvassing_item_id' => ['required', 'exists:prs_canvassing_items,id'],
            'selection_reason' => ['nullable', 'string'],
        ]);

        $canvassing = $prsItem->canvassingItems()->whereKey($validated['canvassing_item_id'])->first();
        if (! $canvassing) {
            return $this->selectResponse(
                $request,
                'Invalid supplier for this item.',
                ['canvassing_item_id' => 'Invalid supplier for this item.'],
                422
            );
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

        $prsItem->load(['prs', 'item', 'canvasser', 'selectedCanvassingItem.supplier']);

        $supplierName = $prsItem->selectedCanvassingItem?->supplier?->name ?? '-';

        if ($prsItem->canvasser) {
            $prsNumber = (string) ($prsItem->prs?->prs_number ?? $prsItem->prs_id);

            app(NotificationRecipientService::class)->notify(
                collect([$prsItem->canvasser]),
                [
                    'type' => 'supplier_selected',
                    'title' => 'Supplier Selected',
                    'message' => sprintf(
                        'PRS #%s item %s — supplier %s selected. Ready to create PO.',
                        $prsNumber,
                        $prsItem->item?->code ?? $prsItem->id,
                        $supplierName
                    ),
                    'action_url' => '/purchase-orders/draft?keyword='.rawurlencode($prsNumber),
                    'icon' => 'fa-light fa-bag-shopping',
                    'icon_color' => 'bg-success',
                    'meta' => [
                        'prs_id' => $prsItem->prs_id,
                        'prs_item_id' => $prsItem->id,
                        'supplier_id' => $canvassing->supplier_id,
                        'canvassing_item_id' => $canvassing->id,
                        'canvasser_id' => $prsItem->canvasser_id,
                    ],
                ]
            );
        }

        $message = 'Supplier selected for this item.';

        if ($this->wantsAjaxSelectResponse($request)) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'prs_item_id' => $prsItem->id,
                'canvassing_item_id' => $canvassing->id,
                'selected_supplier_name' => $supplierName,
                'selection_reason' => $prsItem->selection_reason,
                'report_url' => route('procurement.supplier-comparison.report', $prsItem),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function selectResponse(Request $request, string $message, array $errors, int $status)
    {
        if ($this->wantsAjaxSelectResponse($request)) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return redirect()->back()->withErrors($errors);
    }

    private function wantsAjaxSelectResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    /**
     * Reject canvassing for a PRS item and send it back for revision.
     */
    public function reject(Request $request, PrsItem $prsItem)
    {
        if (! $request->user()?->can('select-supplier-comparison')) {
            abort(403);
        }

        if ($prsItem->purchase_order_id) {
            return redirect()->back()->withErrors(['message' => 'Canvassing rejection is locked because a PO has been created.']);
        }

        if ($prsItem->canvassingItems()->doesntExist()) {
            return redirect()->back()->withErrors(['message' => 'No canvassing quotes available to reject.']);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $previousSelectedId = $prsItem->selected_canvassing_item_id;
        $previousSupplierId = $prsItem->selectedCanvassingItem?->supplier_id;
        $rejectionReason = trim((string) ($validated['rejection_reason'] ?? ''));
        $rejectionReason = $rejectionReason !== '' ? $rejectionReason : null;

        $prsItem->update([
            'selected_canvassing_item_id' => null,
            'selection_reason' => null,
        ]);

        $prsItem->loadMissing('prs');
        $prsItem->prs?->syncCanvassingPurchaseOrderStatus();

        $prsItem->prs?->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'REJECT_SUPPLIER',
            'message' => 'Canvassing rejected for PRS item. Returned for revision.',
            'meta' => [
                'prs_item_id' => $prsItem->id,
                'previous_canvassing_item_id' => $previousSelectedId,
                'previous_supplier_id' => $previousSupplierId,
                'rejection_reason' => $rejectionReason,
            ],
        ]);

        $prsItem->load(['prs', 'item', 'canvasser']);

        if ($prsItem->canvasser) {
            $prsNumber = (string) ($prsItem->prs?->prs_number ?? $prsItem->prs_id);

            app(NotificationRecipientService::class)->notify(
                collect([$prsItem->canvasser]),
                [
                    'type' => 'supplier_rejected',
                    'title' => 'Canvassing Rejected',
                    'message' => sprintf(
                        'PRS #%s item %s — canvassing rejected%s. Please revise supplier quotes.',
                        $prsNumber,
                        $prsItem->item?->code ?? $prsItem->id,
                        $rejectionReason ? ': '.$rejectionReason : ''
                    ),
                    'action_url' => route('canvassing.show', $prsItem, false),
                    'icon' => 'fa-light fa-rotate-left',
                    'icon_color' => 'bg-warning',
                    'meta' => [
                        'prs_id' => $prsItem->prs_id,
                        'prs_item_id' => $prsItem->id,
                        'canvasser_id' => $prsItem->canvasser_id,
                        'rejection_reason' => $rejectionReason,
                    ],
                ]
            );
        }

        return redirect()->back()->with('success', 'Canvassing rejected. Item returned for revision.');
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

        if (! $prsItem->selected_canvassing_item_id) {
            return redirect()
                ->back()
                ->withErrors(['message' => 'Selection report cannot be generated because no supplier has been selected yet.']);
        }

        $filename = sprintf(
            'supplier-selection-report-%s-%s.pdf',
            $prsItem->item?->code ?? ('item-'.$prsItem->item_id),
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
