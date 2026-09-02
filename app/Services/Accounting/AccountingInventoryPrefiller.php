<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryTransactionLine;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\ReceivingReport;
use App\Models\StockInventory;
use App\Models\TransferSlip;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingInventoryPrefiller
{
    /**
     * @return array{
     *     header: array<string, mixed>,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function buildFromSource(Model $source, int $categoryId): array
    {
        return match (true) {
            $source instanceof ReceivingReport => $this->fromReceivingReport($source, $categoryId),
            $source instanceof TransferSlip => $this->fromTransferSlip($source, $categoryId),
            $source instanceof Delivery => $this->fromDelivery($source, $categoryId),
            default => throw new InvalidArgumentException('Unsupported source model ['.$source::class.'].'),
        };
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function fromReceivingReport(ReceivingReport $receivingReport, int $categoryId): array
    {
        $receivingReport->loadMissing([
            'purchaseOrder.supplier',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
        ]);

        $lines = [];
        foreach ($receivingReport->items as $index => $reportItem) {
            $poItem = $reportItem->purchaseOrderItem;
            $item = $poItem?->item;
            if ($item === null || (int) $item->category_id !== $categoryId) {
                continue;
            }

            $quantity = (float) $reportItem->qty_good;
            if ($quantity <= 0) {
                continue;
            }

            $unitCost = (float) ($poItem->unit_price ?? 0);
            $lines[] = $this->linePayload(
                item: $item,
                direction: AccountingInventoryTransactionLine::DIRECTION_IN,
                quantity: $quantity,
                unitCost: $unitCost,
                sortOrder: $index,
                categoryId: $categoryId,
            );
        }

        return [
            'header' => [
                'category_id' => $categoryId,
                'doc_type' => 'RR',
                'doc_number' => trim((string) $receivingReport->rr_number),
                'doc_date' => $receivingReport->received_date?->toDateString() ?? now()->toDateString(),
                'po_number' => $receivingReport->purchaseOrder?->po_number,
                'party_code' => $receivingReport->purchaseOrder?->supplier?->code,
                'party_name' => $receivingReport->purchaseOrder?->supplier?->name,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function fromTransferSlip(TransferSlip $transferSlip, int $categoryId): array
    {
        $transferSlip->loadMissing(['items.item.unit', 'items.item.category']);

        $lines = [];
        foreach ($transferSlip->items as $index => $slipItem) {
            $item = $slipItem->item;
            if ($item === null || (int) $item->category_id !== $categoryId) {
                continue;
            }

            $quantity = (float) $slipItem->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $unitCost = $this->operationalAveragePrice((int) $item->id);
            $lines[] = $this->linePayload(
                item: $item,
                direction: AccountingInventoryTransactionLine::DIRECTION_OUT,
                quantity: $quantity,
                unitCost: $unitCost,
                sortOrder: $index,
                categoryId: $categoryId,
            );
        }

        return [
            'header' => [
                'category_id' => $categoryId,
                'doc_type' => 'TS',
                'doc_number' => trim((string) $transferSlip->ts_number),
                'doc_date' => $transferSlip->ts_date?->toDateString() ?? now()->toDateString(),
                'po_number' => null,
                'party_code' => null,
                'party_name' => $transferSlip->transfer_to,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function fromDelivery(Delivery $delivery, int $categoryId): array
    {
        $delivery->loadMissing('supplier');

        $rows = DB::table('delivery_items as di')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->leftJoin('unit_of_measures as uom', 'uom.id', '=', 'i.unit_of_measure_id')
            ->where('di.delivery_id', $delivery->id)
            ->whereNull('di.deleted_at')
            ->where('i.category_id', $categoryId)
            ->orderBy('di.id')
            ->get([
                'di.id',
                'di.item_id',
                'di.quantity',
                'di.meta',
                'i.code',
                'i.name',
                'i.unit_of_measure_id',
                'uom.name as unit_name',
            ]);

        $lines = [];
        foreach ($rows as $index => $row) {
            $quantity = (float) $row->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $meta = is_string($row->meta) ? json_decode($row->meta, true) : (array) ($row->meta ?? []);
            $unitCost = (float) ($meta['legacy_unit_cost'] ?? $this->operationalAveragePrice((int) $row->item_id));

            $item = Item::query()->with('unit')->find((int) $row->item_id);
            if ($item === null) {
                continue;
            }

            $lines[] = $this->linePayload(
                item: $item,
                direction: AccountingInventoryTransactionLine::DIRECTION_OUT,
                quantity: $quantity,
                unitCost: $unitCost,
                sortOrder: $index,
                categoryId: $categoryId,
            );
        }

        return [
            'header' => [
                'category_id' => $categoryId,
                'doc_type' => 'DR',
                'doc_number' => trim((string) $delivery->dr_number),
                'doc_date' => $delivery->dr_date?->toDateString() ?? now()->toDateString(),
                'po_number' => null,
                'party_code' => $delivery->supplier?->code,
                'party_name' => $delivery->supplier?->name ?? $delivery->from_name,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(
        Item $item,
        string $direction,
        float $quantity,
        float $unitCost,
        int $sortOrder,
        int $categoryId,
    ): array {
        $quantity = round($quantity, 5);
        $unitCost = round($unitCost, 4);
        $amount = round($quantity * $unitCost, 4);

        return [
            'item_id' => (int) $item->id,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_of_measure_id' => $item->unit_of_measure_id,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'prefill_quantity' => $quantity,
            'prefill_unit_cost' => $unitCost,
            'available_qty_snapshot' => null,
            'sort_order' => $sortOrder,
            'category_id' => $categoryId,
        ];
    }

    private function operationalAveragePrice(int $itemId): float
    {
        $average = StockInventory::query()
            ->where('item_id', $itemId)
            ->where('wh_code', StockService::DEFAULT_WH_CODE)
            ->where('is_delete', false)
            ->value('average_price');

        return round((float) ($average ?? 0), 4);
    }

    /**
     * @param  Collection<int, ItemCategory>  $categories
     * @return list<int>
     */
    public function resolveCategoryIdsForSource(Model $source, Collection $categories): array
    {
        $itemCategoryIds = match (true) {
            $source instanceof ReceivingReport => $this->categoryIdsFromReceivingReport($source),
            $source instanceof TransferSlip => $this->categoryIdsFromTransferSlip($source),
            $source instanceof Delivery => $this->categoryIdsFromDelivery($source),
            default => [],
        };

        $activeCategoryIds = $categories->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($itemCategoryIds, $activeCategoryIds));
    }

    /**
     * @return list<int>
     */
    private function categoryIdsFromReceivingReport(ReceivingReport $receivingReport): array
    {
        return DB::table('receiving_report_items as rri')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->where('rri.receiving_report_id', $receivingReport->id)
            ->whereNull('rri.deleted_at')
            ->where('rri.qty_good', '>', 0)
            ->distinct()
            ->pluck('i.category_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function categoryIdsFromTransferSlip(TransferSlip $transferSlip): array
    {
        return DB::table('transfer_slip_items as tsi')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->where('tsi.transfer_slip_id', $transferSlip->id)
            ->whereNull('tsi.deleted_at')
            ->where('tsi.quantity', '>', 0)
            ->distinct()
            ->pluck('i.category_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function categoryIdsFromDelivery(Delivery $delivery): array
    {
        return DB::table('delivery_items as di')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->where('di.delivery_id', $delivery->id)
            ->whereNull('di.deleted_at')
            ->where('di.quantity', '>', 0)
            ->distinct()
            ->pluck('i.category_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
