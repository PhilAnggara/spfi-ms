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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * List PO for canvasser/admin.
     */
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'items', 'createdBy'])
            ->orderByDesc('id');

        // Limit non-manager users to only their own PO.
        if (! $request->user()->hasRole('administrator') && ! $request->user()->hasRole('purchasing-manager') && ! $request->user()->hasRole('general-manager')) {
            $query->where('created_by', $request->user()->id);
        }

        $status = $request->query('status');
        if ($status) {
            $query->where('status', $status);
        }

        $purchaseOrders = $query->get();

        return view('pages.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'status' => $status,
        ]);
    }
    /**
     * Draft PO list grouped by supplier for canvasser.
     */
    public function draft(Request $request)
    {
        $userId = $request->user()->id;

        $prsItems = PrsItem::with([
            'prs',
            'item.unit',
            'selectedCanvasingItem.supplier',
        ])
            ->where('canvaser_id', $userId)
            ->whereNull('purchase_order_id')
            ->where('is_direct_purchase', false)
            ->whereNotNull('selected_canvasing_item_id')
            ->orderByDesc('created_at')
            ->get();

        $itemsBySupplier = $prsItems
            ->filter(fn ($item) => $item->selectedCanvasingItem?->supplier_id)
            ->groupBy(fn ($item) => $item->selectedCanvasingItem->supplier_id);

        $suppliers = $itemsBySupplier
            ->map(fn ($items) => $items->first()?->selectedCanvasingItem?->supplier)
            ->filter();

        return view('pages.purchase-orders.draft', [
            'itemsBySupplier' => $itemsBySupplier,
            'suppliers' => $suppliers,
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
        $checkedItemIds = array_column($checkedItems, 'prs_item_id');

        $prsItems = PrsItem::with(['prs', 'item.unit', 'selectedCanvasingItem'])
            ->whereIn('id', $checkedItemIds)
            ->where('canvaser_id', $userId)
            ->whereNull('purchase_order_id')
            ->whereNotNull('selected_canvasing_item_id')
            ->get();

        if ($prsItems->count() !== count($checkedItemIds)) {
            return redirect()->back()->withErrors(['items' => 'One or more PR items are invalid or already assigned.']);
        }

        $invalidSupplierItems = $prsItems->filter(function ($item) use ($validated) {
            return $item->selectedCanvasingItem?->supplier_id !== (int) $validated['supplier_id'];
        });

        if ($invalidSupplierItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'Selected items must belong to the same supplier.']);
        }

        $lineItems = $prsItems->map(function ($item) {
            $unitPrice = $item->selectedCanvasingItem?->unit_price ?? 0;
            $quantity = $item->quantity;
            $lineTotal = $quantity * $unitPrice;

            return [
                'prs_item_id' => $item->id,
                'item_code' => $item->item->code,
                'item_name' => $item->item->name,
                'unit_name' => $item->item->unit?->name ?? 'PCS',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'notes' => $item->selectedCanvasingItem?->notes,
                'line_total' => $lineTotal,
                'prs_number' => $item->prs?->prs_number,
                'discount_rate' => 0,
                'ppn_rate' => 0,
                'pph_rate' => 0,
            ];
        });

        $subtotal = $lineItems->sum('line_total');
        $currencies = Currency::query()->orderBy('id')->get();
        $currencyId = $currencies->first()?->id;

        return view('pages.purchase-orders.preview', [
            'supplier' => Supplier::findOrFail($validated['supplier_id']),
            'lineItems' => $lineItems,
            'subtotal' => $subtotal,
            'currencies' => $currencies,
            'currencyId' => $currencyId,
            'remarkType' => 'Normal',
            'remarkText' => '',
            'fees' => 0,
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
            'fees' => ['nullable', 'numeric', 'min:0'],
            'remark_type' => ['required', 'in:Normal,Confirmatory'],
            'remark_text' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.prs_item_id' => ['required', 'distinct', 'exists:prs_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.pph_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $fees = (float) ($validated['fees'] ?? 0);

        $prsItemIds = collect($validated['items'])->pluck('prs_item_id');

        $prsItems = PrsItem::with(['prs', 'item', 'selectedCanvasingItem'])
            ->whereIn('id', $prsItemIds)
            ->where('canvaser_id', $request->user()->id)
            ->whereNull('purchase_order_id')
            ->whereNotNull('selected_canvasing_item_id')
            ->get();

        if ($prsItems->count() !== count($prsItemIds)) {
            return redirect()->back()->withErrors(['items' => 'One or more PR items are invalid or already assigned.']);
        }

        $invalidSupplierItems = $prsItems->filter(function ($item) use ($validated) {
            return $item->selectedCanvasingItem?->supplier_id !== (int) $validated['supplier_id'];
        });

        if ($invalidSupplierItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'Selected items must belong to the same supplier.']);
        }

        $itemsById = $prsItems->keyBy('id');

        // Atomic create: PO header, items, and PR item marking.
        $purchaseOrder = DB::transaction(function () use ($validated, $itemsById, $fees, $request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'],
                'created_by' => $request->user()->id,
                'status' => $validated['action'] === 'submit' ? 'PENDING_APPROVAL' : 'DRAFT',
                'tax_rate' => 0,
                'fees' => $fees,
                'discount_rate' => 0,
                'ppn_rate' => 0,
                'pph_rate' => 0,
                'remark_type' => $validated['remark_type'],
                'remark_text' => $validated['remark_text'],
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

                $canvasing = $prsItem->selectedCanvasingItem;

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
                        'lead_time_days' => $canvasing?->lead_time_days,
                        'term_of_payment_type' => $canvasing?->term_of_payment_type,
                        'term_of_payment' => $canvasing?->term_of_payment,
                        'term_of_delivery' => $canvasing?->term_of_delivery,
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
            'fees' => ['nullable', 'numeric', 'min:0'],
            'remark_type' => ['required', 'in:Normal,Confirmatory'],
            'remark_text' => ['nullable', 'string', 'max:255'],
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
        $fees = (float) ($validated['fees'] ?? 0);

        DB::transaction(function () use ($validated, $purchaseOrder, $itemsById, $fees) {
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
                ]);
            }

            $purchaseOrder->update([
                'currency_id' => $validated['currency_id'],
                'fees' => $fees,
                'remark_type' => $validated['remark_type'],
                'remark_text' => $validated['remark_text'],
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
}
