<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ReceivingReport;
use App\Models\StockBalance;
use App\Models\StockInventory;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class StockService
{
    private const DEFAULT_WH_CODE = 'MAIN';

    /**
     * @param  array<int|string, array<string, mixed>>  $currentLines
     * @param  array<int|string, array<string, mixed>>  $previousLines
     */
    public function applyReceivingReportAdjustment(
        ReceivingReport $receivingReport,
        array $currentLines,
        array $previousLines = [],
        ?int $userId = null
    ): void {
        $currentLines = $this->normalizeLines($currentLines);
        $previousLines = $this->normalizeLines($previousLines);
        $movementDate = $receivingReport->received_date instanceof CarbonInterface
            ? $receivingReport->received_date->toDateString()
            : (string) $receivingReport->received_date;

        $poItemIds = array_unique(array_merge(array_keys($currentLines), array_keys($previousLines)));

        foreach ($poItemIds as $purchaseOrderItemId) {
            $current = $currentLines[$purchaseOrderItemId] ?? null;
            $previous = $previousLines[$purchaseOrderItemId] ?? null;

            $currentQty = (float) ($current['qty_good'] ?? 0);
            $previousQty = (float) ($previous['qty_good'] ?? 0);
            $deltaQty = round($currentQty - $previousQty, 2);

            if (abs($deltaQty) < 0.01) {
                continue;
            }

            $line = $current ?? $previous;
            if (! $line || empty($line['item_id']) || empty($line['product_code'])) {
                continue;
            }

            $this->applyInventoryMovement(
                itemId: (int) $line['item_id'],
                productCode: (string) $line['product_code'],
                whCode: (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE),
                deltaQty: $deltaQty,
                movementPrice: (float) ($line['unit_price'] ?? 0),
                movementDate: $movementDate,
                referenceType: 'receiving_report',
                referenceId: $receivingReport->id,
                referenceLineId: (int) $purchaseOrderItemId,
                userId: $userId,
            );
        }
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $key => $line) {
            if (! is_array($line)) {
                continue;
            }

            $purchaseOrderItemId = isset($line['purchase_order_item_id'])
                ? (int) $line['purchase_order_item_id']
                : (int) $key;

            if ($purchaseOrderItemId <= 0) {
                continue;
            }

            $normalized[$purchaseOrderItemId] = [
                'purchase_order_item_id' => $purchaseOrderItemId,
                'item_id' => (int) ($line['item_id'] ?? 0),
                'product_code' => (string) ($line['product_code'] ?? ''),
                'qty_good' => (float) ($line['qty_good'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'wh_code' => (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE),
            ];
        }

        return $normalized;
    }

    private function applyInventoryMovement(
        int $itemId,
        string $productCode,
        string $whCode,
        float $deltaQty,
        float $movementPrice,
        string $movementDate,
        string $referenceType,
        int $referenceId,
        int $referenceLineId,
        ?int $userId = null
    ): void {
        $stockInventory = StockInventory::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->lockForUpdate()
            ->first();

        if (! $stockInventory) {
            $stockInventory = StockInventory::create([
                'item_id' => $itemId,
                'product_code' => $productCode,
                'wh_code' => $whCode,
                'balance' => 0,
                'start_balance' => 0,
                'average_price' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $stockInventory = StockInventory::query()
                ->whereKey($stockInventory->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $beginQty = (float) $stockInventory->balance;
        $beginAvgPrice = (float) $stockInventory->average_price;

        $qtyIn1 = 0.0;
        $qtyOut1 = 0.0;
        $accQtyIn1 = 0.0;
        $accAveragePriceIn1 = 0.0;
        $endQty = $beginQty;
        $endAvgPrice = $beginAvgPrice;

        if ($deltaQty > 0) {
            $qtyIn1 = $deltaQty;
            $accQtyIn1 = $deltaQty;
            $accAveragePriceIn1 = $movementPrice;

            $endQty = $beginQty + $deltaQty;
            $endAvgPrice = $endQty > 0
                ? (($beginQty * $beginAvgPrice) + ($deltaQty * $movementPrice)) / $endQty
                : 0;
        } else {
            $qtyOut1 = abs($deltaQty);
            $endQty = $beginQty - $qtyOut1;

            if ($endQty < -0.00001) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for product {$productCode} in warehouse {$whCode}.",
                ]);
            }

            if ($endQty < 0) {
                $endQty = 0;
            }

            $endAvgPrice = $endQty > 0 ? $beginAvgPrice : 0;
        }

        $stockInventory->balance = round($endQty, 2);
        $stockInventory->average_price = round($endAvgPrice, 2);
        $stockInventory->product_code = $productCode;
        $stockInventory->updated_by = $userId;
        $stockInventory->save();

        StockBalance::create([
            'date' => $movementDate,
            'item_id' => $itemId,
            'product_code' => $productCode,
            'wh_code' => $whCode,
            'begin' => round($beginQty, 2),
            'qty_in1' => round($qtyIn1, 2),
            'qty_in2' => 0,
            'qty_in3' => 0,
            'qty_out1' => round($qtyOut1, 2),
            'qty_out2' => 0,
            'qty_out3' => 0,
            'end' => round($endQty, 2),
            'acc_qty_in1' => round($accQtyIn1, 2),
            'acc_average_price_in1' => round($accAveragePriceIn1, 2),
            'acc_qty_total' => round($endQty, 2),
            'acc_average_price_total' => round($endAvgPrice, 2),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_line_id' => $referenceLineId,
            'created_by' => $userId,
        ]);

        $this->syncItemStockOnHand($itemId);
    }

    private function syncItemStockOnHand(int $itemId): void
    {
        $totalBalance = (float) StockInventory::query()
            ->where('item_id', $itemId)
            ->where('is_active', true)
            ->where('is_delete', false)
            ->sum('balance');

        Item::query()->whereKey($itemId)->update([
            'stock_on_hand' => (int) round($totalBalance),
        ]);
    }
}
