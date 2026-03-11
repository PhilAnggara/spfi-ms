<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
