<?php

namespace App\Support\Concerns;

use App\Models\Item;
use App\Services\Accounting\AccountingInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait SearchesAccountingInventoryItems
{
    public function searchAccountingInventoryItems(Request $request, AccountingInventoryService $inventoryService): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = trim((string) $request->query('category_id', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $like = '%'.mb_strtolower($q).'%';

        $items = Item::query()
            ->with(['unit:id,name'])
            ->where('is_active', true)
            ->when($categoryId !== '' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->where('category_id', (int) $categoryId);
            })
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            })
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'name', 'unit_of_measure_id']);

        $categoryIdInt = is_numeric($categoryId) ? (int) $categoryId : 0;

        return response()->json([
            'items' => $items->map(function (Item $item) use ($categoryIdInt, $inventoryService): array {
                return [
                    'id' => (int) $item->id,
                    'code' => (string) $item->code,
                    'name' => (string) $item->name,
                    'unit' => $item->unit?->name ?? 'PCS',
                    'unit_of_measure_id' => $item->unit_of_measure_id,
                    'balance' => $categoryIdInt > 0
                        ? $inventoryService->getAvailableQty($categoryIdInt, (int) $item->id)
                        : 0,
                    'unit_cost' => $categoryIdInt > 0
                        ? $inventoryService->getWeightedUnitCost($categoryIdInt, (int) $item->id)
                        : 0,
                ];
            })->values()->all(),
        ]);
    }
}
