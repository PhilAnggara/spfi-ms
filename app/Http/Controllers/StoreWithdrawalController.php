<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreWithdrawalController extends Controller
{
    /**
     * Display stores withdrawal list.
     */
    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'department' => trim((string) $request->query('department', '')),
            'sws_start' => trim((string) $request->query('sws_start', '')),
            'sws_end' => trim((string) $request->query('sws_end', '')),
        ];

        $storeWithdrawals = $this->paginateStoreWithdrawals($filters, 10);
        $storeWithdrawalIds = $storeWithdrawals->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $storeWithdrawalItems = $this->groupStoreWithdrawalItems($storeWithdrawalIds);

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.stores-withdrawals.index', [
            'storeWithdrawals' => $storeWithdrawals,
            'storeWithdrawalItems' => $storeWithdrawalItems,
            'departmentOptions' => $departmentOptions,
            'filters' => $filters,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $departments = Department::all();
        $categories = ItemCategory::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $search = trim((string) $request->query('search'));
        $categoryId = trim((string) $request->query('category'));
        $searchTerms = collect(preg_split('/\s+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->values();

        $itemsQuery = Item::with(['unit', 'category'])
            ->select(['id', 'name', 'code', 'stock_on_hand', 'unit_of_measure_id', 'category_id'])
            ->where('is_active', true)
            ->when($searchTerms->isNotEmpty(), function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->where(function ($subQuery) use ($term) {
                        $subQuery
                            ->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                            ->orWhereRaw('LOWER(code) LIKE ?', ['%' . $term . '%']);
                    });
                }
            })
            ->when($categoryId !== '' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->where('category_id', (int) $categoryId);
            })
            ->orderBy('name');

        $items = $itemsQuery
            ->paginate(36)
            ->withQueryString();

        if ($request->expectsJson() || $request->ajax()) {
            $transformedItems = $items->getCollection()->map(function ($item) {
                $categoryName = $item->category?->name ?? 'Uncategorized';

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'stock_on_hand' => (float) $item->stock_on_hand,
                    'unit' => $item->unit?->name ?? 'PCS',
                    'category' => $categoryName,
                    'category_icon' => category_icon($categoryName),
                    'category_data' => category_data_attr($categoryName),
                ];
            })->values();

            return response()->json([
                'data' => $transformedItems,
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                ],
            ]);
        }

        return view('pages.stores-withdrawals.create', [
            'departments' => $departments,
            'categories' => $categories,
            'items' => $items,
            'search' => $search,
            'selectedCategory' => $categoryId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'sws_date' => ['required', 'date'],
            'type' => ['required', 'in:NORMAL,CONFIRMATORY'],
            'info' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $department = Department::query()
            ->select(['id', 'code'])
            ->findOrFail((int) $validated['department_id']);
        $departmentCode = strtoupper(trim((string) $department->code));

        $swsDate = Carbon::parse($validated['sws_date'])->startOfDay();
        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) $row['quantity'], 3),
                ];
            })
            ->filter(fn (array $row): bool => $row['item_id'] > 0 && $row['quantity'] > 0)
            ->groupBy('item_id')
            ->map(function ($rows, $itemId): array {
                return [
                    'item_id' => (int) $itemId,
                    'quantity' => round((float) $rows->sum('quantity'), 3),
                ];
            })
            ->values();

        if ($requestedItems->isEmpty()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one valid item before submitting.',
            ]);
        }

        $itemRows = DB::table('items as i')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereIn('i.id', $requestedItems->pluck('item_id')->all())
            ->whereNull('i.deleted_at')
            ->select([
                'i.id',
                'i.code',
                'i.stock_on_hand',
                'u.name as uom_name',
            ])
            ->get()
            ->keyBy('id');

        if ($itemRows->count() !== $requestedItems->count()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Some selected items are no longer available.',
            ]);
        }

        if ($validated['type'] === 'NORMAL') {
            $zeroStockIds = $requestedItems
                ->filter(function (array $row) use ($itemRows): bool {
                    $stock = (float) (($itemRows[$row['item_id']]->stock_on_hand ?? 0));
                    return $stock <= 0;
                })
                ->pluck('item_id');

            if ($zeroStockIds->isNotEmpty()) {
                return redirect()->back()->withInput()
                    ->withErrors([
                        'items' => 'Normal type does not allow zero-stock items. Use Confirmatory if needed.',
                    ]);
            }
        }

        $authUserId = Auth::id();
        $now = now();

        $swsNumber = DB::transaction(function () use ($department, $departmentCode, $swsDate, $validated, $requestedItems, $itemRows, $authUserId, $now): string {
            $swsNumber = $this->generateSwsNumber($departmentCode, $swsDate);

            $storeWithdrawalId = DB::table('store_withdrawals')->insertGetId([
                'sws_number' => $swsNumber,
                'sws_date' => $swsDate->toDateString(),
                'department_id' => (int) $department->id,
                'department_code' => $departmentCode,
                'type' => strtolower((string) $validated['type']),
                'info' => $validated['info'] ?? null,
                'approved_by' => null,
                'approved_at' => null,
                'created_by' => $authUserId,
                'updated_by' => $authUserId,
                'meta' => json_encode([
                    'source' => 'sws-create-form',
                    'item_count' => $requestedItems->count(),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            $detailRows = $requestedItems->map(function (array $row) use ($storeWithdrawalId, $itemRows, $authUserId, $now): array {
                $item = $itemRows[$row['item_id']];

                return [
                    'store_withdrawal_id' => (int) $storeWithdrawalId,
                    'item_id' => (int) $item->id,
                    'product_code' => (string) $item->code,
                    'quantity' => $row['quantity'],
                    'stock_on_hand_snapshot' => round((float) ($item->stock_on_hand ?? 0), 3),
                    'uom' => $item->uom_name ?? 'PCS',
                    'created_by' => $authUserId,
                    'updated_by' => $authUserId,
                    'meta' => json_encode([
                        'created_from' => 'sws-create-form',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            })->all();

            DB::table('store_withdrawal_items')->insert($detailRows);

            return $swsNumber;
        });

        return redirect()
            ->route('stores-withdrawals.index')
            ->with('success', "Stores Withdrawal {$swsNumber} has been created successfully.");
    }

    public function show(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal detail page is not implemented yet (scaffold stage).');
    }

    public function print(string $storeWithdrawal)
    {
        $storeWithdrawalId = (int) $storeWithdrawal;

        $sws = DB::table('store_withdrawals as sw')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'sw.created_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'sw.approved_by')
            ->where('sw.id', $storeWithdrawalId)
            ->whereNull('sw.deleted_at')
            ->select([
                'sw.id',
                'sw.sws_number',
                'sw.sws_date',
                'sw.department_code',
                'sw.type',
                'sw.info',
                'sw.approved_at',
                'sw.created_at',
                'd.name as department_name',
                'creator.name as created_by_name',
                'approver.name as approved_by_name',
            ])
            ->first();

        if (! $sws) {
            abort(404);
        }

        $items = DB::table('store_withdrawal_items as swi')
            ->leftJoin('items as i', 'i.id', '=', 'swi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->where('swi.store_withdrawal_id', $storeWithdrawalId)
            ->whereNull('swi.deleted_at')
            ->orderBy('swi.id')
            ->select([
                'swi.id',
                'swi.product_code',
                'swi.quantity',
                'swi.stock_on_hand_snapshot',
                'swi.uom',
                'i.name as item_name',
                'i.code as item_code',
                'u.name as item_uom_name',
            ])
            ->get();

        $filename = sprintf(
            'SWS-%s-%s.pdf',
            str_replace(['/', '\\', ' '], '-', (string) $sws->sws_number),
            now()->format('Y-m-d')
        );

        return Pdf::loadView('pdf.store-withdrawal-slip', [
            'sws' => $sws,
            'items' => $items,
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    public function edit(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal edit page is not implemented yet (scaffold stage).');
    }

    public function update(Request $request, string $storeWithdrawal)
    {
        $storeWithdrawalId = (int) $storeWithdrawal;

        $exists = DB::table('store_withdrawals')
            ->where('id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            abort(404);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.remove' => ['nullable', 'in:0,1'],
        ]);

        $existingItemIds = DB::table('store_withdrawal_items')
            ->where('store_withdrawal_id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($existingItemIds)) {
            return redirect()->back()->withErrors([
                'items' => 'No active item found for this stores withdrawal.',
            ]);
        }

        $existingItemLookup = array_fill_keys($existingItemIds, true);
        $updatePayloads = [];
        $removeIds = [];

        foreach ($validated['items'] as $itemRow) {
            $itemId = (int) $itemRow['id'];
            if (! isset($existingItemLookup[$itemId])) {
                continue;
            }

            $remove = ((string) ($itemRow['remove'] ?? '0')) === '1';
            if ($remove) {
                $removeIds[$itemId] = true;
                continue;
            }

            $updatePayloads[$itemId] = round((float) $itemRow['quantity'], 3);
        }

        if (count($existingItemIds) <= 1 && ! empty($removeIds)) {
            return redirect()->back()->withErrors([
                'items' => 'Cannot remove item because this stores withdrawal only has one item.',
            ]);
        }

        if (empty($updatePayloads)) {
            return redirect()->back()->withErrors([
                'items' => 'At least one item must remain in this stores withdrawal.',
            ]);
        }

        $now = now();
        $authUserId = Auth::id();
        $removeItemIds = array_keys($removeIds);

        DB::transaction(function () use ($storeWithdrawalId, $updatePayloads, $removeItemIds, $now, $authUserId): void {
            foreach ($updatePayloads as $itemId => $quantity) {
                DB::table('store_withdrawal_items')
                    ->where('id', $itemId)
                    ->where('store_withdrawal_id', $storeWithdrawalId)
                    ->whereNull('deleted_at')
                    ->update([
                        'quantity' => $quantity,
                        'updated_by' => $authUserId,
                        'updated_at' => $now,
                    ]);
            }

            if (! empty($removeItemIds)) {
                DB::table('store_withdrawal_items')
                    ->where('store_withdrawal_id', $storeWithdrawalId)
                    ->whereIn('id', $removeItemIds)
                    ->whereNull('deleted_at')
                    ->update([
                        'updated_by' => $authUserId,
                        'updated_at' => $now,
                        'deleted_at' => $now,
                    ]);
            }

            DB::table('store_withdrawals')
                ->where('id', $storeWithdrawalId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                ]);
        });

        return redirect()->back()->with('success', 'Stores withdrawal updated successfully.');
    }

    public function destroy(string $storeWithdrawal)
    {
        $storeWithdrawalId = (int) $storeWithdrawal;
        $now = now();
        $authUserId = Auth::id();

        $deleted = DB::transaction(function () use ($storeWithdrawalId, $now, $authUserId): int {
            DB::table('store_withdrawal_items')
                ->where('store_withdrawal_id', $storeWithdrawalId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);

            return DB::table('store_withdrawals')
                ->where('id', $storeWithdrawalId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);
        });

        if ($deleted === 0) {
            return redirect()->back()->with('error', 'Stores withdrawal not found or already deleted.');
        }

        return redirect()->back()->with('success', 'Stores withdrawal deleted successfully.');
    }

    /**
     * SQL Server-compatible pagination for stores withdrawals.
     */
    private function paginateStoreWithdrawals(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $department = mb_strtolower(trim((string) ($filters['department'] ?? '')));
        $swsStart = trim((string) ($filters['sws_start'] ?? ''));
        $swsEnd = trim((string) ($filters['sws_end'] ?? ''));

        $keywordLike = "%{$keyword}%";

        $query = DB::table('store_withdrawals as sw')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('users as u', 'u.id', '=', 'sw.created_by')
            ->whereNull('sw.deleted_at')
            ->select([
                'sw.id',
                'sw.sws_number',
                'sw.sws_date',
                'sw.department_code',
                'sw.type',
                'd.name as department_name',
                'sw.info',
                'u.name as created_by_name',
            ])
            ->when($keyword !== '', function ($subQuery) use ($keywordLike) {
                $subQuery->where(function ($whereQuery) use ($keywordLike) {
                    $whereQuery
                        ->whereRaw('LOWER(sw.sws_number) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(sw.department_code) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(d.name, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(sw.info, \'\')) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(COALESCE(u.name, \'\')) LIKE ?', [$keywordLike]);
                });
            })
            ->when($department !== '', function ($subQuery) use ($department) {
                $subQuery->whereRaw('LOWER(sw.department_code) = ?', [$department]);
            })
            ->when($swsStart !== '', function ($subQuery) use ($swsStart) {
                $subQuery->whereDate('sw.sws_date', '>=', $swsStart);
            })
            ->when($swsEnd !== '', function ($subQuery) use ($swsEnd) {
                $subQuery->whereDate('sw.sws_date', '<=', $swsEnd);
            })
            ->orderByDesc('sw.sws_date')
            ->orderByDesc('sw.id');

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
            ->select('sw.id')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY sw.sws_date DESC, sw.id DESC) as row_num');

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_sws')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = DB::table('store_withdrawals as sw')
                ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
                ->leftJoin('users as u', 'u.id', '=', 'sw.created_by')
                ->whereNull('sw.deleted_at')
                ->whereIn('sw.id', $ids)
                ->select([
                    'sw.id',
                    'sw.sws_number',
                    'sw.sws_date',
                    'sw.department_code',
                    'sw.type',
                    'd.name as department_name',
                    'sw.info',
                    'u.name as created_by_name',
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
     * @param  array<int, int>  $storeWithdrawalIds
     * @return array<int, array<int, object>>
     */
    private function groupStoreWithdrawalItems(array $storeWithdrawalIds): array
    {
        if (empty($storeWithdrawalIds)) {
            return [];
        }

        $rows = DB::table('store_withdrawal_items as swi')
            ->leftJoin('items as i', 'i.id', '=', 'swi.item_id')
            ->whereIn('swi.store_withdrawal_id', $storeWithdrawalIds)
            ->whereNull('swi.deleted_at')
            ->orderBy('swi.store_withdrawal_id')
            ->orderBy('swi.id')
            ->select([
                'swi.id',
                'swi.store_withdrawal_id',
                'swi.item_id',
                'swi.product_code',
                'swi.quantity',
                'swi.stock_on_hand_snapshot',
                'swi.uom',
                'i.name as item_name',
                'i.code as item_code',
            ])
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $withdrawalId = (int) $row->store_withdrawal_id;
            $grouped[$withdrawalId][] = $row;
        }

        return $grouped;
    }

    private function generateSwsNumber(string $departmentCode, Carbon $swsDate): string
    {
        $normalizedDepartmentCode = strtoupper(trim($departmentCode));
        $prefix = $normalizedDepartmentCode . '-' . $swsDate->format('dmy') . '-';

        $lastSwsNumber = DB::table('store_withdrawals')
            ->whereRaw('UPPER(department_code) = ?', [$normalizedDepartmentCode])
            ->where('sws_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('sws_number');

        $lastNumber = 0;
        if (is_string($lastSwsNumber) && preg_match('/(\d+)$/', $lastSwsNumber, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        $newNumber = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);

        return $prefix . $newNumber;
    }
}
