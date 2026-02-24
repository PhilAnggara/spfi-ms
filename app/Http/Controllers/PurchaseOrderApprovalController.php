<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;

class PurchaseOrderApprovalController extends Controller
{
    /**
     * List PO for purchasing manager (including approved).
     */
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'items', 'createdBy'])
            ->whereIn('status', ['PENDING_APPROVAL', 'CHANGES_REQUESTED', 'APPROVED'])
            ->orderByDesc('id')
            ->get();

        return view('pages.purchase-orders.approval', [
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Approve PO.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            return redirect()->back()->withErrors(['message' => 'Only pending PO can be approved.']);
        }

        $manager = $request->user();
        $gm = User::where('role', 'General Manager')->first();

        // Approval routing follows total threshold rule.
        $approvedBy = $purchaseOrder->total > 4000000 && $gm ? $gm : $manager;
        $certifiedBy = $manager;

        $purchaseOrder->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'certified_by_user_id' => $certifiedBy->id,
            'approved_by_user_id' => $approvedBy->id,
            'approval_notes' => null,
            'signature_meta' => [
                'certified_by' => [
                    'user_id' => $certifiedBy->id,
                    'name' => $certifiedBy->name,
                    'title' => get_job_title($certifiedBy),
                ],
                'approved_by' => [
                    'user_id' => $approvedBy->id,
                    'name' => $approvedBy->name,
                    'title' => get_job_title($approvedBy),
                ],
                'rules' => [
                    'threshold' => 4000000,
                    'currency' => 'IDR',
                ],
            ],
        ]);

        $purchaseOrder->load(['items.prsItem.prs']);
        $prsById = $purchaseOrder->items
            ->map(fn ($item) => $item->prsItem?->prs)
            ->filter()
            ->unique('id');

        foreach ($prsById as $prs) {
            $previousStatus = $prs->status;
            $prs->update(['status' => 'APPROVED']);
            $prs->logs()->create([
                'user_id' => $request->user()?->id,
                'action' => 'APPROVED',
                'message' => 'PO approved for this PRS.',
                'meta' => [
                    'previous_status' => $previousStatus,
                    'purchase_order_id' => $purchaseOrder->id,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Purchase order approved.');
    }

    /**
     * Request changes for PO.
     */
    public function requestChanges(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $purchaseOrder->update([
            'status' => 'CHANGES_REQUESTED',
            'approval_notes' => $validated['message'],
        ]);

        return redirect()->back()->with('success', 'Changes requested for purchase order.');
    }
}
