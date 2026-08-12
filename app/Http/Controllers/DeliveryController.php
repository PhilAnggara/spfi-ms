<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use App\Support\Concerns\UsesSmartCatalogSearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    use PaginatesLegacySqlServer;
    use UsesSmartCatalogSearch;

    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'from_location' => trim((string) $request->query('from_location', '')),
            'to_location' => trim((string) $request->query('to_location', '')),
            'dr_start' => trim((string) $request->query('dr_start', '')),
            'dr_end' => trim((string) $request->query('dr_end', '')),
        ];

        $deliveries = $this->paginateDeliveries($filters, 10);
        $deliveryIds = $deliveries->getCollection()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deliveryItems = $this->groupDeliveryItems($deliveryIds);

        return view('pages.deliveries.index', [
            'deliveries' => $deliveries,
            'deliveryItems' => $deliveryItems,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $categories = ItemCategory::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

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

        $suppliers = Supplier::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'address']);

        if ($request->expectsJson() || $request->ajax()) {
            $transformedItems = $items->getCollection()->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'stock_on_hand' => round((float) $item->stock_on_hand, 5),
                    'unit' => $item->unit?->name ?? 'PCS',
                    'category' => $item->category?->name,
                ];
            })->values();

            return response()->json([
                'data' => $transformedItems,
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        }

        return view('pages.deliveries.create', [
            'categories' => $categories,
            'items' => $items,
            'search' => $search,
            'selectedCategory' => $categoryId,
            'selectedStockFilter' => $stockFilter,
            'suppliers' => $suppliers,
            'nextDrNumber' => app(DocumentNumberService::class)->previewNext('DR'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dr_number' => ['nullable', 'string', 'max:50'],
            'dr_number_suggested' => ['nullable', 'string', 'max:50'],
            'dr_date' => ['required', 'date'],
            'from_name' => ['required', 'string', 'max:120'],
            'from_location' => ['nullable', 'string', 'max:120'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'to_location' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'or_number' => ['nullable', 'string', 'max:80'],
            'dm_number' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.00001'],
        ]);

        $requestedItems = collect($validated['items'])
            ->map(function (array $row): array {
                return [
                    'item_id' => (int) ($row['item_id'] ?? 0),
                    'quantity' => round((float) ($row['quantity'] ?? 0), 5),
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

        $zeroStockIds = $requestedItems
            ->filter(function (array $row) use ($itemRows): bool {
                $stock = round((float) ($itemRows[$row['item_id']]->stock_on_hand ?? 0), 5);

                return $stock <= 0;
            })
            ->pluck('item_id')
            ->all();

        if (! empty($zeroStockIds)) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Cannot submit delivery with zero-stock items.',
            ]);
        }

        $overStockIds = $requestedItems
            ->filter(function (array $row) use ($itemRows): bool {
                $stock = round((float) ($itemRows[$row['item_id']]->stock_on_hand ?? 0), 5);

                return $row['quantity'] > $stock;
            })
            ->pluck('item_id')
            ->all();

        if (! empty($overStockIds)) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Requested quantity exceeds stock on hand for one or more items.',
            ]);
        }

        $authUserId = Auth::id();
        $now = now();
        $numberService = app(DocumentNumberService::class);
        $createdDrNumber = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolvedNumber = $numberService->resolve('DR', $validated['dr_number'] ?? null, $validated['dr_number_suggested'] ?? null);
            $numberService->assertUnique('DR', $resolvedNumber['number']);

            try {
                DB::transaction(function () use ($validated, $requestedItems, $itemRows, $authUserId, $now, $resolvedNumber, &$createdDrNumber): void {
                    $deliveryId = DB::table('deliveries')->insertGetId([
                        'dr_number' => $resolvedNumber['number'],
                        'dr_date' => $validated['dr_date'],
                        'from_name' => trim((string) $validated['from_name']),
                        'from_location' => trim((string) ($validated['from_location'] ?? '')) ?: null,
                        'supplier_id' => (int) $validated['supplier_id'],
                        'to_location' => trim((string) ($validated['to_location'] ?? '')) ?: null,
                        'remarks' => $validated['remarks'] ?? null,
                        'or_number' => trim((string) ($validated['or_number'] ?? '')) ?: null,
                        'dm_number' => trim((string) ($validated['dm_number'] ?? '')) ?: null,
                        'created_by' => $authUserId,
                        'updated_by' => $authUserId,
                        'meta' => json_encode([
                            'source' => 'delivery-create-page',
                            'item_count' => $requestedItems->count(),
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);

                    $detailRows = $requestedItems->map(function (array $row) use ($deliveryId, $itemRows, $authUserId, $now): array {
                        $item = $itemRows[$row['item_id']];

                        return [
                            'delivery_id' => (int) $deliveryId,
                            'item_id' => $row['item_id'],
                            'product_code' => $item->code,
                            'uom' => $item->uom_name,
                            'quantity' => $row['quantity'],
                            'created_by' => $authUserId,
                            'updated_by' => $authUserId,
                            'meta' => json_encode([
                                'stock_on_hand_snapshot' => round((float) ($item->stock_on_hand ?? 0), 5),
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ];
                    })->values()->all();

                    DB::table('delivery_items')->insert($detailRows);

                    $stockLines = DB::table('delivery_items')
                        ->where('delivery_id', $deliveryId)
                        ->whereNull('deleted_at')
                        ->get(['id', 'item_id', 'product_code', 'quantity'])
                        ->map(fn ($row): array => [
                            'item_id' => (int) $row->item_id,
                            'product_code' => (string) $row->product_code,
                            'quantity' => (float) $row->quantity,
                            'reference_line_id' => (int) $row->id,
                        ])
                        ->all();

                    app(StockService::class)->applyDeliveryIssue(
                        deliveryId: (int) $deliveryId,
                        movementDate: $validated['dr_date'],
                        lines: $stockLines,
                        userId: $authUserId,
                    );

                    $createdDrNumber = $resolvedNumber['number'];
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
            ->route('deliveries.index')
            ->with('success', "Delivery {$createdDrNumber} has been created successfully.");
    }

    public function destroy(string $delivery)
    {
        $deliveryId = (int) $delivery;
        $now = now();
        $authUserId = Auth::id();

        $deleted = DB::transaction(function () use ($deliveryId, $now, $authUserId): int {
            $delivery = DB::table('deliveries')
                ->where('id', $deliveryId)
                ->whereNull('deleted_at')
                ->first(['id', 'dr_date', 'dr_number']);

            if (! $delivery) {
                return 0;
            }

            app(StockService::class)->purgeDocumentMovementsAndRechain(
                StockService::REF_DELIVERY,
                $deliveryId,
            );

            DB::table('delivery_items')
                ->where('delivery_id', $deliveryId)
                ->whereNull('deleted_at')
                ->update([
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);

            return DB::table('deliveries')
                ->where('id', $deliveryId)
                ->whereNull('deleted_at')
                ->update([
                    'dr_number' => 'DELETED-'.$deliveryId,
                    'updated_by' => $authUserId,
                    'updated_at' => $now,
                    'deleted_at' => $now,
                ]);
        });

        if ($deleted === 0) {
            return redirect()->back()->with('error', 'Delivery not found or already deleted.');
        }

        return redirect()->back()->with('success', 'Delivery deleted successfully. The DR number was released for reuse.');
    }

    private function paginateDeliveries(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $fromLocation = mb_strtolower(trim((string) ($filters['from_location'] ?? '')));
        $toLocation = mb_strtolower(trim((string) ($filters['to_location'] ?? '')));
        $drStart = trim((string) ($filters['dr_start'] ?? ''));
        $drEnd = trim((string) ($filters['dr_end'] ?? ''));

        $keywordLike = "%{$keyword}%";

        $summaryQuery = DB::table('delivery_items')
            ->whereNull('deleted_at')
            ->selectRaw('delivery_id, COUNT(*) as item_count, SUM(quantity) as total_quantity')
            ->groupBy('delivery_id');

        $query = DB::table('deliveries as d')
            ->leftJoin('users as creator', 'creator.id', '=', 'd.created_by')
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->leftJoinSub($summaryQuery, 'di_summary', function ($join) {
                $join->on('di_summary.delivery_id', '=', 'd.id');
            })
            ->whereNull('d.deleted_at')
            ->select([
                'd.id',
                'd.dr_number',
                'd.dr_date',
                'd.from_name',
                'd.from_location',
                'd.supplier_id',
                's.name as to_name',
                'd.to_location',
                'd.remarks',
                'd.or_number',
                'd.dm_number',
                'creator.name as created_by_name',
                DB::raw('COALESCE(di_summary.item_count, 0) as item_count'),
                DB::raw('COALESCE(di_summary.total_quantity, 0) as total_quantity'),
            ])
            ->when($keyword !== '', function ($subQuery) use ($keywordLike) {
                $subQuery->where(function ($whereQuery) use ($keywordLike) {
                    $whereQuery
                        ->whereRaw('LOWER(d.dr_number) LIKE ?', [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.from_name, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.from_location, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(s.name, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.to_location, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.remarks, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.or_number, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(d.dm_number, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(creator.name, '')) LIKE ?", [$keywordLike]);
                });
            })
            ->when($fromLocation !== '', function ($subQuery) use ($fromLocation) {
                $subQuery->whereRaw("LOWER(COALESCE(d.from_location, '')) = ?", [$fromLocation]);
            })
            ->when($toLocation !== '', function ($subQuery) use ($toLocation) {
                $subQuery->whereRaw("LOWER(COALESCE(d.to_location, '')) = ?", [$toLocation]);
            })
            ->when($drStart !== '', function ($subQuery) use ($drStart) {
                $subQuery->whereDate('d.dr_date', '>=', $drStart);
            })
            ->when($drEnd !== '', function ($subQuery) use ($drEnd) {
                $subQuery->whereDate('d.dr_date', '<=', $drEnd);
            })
            ->orderByDesc('d.created_at');

        if (! $this->isSqlServerConnection()) {
            return $query
                ->paginate($perPage)
                ->withQueryString();
        }

        $total = (clone $query)->reorder()->count();
        $startRow = (($currentPage - 1) * $perPage) + 1;
        $endRow = $currentPage * $perPage;

        $rankedIdsQuery = (clone $query)
            ->reorder()
            ->select('d.id')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY d.created_at DESC) as row_num');

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_deliveries')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = DB::table('deliveries as d')
                ->leftJoin('users as creator', 'creator.id', '=', 'd.created_by')
                ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
                ->leftJoinSub($summaryQuery, 'di_summary', function ($join) {
                    $join->on('di_summary.delivery_id', '=', 'd.id');
                })
                ->whereNull('d.deleted_at')
                ->whereIn('d.id', $ids)
                ->select([
                    'd.id',
                    'd.dr_number',
                    'd.dr_date',
                    'd.from_name',
                    'd.from_location',
                    'd.supplier_id',
                    's.name as to_name',
                    'd.to_location',
                    'd.remarks',
                    'd.or_number',
                    'd.dm_number',
                    'creator.name as created_by_name',
                    DB::raw('COALESCE(di_summary.item_count, 0) as item_count'),
                    DB::raw('COALESCE(di_summary.total_quantity, 0) as total_quantity'),
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

    /**
     * @param  array<int, int>  $deliveryIds
     * @return array<int, array<int, object>>
     */
    private function groupDeliveryItems(array $deliveryIds): array
    {
        if (empty($deliveryIds)) {
            return [];
        }

        $rows = DB::table('delivery_items as di')
            ->leftJoin('items as i', 'i.id', '=', 'di.item_id')
            ->whereIn('di.delivery_id', $deliveryIds)
            ->whereNull('di.deleted_at')
            ->orderBy('di.delivery_id')
            ->orderBy('di.id')
            ->select([
                'di.id',
                'di.delivery_id',
                'di.item_id',
                'di.product_code',
                'di.uom',
                'di.quantity',
                'i.name as item_name',
                'i.code as item_code',
            ])
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $deliveryId = (int) $row->delivery_id;
            $grouped[$deliveryId][] = $row;
        }

        return $grouped;
    }
}
