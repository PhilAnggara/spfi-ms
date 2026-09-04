<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Support\Carbon;

class AccountingInventoryLegacyPostingService
{
    public function postEncodedTransaction(AccountingInventoryTransaction $transaction, User $user): void
    {
        $transaction->loadMissing(['lines.item.unit', 'category']);

        $categoryName = (string) ($transaction->category?->name ?? '');
        $docNo = $this->displayDocNumber($transaction);
        $tranDate = $transaction->doc_date?->toDateString() ?? now()->toDateString();
        $inputTime = now()->format('H:i:s');

        foreach ($transaction->lines as $line) {
            $this->postLine(
                transaction: $transaction,
                line: $line,
                categoryName: $categoryName,
                docNo: $docNo,
                tranDate: $tranDate,
                inputTime: $inputTime,
            );
        }
    }

    public function reverseEncodedTransaction(AccountingInventoryTransaction $transaction): void
    {
        $docTranIds = AccountingInventoryDocTran::query()
            ->where('accounting_inventory_transaction_id', $transaction->id)
            ->pluck('id');

        if ($docTranIds->isEmpty()) {
            return;
        }

        AccountingInventoryMonthly::query()
            ->whereIn('accounting_inventory_doc_tran_id', $docTranIds)
            ->delete();

        AccountingInventoryDocTran::query()
            ->whereIn('id', $docTranIds)
            ->delete();
    }

    private function postLine(
        AccountingInventoryTransaction $transaction,
        AccountingInventoryTransactionLine $line,
        string $categoryName,
        string $docNo,
        string $tranDate,
        string $inputTime,
    ): void {
        $item = $line->item;
        $itemCode = (string) ($item?->code ?? '');
        if ($itemCode === '' || $categoryName === '') {
            return;
        }

        $signedQty = $line->direction === AccountingInventoryTransactionLine::DIRECTION_OUT
            ? -1 * abs((float) $line->quantity)
            : abs((float) $line->quantity);

        $unitCost = (float) $line->unit_cost;
        $amount = round($signedQty * $unitCost, 4);
        $snapshot = $this->latestBalanceSnapshot($itemCode, $categoryName, $tranDate);
        $begining = $snapshot['ending'];
        $beginingUCost = $snapshot['u_cost'];
        $ending = round($begining + $signedQty, 8);
        $aveCost = $this->resolveAverageCost($signedQty, $unitCost, $begining, $beginingUCost, $ending);
        $tQty = $ending;

        $docTran = AccountingInventoryDocTran::query()->create([
            'legacy_tran_id' => null,
            'doc_code' => strtoupper((string) $transaction->doc_type),
            'doc_no' => $docNo,
            'doc_date' => $transaction->doc_date?->toDateString() ?? $tranDate,
            'po_no' => $transaction->po_number,
            'item_code' => $itemCode,
            'qty' => round($signedQty, 5),
            'u_cost' => round($unitCost, 8),
            'uom' => $item?->unit?->name ?? $item?->unit?->code,
            'ave_cost' => round($aveCost, 8),
            't_qty' => round($tQty, 5),
            'tran_date' => $tranDate,
            'input_time' => $inputTime,
            'modify_date' => null,
            'category' => $categoryName,
            'amount' => $amount,
            'item_id' => $line->item_id,
            'category_id' => $transaction->category_id,
            'accounting_inventory_transaction_id' => $transaction->id,
            'accounting_inventory_transaction_line_id' => $line->id,
        ]);

        $monthEnd = Carbon::parse($tranDate)->endOfMonth()->toDateString();

        AccountingInventoryMonthly::query()->create([
            'legacy_monthly_id' => null,
            'item_code' => $itemCode,
            'doc_code' => strtoupper((string) $transaction->doc_type),
            'doc_no' => $docNo,
            'qty' => round($signedQty, 8),
            'u_cost' => round($unitCost, 8),
            'begining' => round($begining, 8),
            'ending' => $ending,
            'tran_date' => $monthEnd,
            'category' => $categoryName,
            'begining_u_cost' => $beginingUCost > 0 ? round($beginingUCost, 8) : null,
            'item_id' => $line->item_id,
            'category_id' => $transaction->category_id,
            'accounting_inventory_doc_tran_id' => $docTran->id,
        ]);
    }

    /**
     * @return array{ending: float, u_cost: float}
     */
    private function latestBalanceSnapshot(string $itemCode, string $categoryName, string $beforeOrOnDate): array
    {
        $row = AccountingInventoryMonthly::query()
            ->where('item_code', $itemCode)
            ->where('category', $categoryName)
            ->whereDate('tran_date', '<=', $beforeOrOnDate)
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            $row = AccountingInventoryDocTran::query()
                ->where('item_code', $itemCode)
                ->where('category', $categoryName)
                ->whereDate('tran_date', '<=', $beforeOrOnDate)
                ->orderByDesc('tran_date')
                ->orderByDesc('id')
                ->first();

            if ($row === null) {
                return ['ending' => 0.0, 'u_cost' => 0.0];
            }

            return [
                'ending' => (float) ($row->t_qty ?? 0),
                'u_cost' => (float) ($row->ave_cost ?? $row->u_cost ?? 0),
            ];
        }

        return [
            'ending' => (float) $row->ending,
            'u_cost' => (float) ($row->u_cost ?? 0),
        ];
    }

    private function resolveAverageCost(
        float $signedQty,
        float $unitCost,
        float $begining,
        float $beginingUCost,
        float $ending,
    ): float {
        if ($ending <= 0) {
            return 0.0;
        }

        if ($signedQty >= 0) {
            $beginAmount = $begining * max(0, $beginingUCost);
            $inAmount = abs($signedQty) * $unitCost;

            return round(($beginAmount + $inAmount) / $ending, 8);
        }

        return $beginingUCost > 0 ? round($beginingUCost, 8) : round($unitCost, 8);
    }

    /**
     * @return array{item_id: ?int, category_id: ?int}
     */
    public function resolveMasterIds(string $itemCode, string $categoryName): array
    {
        static $itemCache = [];
        static $categoryCache = [];

        $itemKey = strtoupper(trim($itemCode));
        $categoryKey = strtoupper(trim($categoryName));

        if (! array_key_exists($itemKey, $itemCache)) {
            $itemCache[$itemKey] = Item::query()->where('code', $itemCode)->value('id');
        }

        if (! array_key_exists($categoryKey, $categoryCache)) {
            $categoryCache[$categoryKey] = ItemCategory::query()
                ->whereRaw('UPPER(name) = ?', [$categoryKey])
                ->value('id');
        }

        return [
            'item_id' => $itemCache[$itemKey] !== null ? (int) $itemCache[$itemKey] : null,
            'category_id' => $categoryCache[$categoryKey] !== null ? (int) $categoryCache[$categoryKey] : null,
        ];
    }

    private function displayDocNumber(AccountingInventoryTransaction $transaction): string
    {
        $parts = explode('|', (string) $transaction->doc_number, 3);
        if (count($parts) === 3) {
            return $parts[1];
        }

        return (string) $transaction->doc_number;
    }
}
