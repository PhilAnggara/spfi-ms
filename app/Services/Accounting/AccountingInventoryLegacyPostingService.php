<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AccountingInventoryLegacyPostingService
{
    public function postEncodedTransaction(AccountingInventoryTransaction $transaction, User $user): void
    {
        $categoryId = (int) $transaction->category_id;
        if ($categoryId <= 0) {
            throw ValidationException::withMessages([
                'category_id' => 'A valid category is required before encoding.',
            ]);
        }

        if ($transaction->category === null) {
            $transaction->category = ItemCategory::query()->find($categoryId);
        }

        $categoryName = trim((string) ($transaction->category?->name ?? ''));
        if ($categoryName === '') {
            throw ValidationException::withMessages([
                'category_id' => 'Category master record was not found for encoding.',
            ]);
        }

        $this->hydratePartyFromSupplier($transaction);

        $docNo = $transaction->displayDocNumber();
        $tranDate = $transaction->doc_date?->toDateString() ?? now()->toDateString();
        $inputTime = now()->format('H:i:s');
        $encodedAt = now();

        foreach ($transaction->lines as $index => $line) {
            $this->assertLineIsPostable($line, $index);

            $this->postLine(
                transaction: $transaction,
                line: $line,
                categoryId: $categoryId,
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

    private function hydratePartyFromSupplier(AccountingInventoryTransaction $transaction): void
    {
        $supplierId = (int) ($transaction->supplier_id ?? 0);
        if ($supplierId <= 0) {
            return;
        }

        $partyName = trim((string) ($transaction->party_name ?? ''));
        $partyCode = trim((string) ($transaction->party_code ?? ''));
        if ($partyName !== '' && $partyCode !== '') {
            return;
        }

        $supplier = Supplier::query()->find($supplierId);
        if ($supplier === null) {
            return;
        }

        if ($partyName === '') {
            $transaction->party_name = $supplier->name;
        }

        if ($partyCode === '') {
            $transaction->party_code = $supplier->code;
        }
    }

    private function assertLineIsPostable(AccountingInventoryTransactionLine $line, int $index): void
    {
        $itemId = (int) $line->item_id;
        $itemCode = trim((string) ($line->item?->code ?? ''));

        if ($itemId <= 0 || $itemCode === '') {
            throw ValidationException::withMessages([
                'lines' => 'Line '.($index + 1).' is missing a valid item code and cannot be encoded.',
            ]);
        }
    }

    private function postLine(
        AccountingInventoryTransaction $transaction,
        AccountingInventoryTransactionLine $line,
        int $categoryId,
        string $categoryName,
        string $docNo,
        string $tranDate,
        string $inputTime,
        User $user,
        Carbon $encodedAt,
    ): void {
        $item = $line->item;
        $itemCode = trim((string) ($item?->code ?? ''));
        $itemId = (int) $line->item_id;

        if ($itemId <= 0 || $itemCode === '' || $categoryName === '') {
            throw ValidationException::withMessages([
                'lines' => 'Cannot encode a line without item code and category.',
            ]);
        }

        $signedQty = $line->direction === AccountingInventoryTransactionLine::DIRECTION_OUT
            ? -1 * abs((float) $line->quantity)
            : abs((float) $line->quantity);

        $unitCost = (float) $line->unit_cost;
        $amount = round($signedQty * $unitCost, 4);
        $snapshot = $this->latestBalanceSnapshotByIds($categoryId, $itemId, $tranDate);
        $begining = $snapshot['ending'];
        $beginingUCost = $snapshot['u_cost'];
        $ending = round($begining + $signedQty, 8);
        $aveCost = $this->resolveAverageCost($signedQty, $unitCost, $begining, $beginingUCost, $ending);
        $tQty = $ending;

        $docTran = AccountingInventoryDocTran::query()->create([
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
            'item_id' => $itemId,
            'category_id' => $categoryId,
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
            'item_id' => $itemId,
            'category_id' => $categoryId,
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
    public function latestBalanceSnapshotByIds(int $categoryId, int $itemId, ?string $beforeOrOnDate = null): array
    {
        $docTranQuery = AccountingInventoryDocTran::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId);

        if ($beforeOrOnDate !== null) {
            $docTranQuery->whereDate('tran_date', '<=', $beforeOrOnDate);
        }

        $docTran = $docTranQuery
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($docTran !== null) {
            return [
                'ending' => (float) ($docTran->t_qty ?? 0),
                'u_cost' => (float) ($docTran->ave_cost ?? $docTran->u_cost ?? 0),
            ];
        }

        $monthlyQuery = AccountingInventoryMonthly::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId);

        if ($beforeOrOnDate !== null) {
            $monthlyQuery->whereDate('tran_date', '<=', $beforeOrOnDate);
        }

        $row = $monthlyQuery
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return ['ending' => 0.0, 'u_cost' => 0.0];
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
}
