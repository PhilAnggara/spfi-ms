<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivingReportController extends Controller
{
    public function index()
    {
        $receivingReports = ReceivingReport::with([
            'purchaseOrder.supplier',
            'purchaseOrder.items.item.unit',
            'items.purchaseOrderItem.item.unit',
            'createdBy',
        ])
            ->orderByDesc('id')
            ->get();

        return view('pages.receiving-reports.index', [
            'receivingReports' => $receivingReports,
        ]);
    }

    public function poByNumber(Request $request)
    {
        $validated = $request->validate([
            'po_number' => ['required', 'string', 'max:50'],
        ]);

        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'items.item.unit',
        ])
            ->where('po_number', $validated['po_number'])
            ->first();

        if (! $purchaseOrder) {
            return response()->json([
                'message' => 'PO number not found.',
            ], 404);
        }

        $receivedMap = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->whereIn('receiving_report_items.purchase_order_item_id', $purchaseOrder->items->pluck('id'))
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        $items = $purchaseOrder->items->map(function ($item) use ($receivedMap) {
            $qtyOrdered = (float) $item->quantity;
            $qtyReceived = (float) ($receivedMap[$item->id] ?? 0);
            $qtyRemaining = max(0, $qtyOrdered - $qtyReceived);

            return [
                'purchase_order_item_id' => $item->id,
                'item_code' => $item->item?->code,
                'item_name' => $item->item?->name,
                'unit_name' => $item->item?->unit?->name ?? 'PCS',
                'qty_ordered' => $qtyOrdered,
                'qty_received' => $qtyReceived,
                'qty_remaining' => $qtyRemaining,
            ];
        })->values();

        return response()->json([
            'purchase_order' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'po_date' => optional($purchaseOrder->created_at)->format('Y-m-d'),
                'status' => $purchaseOrder->status,
                'supplier_name' => $purchaseOrder->supplier?->name,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rr_number' => ['required', 'string', 'max:50', 'unique:receiving_reports,rr_number'],
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.selected' => ['nullable', 'in:0,1'],
            'items.*.qty_good' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_bad' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseOrder = PurchaseOrder::with(['items'])
            ->findOrFail($validated['purchase_order_id']);

        $poItemIds = $purchaseOrder->items->pluck('id')->all();

        $selectedRows = collect($validated['items'])
            ->filter(function ($row) {
                return ($row['selected'] ?? '0') === '1';
            })
            ->values();

        if ($selectedRows->isEmpty()) {
            return redirect()->back()->withErrors([
                'items' => 'Please select at least one item to receive.',
            ])->withInput();
        }

        $receivedMap = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->whereIn('receiving_report_items.purchase_order_item_id', $poItemIds)
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        $poItemsById = $purchaseOrder->items->keyBy('id');

        foreach ($selectedRows as $row) {
            $poItemId = (int) $row['purchase_order_item_id'];

            if (! in_array($poItemId, $poItemIds, true)) {
                return redirect()->back()->withErrors([
                    'items' => 'Invalid PO item selected.',
                ])->withInput();
            }

            $qtyGood = (float) ($row['qty_good'] ?? 0);
            $qtyBad = (float) ($row['qty_bad'] ?? 0);
            $qtyInput = $qtyGood + $qtyBad;

            if ($qtyInput <= 0) {
                return redirect()->back()->withErrors([
                    'items' => 'Qty good/bad must be greater than 0 for selected items.',
                ])->withInput();
            }

            $ordered = (float) ($poItemsById[$poItemId]->quantity ?? 0);
            $received = (float) ($receivedMap[$poItemId] ?? 0);
            $remaining = max(0, $ordered - $received);

            if ($qtyInput > $remaining) {
                return redirect()->back()->withErrors([
                    'items' => 'Input quantity exceeds remaining quantity for one or more items.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $selectedRows, $request) {
            $receivingReport = ReceivingReport::create([
                'rr_number' => $validated['rr_number'],
                'purchase_order_id' => $validated['purchase_order_id'],
                'received_date' => $validated['received_date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($selectedRows as $row) {
                ReceivingReportItem::create([
                    'receiving_report_id' => $receivingReport->id,
                    'purchase_order_item_id' => $row['purchase_order_item_id'],
                    'qty_good' => (float) ($row['qty_good'] ?? 0),
                    'qty_bad' => (float) ($row['qty_bad'] ?? 0),
                ]);
            }

            // Trigger PRS status check for all affected items
            $this->checkPrsDeliveryStatus($receivingReport->purchase_order_id);
        });

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been created.');
    }

    public function update(Request $request, ReceivingReport $receivingReport)
    {
        $validated = $request->validate([
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.selected' => ['nullable', 'in:0,1'],
            'items.*.qty_good' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_bad' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receivingReport->load([
            'purchaseOrder.items',
        ]);

        $poItems = $receivingReport->purchaseOrder->items;
        $poItemIds = $poItems->pluck('id')->all();
        $poItemsById = $poItems->keyBy('id');

        $selectedRows = collect($validated['items'])
            ->filter(function ($row) {
                return ($row['selected'] ?? '0') === '1';
            })
            ->values();

        if ($selectedRows->isEmpty()) {
            return redirect()->back()->withErrors([
                'items' => 'Please select at least one item to receive.',
            ])->withInput();
        }

        $receivedMapExcludingCurrent = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->where('receiving_reports.id', '!=', $receivingReport->id)
            ->whereIn('receiving_report_items.purchase_order_item_id', $poItemIds)
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        foreach ($selectedRows as $row) {
            $poItemId = (int) $row['purchase_order_item_id'];

            if (! in_array($poItemId, $poItemIds, true)) {
                return redirect()->back()->withErrors([
                    'items' => 'Invalid PO item selected.',
                ])->withInput();
            }

            $qtyGood = (float) ($row['qty_good'] ?? 0);
            $qtyBad = (float) ($row['qty_bad'] ?? 0);
            $qtyInput = $qtyGood + $qtyBad;

            if ($qtyInput <= 0) {
                return redirect()->back()->withErrors([
                    'items' => 'Qty good/bad must be greater than 0 for selected items.',
                ])->withInput();
            }

            $ordered = (float) ($poItemsById[$poItemId]->quantity ?? 0);
            $receivedExcludingCurrent = (float) ($receivedMapExcludingCurrent[$poItemId] ?? 0);
            $remaining = max(0, $ordered - $receivedExcludingCurrent);

            if ($qtyInput > $remaining) {
                return redirect()->back()->withErrors([
                    'items' => 'Input quantity exceeds remaining quantity for one or more items.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($receivingReport, $validated, $selectedRows) {
            $receivingReport->update([
                'received_date' => $validated['received_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $receivingReport->items()->delete();

            foreach ($selectedRows as $row) {
                ReceivingReportItem::create([
                    'receiving_report_id' => $receivingReport->id,
                    'purchase_order_item_id' => $row['purchase_order_item_id'],
                    'qty_good' => (float) ($row['qty_good'] ?? 0),
                    'qty_bad' => (float) ($row['qty_bad'] ?? 0),
                ]);
            }

            // Trigger PRS status check for all affected items
            $this->checkPrsDeliveryStatus($receivingReport->purchase_order_id);
        });

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been updated.');
    }

    public function destroy(ReceivingReport $receivingReport)
    {
        $receivingReport->delete();

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been deleted.');
    }

    /**
     * Check and update PRS delivery status for all items related to a PO
     */
    private function checkPrsDeliveryStatus($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.prsItem.prs'])
            ->find($purchaseOrderId);

        if (! $purchaseOrder) {
            return;
        }

        // Collect all unique PRS records from the PO items
        $prsRecords = $purchaseOrder->items
            ->pluck('prsItem.prs')
            ->whereNotNull()
            ->unique('id');

        // Check and update status for each PRS
        foreach ($prsRecords as $prs) {
            $prs->checkAndUpdateDeliveryStatus();
        }
    }
}
