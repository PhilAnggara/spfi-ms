<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PoSubmittedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Normalize fee item payload and calculate fee total.
     */
    private function buildFeeBreakdown(array $feeItems): array
    {
        $normalized = collect($feeItems)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $type = trim((string) ($row['type'] ?? ''));
                $amount = (float) ($row['amount'] ?? 0);

                return [
                    'type' => $type,
                    'amount' => max(0, $amount),
                ];
            })
            ->filter(fn (array $row) => $row['type'] !== '' || $row['amount'] > 0)
            ->values()
            ->all();

        $fees = collect($normalized)->sum('amount');

        return [
            'fees' => (float) $fees,
            'fee_breakdown' => $normalized,
        ];
    }

    /**
     * List PO for canvasser/admin.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user->hasAnyRole([
            'administrator',
            'purchasing-manager',
            'general-manager',
        ]);

        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'status' => trim((string) $request->query('status', '')),
            'created_start' => trim((string) $request->query('created_start', '')),
            'created_end' => trim((string) $request->query('created_end', '')),
        ];

        $purchaseOrders = $this->paginatePurchaseOrdersForSqlServer(
            canViewAll: $canViewAll,
            userId: $user->id,
            filters: $filters,
            perPage: 10,
        );

        return view('pages.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'status' => $filters['status'],
            'filters' => $filters,
        ]);
    }

    /**
     * SQL Server-compatible pagination without OFFSET/FETCH for PO list.
     */
    private function paginatePurchaseOrdersForSqlServer(bool $canViewAll, int $userId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $baseQuery = PurchaseOrder::query();

        if (! $canViewAll) {
            $baseQuery->where('created_by', $userId);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $createdStart = trim((string) ($filters['created_start'] ?? ''));
        $createdEnd = trim((string) ($filters['created_end'] ?? ''));

        if ($keyword !== '') {
            $baseQuery->where(function ($query) use ($keyword) {
                $query->where('po_number', 'like', "%{$keyword}%")
                    ->orWhereHas('supplier', function ($supplierQuery) use ($keyword) {
                        $supplierQuery->where('name', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('createdBy', function ($userQuery) use ($keyword) {
                        $userQuery->where('name', 'like', "%{$keyword}%");
                    });

                if (is_numeric($keyword)) {
                    $query->orWhere('id', (int) $keyword);
                }
            });
        }

        if ($status !== '') {
            $baseQuery->where('status', $status);
        }

        if ($createdStart !== '') {
            $baseQuery->whereDate('created_at', '>=', $createdStart);
        }
        if ($createdEnd !== '') {
            $baseQuery->whereDate('created_at', '<=', $createdEnd);
        }

        $total = (clone $baseQuery)->count();
        $startRow = (($currentPage - 1) * $perPage) + 1;
        $endRow = $currentPage * $perPage;

        $rankedIdsQuery = (clone $baseQuery)
            ->selectRaw('id, ROW_NUMBER() OVER (ORDER BY id DESC) as row_num');

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_purchase_orders')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = PurchaseOrder::with(['supplier', 'createdBy'])
                ->withCount('items')
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $collection = collect($ids)
                ->map(fn ($id) => $itemsById->get($id))
                ->filter()
                ->values();
        }

        return new LengthAwarePaginator(
            items: $collection,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }
    /**
     * Draft PO list grouped by supplier for canvasser.
     */
    public function draft(Request $request)
    {
        $userId = $request->user()->id;
        $keyword = trim((string) $request->query('keyword', ''));

        $prsItems = PrsItem::with([
            'prs',
            'item.unit',
            'selectedCanvassingItem.supplier',
        ])
            ->where('canvasser_id', $userId)
            ->whereNull('purchase_order_id')
            ->where('is_direct_purchase', false)
            ->whereNotNull('selected_canvassing_item_id')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($searchQuery) use ($keyword) {
                    $searchQuery
                        ->whereHas('selectedCanvassingItem.supplier', function ($supplierQuery) use ($keyword) {
                            $supplierQuery->where('name', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('item', function ($itemQuery) use ($keyword) {
                            $itemQuery
                                ->where('name', 'like', "%{$keyword}%")
                                ->orWhere('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('prs', function ($prsQuery) use ($keyword) {
                            $prsQuery->where('prs_number', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $itemsBySupplier = $prsItems
            ->filter(fn ($item) => $item->selectedCanvassingItem?->supplier_id)
            ->groupBy(fn ($item) => $item->selectedCanvassingItem->supplier_id);

        $suppliers = $itemsBySupplier
            ->map(fn ($items) => $items->first()?->selectedCanvassingItem?->supplier)
            ->filter();

        return view('pages.purchase-orders.draft', [
            'itemsBySupplier' => $itemsBySupplier,
            'suppliers' => $suppliers,
            'keyword' => $keyword,
        ]);
    }

    /**
     * Preview selected items before creating PO.
     * Filter items based on checked status (items[*][checked] = "1").
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array'],
            'items.*' => ['required', 'array'],
            'items.*.prs_item_id' => ['required', 'exists:prs_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.checked' => ['required', 'in:0,1'],
        ]);

        // Filter only checked items
        $checkedItems = array_filter($validated['items'], fn ($item) => $item['checked'] === '1');
        if (empty($checkedItems)) {
            return redirect()->back()->withErrors(['items' => 'Please select at least one item.']);
        }

        $userId = $request->user()->id;
        $checkedItemIds = array_map('intval', array_column($checkedItems, 'prs_item_id'));

        $prsItems = PrsItem::with(['prs', 'item.unit', 'selectedCanvassingItem'])
            ->whereIn('id', $checkedItemIds)
            ->where('canvasser_id', $userId)
            ->whereNull('purchase_order_id')
            ->whereNotNull('selected_canvassing_item_id')
            ->get()
            ->sortBy(fn (PrsItem $item) => array_search($item->id, $checkedItemIds, true))
            ->values();

        if ($prsItems->count() !== count($checkedItemIds)) {
            return redirect()->back()->withErrors(['items' => 'One or more PR items are invalid or already assigned.']);
        }

        if ($prsItems->contains(fn ($item) => ! $item->item)) {
            return redirect()->back()->withErrors(['items' => 'One or more selected PR items no longer have item master data.']);
        }

        $invalidSupplierItems = $prsItems->filter(function ($item) use ($validated) {
            return $item->selectedCanvassingItem?->supplier_id !== (int) $validated['supplier_id'];
        });

        if ($invalidSupplierItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'Selected items must belong to the same supplier.']);
        }

        if ($this->containsMixedCapexStatus($prsItems)) {
            return redirect()->back()->withErrors(['items' => 'Selected items cannot mix CAPEX and Non-CAPEX in one purchase order.']);
        }

        $lineItems = $prsItems->map(function ($item) {
            $unitPrice = $item->selectedCanvassingItem?->unit_price ?? 0;
            $quantity = $item->quantity;
            $lineTotal = $quantity * $unitPrice;

            return [
                'prs_item_id' => $item->id,
                'item_code' => $item->item->code,
                'item_name' => $item->item->name,
                'unit_name' => $item->item->unit?->name ?? 'PCS',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'notes' => $item->selectedCanvassingItem?->notes,
                'line_total' => $lineTotal,
                'prs_number' => $item->prs?->prs_number,
                'is_capex' => $this->isCapexPrsItem($item),
                'discount_rate' => 0,
                'ppn_rate' => 0,
                'pph_rate' => 0,
            ];
        });

        $subtotal = $lineItems->sum('line_total');
        $currencies = Currency::query()->orderBy('id')->get();
        $currencyId = $currencies->first()?->id;
        $defaultTerms = $this->defaultTermsFromPrsItems($prsItems);

        return view('pages.purchase-orders.preview', [
            'supplier' => Supplier::findOrFail($validated['supplier_id']),
            'lineItems' => $lineItems,
            'subtotal' => $subtotal,
            'currencies' => $currencies,
            'currencyId' => $currencyId,
            'remarkType' => 'Normal',
            'remarkText' => '',
            'termOfPaymentType' => $defaultTerms['term_of_payment_type'],
            'termOfPayment' => $defaultTerms['term_of_payment'],
            'termOfDelivery' => $defaultTerms['term_of_delivery'],
            'feeItems' => [],
        ]);
    }

    /**
     * Store a PO as draft or submit for approval.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'action' => ['required', 'in:draft,submit'],
            'fee_items' => ['nullable', 'array'],
            'fee_items.*.type' => ['nullable', 'string', 'max:100'],
            'fee_items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'remark_type' => ['required', 'in:Normal,Confirmatory'],
            'remark_text' => ['nullable', 'string', 'max:255'],
            'term_of_payment_type' => ['required', 'in:cash,credit'],
            'term_of_payment' => ['nullable', 'string', 'max:255'],
            'term_of_delivery' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.prs_item_id' => ['required', 'distinct', 'exists:prs_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.pph_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $feePayload = $this->buildFeeBreakdown($validated['fee_items'] ?? []);
        $fees = $feePayload['fees'];
        $feesBreakdown = $feePayload['fee_breakdown'];

        $prsItemIds = collect($validated['items'])->pluck('prs_item_id');

        $prsItems = PrsItem::with(['prs', 'item', 'selectedCanvassingItem'])
            ->whereIn('id', $prsItemIds)
            ->where('canvasser_id', $request->user()->id)
            ->whereNull('purchase_order_id')
            ->whereNotNull('selected_canvassing_item_id')
            ->get();

        if ($prsItems->count() !== count($prsItemIds)) {
            return redirect()->back()->withErrors(['items' => 'One or more PR items are invalid or already assigned.']);
        }

        if ($prsItems->contains(fn ($item) => ! $item->item)) {
            return redirect()->back()->withErrors(['items' => 'One or more selected PR items no longer have item master data.']);
        }

        $invalidSupplierItems = $prsItems->filter(function ($item) use ($validated) {
            return $item->selectedCanvassingItem?->supplier_id !== (int) $validated['supplier_id'];
        });

        if ($invalidSupplierItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'Selected items must belong to the same supplier.']);
        }

        if ($this->containsMixedCapexStatus($prsItems)) {
            return redirect()->back()->withErrors(['items' => 'Selected items cannot mix CAPEX and Non-CAPEX in one purchase order.']);
        }

        $itemsById = $prsItems->keyBy('id');

        // Atomic create: PO header, items, and PR item marking.
        $purchaseOrder = DB::transaction(function () use ($validated, $itemsById, $fees, $feesBreakdown, $request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'],
                'created_by' => $request->user()->id,
                'status' => $validated['action'] === 'submit' ? 'PENDING_APPROVAL' : 'DRAFT',
                'tax_rate' => 0,
                'fees' => $fees,
                'fees_breakdown' => $feesBreakdown,
                'discount_rate' => 0,
                'ppn_rate' => 0,
                'pph_rate' => 0,
                'remark_type' => $validated['remark_type'],
                'remark_text' => $validated['remark_text'],
                'term_of_payment_type' => $validated['term_of_payment_type'],
                'term_of_payment' => $validated['term_of_payment'],
                'term_of_delivery' => $validated['term_of_delivery'] ?? null,
                'submitted_at' => $validated['action'] === 'submit' ? now() : null,
            ]);

            $subtotal = 0;
            $discountTotal = 0;
            $ppnTotal = 0;
            $pphTotal = 0;
            $itemsTotal = 0;

            foreach ($validated['items'] as $row) {
                $prsItem = $itemsById->get($row['prs_item_id']);
                $lineSubtotal = $row['quantity'] * $row['unit_price'];
                $discountRate = (float) ($row['discount_rate'] ?? 0);
                $ppnRate = (float) ($row['ppn_rate'] ?? 0);
                $pphRate = (float) ($row['pph_rate'] ?? 0);
                $discountAmount = $lineSubtotal * ($discountRate / 100);
                $baseAmount = $lineSubtotal - $discountAmount;
                $ppnAmount = $baseAmount * ($ppnRate / 100);
                $pphAmount = $baseAmount * ($pphRate / 100);
                $lineTotal = $baseAmount + $ppnAmount - $pphAmount;

                $subtotal += $lineSubtotal;
                $discountTotal += $discountAmount;
                $ppnTotal += $ppnAmount;
                $pphTotal += $pphAmount;
                $itemsTotal += $lineTotal;

                $canvassing = $prsItem->selectedCanvassingItem;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'prs_item_id' => $prsItem->id,
                    'item_id' => $prsItem->item_id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_subtotal' => $lineSubtotal,
                    'discount_rate' => $discountRate,
                    'discount_amount' => $discountAmount,
                    'ppn_rate' => $ppnRate,
                    'ppn_amount' => $ppnAmount,
                    'pph_rate' => $pphRate,
                    'pph_amount' => $pphAmount,
                    'total' => $lineTotal,
                    'notes' => $row['notes'] ?? null,
                    'meta' => [
                        'prs_id' => $prsItem->prs_id,
                        'prs_number' => $prsItem->prs?->prs_number,
                        'is_capex' => $this->isCapexPrsItem($prsItem),
                        'lead_time_days' => $canvassing?->lead_time_days,
                        'term_of_payment_type' => $validated['term_of_payment_type'],
                        'term_of_payment' => $validated['term_of_payment'],
                        'term_of_delivery' => $validated['term_of_delivery'] ?? $canvassing?->term_of_delivery,
                    ],
                ]);
            }

            $total = $itemsTotal + $fees;

            $purchaseOrder->update([
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'discount_amount' => $discountTotal,
                'ppn_amount' => $ppnTotal,
                'pph_amount' => $pphTotal,
                'total' => $total,
            ]);

            // Mark PR items so they won't reappear in draft list.
            PrsItem::whereIn('id', $itemsById->keys()->all())
                ->update(['purchase_order_id' => $purchaseOrder->id]);

            $affectedPrsIds = $itemsById
                ->pluck('prs_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($affectedPrsIds)) {
                DB::table('prs')
                    ->whereIn('id', $affectedPrsIds)
                    ->update([
                        'status' => 'PO_CREATED',
                        'updated_at' => now(),
                    ]);
            }

            return $purchaseOrder;
        });

        if ($validated['action'] === 'submit') {
            $purchasingManagers = User::role('purchasing-manager')->get();

            if ($purchasingManagers->isEmpty()) {
                $purchasingManagers = User::permission('approve-po')->get();
            }

            foreach ($purchasingManagers as $manager) {
                $manager->notify(new PoSubmittedNotification($purchaseOrder));
            }
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order has been created.');
    }

    /**
     * Submit a draft PO for approval.
     */
    public function submit(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['DRAFT', 'CHANGES_REQUESTED'], true)) {
            return redirect()->back()->withErrors(['message' => 'Only draft PO can be submitted.']);
        }

        if ($purchaseOrder->created_by !== $request->user()->id && ! $request->user()->hasRole('administrator')) {
            abort(403);
        }

        $purchaseOrder->update([
            'status' => 'PENDING_APPROVAL',
            'submitted_at' => now(),
        ]);

        $purchasingManagers = User::role('purchasing-manager')->get();
        if ($purchasingManagers->isEmpty()) {
            $purchasingManagers = User::permission('approve-po')->get();
        }

        foreach ($purchasingManagers as $manager) {
            $manager->notify(new PoSubmittedNotification($purchaseOrder));
        }

        return redirect()->back()->with('success', 'Purchase order submitted for approval.');
    }

    /**
     * Show PO detail.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'createdBy',
        ]);

        return view('pages.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    /**
     * Update PO details when changes are requested.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['DRAFT', 'CHANGES_REQUESTED'], true)) {
            return redirect()->back()->withErrors(['message' => 'Only draft PO can be updated.']);
        }

        if ($purchaseOrder->created_by !== $request->user()->id && ! $request->user()->hasRole('administrator')) {
            abort(403);
        }

        $validated = $request->validate([
            'currency_id' => ['required', 'exists:currencies,id'],
            'fee_items' => ['nullable', 'array'],
            'fee_items.*.type' => ['nullable', 'string', 'max:100'],
            'fee_items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'remark_type' => ['required', 'in:Normal,Confirmatory'],
            'remark_text' => ['nullable', 'string', 'max:255'],
            'term_of_payment_type' => ['required', 'in:cash,credit'],
            'term_of_payment' => ['nullable', 'string', 'max:255'],
            'term_of_delivery' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.pph_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $itemIds = collect($validated['items'])->pluck('id')->values();
        $poItems = $purchaseOrder->items()->whereIn('id', $itemIds)->get();

        if ($poItems->count() !== $itemIds->count()) {
            return redirect()->back()->withErrors(['items' => 'Invalid PO items submitted.']);
        }

        $itemsById = $poItems->keyBy('id');
        $feePayload = $this->buildFeeBreakdown($validated['fee_items'] ?? []);
        $fees = $feePayload['fees'];
        $feesBreakdown = $feePayload['fee_breakdown'];

        DB::transaction(function () use ($validated, $purchaseOrder, $itemsById, $fees, $feesBreakdown) {
            $subtotal = 0;
            $discountTotal = 0;
            $ppnTotal = 0;
            $pphTotal = 0;
            $itemsTotal = 0;

            foreach ($validated['items'] as $row) {
                $poItem = $itemsById->get($row['id']);
                $lineSubtotal = $row['quantity'] * $row['unit_price'];
                $discountRate = (float) ($row['discount_rate'] ?? 0);
                $ppnRate = (float) ($row['ppn_rate'] ?? 0);
                $pphRate = (float) ($row['pph_rate'] ?? 0);
                $discountAmount = $lineSubtotal * ($discountRate / 100);
                $baseAmount = $lineSubtotal - $discountAmount;
                $ppnAmount = $baseAmount * ($ppnRate / 100);
                $pphAmount = $baseAmount * ($pphRate / 100);
                $lineTotal = $baseAmount + $ppnAmount - $pphAmount;

                $subtotal += $lineSubtotal;
                $discountTotal += $discountAmount;
                $ppnTotal += $ppnAmount;
                $pphTotal += $pphAmount;
                $itemsTotal += $lineTotal;

                $poItem->update([
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_subtotal' => $lineSubtotal,
                    'discount_rate' => $discountRate,
                    'discount_amount' => $discountAmount,
                    'ppn_rate' => $ppnRate,
                    'ppn_amount' => $ppnAmount,
                    'pph_rate' => $pphRate,
                    'pph_amount' => $pphAmount,
                    'total' => $lineTotal,
                    'notes' => $row['notes'] ?? null,
                    'meta' => $this->syncPoItemTermMeta($poItem->meta ?? [], $validated),
                ]);
            }

            $purchaseOrder->update([
                'currency_id' => $validated['currency_id'],
                'fees' => $fees,
                'fees_breakdown' => $feesBreakdown,
                'remark_type' => $validated['remark_type'],
                'remark_text' => $validated['remark_text'],
                'term_of_payment_type' => $validated['term_of_payment_type'],
                'term_of_payment' => $validated['term_of_payment'],
                'term_of_delivery' => $validated['term_of_delivery'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'discount_amount' => $discountTotal,
                'ppn_amount' => $ppnTotal,
                'pph_amount' => $pphTotal,
                'total' => $itemsTotal + $fees,
            ]);
        });

        return redirect()->back()->with('success', 'Purchase order updated.');
    }

    /**
     * Update PO number before print.
     */
    public function updateNumber(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'po_number' => ['required', 'string', 'max:50'],
        ]);

        $purchaseOrder->update([
            'po_number' => $validated['po_number'],
        ]);

        return redirect()->back()->with('success', 'PO number updated.');
    }

    /**
     * Print approved PO.
     */
    public function print(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'createdBy',
            'certifiedBy',
            'approvedBy',
        ]);

        if ($purchaseOrder->status !== 'APPROVED') {
            return redirect()->back()->withErrors(['message' => 'PO must be approved before printing.']);
        }

        if ($request->filled('po_number')) {
            $validated = $request->validate([
                'po_number' => ['required', 'string', 'max:50'],
            ]);

            $purchaseOrder->update([
                'po_number' => $validated['po_number'],
            ]);
        }

        if (! $purchaseOrder->po_number) {
            return redirect()->back()->withErrors(['message' => 'PO number is required before printing.']);
        }

        $data = [
            'purchaseOrder' => $purchaseOrder,
        ];

        return Pdf::loadView('pdf.purchase-order', $data)
            ->setPaper('a4', 'portrait')
            ->stream('PO-' . $purchaseOrder->po_number . '.pdf');
    }

    private function isCapexPrsItem(?PrsItem $prsItem): bool
    {
        return (bool) ($prsItem?->prs?->is_capex ?? false);
    }

    private function containsMixedCapexStatus($prsItems): bool
    {
        return $prsItems
            ->map(fn (PrsItem $prsItem) => $this->isCapexPrsItem($prsItem))
            ->unique()
            ->count() > 1;
    }

    private function defaultTermsFromPrsItems($prsItems): array
    {
        $selectedCanvassing = $prsItems
            ->pluck('selectedCanvassingItem')
            ->filter()
            ->first(function ($canvassing) {
                return filled($canvassing?->term_of_payment_type) || filled($canvassing?->term_of_payment);
            });

        if (! $selectedCanvassing) {
            $selectedCanvassing = $prsItems
                ->pluck('selectedCanvassingItem')
                ->filter()
                ->first();
        }

        return [
            'term_of_payment_type' => strtolower(trim((string) ($selectedCanvassing?->term_of_payment_type ?? ''))),
            'term_of_payment' => $selectedCanvassing?->term_of_payment,
            'term_of_delivery' => $selectedCanvassing?->term_of_delivery,
        ];
    }

    private function syncPoItemTermMeta(array $meta, array $validated): array
    {
        $meta['term_of_payment_type'] = $validated['term_of_payment_type'];
        $meta['term_of_payment'] = $validated['term_of_payment'];
        $meta['term_of_delivery'] = $validated['term_of_delivery'] ?? ($meta['term_of_delivery'] ?? null);

        return $meta;
    }
}
