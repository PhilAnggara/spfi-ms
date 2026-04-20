<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use App\Models\User;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PrsApprovalController extends Controller
{
    /**
     * Display list of PRS pending approval
     */
    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'status' => trim((string) $request->query('status', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $items = $this->paginatePrsForApproval($filters, perPage: 20);
        $canvassers = User::role('purchasing-staff')->orderBy('name')->get();

        $statusOptions = [
            'SUBMITTED' => 'SUBMITTED',
            'RESUBMITTED' => 'RESUBMITTED',
            'ON_HOLD' => 'ON_HOLD',
            'CANVASSING' => 'CANVASSING',
            'APPROVED' => 'APPROVED',
            'DELIVERY_COMPLETE' => 'DELIVERY_COMPLETE',
            'REJECTED' => 'REJECTED',
            'DRAFT' => 'DRAFT',
        ];

        return view('pages.prs-approval', [
            'items' => $items,
            'canvassers' => $canvassers,
            'filters' => $filters,
            'statusOptions' => $statusOptions,
        ]);
    }

    /**
     * Hold a PRS with a reason.
     */
    public function hold(Request $request, Prs $prs)
    {
        if ($prs->status === 'ON_HOLD') {
            return redirect()->back()->withErrors(['message' => 'PRS is already on hold.']);
        }
        if ($prs->status === 'APPROVED') {
            return redirect()->back()->withErrors(['message' => 'Approved PRS cannot be held.']);
        }

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $previousStatus = $prs->status;
        $prs->status = 'ON_HOLD';
        $prs->save();

        $prs->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'HOLD',
            'message' => $data['message'],
            'meta' => [
                'previous_status' => $previousStatus,
            ],
        ]);

        $recipients = app(NotificationRecipientService::class)->uniqueUsers(
            collect([$prs->user])->filter(),
            app(NotificationRecipientService::class)->purchasingManagers()
        );

        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'prs_on_hold',
            'title' => 'PRS On Hold',
            'message' => 'PRS #'.$prs->prs_number.' is on hold.',
            'action_url' => '/procurement/approval/'.$prs->id,
            'icon' => 'fa-light fa-circle-pause',
            'icon_color' => 'bg-warning',
            'meta' => [
                'prs_id' => $prs->id,
                'previous_status' => $previousStatus,
            ],
        ]);

        return redirect()->back()->with('success', 'PRS has been put on hold.');
    }

    /**
     * Approve and assign a canvasser.
     */
    public function approve(Request $request, Prs $prs)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.prs_item_id' => ['required', 'distinct', 'exists:prs_items,id'],
            'items.*.canvasser_id' => ['required', 'exists:users,id'],
        ]);

        $assignments = collect($data['items'])->keyBy('prs_item_id');
        $assignedPrsItemIds = $prs->items()->pluck('id')->all();
        $invalidPrsItems = $assignments->keys()->diff($assignedPrsItemIds);
        if ($invalidPrsItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'One or more PRS items are invalid for this PRS.']);
        }

        $canvasserIds = $assignments->pluck('canvasser_id')->unique()->values();
        $validCanvasserIds = User::role('purchasing-staff')->whereIn('id', $canvasserIds)->pluck('id');
        $invalidCanvassers = $canvasserIds->diff($validCanvasserIds);
        if ($invalidCanvassers->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'One or more selected users are not canvassers.']);
        }

        $previousStatus = $prs->status;

        DB::transaction(function () use ($prs, $assignments, $previousStatus, $request) {
            foreach ($assignments as $prsItemId => $row) {
                $prs->items()->whereKey($prsItemId)->update([
                    'canvasser_id' => $row['canvasser_id'],
                ]);
            }

            $prs->status = 'CANVASSING';
            $prs->save();

            $prs->logs()->create([
                'user_id' => $request->user()?->id,
                'action' => 'CANVASSING',
                'message' => 'Approved and assigned canvassers per item.',
                'meta' => [
                    'previous_status' => $previousStatus,
                    'assignments' => $assignments->values()->all(),
                ],
            ]);
        });

        $assignedCanvassers = User::whereIn('id', $assignments->pluck('canvasser_id')->unique()->all())->get();
        $recipients = app(NotificationRecipientService::class)->uniqueUsers(
            collect([$prs->user])->filter(),
            $assignedCanvassers
        );

        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'prs_approved_canvassing',
            'title' => 'PRS Approved',
            'message' => 'PRS #'.$prs->prs_number.' has been approved and moved to canvassing.',
            'action_url' => '/procurement/approval/'.$prs->id,
            'icon' => 'fa-light fa-badge-check',
            'icon_color' => 'bg-success',
            'meta' => [
                'prs_id' => $prs->id,
                'previous_status' => $previousStatus,
            ],
        ]);

        return redirect()->back()->with('success', 'PRS has been approved and assigned.');
    }

    /**
     * SQL Server-safe pagination with filters for canvasser assignment.
     */
    private function paginatePrsForApproval(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $currentPage = max(1, (int) LengthAwarePaginator::resolveCurrentPage());

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $keywordLike = "%{$keyword}%";

        $query = Prs::query()
            ->when($keyword !== '', function ($subQuery) use ($keywordLike) {
                $subQuery->where(function ($whereQuery) use ($keywordLike) {
                    $whereQuery->whereRaw("LOWER(COALESCE(prs_number, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(remarks, '')) LIKE ?", [$keywordLike])
                        ->orWhereHas('department', function ($departmentQuery) use ($keywordLike) {
                            $departmentQuery->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$keywordLike]);
                        })
                        ->orWhereHas('user', function ($userQuery) use ($keywordLike) {
                            $userQuery->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$keywordLike]);
                        });
                });
            })
            ->when($status !== '', function ($subQuery) use ($status) {
                $subQuery->where('status', $status);
            })
            ->when($dateFrom !== '', function ($subQuery) use ($dateFrom) {
                $subQuery->whereDate('prs_date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($subQuery) use ($dateTo) {
                $subQuery->whereDate('prs_date', '<=', $dateTo);
            })
            ->orderByDesc('prs_date')
            ->orderByDesc('id');

        $total = (clone $query)->reorder()->count();
        $ids = [];

        if ($this->isSqlServer()) {
            $startRow = (($currentPage - 1) * $perPage) + 1;
            $endRow = $currentPage * $perPage;

            $rankedIdsQuery = (clone $query)
                ->reorder()
                ->selectRaw('id, ROW_NUMBER() OVER (ORDER BY prs_date DESC, id DESC) as row_num');

            $ids = DB::query()
                ->fromSub($rankedIdsQuery, 'ranked_prs')
                ->whereBetween('row_num', [$startRow, $endRow])
                ->orderBy('row_num')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $ids = (clone $query)
                ->forPage($currentPage, $perPage)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = Prs::with([
                'department',
                'user',
                'items.item',
                'items.canvasser',
                'items.canvassingItems',
                'items.selectedCanvassingItem',
                'items.purchaseOrderItem.receivingReportItems',
                'logs' => function ($query) {
                    $query->latest();
                },
            ])->whereIn('id', $ids)->get()->keyBy('id');

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

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
}
