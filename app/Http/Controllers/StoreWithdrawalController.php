<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Services\CapexWithdrawalAvailabilityService;
use App\Services\NotificationRecipientService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use App\Support\Concerns\UsesSmartCatalogSearch;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreWithdrawalController extends Controller
{
    use PaginatesLegacySqlServer;
    use UsesSmartCatalogSearch;

    /**
     * Display stores withdrawal list.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $canViewAll = $user && $user->hasAnyRole([
            'administrator',
            'im-manager',
            'im-supervisor',
            'im-staff',
        ]);

        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'department' => trim((string) $request->query('department', '')),
            'sws_start' => trim((string) $request->query('sws_start', '')),
            'sws_end' => trim((string) $request->query('sws_end', '')),
            'type' => trim((string) $request->query('type', '')),
        ];

        $storeWithdrawals = $this->paginateStoreWithdrawals(
            canViewAll: $canViewAll,
            userId: $user?->id,
            filters: $filters,
            perPage: 10,
        );
        $storeWithdrawalIds = $storeWithdrawals->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $storeWithdrawalItems = $this->groupStoreWithdrawalItems($storeWithdrawalIds);
        $lockedStoreWithdrawalIds = $this->lockedStoreWithdrawalIds($storeWithdrawalIds);
        $lockedStoreWithdrawalLookup = array_fill_keys($lockedStoreWithdrawalIds, true);

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.stores-withdrawals.index', [
            'storeWithdrawals' => $storeWithdrawals,
            'storeWithdrawalItems' => $storeWithdrawalItems,
            'lockedStoreWithdrawalLookup' => $lockedStoreWithdrawalLookup,
            'departmentOptions' => $canViewAll ? $departmentOptions : collect(),
            'canFilterDepartment' => $canViewAll,
            'filters' => $filters,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $withdrawalMode = $this->normalizeWithdrawalMode((string) $request->query('mode', 'normal'));
        $departments = Department::all();
        $categories = ItemCategory::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        if ($withdrawalMode === 'capex') {
            if ($request->expectsJson() || $request->ajax()) {
                return $this->capexLinesJsonResponse($request);
            }

            return view('pages.stores-withdrawals.create', [
                'departments' => $departments,
                'categories' => $categories,
                'items' => new LengthAwarePaginator([], 0, 36),
                'search' => trim((string) $request->query('search', '')),
                'selectedCategory' => '',
                'selectedStockFilter' => '',
                'withdrawalMode' => 'capex',
                'selectedDepartmentId' => (string) $request->query('department_id', auth()->user()?->department_id ?? ''),
            ]);
        }

        $search = trim((string) $request->query('search'));
        $categoryId = trim((string) $request->query('category'));
        $stockFilter = trim((string) $request->query('stock'));
        if (! in_array($stockFilter, ['in_stock', 'zero_stock'], true)) {
            $stockFilter = '';
        }

        $itemsBaseQuery = Item::query()
            ->select(['id', 'name', 'code', 'stock_on_hand', 'unit_of_measure_id', 'category_id'])
            ->where('is_active', true)
            ->when($categoryId !== '' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->where('category_id', (int) $categoryId);
            })
            ->when($stockFilter === 'in_stock', function ($query) {
                $query->where('stock_on_hand', '>', 0);
            })
            ->when($stockFilter === 'zero_stock', function ($query) {
                $query->where('stock_on_hand', '<=', 0);
            });

        $items = $this->smartCatalogPaginator($itemsBaseQuery, $search, 36);

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
            'selectedStockFilter' => $stockFilter,
            'withdrawalMode' => 'normal',
            'selectedDepartmentId' => (string) (auth()->user()?->department_id ?? ''),
        ]);
    }

    public function capexLines(Request $request)
    {
        return $this->capexLinesJsonResponse($request);
    }

    private function capexLinesJsonResponse(Request $request)
    {
        $departmentId = (int) $request->query('department_id', 0);
        if ($departmentId <= 0) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                    'per_page' => 36,
                ],
                'message' => 'Select a department to load CAPEX items.',
            ]);
        }

        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));
        $lines = app(CapexWithdrawalAvailabilityService::class)
            ->paginateAvailableLines($departmentId, $search, $page, 36);

        return response()->json([
            'data' => $lines->items(),
            'meta' => [
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'total' => $lines->total(),
                'per_page' => $lines->perPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'sws_date' => ['required', 'date'],
            'type' => ['required', 'in:NORMAL,CONFIRMATORY,CAPEX'],
            'info' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.00001'],
            'items.*.receiving_report_item_id' => ['nullable', 'integer', 'exists:receiving_report_items,id'],
        ]);

        if ($validated['type'] === 'CAPEX') {
            return $this->storeCapexWithdrawal($validated);
        }

        $department = Department::query()
            ->select(['id', 'code'])
            ->findOrFail((int) $validated['department_id']);
        $departmentCode = strtoupper(trim((string) $department->code));

        $swsDate = Carbon::parse($validated['sws_date'])->startOfDay();
        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) $row['quantity'], 5),
                ];
            })
            ->filter(fn (array $row): bool => $row['item_id'] > 0 && $row['quantity'] > 0)
            ->groupBy('item_id')
            ->map(function ($rows, $itemId): array {
                return [
                    'item_id' => (int) $itemId,
                    'quantity' => round((float) $rows->sum('quantity'), 5),
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

            $overStockIds = $requestedItems
                ->filter(function (array $row) use ($itemRows): bool {
                    $stock = round((float) (($itemRows[$row['item_id']]->stock_on_hand ?? 0)), 5);

                    return $row['quantity'] > $stock;
                })
                ->pluck('item_id');

            if ($zeroStockIds->isNotEmpty()) {
                return redirect()->back()->withInput()
                    ->withErrors([
                        'items' => 'Normal type does not allow zero-stock items. Use Confirmatory if needed.',
                    ]);
            }

            if ($overStockIds->isNotEmpty()) {
                return redirect()->back()->withInput()
                    ->withErrors([
                        'items' => 'Normal type does not allow quantity to exceed available stock. Reduce the quantity or switch to Confirmatory.',
                    ]);
            }
        }

        $authUserId = Auth::id();
        $now = now();

        $createdStoreWithdrawal = $this->transactionSerializable(function () use ($department, $departmentCode, $swsDate, $validated, $requestedItems, $itemRows, $authUserId, $now): array {
            $swsNumber = $this->generateSwsNumber($departmentCode);

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
                    'stock_on_hand_snapshot' => round((float) ($item->stock_on_hand ?? 0), 5),
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

            return [
                'id' => (int) $storeWithdrawalId,
                'sws_number' => $swsNumber,
            ];
        });

        $recipientService = app(NotificationRecipientService::class);
        $documentUsers = User::whereIn('id', array_filter([$authUserId]))->get();
        $recipients = $recipientService->uniqueUsers(
            $recipientService->inventoryTeam(),
            $documentUsers
        );

        $recipientService->notify($recipients, [
            'type' => 'store_withdrawal_created',
            'title' => 'Store Withdrawal Created',
            'message' => 'SWS '.$createdStoreWithdrawal['sws_number'].' has been created.',
            'action_url' => '/stores-withdrawals',
            'icon' => 'fa-light fa-box-open-full',
            'icon_color' => 'bg-success',
            'meta' => [
                'store_withdrawal_id' => $createdStoreWithdrawal['id'],
                'sws_number' => $createdStoreWithdrawal['sws_number'],
            ],
        ]);

        return redirect()
            ->route('stores-withdrawals.index')
            ->with('success', "Stores Withdrawal {$createdStoreWithdrawal['sws_number']} has been created successfully.");
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeCapexWithdrawal(array $validated)
    {
        $department = Department::query()
            ->select(['id', 'code'])
            ->findOrFail((int) $validated['department_id']);
        $departmentCode = strtoupper(trim((string) $department->code));

        $requestedLines = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'receiving_report_item_id' => (int) ($row['receiving_report_item_id'] ?? 0),
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) $row['quantity'], 5),
                ];
            })
            ->filter(fn (array $row): bool => $row['receiving_report_item_id'] > 0 && $row['item_id'] > 0 && $row['quantity'] > 0)
            ->groupBy('receiving_report_item_id')
            ->map(function ($rows, $receivingReportItemId): array {
                return [
                    'receiving_report_item_id' => (int) $receivingReportItemId,
                    'item_id' => (int) $rows->first()['item_id'],
                    'quantity' => round((float) $rows->sum('quantity'), 5),
                ];
            })
            ->values();

        if ($requestedLines->isEmpty()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one valid CAPEX line before submitting.',
            ]);
        }

        $availability = app(CapexWithdrawalAvailabilityService::class);
        $validation = $availability->validateRequestedLines($requestedLines->all());
        if (! $validation['valid']) {
            return redirect()->back()->withInput()->withErrors([
                'items' => $validation['message'],
            ]);
        }

        $lineRows = $availability->loadLinesByReceivingReportItemIds(
            $requestedLines->pluck('receiving_report_item_id')->all()
        );

        if ((int) $lineRows->first()->department_id !== (int) $department->id) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Selected CAPEX lines must match the charged department.',
            ]);
        }

        $swsDate = Carbon::parse($validated['sws_date'])->startOfDay();
        $authUserId = Auth::id();
        $now = now();

        $createdStoreWithdrawal = $this->transactionSerializable(function () use ($department, $departmentCode, $swsDate, $validated, $requestedLines, $lineRows, $authUserId, $now): array {
            $swsNumber = $this->generateSwsNumber($departmentCode);

            $storeWithdrawalId = DB::table('store_withdrawals')->insertGetId([
                'sws_number' => $swsNumber,
                'sws_date' => $swsDate->toDateString(),
                'department_id' => (int) $department->id,
                'department_code' => $departmentCode,
                'type' => 'capex',
                'info' => $validated['info'] ?? null,
                'approved_by' => null,
                'approved_at' => null,
                'created_by' => $authUserId,
                'updated_by' => $authUserId,
                'meta' => json_encode([
                    'source' => 'sws-create-form',
                    'withdrawal_mode' => 'capex',
                    'item_count' => $requestedLines->count(),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            $detailRows = $requestedLines->map(function (array $row) use ($storeWithdrawalId, $lineRows, $authUserId, $now): array {
                $line = $lineRows->get($row['receiving_report_item_id']);

                return [
                    'store_withdrawal_id' => (int) $storeWithdrawalId,
                    'receiving_report_item_id' => (int) $row['receiving_report_item_id'],
                    'purchase_order_item_id' => (int) $line->purchase_order_item_id,
                    'prs_item_id' => (int) $line->prs_item_id,
                    'item_id' => (int) $line->item_id,
                    'product_code' => (string) $line->item_code,
                    'quantity' => $row['quantity'],
                    'stock_on_hand_snapshot' => round((float) $line->qty_good, 5),
                    'uom' => $line->unit_name ?? 'PCS',
                    'created_by' => $authUserId,
                    'updated_by' => $authUserId,
                    'meta' => json_encode([
                        'created_from' => 'sws-create-form',
                        'is_capex' => true,
                        'prs_number' => $line->prs_number,
                        'po_number' => $line->po_number,
                        'rr_number' => $line->rr_number,
                        'qty_rr_good' => round((float) $line->qty_good, 5),
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            })->all();

            DB::table('store_withdrawal_items')->insert($detailRows);

            return [
                'id' => (int) $storeWithdrawalId,
                'sws_number' => $swsNumber,
            ];
        });

        $recipientService = app(NotificationRecipientService::class);
        $documentUsers = User::whereIn('id', array_filter([$authUserId]))->get();
        $recipients = $recipientService->uniqueUsers(
            $recipientService->inventoryTeam(),
            $documentUsers
        );

        $recipientService->notify($recipients, [
            'type' => 'store_withdrawal_created',
            'title' => 'CAPEX Store Withdrawal Created',
            'message' => 'SWS '.$createdStoreWithdrawal['sws_number'].' (CAPEX) has been created.',
            'action_url' => '/stores-withdrawals',
            'icon' => 'fa-light fa-building-columns',
            'icon_color' => 'bg-success',
            'meta' => [
                'store_withdrawal_id' => $createdStoreWithdrawal['id'],
                'sws_number' => $createdStoreWithdrawal['sws_number'],
                'is_capex' => true,
            ],
        ]);

        return redirect()
            ->route('stores-withdrawals.index')
            ->with('success', "CAPEX Stores Withdrawal {$createdStoreWithdrawal['sws_number']} has been created successfully.");
    }

    public function show(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal detail page is not implemented yet (scaffold stage).');
    }

    public function print(Request $request, string $storeWithdrawal)
    {
        $storeWithdrawalId = (int) $storeWithdrawal;

        $this->ensureUserCanManageStoreWithdrawal($request, $storeWithdrawalId);

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
                'swi.meta',
                'i.name as item_name',
                'i.code as item_code',
                'u.name as item_uom_name',
            ])
            ->get()
            ->map(function ($item) {
                $meta = is_string($item->meta) ? json_decode($item->meta, true) : (array) ($item->meta ?? []);
                $item->prs_number = $meta['prs_number'] ?? null;
                $item->po_number = $meta['po_number'] ?? null;
                $item->rr_number = $meta['rr_number'] ?? null;

                return $item;
            });

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

        $this->ensureUserCanManageStoreWithdrawal($request, $storeWithdrawalId);

        $exists = DB::table('store_withdrawals')
            ->where('id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            abort(404);
        }

        if ($this->hasActiveTransferSlip($storeWithdrawalId)) {
            return redirect()->back()->withErrors([
                'items' => 'Stores withdrawal cannot be edited because a transfer slip has already been created.',
            ]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.00001'],
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

        $storeWithdrawal = DB::table('store_withdrawals')
            ->where('id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->first(['id', 'sws_number', 'created_by', 'type']);

        if (! $storeWithdrawal) {
            abort(404);
        }

        $isCapexWithdrawal = strtolower((string) ($storeWithdrawal->type ?? '')) === 'capex';

        if ($isCapexWithdrawal) {
            $existingItems = DB::table('store_withdrawal_items')
                ->where('store_withdrawal_id', $storeWithdrawalId)
                ->whereNull('deleted_at')
                ->get(['id', 'receiving_report_item_id', 'quantity']);

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

                $newQuantity = round((float) $itemRow['quantity'], 5);
                $existingItem = $existingItems->firstWhere('id', $itemId);
                if (! $existingItem || (int) $existingItem->receiving_report_item_id <= 0) {
                    return redirect()->back()->withErrors([
                        'items' => 'CAPEX withdrawal lines are missing receiving report references.',
                    ]);
                }

                $lineValidation = app(CapexWithdrawalAvailabilityService::class)->validateRequestedLines([
                    [
                        'receiving_report_item_id' => (int) $existingItem->receiving_report_item_id,
                        'quantity' => $newQuantity,
                    ],
                ], $storeWithdrawalId);

                if (! $lineValidation['valid']) {
                    return redirect()->back()->withErrors([
                        'items' => $lineValidation['message'],
                    ]);
                }

                $updatePayloads[$itemId] = $newQuantity;
            }
        } else {
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

                $updatePayloads[$itemId] = round((float) $itemRow['quantity'], 5);
            }
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

        $recipientService = app(NotificationRecipientService::class);
        $documentUsers = User::whereIn('id', array_filter([(int) ($storeWithdrawal->created_by ?? 0), (int) $authUserId]))->get();
        $recipients = $recipientService->uniqueUsers(
            $recipientService->inventoryTeam(),
            $documentUsers
        );

        $recipientService->notify($recipients, [
            'type' => 'store_withdrawal_updated',
            'title' => 'Store Withdrawal Updated',
            'message' => 'SWS '.($storeWithdrawal->sws_number ?? $storeWithdrawalId).' has been updated.',
            'action_url' => '/stores-withdrawals',
            'icon' => 'fa-light fa-pen-to-square',
            'icon_color' => 'bg-info',
            'meta' => [
                'store_withdrawal_id' => $storeWithdrawalId,
                'sws_number' => $storeWithdrawal->sws_number ?? null,
            ],
        ]);

        return redirect()->back()->with('success', 'Stores withdrawal updated successfully.');
    }

    public function destroy(Request $request, string $storeWithdrawal)
    {
        $storeWithdrawalId = (int) $storeWithdrawal;

        $this->ensureUserCanManageStoreWithdrawal($request, $storeWithdrawalId);

        if ($this->hasActiveTransferSlip($storeWithdrawalId)) {
            return redirect()->back()->with('error', 'Stores withdrawal cannot be deleted because a transfer slip has already been created.');
        }

        $now = now();
        $authUserId = Auth::id();
        $storeWithdrawal = DB::table('store_withdrawals')
            ->where('id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->first(['id', 'sws_number', 'created_by']);

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

        $recipientService = app(NotificationRecipientService::class);
        $documentUsers = User::whereIn('id', array_filter([(int) ($storeWithdrawal->created_by ?? 0), (int) $authUserId]))->get();
        $recipients = $recipientService->uniqueUsers(
            $recipientService->inventoryTeam(),
            $documentUsers
        );

        $recipientService->notify($recipients, [
            'type' => 'store_withdrawal_deleted',
            'title' => 'Store Withdrawal Deleted',
            'message' => 'SWS '.($storeWithdrawal->sws_number ?? $storeWithdrawalId).' has been deleted.',
            'action_url' => '/stores-withdrawals',
            'icon' => 'fa-light fa-trash-can',
            'icon_color' => 'bg-danger',
            'meta' => [
                'store_withdrawal_id' => $storeWithdrawalId,
                'sws_number' => $storeWithdrawal->sws_number ?? null,
            ],
        ]);

        return redirect()->back()->with('success', 'Stores withdrawal deleted successfully.');
    }

    /**
     * SQL Server-compatible pagination for stores withdrawals.
     */
    private function paginateStoreWithdrawals(bool $canViewAll, ?int $userId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $department = mb_strtolower(trim((string) ($filters['department'] ?? '')));
        $swsStart = trim((string) ($filters['sws_start'] ?? ''));
        $swsEnd = trim((string) ($filters['sws_end'] ?? ''));

        $keywordLike = "%{$keyword}%";

        $typeFilter = mb_strtolower(trim((string) ($filters['type'] ?? '')));

        $query = DB::table('store_withdrawals as sw')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('users as u', 'u.id', '=', 'sw.created_by')
            ->whereNull('sw.deleted_at')
            ->when(! $canViewAll, function ($subQuery) use ($userId) {
                $subQuery->where('sw.created_by', $userId);
            })
            ->select([
                'sw.id',
                'sw.sws_number',
                'sw.sws_date',
                'sw.department_code',
                'sw.type',
                'sw.created_by',
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
            ->when(in_array($typeFilter, ['normal', 'confirmatory', 'capex'], true), function ($subQuery) use ($typeFilter) {
                $subQuery->where('sw.type', $typeFilter);
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
                    'sw.created_by',
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
        return $this->isSqlServerConnection();
    }

    /**
     * @param  array<int, int>  $storeWithdrawalIds
     * @return array<int, int>
     */
    private function lockedStoreWithdrawalIds(array $storeWithdrawalIds): array
    {
        if (empty($storeWithdrawalIds)) {
            return [];
        }

        return DB::table('transfer_slips')
            ->whereIn('store_withdrawal_id', $storeWithdrawalIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('store_withdrawal_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hasActiveTransferSlip(int $storeWithdrawalId): bool
    {
        return DB::table('transfer_slips')
            ->where('store_withdrawal_id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function ensureUserCanManageStoreWithdrawal(Request $request, int $storeWithdrawalId): void
    {
        $sws = DB::table('store_withdrawals')
            ->where('id', $storeWithdrawalId)
            ->whereNull('deleted_at')
            ->first(['created_by']);

        if (! $sws) {
            abort(404);
        }

        $user = $request->user();
        $canViewAll = $user?->hasAnyRole([
            'administrator',
            'im-manager',
            'im-supervisor',
            'im-staff',
        ]);

        if (! $canViewAll && (int) $sws->created_by !== (int) $user?->id) {
            abort(403);
        }
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
                'swi.meta',
                'swi.receiving_report_item_id',
                'i.name as item_name',
                'i.code as item_code',
            ])
            ->get()
            ->map(function ($row) {
                $meta = is_string($row->meta) ? json_decode($row->meta, true) : (array) ($row->meta ?? []);
                $row->prs_number = $meta['prs_number'] ?? null;
                $row->po_number = $meta['po_number'] ?? null;
                $row->rr_number = $meta['rr_number'] ?? null;

                return $row;
            });

        $grouped = [];
        foreach ($rows as $row) {
            $withdrawalId = (int) $row->store_withdrawal_id;
            $grouped[$withdrawalId][] = $row;
        }

        return $grouped;
    }

    // Sinkron dengan sistem lama: {DEPTCODE}{#######}, urutan naik per department.
    private function generateSwsNumber(string $departmentCode): string
    {
        $normalizedDepartmentCode = strtoupper(trim($departmentCode));

        $start = strlen($normalizedDepartmentCode) + 1;

        $lastSequence = DB::table('store_withdrawals')
            ->whereRaw('UPPER(department_code) = ?', [$normalizedDepartmentCode])
            ->where('sws_number', 'like', $normalizedDepartmentCode.'%')
            ->selectRaw(
                'MAX(CASE WHEN LEN(SUBSTRING(sws_number, ?, 100)) > 0 '
                ."AND SUBSTRING(sws_number, ?, 100) NOT LIKE '%[^0-9]%' "
                .'THEN CAST(SUBSTRING(sws_number, ?, 100) AS INT) ELSE NULL END) as last_sequence',
                [$start, $start, $start]
            )
            ->value('last_sequence');

        $lastNumber = (int) ($lastSequence ?? 0);

        // Sequence selalu 7 digit agar konsisten: 0000001, 0000002, dst.
        $newNumber = str_pad((string) ($lastNumber + 1), 7, '0', STR_PAD_LEFT);

        return $normalizedDepartmentCode.$newNumber;
    }

    private function transactionSerializable(callable $callback): mixed
    {
        $connection = DB::connection();

        if ($this->isSqlServer()) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

            try {
                return $connection->transaction($callback);
            } finally {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
        }

        return $connection->transaction($callback);
    }

    private function normalizeWithdrawalMode(string $mode): string
    {
        return strtolower(trim($mode)) === 'capex' ? 'capex' : 'normal';
    }
}
