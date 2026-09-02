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
        $categoryId = (int) $request->query('category_id', 0);

        if (mb_strlen($q) < 2 || $categoryId <= 0) {
            return response()->json(['items' => []]);
        }

        $like = '%'.mb_strtolower($q).'%';

        $items = Item::query()
            ->with(['unit:id,name'])
            ->where('is_active', true)
            ->where('category_id', $categoryId)
            ->where(function ($query) use ($like) {
                $query->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            })
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'name', 'unit_of_measure_id']);

        return response()->json([
            'items' => $items->map(fn (Item $item): array => [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'name' => (string) $item->name,
                'unit' => $item->unit?->name ?? 'PCS',
                'unit_of_measure_id' => $item->unit_of_measure_id,
                'balance' => $inventoryService->getAvailableQty($categoryId, (int) $item->id),
                'unit_cost' => $inventoryService->getWeightedUnitCost($categoryId, (int) $item->id),
            ])->values()->all(),
        ]);
    }
}
