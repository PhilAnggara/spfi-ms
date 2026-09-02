<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryLedger;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingInventoryService
{
    public function __construct(
        private readonly GlJournalEncoder $glJournalEncoder,
    ) {}

    public function getAvailableQty(int $categoryId, int $itemId): float
    {
        $latest = AccountingInventoryLedger::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->first();

        return round((float) ($latest?->balance_qty ?? 0), 5);
    }

    public function getWeightedUnitCost(int $categoryId, int $itemId): float
    {
        return round((float) ($this->latestLedgerSnapshot($categoryId, $itemId)['weighted_unit_cost'] ?? 0), 4);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncLines(AccountingInventoryTransaction $transaction, array $lines, ?int $userId = null): AccountingInventoryTransaction
    {
        if ($transaction->isEncoded()) {
            throw ValidationException::withMessages([
                'transaction' => 'Encoded transactions cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $lines, $userId): AccountingInventoryTransaction {
            $transaction->lines()->delete();

            $normalized = $this->normalizeLines($transaction, $lines);
            $isCorrected = false;
            $totalAmount = 0.0;

            foreach ($normalized as $index => $line) {
                $availableQty = $this->getAvailableQty($transaction->category_id, $line['item_id']);
                if ($line['direction'] === AccountingInventoryTransactionLine::DIRECTION_OUT) {
                    $availableQty += (float) ($line['prefill_quantity'] ?? 0);
                }

                $lineModel = $transaction->lines()->create([
                    ...$line,
                    'available_qty_snapshot' => round($availableQty, 5),
                    'sort_order' => $index,
                ]);

                if ($lineModel->wasCorrected()) {
                    $isCorrected = true;
                }

                $totalAmount += (float) $line['amount'];
            }

            $transaction->update([
                'total_amount' => round($totalAmount, 4),
                'is_corrected' => $isCorrected,
                'updated_by' => $userId,
            ]);

            return $transaction->fresh(['lines.item.unit', 'category']) ?? $transaction;
        });
    }

    public function encode(AccountingInventoryTransaction $transaction, User $user): AccountingInventoryTransaction
    {
        if ($transaction->isEncoded()) {
            throw ValidationException::withMessages([
                'transaction' => 'This document is already encoded.',
            ]);
        }

        if ($transaction->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'lines' => 'At least one line is required before encoding.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $user): AccountingInventoryTransaction {
            $transaction->load(['lines.item']);

            foreach ($transaction->lines as $line) {
                $this->assertLineCanPost($transaction, $line);
                $this->postLineToLedger($transaction, $line, $user);
            }

            $this->glJournalEncoder->encodeIfEnabled($transaction, $user);

            $transaction->update([
                'status' => AccountingInventoryTransaction::STATUS_ENCODED,
                'encoded_by' => $user->id,
                'encoded_at' => now(),
                'updated_by' => $user->id,
            ]);

            return $transaction->fresh(['lines.item.unit', 'category', 'encodedBy']) ?? $transaction;
        });
    }

    public function voidTransaction(AccountingInventoryTransaction $transaction, User $user, string $reason): AccountingInventoryTransaction
    {
        if (! $transaction->isEncoded()) {
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

        return DB::transaction(function () use ($transaction, $user, $reason): AccountingInventoryTransaction {
            $transaction->load('lines');

            foreach ($transaction->lines as $line) {
                $this->postReversalLine($transaction, $line, $user);
            }

            $transaction->update([
                'status' => AccountingInventoryTransaction::STATUS_VOIDED,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
                'updated_by' => $user->id,
            ]);

            return $transaction->fresh(['lines.item.unit', 'category', 'voidedBy']) ?? $transaction;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(AccountingInventoryTransaction $transaction, array $lines): array
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
            $unitCost = round(max(0, (float) ($line['unit_cost'] ?? 0)), 4);
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
                'prefill_unit_cost' => isset($line['prefill_unit_cost']) ? round((float) $line['prefill_unit_cost'], 4) : null,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one valid line is required.',
            ]);
        }

        return $normalized;
    }

    private function assertLineCanPost(AccountingInventoryTransaction $transaction, AccountingInventoryTransactionLine $line): void
    {
        if ($line->direction !== AccountingInventoryTransactionLine::DIRECTION_OUT) {
            return;
        }

        $available = $this->getAvailableQty($transaction->category_id, (int) $line->item_id);
        if ((float) $line->quantity > $available + 0.00001) {
            $itemCode = $line->item?->code ?? (string) $line->item_id;
            throw ValidationException::withMessages([
                'lines' => "Insufficient accounting quantity for item {$itemCode}. Available: {$available}.",
            ]);
        }
    }

    private function postLineToLedger(
        AccountingInventoryTransaction $transaction,
        AccountingInventoryTransactionLine $line,
        User $user,
        bool $isReversal = false,
    ): void {
        $categoryId = (int) $transaction->category_id;
        $itemId = (int) $line->item_id;
        $latest = $this->latestLedgerSnapshot($categoryId, $itemId);

        $balanceQty = (float) ($latest['balance_qty'] ?? 0);
        $balanceAmount = (float) ($latest['balance_amount'] ?? 0);
        $weightedCost = (float) ($latest['weighted_unit_cost'] ?? 0);

        $quantity = (float) $line->quantity;
        $unitCost = (float) $line->unit_cost;
        $amount = (float) $line->amount;

        if ($line->direction === AccountingInventoryTransactionLine::DIRECTION_IN) {
            $newQty = $balanceQty + $quantity;
            $newAmount = $balanceAmount + $amount;
            $weightedCost = $newQty > 0 ? round($newAmount / $newQty, 4) : 0.0;
            $balanceQty = $newQty;
            $balanceAmount = $newAmount;
        } else {
            $issueCost = $weightedCost > 0 ? $weightedCost : $unitCost;
            $issueAmount = round($quantity * $issueCost, 4);
            $balanceQty = max(0, $balanceQty - $quantity);
            $balanceAmount = max(0, $balanceAmount - $issueAmount);
            $amount = $issueAmount;
            $unitCost = $issueCost;
            $weightedCost = $balanceQty > 0 ? round($balanceAmount / $balanceQty, 4) : 0.0;
        }

        AccountingInventoryLedger::query()->create([
            'accounting_inventory_transaction_id' => $transaction->id,
            'accounting_inventory_transaction_line_id' => $line->id,
            'category_id' => $categoryId,
            'item_id' => $itemId,
            'doc_type' => $transaction->doc_type,
            'doc_number' => $transaction->doc_number,
            'doc_date' => $transaction->doc_date,
            'movement_date' => $isReversal ? now()->toDateString() : $transaction->doc_date,
            'direction' => $line->direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'balance_qty' => round($balanceQty, 5),
            'balance_amount' => round($balanceAmount, 4),
            'weighted_unit_cost' => $weightedCost,
            'is_reversal' => $isReversal,
            'created_by' => $user->id,
        ]);
    }

    private function postReversalLine(
        AccountingInventoryTransaction $transaction,
        AccountingInventoryTransactionLine $line,
        User $user,
    ): void {
        $reversalDirection = $line->direction === AccountingInventoryTransactionLine::DIRECTION_IN
            ? AccountingInventoryTransactionLine::DIRECTION_OUT
            : AccountingInventoryTransactionLine::DIRECTION_IN;

        $reversalLine = $line->replicate(['id']);
        $reversalLine->direction = $reversalDirection;
        $reversalLine->exists = false;

        $this->postLineToLedger($transaction, $reversalLine, $user, true);
    }

    /**
     * @return array{balance_qty: float, balance_amount: float, weighted_unit_cost: float}
     */
    private function latestLedgerSnapshot(int $categoryId, int $itemId): array
    {
        $latest = AccountingInventoryLedger::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->first();

        return [
            'balance_qty' => (float) ($latest?->balance_qty ?? 0),
            'balance_amount' => (float) ($latest?->balance_amount ?? 0),
            'weighted_unit_cost' => (float) ($latest?->weighted_unit_cost ?? 0),
        ];
    }
}
