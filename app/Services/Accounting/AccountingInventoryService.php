<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingInventoryService
{
    public function __construct(
        private readonly GlJournalEncoder $glJournalEncoder,
        private readonly AccountingInventoryLegacyPostingService $legacyPostingService,
    ) {}

    public function getAvailableQty(int $categoryId, int $itemId): float
    {
        return (float) ($this->getAvailableQtyMap($categoryId, [$itemId])[$itemId] ?? 0);
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    public function getAvailableQtyMap(int $categoryId, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === []) {
            return [];
        }

        $map = array_fill_keys($itemIds, 0.0);

        foreach ($itemIds as $itemId) {
            $snapshot = $this->legacyPostingService->latestBalanceSnapshotByIds($categoryId, $itemId);
            $map[$itemId] = round((float) $snapshot['ending'], 5);
        }

        return $map;
    }

    public function getWeightedUnitCost(int $categoryId, int $itemId): float
    {
        $snapshot = $this->legacyPostingService->latestBalanceSnapshotByIds($categoryId, $itemId);

        return round((float) ($snapshot['u_cost'] ?? 0), 5);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function encodeDocument(AccountingInventoryTransaction $document, array $lines, User $user): AccountingInventoryTransaction
    {
        if ($document->isEncoded()) {
            throw ValidationException::withMessages([
                'transaction' => 'This document is already encoded.',
            ]);
        }

        $normalized = $this->normalizeLines($lines);
        $this->hydrateDocumentLines($document, $normalized);

        if ($document->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'At least one line is required before encoding.',
            ]);
        }

        if ($this->isDocumentEncoded($document->doc_type, $document->displayDocNumber(), $document->category_id)) {
            throw ValidationException::withMessages([
                'transaction' => 'This document is already encoded.',
            ]);
        }

        return DB::transaction(function () use ($document, $user): AccountingInventoryTransaction {
            $projected = $this->getAvailableQtyMap(
                $document->category_id,
                $document->lines->map(fn (AccountingInventoryTransactionLine $line): int => (int) $line->item_id)->all(),
            );

            foreach ($document->lines as $line) {
                $this->assertLineCanPost($document, $line, $projected);

                $itemId = (int) $line->item_id;
                if ($line->direction === AccountingInventoryTransactionLine::DIRECTION_IN) {
                    $projected[$itemId] = round(($projected[$itemId] ?? 0) + (float) $line->quantity, 5);
                } else {
                    $projected[$itemId] = round(($projected[$itemId] ?? 0) - (float) $line->quantity, 5);
                }
            }

            $this->legacyPostingService->postEncodedTransaction($document, $user);
            $this->glJournalEncoder->encodeIfEnabled($document, $user);

            $document->status = AccountingInventoryTransaction::STATUS_ENCODED;
            $document->encoded_by = $user->id;
            $document->encoded_at = now();
            $document->encodedBy = $user;
            $document->is_corrected = $document->lines->contains(fn (AccountingInventoryTransactionLine $line): bool => $line->wasCorrected());
            $document->total_amount = round($document->lines->sum(fn (AccountingInventoryTransactionLine $line): float => (float) $line->amount), 4);

            return $document;
        });
    }

    public function voidDocument(AccountingInventoryTransaction $document, User $user, string $reason): AccountingInventoryTransaction
    {
        if (! $document->isEncoded()) {
            throw ValidationException::withMessages([
                'transaction' => 'Only encoded transactions can be voided.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'void_reason' => 'A void reason is required.',
            ]);
        }

        return DB::transaction(function () use ($document): AccountingInventoryTransaction {
            $this->legacyPostingService->reverseEncodedDocument(
                $document->doc_type,
                $document->displayDocNumber(),
                $document->category_id,
            );

            $document->status = AccountingInventoryTransaction::STATUS_VOIDED;

            return $document;
        });
    }

    public function isDocumentEncoded(string $docCode, string $docNo, int $categoryId): bool
    {
        return AccountingInventoryDocTran::query()
            ->where('doc_code', strtoupper($docCode))
            ->where('doc_no', $docNo)
            ->where('category_id', $categoryId)
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $direction = strtolower(trim((string) ($line['direction'] ?? '')));
            if (! in_array($direction, [AccountingInventoryTransactionLine::DIRECTION_IN, AccountingInventoryTransactionLine::DIRECTION_OUT], true)) {
                throw ValidationException::withMessages([
                    'lines' => 'Each line must have direction in or out.',
                ]);
            }

            $quantity = round(max(0, (float) ($line['quantity'] ?? 0)), 5);
            $unitCost = round(max(0, (float) ($line['unit_cost'] ?? 0)), 5);
            $amount = round($quantity * $unitCost, 4);
            $expectedAmount = round((float) ($line['amount'] ?? $amount), 4);

            if (abs($amount - $expectedAmount) > 0.01) {
                throw ValidationException::withMessages([
                    'lines' => 'Line amount must equal quantity multiplied by unit cost.',
                ]);
            }

            if ($quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'item_id' => $itemId,
                'direction' => $direction,
                'quantity' => $quantity,
                'unit_of_measure_id' => isset($line['unit_of_measure_id']) ? (int) $line['unit_of_measure_id'] : null,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'prefill_quantity' => isset($line['prefill_quantity']) ? round((float) $line['prefill_quantity'], 5) : null,
                'prefill_unit_cost' => isset($line['prefill_unit_cost']) ? round((float) $line['prefill_unit_cost'], 5) : null,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one valid line is required.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $normalized
     */
    public function hydrateDocumentLines(AccountingInventoryTransaction $document, array $normalized): void
    {
        $itemIds = array_map(fn (array $line): int => (int) $line['item_id'], $normalized);
        $items = Item::query()->with('unit')->whereIn('id', $itemIds)->get()->keyBy('id');
        $availableByItem = $this->getAvailableQtyMap($document->category_id, $itemIds);

        $lines = collect();
        foreach ($normalized as $index => $line) {
            $itemId = (int) $line['item_id'];
            $availableQty = (float) ($availableByItem[$itemId] ?? 0);
            if ($line['direction'] === AccountingInventoryTransactionLine::DIRECTION_OUT) {
                $availableQty += (float) ($line['prefill_quantity'] ?? 0);
            }

            $lines->push(AccountingInventoryTransactionLine::make([
                ...$line,
                'available_qty_snapshot' => round($availableQty, 5),
                'sort_order' => $index,
                'item' => $items->get($itemId),
            ]));
        }

        $document->lines = $lines;
        $document->total_amount = round($lines->sum(fn (AccountingInventoryTransactionLine $line): float => (float) $line->amount), 4);
        $document->is_corrected = $lines->contains(fn (AccountingInventoryTransactionLine $line): bool => $line->wasCorrected());

        if ($document->category === null && $document->category_id > 0) {
            $document->category = ItemCategory::query()->find($document->category_id);
        }
    }

    /**
     * @param  array<int, float>  $projectedAvailable
     */
    private function assertLineCanPost(
        AccountingInventoryTransaction $document,
        AccountingInventoryTransactionLine $line,
        array $projectedAvailable,
    ): void {
        if ($line->direction !== AccountingInventoryTransactionLine::DIRECTION_OUT) {
            return;
        }

        // Source-backed RR/TS/DR only book warehouse movements already done; do not block on qty.
        if (! $document->isManual() && $document->source_id) {
            return;
        }

        $available = (float) ($projectedAvailable[(int) $line->item_id] ?? 0);
        if ((float) $line->quantity > $available + 0.00001) {
            $itemCode = $line->item?->code ?? (string) $line->item_id;
            throw ValidationException::withMessages([
                'lines' => "Insufficient accounting quantity for item {$itemCode}. Available: {$available}.",
            ]);
        }
    }
}
