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
        $categoryName = (string) ($transaction->category?->name ?? '');
        $docNo = $transaction->displayDocNumber();
        $tranDate = $transaction->doc_date?->toDateString() ?? now()->toDateString();
        $inputTime = now()->format('H:i:s');
        $encodedAt = now();

        foreach ($transaction->lines as $line) {
            $this->postLine(
                transaction: $transaction,
                line: $line,
                categoryName: $categoryName,
                docNo: $docNo,
                tranDate: $tranDate,
                inputTime: $inputTime,
                user: $user,
                encodedAt: $encodedAt,
            );
        }
    }

    public function reverseEncodedDocument(string $docCode, string $docNo, int $categoryId): void
    {
        $docTranIds = AccountingInventoryDocTran::query()
            ->where('doc_code', strtoupper($docCode))
            ->where('doc_no', $docNo)
            ->where('category_id', $categoryId)
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
        User $user,
        Carbon $encodedAt,
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
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'supplier_id' => $transaction->supplier_id,
            'purchase_order_id' => $transaction->purchase_order_id,
            'party_code' => $transaction->party_code,
            'party_name' => $transaction->party_name,
            'remarks' => $transaction->remarks,
            'is_corrected' => $line->wasCorrected() || $transaction->is_corrected,
            'encoded_by' => $user->id,
            'encoded_at' => $encodedAt,
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
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'supplier_id' => $transaction->supplier_id,
            'purchase_order_id' => $transaction->purchase_order_id,
            'accounting_inventory_doc_tran_id' => $docTran->id,
        ]);
    }

    /**
     * @return array{ending: float, u_cost: float}
     */
    public function latestBalanceSnapshot(string $itemCode, string $categoryName, string $beforeOrOnDate): array
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

    /**
     * @return array{ending: float, u_cost: float}
     */
    public function latestBalanceSnapshotByIds(int $categoryId, int $itemId): array
    {
        $row = AccountingInventoryMonthly::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($row !== null) {
            return [
                'ending' => (float) $row->ending,
                'u_cost' => (float) ($row->u_cost ?? 0),
            ];
        }

        $docTran = AccountingInventoryDocTran::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($docTran === null) {
            return ['ending' => 0.0, 'u_cost' => 0.0];
        }

        return [
            'ending' => (float) ($docTran->t_qty ?? 0),
            'u_cost' => (float) ($docTran->ave_cost ?? $docTran->u_cost ?? 0),
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
}
