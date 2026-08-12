<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferSlipController extends Controller
{
    private const MM_TO_PT = 2.834645669;

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
        $transferSlipEditItems = $this->buildEditPayload($transferSlips->getCollection()->all());

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.transfer-slips.index', [
            'transferSlips' => $transferSlips,
            'transferSlipItems' => $transferSlipItems,
            'transferSlipEditItems' => $transferSlipEditItems,
            'departmentOptions' => $departmentOptions,
            'filters' => $filters,
            'nextTsNumber' => app(DocumentNumberService::class)->previewNext('TS'),
        ]);
    }

    public function swsByNumber(Request $request)
    {
        $validated = $request->validate([
            'sws_number' => ['required', 'string', 'max:50'],
        ]);

        $swsNumber = $this->normalizeSwsNumber($validated['sws_number']);

        if ($swsNumber === '') {
            return response()->json([
                'message' => 'SWS number not found.',
            ], 404);
        }

        $storeWithdrawal = DB::table('store_withdrawals as sw')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->whereNull('sw.deleted_at')
            ->whereRaw('LOWER(sw.sws_number) = ?', [strtolower($swsNumber)])
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
                'swi.meta',
                'i.name as item_name',
                'u.name as unit_name',
            ])
            ->get();

        $isCapex = strtolower((string) ($storeWithdrawal->type ?? '')) === 'capex';

        $transferredMap = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereIn('tsi.store_withdrawal_item_id', $sourceItems->pluck('id')->all())
            ->selectRaw('tsi.store_withdrawal_item_id, SUM(tsi.quantity) as transferred_quantity')
            ->groupBy('tsi.store_withdrawal_item_id')
            ->pluck('transferred_quantity', 'tsi.store_withdrawal_item_id');

        $items = $sourceItems->map(function ($item) use ($transferredMap, $isCapex) {
            $transferred = round((float) ($transferredMap[$item->id] ?? 0), 5);
            $sourceQuantity = round((float) $item->quantity, 5);
            $remaining = max(0, round($sourceQuantity - $transferred, 5));
            $meta = is_string($item->meta) ? json_decode($item->meta, true) : (array) ($item->meta ?? []);

            return [
                'store_withdrawal_item_id' => (int) $item->id,
                'item_id' => (int) $item->item_id,
                'product_code' => $item->product_code,
                'item_name' => $item->item_name,
                'quantity_source' => $sourceQuantity,
                'quantity_transferred' => $transferred,
                'quantity_remaining' => $remaining,
                'uom' => $item->uom ?? $item->unit_name ?? 'PCS',
                'is_capex' => $isCapex,
                'prs_number' => $meta['prs_number'] ?? null,
                'po_number' => $meta['po_number'] ?? null,
                'rr_number' => $meta['rr_number'] ?? null,
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
                'is_capex' => $isCapex,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ts_number' => ['nullable', 'string', 'max:50'],
            'ts_number_suggested' => ['nullable', 'string', 'max:50'],
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

        $validated['sws_number'] = $this->normalizeSwsNumber($validated['sws_number']);

        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'store_withdrawal_item_id' => (int) $row['store_withdrawal_item_id'],
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) ($row['quantity'] ?? 0), 5),
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
            ->select(['id', 'sws_number', 'type'])
            ->first();

        if (! $storeWithdrawal || ! $this->swsNumbersMatch((string) $storeWithdrawal->sws_number, $validated['sws_number'])) {
            return redirect()->back()->withInput()->withErrors([
                'sws_number' => 'Selected SWS is no longer valid. Please load the SWS again.',
            ]);
        }

        $validated['sws_number'] = trim((string) $storeWithdrawal->sws_number);

        $allowNegativeBalance = strtolower((string) ($storeWithdrawal->type ?? '')) === 'confirmatory';

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

            $alreadyTransferred = round((float) ($transferredMap[$storeWithdrawalItemId] ?? 0), 5);
            $remaining = max(0, round(((float) $sourceItem->quantity) - $alreadyTransferred, 5));

            if ($row['quantity'] > $remaining) {
                return redirect()->back()->withInput()->withErrors([
                    'items' => 'Transfer quantity exceeds the remaining quantity for one or more SWS items.',
                ]);
            }
        }

        $authUserId = Auth::id();
        $now = now();
        $numberService = app(DocumentNumberService::class);
        $createdTsNumber = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolvedNumber = $numberService->resolve('TS', $validated['ts_number'] ?? null, $validated['ts_number_suggested'] ?? null);
            $numberService->assertUnique('TS', $resolvedNumber['number']);

            try {
                DB::transaction(function () use ($validated, $requestedItems, $sourceItems, $authUserId, $now, $resolvedNumber, $allowNegativeBalance, &$createdTsNumber): void {
                    $transferSlipId = DB::table('transfer_slips')->insertGetId([
                        'ts_number' => $resolvedNumber['number'],
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
                                'source_quantity' => round((float) $sourceItem->quantity, 5),
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ];
                    })->values()->all();

                    DB::table('transfer_slip_items')->insert($detailRows);

                    $stockLines = DB::table('transfer_slip_items')
                        ->where('transfer_slip_id', $transferSlipId)
                        ->whereNull('deleted_at')
                        ->get(['id', 'item_id', 'product_code', 'quantity'])
                        ->map(fn ($row): array => [
                            'item_id' => (int) $row->item_id,
                            'product_code' => (string) $row->product_code,
                            'quantity' => (float) $row->quantity,
                            'reference_line_id' => (int) $row->id,
                        ])
                        ->all();

                    app(StockService::class)->applyTransferSlipIssue(
                        transferSlipId: (int) $transferSlipId,
                        movementDate: $validated['ts_date'],
                        lines: $stockLines,
                        userId: $authUserId,
                        allowNegativeBalance: $allowNegativeBalance,
                    );

                    $createdTsNumber = $resolvedNumber['number'];
                });
                break;
            } catch (ValidationException $exception) {
                return redirect()->back()->withInput()->withErrors($exception->errors());
            } catch (QueryException $exception) {
                $canRetry = $resolvedNumber['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                throw $exception;
            }
        }

        return redirect()
            ->route('transfer-slips.index')
            ->with('success', "Transfer slip {$createdTsNumber} has been created successfully.");
    }

    public function update(Request $request, string $transferSlip)
    {
        $transferSlipId = (int) $transferSlip;

        $existing = DB::table('transfer_slips')
            ->where('id', $transferSlipId)
            ->whereNull('deleted_at')
            ->first([
                'id',
                'ts_number',
                'ts_date',
                'store_withdrawal_id',
                'for_production',
                'remarks',
                'meta',
            ]);

        if (! $existing) {
            return redirect()
                ->route('transfer-slips.index')
                ->with('error', 'Transfer slip not found or already deleted.');
        }

        $validated = $request->validate([
            'ts_number' => ['required', 'string', 'max:50'],
            'ts_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'for_production' => ['required', 'in:0,1'],
            'sws_number' => ['required', 'string', 'max:50'],
            'store_withdrawal_id' => ['required', 'integer', 'exists:store_withdrawals,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.store_withdrawal_item_id' => ['required', 'integer', 'exists:store_withdrawal_items,id'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['sws_number'] = $this->normalizeSwsNumber($validated['sws_number']);
        $validated['ts_number'] = trim((string) $validated['ts_number']);
        $currentTsNumber = trim((string) $existing->ts_number);
        $tsNumber = $validated['ts_number'] !== '' ? $validated['ts_number'] : $currentTsNumber;

        if ($tsNumber !== $currentTsNumber) {
            try {
                app(DocumentNumberService::class)->assertUnique('TS', $tsNumber, $transferSlipId);
            } catch (ValidationException $exception) {
                return redirect()->back()->withInput()->withErrors($exception->errors());
            }
        }

        if ((int) $validated['store_withdrawal_id'] !== (int) $existing->store_withdrawal_id) {
            return redirect()->back()->withInput()->withErrors([
                'store_withdrawal_id' => 'The linked SWS cannot be changed when editing a transfer slip.',
            ]);
        }

        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'store_withdrawal_item_id' => (int) $row['store_withdrawal_item_id'],
                    'item_id' => (int) $row['item_id'],
                    'quantity' => round((float) ($row['quantity'] ?? 0), 5),
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
            ->where('id', (int) $existing->store_withdrawal_id)
            ->whereNull('deleted_at')
            ->select(['id', 'sws_number', 'type'])
            ->first();

        if (! $storeWithdrawal || ! $this->swsNumbersMatch((string) $storeWithdrawal->sws_number, $validated['sws_number'])) {
            return redirect()->back()->withInput()->withErrors([
                'sws_number' => 'Selected SWS is no longer valid. Please reload the transfer slip.',
            ]);
        }

        $validated['sws_number'] = trim((string) $storeWithdrawal->sws_number);

        $allowNegativeBalance = strtolower((string) ($storeWithdrawal->type ?? '')) === 'confirmatory';

        $sourceItems = DB::table('store_withdrawal_items')
            ->whereIn('id', $requestedItems->keys()->all())
            ->where('store_withdrawal_id', (int) $storeWithdrawal->id)
            ->whereNull('deleted_at')
            ->select(['id', 'store_withdrawal_id', 'item_id', 'product_code', 'quantity', 'uom'])
            ->get()
            ->keyBy('id');

        if ($sourceItems->count() !== $requestedItems->count()) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Some SWS items are no longer available. Please reload the transfer slip.',
            ]);
        }

        $transferredMap = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->where('ts.id', '!=', $transferSlipId)
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

            $alreadyTransferred = round((float) ($transferredMap[$storeWithdrawalItemId] ?? 0), 5);
            $remaining = max(0, round(((float) $sourceItem->quantity) - $alreadyTransferred, 5));

            if ($row['quantity'] > $remaining) {
                return redirect()->back()->withInput()->withErrors([
                    'items' => 'Transfer quantity exceeds the remaining quantity for one or more SWS items.',
                ]);
            }
        }

        $authUserId = Auth::id();
        $now = now();

        try {
            DB::transaction(function () use (
                $validated,
                $requestedItems,
                $sourceItems,
                $authUserId,
                $now,
                $allowNegativeBalance,
                $existing,
                $transferSlipId,
                $storeWithdrawal,
                $tsNumber
            ): void {
                $previousStockLines = DB::table('transfer_slip_items')
                    ->where('transfer_slip_id', $transferSlipId)
                    ->whereNull('deleted_at')
                    ->get(['id', 'item_id', 'product_code', 'quantity'])
                    ->map(fn ($row): array => [
                        'item_id' => (int) $row->item_id,
                        'product_code' => (string) $row->product_code,
                        'quantity' => (float) $row->quantity,
                        'reference_line_id' => (int) $row->id,
                    ])
                    ->all();

                app(StockService::class)->reverseTransferSlipIssue(
                    transferSlipId: $transferSlipId,
                    movementDate: (string) $existing->ts_date,
                    lines: $previousStockLines,
                    userId: $authUserId,
                );

                DB::table('transfer_slip_items')
                    ->where('transfer_slip_id', $transferSlipId)
                    ->whereNull('deleted_at')
                    ->update([
                        'updated_by' => $authUserId,
                        'updated_at' => $now,
                        'deleted_at' => $now,
                    ]);

                $existingMeta = is_string($existing->meta)
                    ? (json_decode($existing->meta, true) ?: [])
                    : (array) ($existing->meta ?? []);

                DB::table('transfer_slips')
                    ->where('id', $transferSlipId)
                    ->whereNull('deleted_at')
                    ->update([
                        'ts_number' => $tsNumber,
                        'ts_date' => $validated['ts_date'],
                        'for_production' => ((string) $validated['for_production']) === '1',
                        'remarks' => $validated['remarks'] ?? null,
                        'updated_by' => $authUserId,
                        'meta' => json_encode(array_merge($existingMeta, [
                            'source' => 'transfer-slip-edit-modal',
                            'sws_number' => $storeWithdrawal->sws_number,
                            'item_count' => $requestedItems->count(),
                        ])),
                        'updated_at' => $now,
                    ]);

                $detailRows = $requestedItems->map(function (array $row) use ($transferSlipId, $sourceItems, $authUserId, $now): array {
                    $sourceItem = $sourceItems->get($row['store_withdrawal_item_id']);

                    return [
                        'transfer_slip_id' => $transferSlipId,
                        'store_withdrawal_item_id' => $row['store_withdrawal_item_id'],
                        'item_id' => $row['item_id'],
                        'product_code' => $sourceItem->product_code,
                        'quantity' => $row['quantity'],
                        'created_by' => $authUserId,
                        'updated_by' => $authUserId,
                        'meta' => json_encode([
                            'sws_uom' => $sourceItem->uom,
                            'source_quantity' => round((float) $sourceItem->quantity, 5),
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ];
                })->values()->all();

                DB::table('transfer_slip_items')->insert($detailRows);

                $stockLines = DB::table('transfer_slip_items')
                    ->where('transfer_slip_id', $transferSlipId)
                    ->whereNull('deleted_at')
                    ->get(['id', 'item_id', 'product_code', 'quantity'])
                    ->map(fn ($row): array => [
                        'item_id' => (int) $row->item_id,
                        'product_code' => (string) $row->product_code,
                        'quantity' => (float) $row->quantity,
                        'reference_line_id' => (int) $row->id,
                    ])
                    ->all();

                app(StockService::class)->applyTransferSlipIssue(
                    transferSlipId: $transferSlipId,
                    movementDate: $validated['ts_date'],
                    lines: $stockLines,
                    userId: $authUserId,
                    allowNegativeBalance: $allowNegativeBalance,
                );
            });
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        } catch (QueryException $exception) {
            if (app(DocumentNumberService::class)->isDuplicateNumberException($exception)) {
                return redirect()->back()->withInput()->withErrors([
                    'ts_number' => "The TS Number {$tsNumber} has already been used.",
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('transfer-slips.index')
            ->with('success', "Transfer slip {$tsNumber} has been updated successfully.");
    }

    public function print(Request $request, string $transferSlip)
    {
        $transferSlipId = (int) $transferSlip;
        $mode = $request->input('mode', $request->query('mode', 'print'));
        $isPreview = $mode !== 'print';

        if ($request->isMethod('post') || $request->filled('ts_number')) {
            $this->saveTsNumberFromRequest($request, $transferSlipId);
        }

        $transferSlipRow = DB::table('transfer_slips as ts')
            ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
            ->leftJoin('users as noted', 'noted.id', '=', 'ts.noted_by')
            ->leftJoin('users as approver', 'approver.id', '=', 'ts.approved_by')
            ->leftJoin('users as receiver', 'receiver.id', '=', 'ts.received_by')
            ->where('ts.id', $transferSlipId)
            ->whereNull('ts.deleted_at')
            ->select([
                'ts.id',
                'ts.ts_number',
                'ts.ts_date',
                'ts.for_production',
                'ts.remarks',
                'ts.transfer_to',
                'ts.meta',
                'ts.noted_at',
                'ts.approved_at',
                'ts.received_at',
                'ts.created_at',
                'sw.sws_number',
                'sw.department_code',
                'd.name as department_name',
                'creator.name as created_by_name',
                'noted.name as noted_by_name',
                'approver.name as approved_by_name',
                'receiver.name as received_by_name',
            ])
            ->first();

        if (! $transferSlipRow) {
            abort(404);
        }

        if (! $isPreview && trim((string) ($transferSlipRow->ts_number ?? '')) === '') {
            return redirect()->back()->withErrors(['message' => 'TS number is required before printing.']);
        }

        $items = DB::table('transfer_slip_items as tsi')
            ->leftJoin('items as i', 'i.id', '=', 'tsi.item_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoin('store_withdrawal_items as swi', 'swi.id', '=', 'tsi.store_withdrawal_item_id')
            ->where('tsi.transfer_slip_id', $transferSlipId)
            ->whereNull('tsi.deleted_at')
            ->orderBy('tsi.id')
            ->select([
                'tsi.id',
                'tsi.product_code',
                'tsi.quantity',
                'i.name as item_name',
                'i.code as item_code',
                'i.type as item_type',
                'ic.name as category_name',
                'u.name as item_uom_name',
                'swi.uom as sws_uom',
            ])
            ->get();

        $pageWidthMm = (float) config('transfer-slip.paper.width_mm', 215);
        $pageHeightMm = (float) config('transfer-slip.paper.height_mm', 105);
        $backgroundImageSrc = null;
        $backgroundWidthPt = $pageWidthMm * self::MM_TO_PT;
        $backgroundHeightPt = $pageHeightMm * self::MM_TO_PT;

        if ($isPreview) {
            $backgroundImageSrc = $this->resolveTransferSlipBackgroundImageSrc();
        }

        $filename = sprintf(
            'TS-%s-%s.pdf',
            str_replace(['/', '\\', ' '], '-', (string) ($transferSlipRow->ts_number ?: $transferSlipRow->id)),
            now()->format('YmdHis')
        );

        $pdf = Pdf::loadView('pdf.transfer-slip', [
            'transferSlip' => $transferSlipRow,
            'items' => $items,
            'isPreview' => $isPreview,
            'backgroundImageSrc' => $backgroundImageSrc,
            'backgroundWidthPt' => $backgroundWidthPt,
            'backgroundHeightPt' => $backgroundHeightPt,
            'pageWidthMm' => $pageWidthMm,
            'pageHeightMm' => $pageHeightMm,
        ])
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [
                base_path(),
                public_path(),
                storage_path('app'),
            ])
            ->setPaper([
                0,
                0,
                $backgroundWidthPt,
                $backgroundHeightPt,
            ]);

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        if ($canvas instanceof \Dompdf\Adapter\CPDF) {
            $canvas->get_cpdf()->setPreferences('PrintScaling', 'None');
        }

        return $pdf->stream($filename);
    }

    private function saveTsNumberFromRequest(Request $request, int $transferSlipId): void
    {
        $validated = $request->validate([
            'ts_number' => ['nullable', 'string', 'max:50'],
            'ts_number_suggested' => ['nullable', 'string', 'max:50'],
        ]);

        $numberService = app(DocumentNumberService::class);
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolvedNumber = $numberService->resolve('TS', $validated['ts_number'] ?? null, $validated['ts_number_suggested'] ?? null);
            $numberService->assertUnique('TS', $resolvedNumber['number'], $transferSlipId);

            try {
                DB::table('transfer_slips')
                    ->where('id', $transferSlipId)
                    ->whereNull('deleted_at')
                    ->update([
                        'ts_number' => $resolvedNumber['number'],
                        'updated_at' => now(),
                    ]);
                break;
            } catch (QueryException $exception) {
                $canRetry = $resolvedNumber['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                throw $exception;
            }
        }
    }

    /**
     * Prepare a DomPDF-friendly background source for the blank TS form.
     */
    private function resolveTransferSlipBackgroundImageSrc(): ?string
    {
        $sourcePath = public_path('assets/images/Blank TS.jpg');
        if (! is_readable($sourcePath)) {
            return null;
        }

        $cacheDir = storage_path('app/dompdf');
        if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
            return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($sourcePath));
        }

        $cachedPath = $cacheDir.DIRECTORY_SEPARATOR.'blank-ts.jpg';
        $sourceMtime = (int) filemtime($sourcePath);
        $needsRefresh = ! is_readable($cachedPath) || (int) filemtime($cachedPath) < $sourceMtime;

        if ($needsRefresh) {
            $image = @imagecreatefromjpeg($sourcePath);
            if ($image === false) {
                @copy($sourcePath, $cachedPath);
            } else {
                imagejpeg($image, $cachedPath, 92);
                imagedestroy($image);
            }
        }

        if (! is_readable($cachedPath)) {
            return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($sourcePath));
        }

        // Data URI is the most reliable DomPDF source on Windows paths.
        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($cachedPath));
    }

    public function destroy(string $transferSlip)
    {
        $transferSlipId = (int) $transferSlip;
        $now = now();
        $authUserId = Auth::id();

        $deleted = DB::transaction(function () use ($transferSlipId, $now, $authUserId): int {
            $transferSlip = DB::table('transfer_slips')
                ->where('id', $transferSlipId)
                ->whereNull('deleted_at')
                ->first(['id', 'ts_date', 'ts_number']);

            if (! $transferSlip) {
                return 0;
            }

            app(StockService::class)->purgeDocumentMovementsAndRechain(
                StockService::REF_TRANSFER_SLIP,
                $transferSlipId,
            );

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
                    'ts_number' => 'DELETED-'.$transferSlipId,
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);
        });

        if ($deleted === 0) {
            return redirect()->back()->with('error', 'Transfer slip not found or already deleted.');
        }

        return redirect()->back()->with('success', 'Transfer slip deleted successfully. The TS number was released for reuse.');
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
                'ts.store_withdrawal_id',
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
            ->orderByDesc('ts.created_at');

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
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY ts.created_at DESC) as row_num');

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
                    'ts.store_withdrawal_id',
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
     * @param  array<int, object>  $transferSlips
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildEditPayload(array $transferSlips): array
    {
        if ($transferSlips === []) {
            return [];
        }

        $storeWithdrawalIds = collect($transferSlips)
            ->map(fn ($row) => (int) ($row->store_withdrawal_id ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($storeWithdrawalIds === []) {
            return [];
        }

        $sourceItems = DB::table('store_withdrawal_items as swi')
            ->leftJoin('items as i', 'i.id', '=', 'swi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereIn('swi.store_withdrawal_id', $storeWithdrawalIds)
            ->whereNull('swi.deleted_at')
            ->orderBy('swi.id')
            ->select([
                'swi.id',
                'swi.store_withdrawal_id',
                'swi.item_id',
                'swi.product_code',
                'swi.quantity',
                'swi.uom',
                'i.name as item_name',
                'u.name as unit_name',
            ])
            ->get();

        $sourceItemIds = $sourceItems->pluck('id')->map(fn ($id) => (int) $id)->all();

        $allActiveTransfers = collect();
        if ($sourceItemIds !== []) {
            $allActiveTransfers = DB::table('transfer_slip_items as tsi')
                ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
                ->whereNull('ts.deleted_at')
                ->whereNull('tsi.deleted_at')
                ->whereIn('tsi.store_withdrawal_item_id', $sourceItemIds)
                ->select([
                    'tsi.transfer_slip_id',
                    'tsi.store_withdrawal_item_id',
                    'tsi.quantity',
                ])
                ->get()
                ->groupBy('store_withdrawal_item_id');
        }

        $sourceItemsBySws = $sourceItems->groupBy('store_withdrawal_id');
        $payload = [];

        foreach ($transferSlips as $transferSlip) {
            $transferSlipId = (int) $transferSlip->id;
            $storeWithdrawalId = (int) ($transferSlip->store_withdrawal_id ?? 0);
            $rows = $sourceItemsBySws->get($storeWithdrawalId, collect());

            $payload[$transferSlipId] = $rows->map(function ($item) use ($transferSlipId, $allActiveTransfers) {
                $sourceQuantity = round((float) $item->quantity, 5);
                $storeWithdrawalItemId = (int) $item->id;
                $transfersForItem = $allActiveTransfers->get($storeWithdrawalItemId, collect());

                $transferredByOthersQty = round((float) $transfersForItem
                    ->filter(fn ($row) => (int) $row->transfer_slip_id !== $transferSlipId)
                    ->sum('quantity'), 5);

                $currentQuantity = round((float) $transfersForItem
                    ->filter(fn ($row) => (int) $row->transfer_slip_id === $transferSlipId)
                    ->sum('quantity'), 5);

                $remaining = max(0, round($sourceQuantity - $transferredByOthersQty, 5));

                return [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => (int) $item->item_id,
                    'product_code' => $item->product_code,
                    'item_name' => $item->item_name,
                    'quantity_source' => $sourceQuantity,
                    'quantity_transferred' => $transferredByOthersQty,
                    'quantity_remaining' => $remaining,
                    'quantity_current' => $currentQuantity,
                    'uom' => $item->uom ?? $item->unit_name ?? 'PCS',
                ];
            })->values()->all();
        }

        return $payload;
    }

    private function normalizeSwsNumber(?string $swsNumber): string
    {
        $normalized = str_replace("\u{00A0}", ' ', (string) $swsNumber);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    private function swsNumbersMatch(string $left, string $right): bool
    {
        return strcasecmp($this->normalizeSwsNumber($left), $this->normalizeSwsNumber($right)) === 0;
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
