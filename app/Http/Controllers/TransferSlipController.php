<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferSlipController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'department' => trim((string) $request->query('department', '')),
            'production' => trim((string) $request->query('production', '')),
            'ts_start' => trim((string) $request->query('ts_start', '')),
            'ts_end' => trim((string) $request->query('ts_end', '')),
        ];

        $transferSlips = $this->paginateTransferSlips($filters, 10);
        $transferSlipIds = $transferSlips->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $transferSlipItems = $this->groupTransferSlipItems($transferSlipIds);

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.transfer-slips.index', [
            'transferSlips' => $transferSlips,
            'transferSlipItems' => $transferSlipItems,
            'departmentOptions' => $departmentOptions,
            'filters' => $filters,
        ]);
    }

    public function swsByNumber(Request $request)
    {
        $validated = $request->validate([
            'sws_number' => ['required', 'string', 'max:50'],
        ]);

        $storeWithdrawal = DB::table('store_withdrawals as sw')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->whereNull('sw.deleted_at')
            ->where('sw.sws_number', $validated['sws_number'])
            ->select([
                'sw.id',
                'sw.sws_number',
                'sw.sws_date',
                'sw.department_code',
                'sw.type',
                'sw.info',
                'd.name as department_name',
            ])
            ->first();

        if (! $storeWithdrawal) {
            return response()->json([
                'message' => 'SWS number not found.',
            ], 404);
        }

        $sourceItems = DB::table('store_withdrawal_items as swi')
            ->leftJoin('items as i', 'i.id', '=', 'swi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->where('swi.store_withdrawal_id', $storeWithdrawal->id)
            ->whereNull('swi.deleted_at')
            ->orderBy('swi.id')
            ->select([
                'swi.id',
                'swi.item_id',
                'swi.product_code',
                'swi.quantity',
                'swi.uom',
                'i.name as item_name',
                'u.name as unit_name',
            ])
            ->get();

        $transferredMap = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereIn('tsi.store_withdrawal_item_id', $sourceItems->pluck('id')->all())
            ->selectRaw('tsi.store_withdrawal_item_id, SUM(tsi.quantity) as transferred_quantity')
            ->groupBy('tsi.store_withdrawal_item_id')
            ->pluck('transferred_quantity', 'tsi.store_withdrawal_item_id');

        $items = $sourceItems->map(function ($item) use ($transferredMap) {
            $transferred = round((float) ($transferredMap[$item->id] ?? 0), 3);
            $sourceQuantity = round((float) $item->quantity, 3);
            $remaining = max(0, round($sourceQuantity - $transferred, 3));

            return [
                'store_withdrawal_item_id' => (int) $item->id,
                'item_id' => (int) $item->item_id,
                'product_code' => $item->product_code,
                'item_name' => $item->item_name,
                'quantity_source' => $sourceQuantity,
                'quantity_transferred' => $transferred,
                'quantity_remaining' => $remaining,
                'uom' => $item->uom ?? $item->unit_name ?? 'PCS',
            ];
        })->values();

        return response()->json([
            'store_withdrawal' => [
                'id' => (int) $storeWithdrawal->id,
                'sws_number' => $storeWithdrawal->sws_number,
                'sws_date' => $storeWithdrawal->sws_date,
                'department_code' => $storeWithdrawal->department_code,
                'department_name' => $storeWithdrawal->department_name,
                'type' => $storeWithdrawal->type,
                'info' => $storeWithdrawal->info,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ts_number' => ['required', 'string', 'max:50', 'unique:transfer_slips,ts_number'],
            'ts_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'for_production' => ['required', 'in:0,1'],
            'sws_number' => ['required', 'string', 'max:50'],
            'store_withdrawal_id' => ['required', 'exists:store_withdrawals,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_withdrawal_item_id' => ['required', 'integer', 'exists:store_withdrawal_items,id'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'store_withdrawal_item_id' => (int) $row['store_withdrawal_item_id'],
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) ($row['quantity'] ?? 0), 3),
                ];
            })
            ->filter(fn (array $row): bool => $row['store_withdrawal_item_id'] > 0 && $row['item_id'] > 0 && $row['quantity'] > 0)
            ->keyBy('store_withdrawal_item_id');

        if ($requestedItems->isEmpty()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one transfer quantity greater than 0.',
            ]);
        }

        $storeWithdrawal = DB::table('store_withdrawals')
            ->where('id', (int) $validated['store_withdrawal_id'])
            ->whereNull('deleted_at')
            ->select(['id', 'sws_number'])
            ->first();

        if (! $storeWithdrawal || $storeWithdrawal->sws_number !== $validated['sws_number']) {
            return redirect()->back()->withInput()->withErrors([
                'sws_number' => 'Selected SWS is no longer valid. Please load the SWS again.',
            ]);
        }

        $sourceItems = DB::table('store_withdrawal_items')
            ->whereIn('id', $requestedItems->keys()->all())
            ->where('store_withdrawal_id', (int) $storeWithdrawal->id)
            ->whereNull('deleted_at')
            ->select(['id', 'store_withdrawal_id', 'item_id', 'product_code', 'quantity', 'uom'])
            ->get()
            ->keyBy('id');

        if ($sourceItems->count() !== $requestedItems->count()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Some SWS items are no longer available. Please reload the SWS data.',
            ]);
        }

        $transferredMap = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereIn('tsi.store_withdrawal_item_id', $requestedItems->keys()->all())
            ->selectRaw('tsi.store_withdrawal_item_id, SUM(tsi.quantity) as transferred_quantity')
            ->groupBy('tsi.store_withdrawal_item_id')
            ->pluck('transferred_quantity', 'tsi.store_withdrawal_item_id');

        foreach ($requestedItems as $storeWithdrawalItemId => $row) {
            $sourceItem = $sourceItems->get($storeWithdrawalItemId);

            if (! $sourceItem || (int) $sourceItem->item_id !== $row['item_id']) {
                return redirect()->back()->withInput()->withErrors([
                    'items' => 'The selected item payload does not match the current SWS detail rows.',
                ]);
            }

            $alreadyTransferred = round((float) ($transferredMap[$storeWithdrawalItemId] ?? 0), 3);
            $remaining = max(0, round(((float) $sourceItem->quantity) - $alreadyTransferred, 3));

            if ($row['quantity'] > $remaining) {
                return redirect()->back()->withInput()->withErrors([
                    'items' => 'Transfer quantity exceeds the remaining quantity for one or more SWS items.',
                ]);
            }
        }

        $authUserId = Auth::id();
        $now = now();

        DB::transaction(function () use ($validated, $requestedItems, $sourceItems, $authUserId, $now): void {
            $transferSlipId = DB::table('transfer_slips')->insertGetId([
                'ts_number' => $validated['ts_number'],
                'ts_date' => $validated['ts_date'],
                'store_withdrawal_id' => (int) $validated['store_withdrawal_id'],
                'for_production' => ((string) $validated['for_production']) === '1',
                'remarks' => $validated['remarks'] ?? null,
                'transfer_to' => null,
                'noted_by' => null,
                'noted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'received_by' => null,
                'received_at' => null,
                'created_by' => $authUserId,
                'updated_by' => $authUserId,
                'meta' => json_encode([
                    'source' => 'transfer-slip-create-modal',
                    'sws_number' => $validated['sws_number'],
                    'item_count' => $requestedItems->count(),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            $detailRows = $requestedItems->map(function (array $row) use ($transferSlipId, $sourceItems, $authUserId, $now): array {
                $sourceItem = $sourceItems->get($row['store_withdrawal_item_id']);

                return [
                    'transfer_slip_id' => (int) $transferSlipId,
                    'store_withdrawal_item_id' => $row['store_withdrawal_item_id'],
                    'item_id' => $row['item_id'],
                    'product_code' => $sourceItem->product_code,
                    'quantity' => $row['quantity'],
                    'created_by' => $authUserId,
                    'updated_by' => $authUserId,
                    'meta' => json_encode([
                        'sws_uom' => $sourceItem->uom,
                        'source_quantity' => round((float) $sourceItem->quantity, 3),
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            })->values()->all();

            DB::table('transfer_slip_items')->insert($detailRows);
        });

        return redirect()
            ->route('transfer-slips.index')
            ->with('success', "Transfer slip {$validated['ts_number']} has been created successfully.");
    }

    public function destroy(string $transferSlip)
    {
        $transferSlipId = (int) $transferSlip;
        $now = now();
        $authUserId = Auth::id();

        $deleted = DB::transaction(function () use ($transferSlipId, $now, $authUserId): int {
            DB::table('transfer_slip_items')
                ->where('transfer_slip_id', $transferSlipId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);

            return DB::table('transfer_slips')
                ->where('id', $transferSlipId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);
        });

        if ($deleted === 0) {
            return redirect()->back()->with('error', 'Transfer slip not found or already deleted.');
        }

        return redirect()->back()->with('success', 'Transfer slip deleted successfully.');
    }

    /**
     * SQL Server-compatible pagination for transfer slips.
     */
    private function paginateTransferSlips(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $department = mb_strtolower(trim((string) ($filters['department'] ?? '')));
        $production = trim((string) ($filters['production'] ?? ''));
        $tsStart = trim((string) ($filters['ts_start'] ?? ''));
        $tsEnd = trim((string) ($filters['ts_end'] ?? ''));

        $keywordLike = "%{$keyword}%";

        $summaryQuery = DB::table('transfer_slip_items')
            ->whereNull('deleted_at')
            ->selectRaw('transfer_slip_id, COUNT(*) as item_count, SUM(quantity) as total_quantity')
            ->groupBy('transfer_slip_id');

        $query = DB::table('transfer_slips as ts')
            ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
            ->leftJoinSub($summaryQuery, 'tsi_summary', function ($join) {
                $join->on('tsi_summary.transfer_slip_id', '=', 'ts.id');
            })
            ->whereNull('ts.deleted_at')
            ->select([
                'ts.id',
                'ts.ts_number',
                'ts.ts_date',
                'ts.for_production',
                'ts.remarks',
                'ts.transfer_to',
                'sw.sws_number',
                'sw.department_code',
                'd.name as department_name',
                'creator.name as created_by_name',
                DB::raw('COALESCE(tsi_summary.item_count, 0) as item_count'),
                DB::raw('COALESCE(tsi_summary.total_quantity, 0) as total_quantity'),
            ])
            ->when($keyword !== '', function ($subQuery) use ($keywordLike) {
                $subQuery->where(function ($whereQuery) use ($keywordLike) {
                    $whereQuery
                        ->whereRaw('LOWER(ts.ts_number) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(sw.sws_number, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(sw.department_code, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(d.name, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(ts.remarks, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(creator.name, \'\')) LIKE ?', [$keywordLike]);
                });
            })
            ->when($department !== '', function ($subQuery) use ($department) {
                $subQuery->whereRaw('LOWER(sw.department_code) = ?', [$department]);
            })
            ->when($production !== '', function ($subQuery) use ($production) {
                $subQuery->where('ts.for_production', $production === '1');
            })
            ->when($tsStart !== '', function ($subQuery) use ($tsStart) {
                $subQuery->whereDate('ts.ts_date', '>=', $tsStart);
            })
            ->when($tsEnd !== '', function ($subQuery) use ($tsEnd) {
                $subQuery->whereDate('ts.ts_date', '<=', $tsEnd);
            })
            ->orderByDesc('ts.id');

        if (! $this->isSqlServer()) {
            return $query
                ->paginate($perPage)
                ->withQueryString();
        }

        $total = (clone $query)->reorder()->count();
        $startRow = (($currentPage - 1) * $perPage) + 1;
        $endRow = $currentPage * $perPage;

        $rankedIdsQuery = (clone $query)
            ->reorder()
            ->select('ts.id')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY ts.id DESC) as row_num');

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_ts')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = DB::table('transfer_slips as ts')
                ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
                ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
                ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
                ->leftJoinSub($summaryQuery, 'tsi_summary', function ($join) {
                    $join->on('tsi_summary.transfer_slip_id', '=', 'ts.id');
                })
                ->whereNull('ts.deleted_at')
                ->whereIn('ts.id', $ids)
                ->select([
                    'ts.id',
                    'ts.ts_number',
                    'ts.ts_date',
                    'ts.for_production',
                    'ts.remarks',
                    'ts.transfer_to',
                    'sw.sws_number',
                    'sw.department_code',
                    'd.name as department_name',
                    'creator.name as created_by_name',
                    DB::raw('COALESCE(tsi_summary.item_count, 0) as item_count'),
                    DB::raw('COALESCE(tsi_summary.total_quantity, 0) as total_quantity'),
                ])
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

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

    /**
     * @param  array<int, int>  $transferSlipIds
     * @return array<int, array<int, object>>
     */
    private function groupTransferSlipItems(array $transferSlipIds): array
    {
        if (empty($transferSlipIds)) {
            return [];
        }

        $rows = DB::table('transfer_slip_items as tsi')
            ->leftJoin('items as i', 'i.id', '=', 'tsi.item_id')
            ->whereIn('tsi.transfer_slip_id', $transferSlipIds)
            ->whereNull('tsi.deleted_at')
            ->orderBy('tsi.transfer_slip_id')
            ->orderBy('tsi.id')
            ->select([
                'tsi.id',
                'tsi.transfer_slip_id',
                'tsi.store_withdrawal_item_id',
                'tsi.item_id',
                'tsi.product_code',
                'tsi.quantity',
                'i.name as item_name',
                'i.code as item_code',
            ])
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $transferSlipId = (int) $row->transfer_slip_id;
            $grouped[$transferSlipId][] = $row;
        }

        return $grouped;
    }
}
