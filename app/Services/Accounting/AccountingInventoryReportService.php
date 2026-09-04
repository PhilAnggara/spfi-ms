<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
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
        return Schema::hasTable('accounting_inventory_doc_tran');
    }

    public function hasEncodedData(): bool
    {
        if (! $this->ledgerAvailable()) {
            return false;
        }

        return AccountingInventoryDocTran::query()->exists();
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

        $groups = AccountingInventoryDocTran::query()
            ->where('category_id', $category->id)
            ->whereDate('doc_date', '>=', $dateFrom)
            ->whereDate('doc_date', '<=', $dateTo)
            ->select([
                'doc_code',
                'doc_no',
                'doc_date',
                DB::raw('SUM(ABS(amount)) as total_amount'),
            ])
            ->groupBy('doc_code', 'doc_no', 'doc_date')
            ->orderBy('doc_code')
            ->orderBy('doc_date')
            ->orderBy('doc_no')
            ->get()
            ->groupBy('doc_code');

        return $groups->map(function (Collection $documents, string $docType): array {
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
                'rows' => $documents->map(fn ($row): array => [
                    'number' => (string) $row->doc_no,
                    'date' => Carbon::parse($row->doc_date)->toDateString(),
                    'amount' => (float) $row->total_amount,
                ])->values(),
                'total' => (float) $documents->sum('total_amount'),
            ];
        })->values();
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

        return DB::table('accounting_inventory_doc_tran as dt')
            ->leftJoin('items as i', 'i.id', '=', 'dt.item_id')
            ->leftJoin('unit_of_measures as uom', 'uom.id', '=', 'i.unit_of_measure_id')
            ->where('dt.doc_code', 'RR')
            ->where('dt.category_id', $category->id)
            ->whereDate('dt.doc_date', '>=', $dateFrom)
            ->whereDate('dt.doc_date', '<=', $dateTo)
            ->orderBy('dt.doc_date')
            ->orderBy('dt.doc_no')
            ->get([
                'dt.doc_no',
                'dt.doc_date',
                'dt.po_no',
                'dt.party_name',
                'dt.item_code',
                'i.name as item_name',
                'uom.name as unit',
                'dt.qty',
                'dt.u_cost',
                'dt.amount',
            ])
            ->map(fn ($row): array => [
                'supplier_name' => $row->party_name,
                'po_number' => $row->po_no,
                'rr_number' => $row->doc_no,
                'date' => $row->doc_date,
                'currency' => 'IDR',
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'unit' => $row->unit ?? '',
                'quantity' => abs((float) $row->qty),
                'unit_price' => abs((float) $row->u_cost),
                'amount' => abs((float) $row->amount),
            ]);
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

        return DB::table('accounting_inventory_doc_tran as dt')
            ->leftJoin('items as i', 'i.id', '=', 'dt.item_id')
            ->leftJoin('unit_of_measures as uom', 'uom.id', '=', 'i.unit_of_measure_id')
            ->where('dt.category_id', $category->id)
            ->whereDate('dt.doc_date', '>=', $dateFrom)
            ->whereDate('dt.doc_date', '<=', $dateTo)
            ->groupBy('dt.item_code', 'i.name', 'uom.name')
            ->orderBy('dt.item_code')
            ->select([
                'dt.item_code as item_code',
                'i.name as item_name',
                'uom.name as unit',
                DB::raw('SUM(CASE WHEN dt.qty > 0 THEN dt.qty ELSE 0 END) as qty_in'),
                DB::raw('SUM(CASE WHEN dt.qty < 0 THEN ABS(dt.qty) ELSE 0 END) as qty_out'),
                DB::raw('SUM(dt.amount) as net_amount'),
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
        $monthly = AccountingInventoryMonthly::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->whereDate('tran_date', '<=', $asOfDate)
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        if ($monthly !== null) {
            $ending = (float) $monthly->ending;
            $unitCost = (float) ($monthly->u_cost ?? 0);

            return [
                'balance_qty' => $ending,
                'balance_amount' => round($ending * $unitCost, 4),
                'weighted_unit_cost' => $unitCost,
            ];
        }

        $latest = AccountingInventoryDocTran::query()
            ->where('category_id', $categoryId)
            ->where('item_id', $itemId)
            ->whereDate('tran_date', '<=', $asOfDate)
            ->orderByDesc('tran_date')
            ->orderByDesc('id')
            ->first();

        $ending = (float) ($latest?->t_qty ?? 0);
        $unitCost = (float) ($latest?->ave_cost ?? $latest?->u_cost ?? 0);

        return [
            'balance_qty' => $ending,
            'balance_amount' => round($ending * $unitCost, 4),
            'weighted_unit_cost' => $unitCost,
        ];
    }

    private function resolveCategory(string $categoryName): ?ItemCategory
    {
        return ItemCategory::query()
            ->whereRaw('UPPER(name) = ?', [mb_strtoupper(trim($categoryName))])
            ->first();
    }
}
