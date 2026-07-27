<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderApprovalController extends Controller
{
    /**
     * List PO for purchasing manager (including approved).
     */
    public function index(Request $request)
    {
        $allowedStatuses = ['PENDING_APPROVAL', 'CHANGES_REQUESTED', 'APPROVED'];
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'status' => strtoupper(trim((string) $request->query('status', ''))),
            'created_start' => trim((string) $request->query('created_start', '')),
            'created_end' => trim((string) $request->query('created_end', '')),
        ];

        $purchaseOrders = $this->paginateApprovalPurchaseOrdersForSqlServer(
            filters: $filters,
            allowedStatuses: $allowedStatuses,
            perPage: 10,
        );

        return view('pages.purchase-orders.approval', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $filters,
            'allowedStatuses' => $allowedStatuses,
        ]);
    }

    /**
     * SQL Server-compatible pagination for PO approval list.
     */
    private function paginateApprovalPurchaseOrdersForSqlServer(array $filters, array $allowedStatuses, int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $baseQuery = PurchaseOrder::query()
            ->whereIn('status', $allowedStatuses);

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

        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
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
            ->fromSub($rankedIdsQuery, 'ranked_approval_purchase_orders')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = PurchaseOrder::with([
                'supplier',
                'createdBy',
                'currency',
                'items.item.unit',
                'items.prsItem.prs.department',
            ])
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
     * Approve PO.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'PENDING_APPROVAL') {
            return redirect()->back()->withErrors(['message' => 'Only pending PO can be approved.']);
        }

        $manager = $request->user();
        $gm = User::where('role', 'General Manager')->first();
        $approvalThreshold = (float) config('purchase-order.signature.approval_threshold', 4000000);
        $certifiedName = (string) config('purchase-order.signature.certified_by_name', 'Denny Tuhatelu');
        $approvedName = (float) $purchaseOrder->total >= $approvalThreshold
            ? (string) config('purchase-order.signature.approved_by_at_or_above_threshold_name', 'Sam Calamba')
            : (string) config('purchase-order.signature.approved_by_below_threshold_name', 'Denny Tuhatelu');

        // Approval routing follows total threshold rule.
        $approvedBy = (float) $purchaseOrder->total >= $approvalThreshold && $gm ? $gm : $manager;
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
                    'name' => $certifiedName,
                    'title' => get_job_title($certifiedBy),
                ],
                'approved_by' => [
                    'user_id' => $approvedBy->id,
                    'name' => $approvedName,
                    'title' => get_job_title($approvedBy),
                ],
                'rules' => [
                    'threshold' => $approvalThreshold,
                    'currency' => config('purchase-order.signature.threshold_currency', 'IDR'),
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
            $prs->syncCanvassingPurchaseOrderStatus();
            $prs->refresh();

            $prs->logs()->create([
                'user_id' => $request->user()?->id,
                'action' => 'PO_CREATED',
                'message' => 'Purchase order has been created for this PRS.',
                'meta' => [
                    'previous_status' => $previousStatus,
                    'purchase_order_id' => $purchaseOrder->id,
                    'status' => $prs->status,
                ],
            ]);
        }

        $recipients = app(NotificationRecipientService::class)->relatedPurchaseOrderUsers($purchaseOrder);
        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'po_approved',
            'title' => 'Purchase Order Approved',
            'message' => 'PO #'.($purchaseOrder->po_number ?: $purchaseOrder->id).' has been approved.',
            'action_url' => '/purchase-orders/'.$purchaseOrder->id,
            'icon' => 'fa-light fa-file-circle-check',
            'icon_color' => 'bg-success',
            'meta' => [
                'purchase_order_id' => $purchaseOrder->id,
            ],
        ]);

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

        $recipients = app(NotificationRecipientService::class)->relatedPurchaseOrderUsers($purchaseOrder);
        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'po_changes_requested',
            'title' => 'PO Needs Changes',
            'message' => 'PO #'.($purchaseOrder->po_number ?: $purchaseOrder->id).' needs changes from approver.',
            'action_url' => '/purchase-orders/'.$purchaseOrder->id,
            'icon' => 'fa-light fa-file-pen',
            'icon_color' => 'bg-warning',
            'meta' => [
                'purchase_order_id' => $purchaseOrder->id,
                'approval_notes' => $validated['message'],
            ],
        ]);

        return redirect()->back()->with('success', 'Changes requested for purchase order.');
    }
}
