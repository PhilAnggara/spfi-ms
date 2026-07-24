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
    public const REF_RECEIVING_REPORT = 'receiving_report';

    public const REF_TRANSFER_SLIP = 'transfer_slip';

    public const REF_DELIVERY = 'delivery';

    public const OUT_BUCKET_TS = 'qty_out1';

    public const OUT_BUCKET_DR = 'qty_out3';

    private const DEFAULT_WH_CODE = 'MAIN';

    /**
     * @param  array<int|string, array<string, mixed>>  $currentLines
     * @param  array<int|string, array<string, mixed>>  $previousLines
     */
    public function applyReceivingReportAdjustment(
        ReceivingReport $receivingReport,
        array $currentLines,
        array $previousLines = [],
        ?int $userId = null,
        bool $allowNegativeBalance = false
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
                referenceType: self::REF_RECEIVING_REPORT,
                referenceId: $receivingReport->id,
                referenceLineId: (int) $purchaseOrderItemId,
                userId: $userId,
                outBucket: self::OUT_BUCKET_TS,
                allowNegativeBalance: $allowNegativeBalance,
            );
        }
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, quantity: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function applyTransferSlipIssue(
        int $transferSlipId,
        string $movementDate,
        array $lines,
        ?int $userId = null,
        bool $allowNegativeBalance = false
    ): void {
        $this->applyDocumentIssue(
            referenceType: self::REF_TRANSFER_SLIP,
            referenceId: $transferSlipId,
            movementDate: $movementDate,
            lines: $lines,
            outBucket: self::OUT_BUCKET_TS,
            userId: $userId,
            allowNegativeBalance: $allowNegativeBalance,
            reverse: false,
        );
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, quantity: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function reverseTransferSlipIssue(
        int $transferSlipId,
        string $movementDate,
        array $lines,
        ?int $userId = null
    ): void {
        $this->applyDocumentIssue(
            referenceType: self::REF_TRANSFER_SLIP,
            referenceId: $transferSlipId,
            movementDate: $movementDate,
            lines: $lines,
            outBucket: self::OUT_BUCKET_TS,
            userId: $userId,
            allowNegativeBalance: true,
            reverse: true,
        );
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, quantity: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function applyDeliveryIssue(
        int $deliveryId,
        string $movementDate,
        array $lines,
        ?int $userId = null,
        bool $allowNegativeBalance = false
    ): void {
        $this->applyDocumentIssue(
            referenceType: self::REF_DELIVERY,
            referenceId: $deliveryId,
            movementDate: $movementDate,
            lines: $lines,
            outBucket: self::OUT_BUCKET_DR,
            userId: $userId,
            allowNegativeBalance: $allowNegativeBalance,
            reverse: false,
        );
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, quantity: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function reverseDeliveryIssue(
        int $deliveryId,
        string $movementDate,
        array $lines,
        ?int $userId = null
    ): void {
        $this->applyDocumentIssue(
            referenceType: self::REF_DELIVERY,
            referenceId: $deliveryId,
            movementDate: $movementDate,
            lines: $lines,
            outBucket: self::OUT_BUCKET_DR,
            userId: $userId,
            allowNegativeBalance: true,
            reverse: true,
        );
    }

    public function hasPostedReference(string $referenceType, int $referenceId, int $referenceLineId): bool
    {
        return StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('reference_line_id', $referenceLineId)
            ->exists();
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, quantity: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    private function applyDocumentIssue(
        string $referenceType,
        int $referenceId,
        string $movementDate,
        array $lines,
        string $outBucket,
        ?int $userId,
        bool $allowNegativeBalance,
        bool $reverse
    ): void {
        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $productCode = (string) ($line['product_code'] ?? '');
            $referenceLineId = (int) ($line['reference_line_id'] ?? 0);
            $quantity = round((float) ($line['quantity'] ?? 0), 2);

            if ($itemId <= 0 || $productCode === '' || $referenceLineId <= 0 || abs($quantity) < 0.01) {
                continue;
            }

            $netIssued = $this->netIssuedQuantity($referenceType, $referenceId, $referenceLineId, $outBucket);

            if ($reverse) {
                if ($netIssued < 0.01) {
                    continue;
                }

                $issueQty = -abs($netIssued);
            } else {
                if ($netIssued >= 0.01) {
                    continue;
                }

                $issueQty = abs($quantity);
            }

            $this->applyIssueMovement(
                itemId: $itemId,
                productCode: $productCode,
                whCode: (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE),
                issueQty: $issueQty,
                movementDate: $movementDate,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceLineId: $referenceLineId,
                userId: $userId,
                outBucket: $outBucket,
                allowNegativeBalance: $allowNegativeBalance,
            );
        }
    }

    private function netIssuedQuantity(
        string $referenceType,
        int $referenceId,
        int $referenceLineId,
        string $outBucket
    ): float {
        return round((float) StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('reference_line_id', $referenceLineId)
            ->sum($outBucket), 2);
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
        ?int $userId = null,
        string $outBucket = self::OUT_BUCKET_TS,
        bool $allowNegativeBalance = false
    ): void {
        if ($deltaQty > 0) {
            $this->writeMovement(
                itemId: $itemId,
                productCode: $productCode,
                whCode: $whCode,
                beginQty: null,
                qtyIn1: $deltaQty,
                qtyOut1: 0,
                qtyOut3: 0,
                movementPrice: $movementPrice,
                isReceipt: true,
                movementDate: $movementDate,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceLineId: $referenceLineId,
                userId: $userId,
                allowNegativeBalance: $allowNegativeBalance,
            );

            return;
        }

        $outQty = abs($deltaQty);
        $this->applyIssueMovement(
            itemId: $itemId,
            productCode: $productCode,
            whCode: $whCode,
            issueQty: $outQty,
            movementDate: $movementDate,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceLineId: $referenceLineId,
            userId: $userId,
            outBucket: $outBucket,
            allowNegativeBalance: $allowNegativeBalance,
        );
    }

    /**
     * @param  float  $issueQty  Positive reduces stock (out); negative restores stock (negative out).
     */
    private function applyIssueMovement(
        int $itemId,
        string $productCode,
        string $whCode,
        float $issueQty,
        string $movementDate,
        string $referenceType,
        int $referenceId,
        int $referenceLineId,
        ?int $userId,
        string $outBucket,
        bool $allowNegativeBalance
    ): void {
        $qtyOut1 = $outBucket === self::OUT_BUCKET_TS ? $issueQty : 0.0;
        $qtyOut3 = $outBucket === self::OUT_BUCKET_DR ? $issueQty : 0.0;

        $this->writeMovement(
            itemId: $itemId,
            productCode: $productCode,
            whCode: $whCode,
            beginQty: null,
            qtyIn1: 0,
            qtyOut1: $qtyOut1,
            qtyOut3: $qtyOut3,
            movementPrice: 0,
            isReceipt: false,
            movementDate: $movementDate,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceLineId: $referenceLineId,
            userId: $userId,
            allowNegativeBalance: $allowNegativeBalance,
        );
    }

    private function writeMovement(
        int $itemId,
        string $productCode,
        string $whCode,
        ?float $beginQty,
        float $qtyIn1,
        float $qtyOut1,
        float $qtyOut3,
        float $movementPrice,
        bool $isReceipt,
        string $movementDate,
        string $referenceType,
        int $referenceId,
        int $referenceLineId,
        ?int $userId,
        bool $allowNegativeBalance
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

        $begin = $beginQty ?? (float) $stockInventory->balance;
        $beginAvgPrice = (float) $stockInventory->average_price;
        $netOut = $qtyOut1 + $qtyOut3;
        $endQty = $begin + $qtyIn1 - $netOut;
        $endAvgPrice = $beginAvgPrice;
        $accQtyIn1 = 0.0;
        $accAveragePriceIn1 = 0.0;

        if ($isReceipt && $qtyIn1 > 0) {
            $accQtyIn1 = $qtyIn1;
            $accAveragePriceIn1 = $movementPrice;
            $endAvgPrice = $endQty > 0
                ? (($begin * $beginAvgPrice) + ($qtyIn1 * $movementPrice)) / $endQty
                : 0;
        } else {
            if ($endQty < -0.00001 && ! $allowNegativeBalance) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for product {$productCode} in warehouse {$whCode}.",
                ]);
            }

            if ($endQty < 0 && ! $allowNegativeBalance) {
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
            'begin' => round($begin, 2),
            'qty_in1' => round($qtyIn1, 2),
            'qty_in2' => 0,
            'qty_in3' => 0,
            'qty_out1' => round($qtyOut1, 2),
            'qty_out2' => 0,
            'qty_out3' => round($qtyOut3, 2),
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
