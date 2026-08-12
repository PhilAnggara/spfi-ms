<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ReceivingReport;
use App\Models\StockBalance;
use App\Models\StockInventory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockService
{
    public const REF_RECEIVING_REPORT = 'receiving_report';

    public const REF_TRANSFER_SLIP = 'transfer_slip';

    public const REF_DELIVERY = 'delivery';

    public const REF_STOCK_ADJUSTMENT = 'stock_adjustment';

    public const REF_OPENING_BALANCE_CORRECTION = 'opening_balance_correction';

    public const OUT_BUCKET_TS = 'qty_out1';

    public const OUT_BUCKET_ADJ = 'qty_out2';

    public const OUT_BUCKET_DR = 'qty_out3';

    public const IN_BUCKET_ADJ = 'qty_in2';

    public const DEFAULT_WH_CODE = 'MAIN';

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
        $rechainTargets = [];

        foreach ($poItemIds as $purchaseOrderItemId) {
            $current = $currentLines[$purchaseOrderItemId] ?? null;
            $previous = $previousLines[$purchaseOrderItemId] ?? null;

            $currentQty = (float) ($current['qty_good'] ?? 0);
            $previousQty = (float) ($previous['qty_good'] ?? 0);
            $deltaQty = round($currentQty - $previousQty, 5);

            if (abs($deltaQty) < 0.00001) {
                continue;
            }

            $line = $current ?? $previous;
            if (! $line || empty($line['item_id']) || empty($line['product_code'])) {
                continue;
            }

            $whCode = (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE);

            $this->applyInventoryMovement(
                itemId: (int) $line['item_id'],
                productCode: (string) $line['product_code'],
                whCode: $whCode,
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

            $rechainTargets[] = [
                'item_id' => (int) $line['item_id'],
                'wh_code' => $whCode,
                'from_date' => $movementDate,
            ];
        }

        $this->rechainLedgerTargets($rechainTargets);
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

    /**
     * @param  array<int, array{item_id: int, product_code: string, delta_qty: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function applyStockAdjustment(
        int $stockAdjustmentId,
        string $movementDate,
        array $lines,
        ?int $userId = null,
        bool $allowNegativeBalance = false,
        string $referenceType = self::REF_STOCK_ADJUSTMENT
    ): void {
        $rechainTargets = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $productCode = (string) ($line['product_code'] ?? '');
            $referenceLineId = (int) ($line['reference_line_id'] ?? 0);
            $deltaQty = round((float) ($line['delta_qty'] ?? 0), 5);
            $whCode = (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE);

            if ($itemId <= 0 || $productCode === '' || $referenceLineId <= 0 || abs($deltaQty) < 0.00001) {
                continue;
            }

            $this->applyAdjustmentDelta(
                itemId: $itemId,
                productCode: $productCode,
                whCode: $whCode,
                deltaQty: $deltaQty,
                movementDate: $movementDate,
                referenceType: $referenceType,
                referenceId: $stockAdjustmentId,
                referenceLineId: $referenceLineId,
                userId: $userId,
                allowNegativeBalance: $allowNegativeBalance,
            );

            $rechainTargets[] = [
                'item_id' => $itemId,
                'wh_code' => $whCode,
                'from_date' => $movementDate,
            ];
        }

        $this->rechainLedgerTargets($rechainTargets);
    }

    /**
     * @param  array<int, array{item_id: int, product_code: string, delta_qty: float|int|string, reference_line_id: int, wh_code?: string}>  $lines
     */
    public function reverseStockAdjustment(
        int $stockAdjustmentId,
        string $movementDate,
        array $lines,
        ?int $userId = null,
        string $referenceType = self::REF_STOCK_ADJUSTMENT
    ): void {
        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $productCode = (string) ($line['product_code'] ?? '');
            $referenceLineId = (int) ($line['reference_line_id'] ?? 0);
            $whCode = (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE);

            if ($itemId <= 0 || $productCode === '' || $referenceLineId <= 0) {
                continue;
            }

            $netAdj = $this->netAdjustmentQuantity($referenceType, $stockAdjustmentId, $referenceLineId);

            if (abs($netAdj) < 0.00001) {
                continue;
            }

            $this->applyAdjustmentDelta(
                itemId: $itemId,
                productCode: $productCode,
                whCode: $whCode,
                deltaQty: -1 * $netAdj,
                movementDate: $movementDate,
                referenceType: $referenceType,
                referenceId: $stockAdjustmentId,
                referenceLineId: $referenceLineId,
                userId: $userId,
                allowNegativeBalance: true,
            );
        }
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
     * Remove all ledger rows for a document and undo their net effect on inventory.
     * Used by stock rebuild so reverse+replay does not accumulate duplicate movements.
     */
    public function purgeDocumentMovements(string $referenceType, int $referenceId): int
    {
        $rows = StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $rows->groupBy(fn (StockBalance $row): string => $row->item_id.'|'.$row->wh_code)
            ->each(function ($group): void {
                /** @var Collection<int, StockBalance> $group */
                $first = $group->first();
                $net = $this->netQuantityFromRows($group);

                $inventory = StockInventory::query()
                    ->where('item_id', $first->item_id)
                    ->where('wh_code', $first->wh_code)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    return;
                }

                $inventory->balance = round((float) $inventory->balance - $net, 5);
                $inventory->updated_by = null;
                $inventory->save();

                $this->syncItemStockOnHand((int) $first->item_id);
            });

        StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->delete();

        return $rows->count();
    }

    /**
     * Remove a document's ledger rows, undo inventory, then recast begin/end
     * on remaining rows from the document date forward so IM reports stay consistent.
     */
    public function purgeDocumentMovementsAndRechain(string $referenceType, int $referenceId): int
    {
        $rows = StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $targets = $rows
            ->groupBy(fn (StockBalance $row): string => $row->item_id.'|'.$row->wh_code)
            ->map(function (Collection $group): array {
                /** @var Collection<int, StockBalance> $group */
                $first = $group->first();
                $fromDate = $group
                    ->map(fn (StockBalance $row): string => $row->date instanceof CarbonInterface
                        ? $row->date->toDateString()
                        : (string) $row->date)
                    ->min();

                return [
                    'item_id' => (int) $first->item_id,
                    'wh_code' => (string) $first->wh_code,
                    'from_date' => (string) $fromDate,
                ];
            });

        $purged = $this->purgeDocumentMovements($referenceType, $referenceId);

        $targets->each(function (array $target): void {
            $this->rechainItemLedger(
                itemId: $target['item_id'],
                whCode: $target['wh_code'],
                fromDate: $target['from_date'],
            );
        });

        return $purged;
    }

    /**
     * @param  list<array{item_id: int, wh_code: string, from_date: string}>  $targets
     */
    private function rechainLedgerTargets(array $targets): void
    {
        $seen = [];

        foreach ($targets as $target) {
            $key = $target['item_id'].'|'.$target['wh_code'].'|'.$target['from_date'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $this->rechainItemLedger($target['item_id'], $target['wh_code'], $target['from_date']);
        }
    }

    /**
     * Recast begin/end on remaining ledger rows from a date forward.
     * Does not change qty buckets or inventory on-hand.
     */
    public function rechainItemLedger(int $itemId, string $whCode, string $fromDate): int
    {
        $priorEnd = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->whereDate('date', '<', $fromDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('end');

        $running = round((float) ($priorEnd ?? 0), 5);

        $rows = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->whereDate('date', '>=', $fromDate)
            ->orderBy('date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $netIn = (float) $row->qty_in1 + (float) $row->qty_in2 + (float) $row->qty_in3;
            $netOut = (float) $row->qty_out1 + (float) $row->qty_out2 + (float) $row->qty_out3;
            $begin = $running;
            $end = round($begin + $netIn - $netOut, 5);

            $row->begin = $begin;
            $row->end = $end;
            $row->acc_qty_total = $end;
            $row->save();

            $running = $end;
        }

        return $rows->count();
    }

    /**
     * Purge all ledger rows for an item/warehouse from a given date (inclusive),
     * undoing their net effect on inventory. Used by opening-balance rebuild.
     */
    public function purgeItemMovementsFromDate(int $itemId, string $whCode, string $fromDate): int
    {
        $rows = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->whereDate('date', '>=', $fromDate)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $net = $this->netQuantityFromRows($rows);

        $inventory = StockInventory::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            $inventory->balance = round((float) $inventory->balance - $net, 5);
            $inventory->updated_by = null;
            $inventory->save();
            $this->syncItemStockOnHand($itemId);
        }

        StockBalance::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->whereDate('date', '>=', $fromDate)
            ->delete();

        return $rows->count();
    }

    public function currentBalance(int $itemId, string $whCode = self::DEFAULT_WH_CODE): float
    {
        return round((float) StockInventory::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->where('is_delete', false)
            ->value('balance'), 5);
    }

    /**
     * Implied period beginning = current balance − net movements from month start.
     */
    public function impliedBeginning(int $itemId, string $whCode, string $monthStart): float
    {
        $current = $this->currentBalance($itemId, $whCode);

        $rows = StockBalance::query()
            ->where('item_id', $itemId)
            ->where('wh_code', $whCode)
            ->whereDate('date', '>=', $monthStart)
            ->get();

        $net = $this->netQuantityFromRows($rows);

        return round($current - $net, 5);
    }

    /**
     * @param  Collection<int, StockBalance>  $rows
     */
    public function netQuantityFromRows(Collection $rows): float
    {
        return round(
            (float) $rows->sum('qty_in1')
            + (float) $rows->sum('qty_in2')
            + (float) $rows->sum('qty_in3')
            - (float) $rows->sum('qty_out1')
            - (float) $rows->sum('qty_out2')
            - (float) $rows->sum('qty_out3'),
            5
        );
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
        $rechainTargets = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $productCode = (string) ($line['product_code'] ?? '');
            $referenceLineId = (int) ($line['reference_line_id'] ?? 0);
            $quantity = round((float) ($line['quantity'] ?? 0), 5);
            $whCode = (string) ($line['wh_code'] ?? self::DEFAULT_WH_CODE);

            if ($itemId <= 0 || $productCode === '' || $referenceLineId <= 0 || abs($quantity) < 0.00001) {
                continue;
            }

            $netIssued = $this->netIssuedQuantity($referenceType, $referenceId, $referenceLineId, $outBucket);

            if ($reverse) {
                if ($netIssued < 0.00001) {
                    continue;
                }

                $issueQty = -abs($netIssued);
            } else {
                if ($netIssued >= 0.00001) {
                    continue;
                }

                $issueQty = abs($quantity);
            }

            $this->applyIssueMovement(
                itemId: $itemId,
                productCode: $productCode,
                whCode: $whCode,
                issueQty: $issueQty,
                movementDate: $movementDate,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceLineId: $referenceLineId,
                userId: $userId,
                outBucket: $outBucket,
                allowNegativeBalance: $allowNegativeBalance,
            );

            $rechainTargets[] = [
                'item_id' => $itemId,
                'wh_code' => $whCode,
                'from_date' => $movementDate,
            ];
        }

        $this->rechainLedgerTargets($rechainTargets);
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
            ->sum($outBucket), 5);
    }

    private function netAdjustmentQuantity(string $referenceType, int $referenceId, int $referenceLineId): float
    {
        $rows = StockBalance::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('reference_line_id', $referenceLineId)
            ->get();

        return round(
            (float) $rows->sum('qty_in2') - (float) $rows->sum('qty_out2'),
            5
        );
    }

    private function applyAdjustmentDelta(
        int $itemId,
        string $productCode,
        string $whCode,
        float $deltaQty,
        string $movementDate,
        string $referenceType,
        int $referenceId,
        int $referenceLineId,
        ?int $userId,
        bool $allowNegativeBalance
    ): void {
        $qtyIn2 = 0.0;
        $qtyOut2 = 0.0;

        if ($deltaQty > 0) {
            $qtyIn2 = $deltaQty;
        } else {
            $qtyOut2 = abs($deltaQty);
        }

        $this->writeMovement(
            itemId: $itemId,
            productCode: $productCode,
            whCode: $whCode,
            beginQty: null,
            qtyIn1: 0,
            qtyIn2: $qtyIn2,
            qtyOut1: 0,
            qtyOut2: $qtyOut2,
            qtyOut3: 0,
            movementPrice: 0,
            isReceipt: $deltaQty > 0,
            movementDate: $movementDate,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceLineId: $referenceLineId,
            userId: $userId,
            allowNegativeBalance: $allowNegativeBalance,
        );
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
                qtyIn2: 0,
                qtyOut1: 0,
                qtyOut2: 0,
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
        $qtyOut2 = $outBucket === self::OUT_BUCKET_ADJ ? $issueQty : 0.0;
        $qtyOut3 = $outBucket === self::OUT_BUCKET_DR ? $issueQty : 0.0;

        $this->writeMovement(
            itemId: $itemId,
            productCode: $productCode,
            whCode: $whCode,
            beginQty: null,
            qtyIn1: 0,
            qtyIn2: 0,
            qtyOut1: $qtyOut1,
            qtyOut2: $qtyOut2,
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
        float $qtyIn2,
        float $qtyOut1,
        float $qtyOut2,
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
        $netIn = $qtyIn1 + $qtyIn2;
        $netOut = $qtyOut1 + $qtyOut2 + $qtyOut3;
        $endQty = $begin + $netIn - $netOut;
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

        $stockInventory->balance = round($endQty, 5);
        $stockInventory->average_price = round($endAvgPrice, 2);
        $stockInventory->product_code = $productCode;
        $stockInventory->updated_by = $userId;
        $stockInventory->save();

        StockBalance::create([
            'date' => $movementDate,
            'item_id' => $itemId,
            'product_code' => $productCode,
            'wh_code' => $whCode,
            'begin' => round($begin, 5),
            'qty_in1' => round($qtyIn1, 5),
            'qty_in2' => round($qtyIn2, 5),
            'qty_in3' => 0,
            'qty_out1' => round($qtyOut1, 5),
            'qty_out2' => round($qtyOut2, 5),
            'qty_out3' => round($qtyOut3, 5),
            'end' => round($endQty, 5),
            'acc_qty_in1' => round($accQtyIn1, 5),
            'acc_average_price_in1' => round($accAveragePriceIn1, 2),
            'acc_qty_total' => round($endQty, 5),
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
            'stock_on_hand' => round($totalBalance, 5),
        ]);
    }
}
