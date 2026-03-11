<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StoreWithdrawalController extends Controller
{
    /**
     * Display stores withdrawal list (scaffold mode using mock rows).
     */
    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'department' => trim((string) $request->query('department', '')),
            'sws_start' => trim((string) $request->query('sws_start', '')),
            'sws_end' => trim((string) $request->query('sws_end', '')),
        ];

        $storeWithdrawals = $this->paginateMockStoreWithdrawals($filters, 10);

        $departmentOptions = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->get();

        return view('pages.stores-withdrawals.index', [
            'storeWithdrawals' => $storeWithdrawals,
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

    /**
     * Temporary store endpoint while DB implementation is pending.
     */
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

        if ($validated['type'] === 'NORMAL') {
            $zeroStockIds = Item::query()
                ->whereIn('id', collect($validated['items'])->pluck('item_id')->all())
                ->where('stock_on_hand', '<=', 0)
                ->pluck('id');

            if ($zeroStockIds->isNotEmpty()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'items' => 'Normal type does not allow zero-stock items. Use Confirmatory if needed.',
                    ]);
            }
        }

        return redirect()
            ->route('stores-withdrawals.index')
            ->with('success', 'Stores Withdrawal scaffold is saved for UI validation. Database persistence will be implemented after your feedback.');
    }

    public function show(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal detail page is not implemented yet (scaffold stage).');
    }

    public function edit(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal edit page is not implemented yet (scaffold stage).');
    }

    public function update(Request $request, string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal update is not implemented yet (scaffold stage).');
    }

    public function destroy(string $storeWithdrawal)
    {
        return redirect()
            ->route('stores-withdrawals.index')
            ->with('info', 'Stores Withdrawal delete is not implemented yet (scaffold stage).');
    }

    /**
     * SQL Server-compatible pagination for mock stores withdrawals.
     */
    private function paginateMockStoreWithdrawals(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $department = mb_strtolower(trim((string) ($filters['department'] ?? '')));
        $swsStart = trim((string) ($filters['sws_start'] ?? ''));
        $swsEnd = trim((string) ($filters['sws_end'] ?? ''));

        $rows = $this->mockStoreWithdrawalRows();

        $filtered = $rows->filter(function (array $row) use ($keyword, $department, $swsStart, $swsEnd) {
            $keywordMatched = true;
            if ($keyword !== '') {
                $keywordMatched = str_contains(mb_strtolower($row['sws_number']), $keyword)
                    || str_contains(mb_strtolower($row['department_code']), $keyword)
                    || str_contains(mb_strtolower($row['department_name']), $keyword)
                    || str_contains(mb_strtolower($row['info']), $keyword)
                    || str_contains(mb_strtolower($row['created_by_name']), $keyword);
            }

            $departmentMatched = $department === ''
                || mb_strtolower($row['department_code']) === $department;

            $dateMatched = true;
            if ($swsStart !== '' && $row['sws_date'] < $swsStart) {
                $dateMatched = false;
            }
            if ($swsEnd !== '' && $row['sws_date'] > $swsEnd) {
                $dateMatched = false;
            }

            return $keywordMatched && $departmentMatched && $dateMatched;
        })->values();

        $total = $filtered->count();
        $offset = ($currentPage - 1) * $perPage;
        $items = $filtered->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    private function mockStoreWithdrawalRows()
    {
        $departments = Department::query()
            ->select(['code', 'name'])
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn ($department) => [
                'code' => (string) $department->code,
                'name' => (string) $department->name,
            ])
            ->values();

        if ($departments->isEmpty()) {
            $departments = collect([
                ['code' => 'GEN', 'name' => 'General'],
                ['code' => 'WHS', 'name' => 'Warehouse'],
            ]);
        }

        $creators = [
            'Budi Santoso',
            'Ani Wulandari',
            'Dimas Pratama',
            'Rina Kurnia',
        ];

        return collect(range(1, 28))
            ->map(function (int $index) use ($departments, $creators) {
                $department = $departments[($index - 1) % $departments->count()];
                $date = now()->copy()->subDays($index - 1);

                return [
                    'id' => $index,
                    'sws_number' => sprintf('SWS-%s-%s', $department['code'], $date->format('ymd') . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT)),
                    'sws_date' => $date->format('Y-m-d'),
                    'department_code' => $department['code'],
                    'department_name' => $department['name'],
                    'info' => $index % 2 === 0
                        ? 'Daily operational material withdrawal.'
                        : 'Consumable withdrawal for maintenance needs.',
                    'created_by_name' => $creators[($index - 1) % count($creators)],
                ];
            })
            ->sortByDesc('sws_date')
            ->values();
    }
}
