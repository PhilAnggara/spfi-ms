<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryLedger;
use App\Models\AccountingInventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingInventoryReportService
{
    public function ledgerAvailable(): bool
    {
        return Schema::hasTable('accounting_inventory_ledger');
    }

    public function hasEncodedData(): bool
    {
        if (! $this->ledgerAvailable()) {
            return false;
        }

        return AccountingInventoryTransaction::query()
            ->where('status', AccountingInventoryTransaction::STATUS_ENCODED)
            ->exists();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function stockCardRows(string $month, string $categoryName): Collection
    {
        $category = $this->resolveCategory($categoryName);
        if ($category === null) {
            return collect();
        }

        $selectedMonth = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();

        $itemIds = $this->itemIdsForCategory($category->id);
        if ($itemIds === []) {
            return collect();
        }

        $rows = collect();

        foreach ($itemIds as $itemId) {
            $beginning = $this->snapshotAt($category->id, $itemId, Carbon::parse($monthStart)->subDay()->toDateString());
            $ending = $this->snapshotAt($category->id, $itemId, $monthEnd);
            if ($beginning['balance_qty'] <= 0 && $ending['balance_qty'] <= 0) {
                continue;
            }

            $unitCost = $ending['weighted_unit_cost'] > 0 ? $ending['weighted_unit_cost'] : $beginning['weighted_unit_cost'];
            $endingQty = $ending['balance_qty'];
            $beginningQty = $beginning['balance_qty'];
            $amount = $endingQty * $unitCost;
            $beginningAmount = $beginningQty * $unitCost;

            $item = Item::query()->with('unit')->find($itemId);

            $rows->push([
                'item_code' => $item?->code,
                'item_description' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty' => $endingQty,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'beginning_amount' => $beginningAmount,
                'transaction' => $amount - $beginningAmount,
            ]);
        }

        return $rows->sortBy('item_code')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function transactionRows(string $dateFrom, string $dateTo, string $categoryName): Collection
    {
        return $this->stockCardRows(
            Carbon::parse($dateFrom)->format('Y-m'),
            $categoryName,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function documentSummaryGroups(string $dateFrom, string $dateTo, string $categoryName): Collection
    {
        $category = $this->resolveCategory($categoryName);
        if ($category === null) {
            return collect();
        }

        return AccountingInventoryTransaction::query()
            ->where('category_id', $category->id)
            ->where('status', AccountingInventoryTransaction::STATUS_ENCODED)
            ->whereDate('doc_date', '>=', $dateFrom)
            ->whereDate('doc_date', '<=', $dateTo)
            ->orderBy('doc_type')
            ->orderBy('doc_date')
            ->orderBy('doc_number')
            ->get()
            ->groupBy('doc_type')
            ->map(function (Collection $transactions, string $docType): array {
                return [
                    'type' => $docType,
                    'title' => match ($docType) {
                        'RR' => 'Receiving Report',
                        'TS' => 'Transfer Slip',
                        'DR' => 'Delivery Receipt',
                        'CV' => 'Cash Voucher',
                        'JV' => 'Journal Voucher',
                        default => $docType,
                    },
                    'rows' => $transactions->map(function (AccountingInventoryTransaction $transaction): array {
                        $displayNumber = app(AccountingInventoryQueueService::class)->displayDocNumber($transaction);

                        return [
                            'number' => $displayNumber,
                            'date' => $transaction->doc_date?->toDateString(),
                            'amount' => (float) $transaction->total_amount,
                        ];
                    })->values(),
                    'total' => (float) $transactions->sum('total_amount'),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function purchaseRows(string $dateFrom, string $dateTo, string $categoryName): Collection
    {
        $category = $this->resolveCategory($categoryName);
        if ($category === null) {
            return collect();
        }

        return DB::table('accounting_inventory_transactions as ait')
            ->join('accounting_inventory_transaction_lines as aitl', 'aitl.accounting_inventory_transaction_id', '=', 'ait.id')
            ->join('items as i', 'i.id', '=', 'aitl.item_id')
            ->leftJoin('unit_of_measures as uom', 'uom.id', '=', 'aitl.unit_of_measure_id')
            ->where('ait.doc_type', 'RR')
            ->where('ait.category_id', $category->id)
            ->where('ait.status', AccountingInventoryTransaction::STATUS_ENCODED)
            ->whereDate('ait.doc_date', '>=', $dateFrom)
            ->whereDate('ait.doc_date', '<=', $dateTo)
            ->orderBy('ait.doc_date')
            ->orderBy('ait.doc_number')
            ->get([
                'ait.doc_number',
                'ait.doc_date',
                'ait.po_number',
                'ait.party_name',
                'i.code as item_code',
                'i.name as item_name',
                'uom.name as unit',
                'aitl.quantity',
                'aitl.unit_cost',
                'aitl.amount',
            ])
            ->map(function ($row): array {
                $displayNumber = $row->doc_number;
                if (preg_match('/^RR\|(.+)\|\d+$/', (string) $row->doc_number, $matches)) {
                    $displayNumber = $matches[1];
                }

                return [
                    'supplier_name' => $row->party_name,
                    'po_number' => $row->po_number,
                    'rr_number' => $displayNumber,
                    'date' => $row->doc_date,
                    'currency' => 'IDR',
                    'item_code' => $row->item_code,
                    'item_name' => $row->item_name,
                    'unit' => $row->unit ?? '',
                    'quantity' => (float) $row->quantity,
                    'unit_price' => (float) $row->unit_cost,
                    'amount' => (float) $row->amount,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function documentSummaryPerItem(string $dateFrom, string $dateTo, string $categoryName): Collection
    {
        $category = $this->resolveCategory($categoryName);
        if ($category === null) {
            return collect();
        }

        return DB::table('accounting_inventory_transaction_lines as aitl')
            ->join('accounting_inventory_transactions as ait', 'ait.id', '=', 'aitl.accounting_inventory_transaction_id')
            ->join('items as i', 'i.id', '=', 'aitl.item_id')
            ->leftJoin('unit_of_measures as uom', 'uom.id', '=', 'aitl.unit_of_measure_id')
            ->where('ait.category_id', $category->id)
            ->where('ait.status', AccountingInventoryTransaction::STATUS_ENCODED)
            ->whereDate('ait.doc_date', '>=', $dateFrom)
            ->whereDate('ait.doc_date', '<=', $dateTo)
            ->groupBy('i.id', 'i.code', 'i.name', 'uom.name')
            ->orderBy('i.code')
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'uom.name as unit',
                DB::raw("SUM(CASE WHEN aitl.direction = 'in' THEN aitl.quantity ELSE 0 END) as qty_in"),
                DB::raw("SUM(CASE WHEN aitl.direction = 'out' THEN aitl.quantity ELSE 0 END) as qty_out"),
                DB::raw("SUM(CASE WHEN aitl.direction = 'in' THEN aitl.amount ELSE -aitl.amount END) as net_amount"),
            ])
            ->get()
            ->map(fn ($row): array => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'unit' => $row->unit,
                'qty_in' => (float) $row->qty_in,
                'qty_out' => (float) $row->qty_out,
                'net_amount' => (float) $row->net_amount,
            ]);
    }

    /**
     * @return list<int>
     */
    private function itemIdsForCategory(int $categoryId): array
    {
        return Item::query()
            ->where('category_id', $categoryId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{balance_qty: float, balance_amount: float, weighted_unit_cost: float}
     */
    private function snapshotAt(int $categoryId, int $itemId, string $asOfDate): array
    {
        $latest = AccountingInventoryLedger::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->whereDate('movement_date', '<=', $asOfDate)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->first();

        return [
            'balance_qty' => (float) ($latest?->balance_qty ?? 0),
            'balance_amount' => (float) ($latest?->balance_amount ?? 0),
            'weighted_unit_cost' => (float) ($latest?->weighted_unit_cost ?? 0),
        ];
    }

    private function resolveCategory(string $categoryName): ?ItemCategory
    {
        return ItemCategory::query()
            ->whereRaw('UPPER(name) = ?', [mb_strtoupper(trim($categoryName))])
            ->first();
    }
}
