<?php

namespace App\Support\Concerns;

use App\Models\Item;
use App\Models\StockInventory;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait SearchesStockCorrectionItems
{
    public function searchStockCorrectionItems(Request $request): JsonResponse
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

        $balances = StockInventory::query()
            ->where('wh_code', StockService::DEFAULT_WH_CODE)
            ->where('is_delete', false)
            ->whereIn('item_id', $items->pluck('id'))
            ->pluck('balance', 'item_id');

        return response()->json([
            'items' => $items->map(fn (Item $item): array => [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'name' => (string) $item->name,
                'unit' => $item->unit?->name ?? 'PCS',
                'balance' => round((float) ($balances[$item->id] ?? 0), 5),
            ])->values()->all(),
        ]);
    }
}
