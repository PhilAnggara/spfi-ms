<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\Item;
use App\Models\ItemCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingInventoryReportService
{
    /**
     * Category names shown on accounting reports (must match item_categories.name).
     *
     * @var list<string>
     */
    public const REPORT_CATEGORY_NAMES = [
        'OFFICE SUPPLIES',
        'PARTS',
        'FACTORY SUPPLIES',
        'CHEM',
        'FUEL',
        'LABEL',
        'CARTON',
        'CAN',
        'PLASTIC BAG FPL',
        'SPICES AND INGREDIENTS',
        'COAL',
        'SLUDGE OIL',
        'LABELING SUPPLIES',
    ];

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
     * @return Collection<int, ItemCategory>
     */
    public function reportCategories(): Collection
    {
        return ItemCategory::query()
            ->whereIn('name', self::REPORT_CATEGORY_NAMES)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function stockCardRows(string $month, int $categoryId): Collection
    {
        $selectedMonth = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();

        $snapshots = $this->stockCardSnapshotsForMonth($categoryId, $monthStart, $monthEnd);

        if ($snapshots->isEmpty()) {
            $snapshots = $this->docTranFallbackSnapshots($categoryId, $monthEnd);
        }

        $snapshots = $snapshots->filter(function (array $row): bool {
            return (float) $row['qty'] > 0 || (float) $row['beginning_qty'] > 0;
        })->values();

        $localItems = $this->localItemsByCode($snapshots->pluck('item_code')->all());

        return $snapshots
            ->map(function (array $row) use ($localItems): array {
                $item = $localItems->get(strtoupper(trim((string) $row['item_code'])));

                return [
                    'item_code' => (string) $row['item_code'],
                    'item_description' => $item?->name,
                    'unit' => $item?->unit?->name,
                    'qty' => (float) $row['qty'],
                    'unit_cost' => (float) $row['unit_cost'],
                    'amount' => (float) $row['amount'],
                    'beginning_qty' => (float) $row['beginning_qty'],
                    'beginning_unit_cost' => (float) $row['beginning_unit_cost'],
                    'beginning_amount' => (float) $row['beginning_amount'],
                    'transaction' => (float) $row['transaction'],
                ];
            })
            ->sortBy('item_code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function transactionGroups(string $dateFrom, string $dateTo, int $categoryId): Collection
    {
        $rows = DB::table('accounting_inventory_doc_tran as dt')
            ->where('dt.category_id', $categoryId)
            ->whereDate('dt.doc_date', '>=', $dateFrom)
            ->whereDate('dt.doc_date', '<=', $dateTo)
            ->orderBy('dt.item_code')
            ->orderBy('dt.doc_date')
            ->orderBy('dt.doc_code')
            ->orderBy('dt.doc_no')
            ->orderBy('dt.id')
            ->get([
                'dt.item_code',
                'dt.doc_date',
                'dt.doc_code',
                'dt.doc_no',
                'dt.qty',
                'dt.u_cost',
                'dt.amount',
                'dt.t_qty',
            ]);

        if ($rows->isEmpty()) {
            return collect();
        }

        $localItems = $this->localItemsByCode($rows->pluck('item_code')->all());

        return $rows
            ->groupBy(fn (object $row): string => strtoupper(trim((string) $row->item_code)))
            ->map(function (Collection $itemRows, string $itemCode) use ($localItems): array {
                $item = $localItems->get($itemCode);
                $lines = $itemRows->map(fn (object $row): array => [
                    'doc_date' => Carbon::parse($row->doc_date)->toDateString(),
                    'doc_type' => strtoupper((string) $row->doc_code),
                    'doc_number' => (string) $row->doc_no,
                    'qty' => (float) $row->qty,
                    'unit_cost' => (float) $row->u_cost,
                    'amount' => (float) $row->amount,
                    'balance' => (float) ($row->t_qty ?? 0),
                ])->values();

                $summary = $lines
                    ->groupBy('doc_type')
                    ->map(fn (Collection $typed, string $docType): array => [
                        'doc_type' => $docType,
                        'qty' => round((float) $typed->sum(fn (array $line): float => abs((float) $line['qty'])), 5),
                        'amount' => round((float) $typed->sum(fn (array $line): float => abs((float) $line['amount'])), 4),
                    ])
                    ->sortKeys()
                    ->values();

                $rrAmount = (float) ($summary->firstWhere('doc_type', 'RR')['amount'] ?? 0);
                $tsAmount = (float) ($summary->firstWhere('doc_type', 'TS')['amount'] ?? 0);
                $rrQty = (float) ($summary->firstWhere('doc_type', 'RR')['qty'] ?? 0);
                $tsQty = (float) ($summary->firstWhere('doc_type', 'TS')['qty'] ?? 0);

                return [
                    'item_code' => (string) $itemRows->first()->item_code,
                    'item_name' => $item?->name,
                    'unit' => $item?->unit?->name,
                    'rows' => $lines,
                    'document_summary' => $summary,
                    'rr_minus_ts_qty' => round($rrQty - $tsQty, 5),
                    'rr_minus_ts_amount' => round($rrAmount - $tsAmount, 4),
                ];
            })
            ->sortBy('item_code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function restatementRows(string $month, int $categoryId): Collection
    {
        $selectedMonth = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();

        $stockCards = $this->stockCardRows($month, $categoryId)
            ->groupBy(fn (array $row): string => strtoupper(trim((string) $row['item_code'])))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $qty = (float) $rows->sum('qty');
                $amount = (float) $rows->sum('amount');
                $beginningQty = (float) $rows->sum('beginning_qty');
                $beginningAmount = (float) $rows->sum('beginning_amount');

                return [
                    'item_code' => (string) $first['item_code'],
                    'qty' => $qty,
                    'unit_cost' => $qty > 0 ? $amount / $qty : 0.0,
                    'amount' => $amount,
                    'beginning_qty' => $beginningQty,
                    'beginning_unit_cost' => $beginningQty > 0 ? $beginningAmount / $beginningQty : 0.0,
                    'beginning_amount' => $beginningAmount,
                    'transaction' => $amount - $beginningAmount,
                ];
            });

        $movements = DB::table('accounting_inventory_doc_tran')
            ->where('category_id', $categoryId)
            ->whereDate('doc_date', '>=', $monthStart)
            ->whereDate('doc_date', '<=', $monthEnd)
            ->select([
                'item_code',
                'doc_code',
                DB::raw('SUM(qty) as qty_sum'),
                DB::raw('SUM(amount) as amount_sum'),
            ])
            ->groupBy('item_code', 'doc_code')
            ->get()
            ->groupBy(fn (object $row): string => strtoupper(trim((string) $row->item_code)));

        $imEndings = $this->imEndingQtyByItemCode($categoryId, $monthEnd);

        $itemCodes = $stockCards->keys()
            ->merge($movements->keys())
            ->merge($imEndings->keys())
            ->unique()
            ->values();

        $localItems = $this->localItemsByCode($itemCodes->all());

        return $itemCodes
            ->map(function (string $itemCode) use ($stockCards, $movements, $localItems, $imEndings): array {
                $stock = $stockCards->get($itemCode);
                $itemMovements = $movements->get($itemCode, collect());

                $purchaseQty = 0.0;
                $purchaseAmount = 0.0;
                $issuanceQty = 0.0;
                $issuanceAmount = 0.0;

                foreach ($itemMovements as $movement) {
                    $docCode = strtoupper((string) $movement->doc_code);
                    $qtySum = (float) $movement->qty_sum;
                    $amountSum = (float) $movement->amount_sum;

                    if ($docCode === 'RR') {
                        $purchaseQty += abs($qtySum);
                        $purchaseAmount += abs($amountSum);
                    } elseif (in_array($docCode, ['TS', 'DR'], true)) {
                        $issuanceQty += abs($qtySum);
                        $issuanceAmount += abs($amountSum);
                    }
                }

                $stock = $stock ?? [];
                $begQty = (float) ($stock['beginning_qty'] ?? 0);
                $begUnitCost = (float) ($stock['beginning_unit_cost'] ?? 0);
                $begAmount = (float) ($stock['beginning_amount'] ?? ($begQty * $begUnitCost));

                $endQty = (float) ($stock['qty'] ?? ($begQty + $purchaseQty - $issuanceQty));
                $endUnitCost = (float) ($stock['unit_cost'] ?? 0);
                $endAmount = (float) ($stock['amount'] ?? ($endQty * $endUnitCost));

                $purchaseUnitCost = $purchaseQty > 0 ? round($purchaseAmount / $purchaseQty, 8) : 0.0;
                $issuanceUnitCost = $issuanceQty > 0 ? round($issuanceAmount / $issuanceQty, 8) : 0.0;

                $percountQty = $imEndings->has($itemCode) ? (float) $imEndings->get($itemCode) : null;
                $percountAmount = $percountQty === null ? null : round($percountQty * $endUnitCost, 4);
                $varianceQty = $percountQty === null ? null : round($percountQty - $endQty, 5);
                $varianceAmount = $percountAmount === null ? null : round($percountAmount - $endAmount, 4);

                $item = $localItems->get($itemCode);

                return [
                    'item_name' => $item?->name,
                    'item_code' => $stock['item_code'] ?? ($itemMovements->first()->item_code ?? ($item?->code ?? $itemCode)),
                    'beg_qty' => $begQty,
                    'beg_unit_cost' => $begUnitCost,
                    'beg_amount' => $begAmount,
                    'purchase_qty' => $purchaseQty,
                    'purchase_unit_cost' => $purchaseUnitCost,
                    'purchase_amount' => $purchaseAmount,
                    'issuance_qty' => $issuanceQty,
                    'issuance_unit_cost' => $issuanceUnitCost,
                    'issuance_amount' => $issuanceAmount,
                    'end_theoretical_qty' => $endQty,
                    'end_theoretical_unit_cost' => $endUnitCost,
                    'end_theoretical_amount' => $endAmount,
                    'percount_qty' => $percountQty,
                    'percount_amount' => $percountAmount,
                    'variance_qty' => $varianceQty,
                    'variance_amount' => $varianceAmount,
                    'total_qty' => $endQty,
                    'total_amount' => $endAmount,
                ];
            })
            ->filter(function (array $row): bool {
                return (float) $row['beg_qty'] != 0
                    || (float) $row['purchase_qty'] != 0
                    || (float) $row['issuance_qty'] != 0
                    || (float) $row['end_theoretical_qty'] != 0
                    || (float) ($row['percount_qty'] ?? 0) != 0;
            })
            ->sortBy('item_code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function stockCardCountRows(string $month, int $categoryId): Collection
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $imEndings = $this->imEndingQtyByItemCode($categoryId, $monthEnd);

        return $this->stockCardRows($month, $categoryId)
            ->map(function (array $row) use ($imEndings): array {
                $itemCode = strtoupper(trim((string) $row['item_code']));
                $stockCardQty = (float) $row['qty'];
                $stockCardAmount = (float) $row['amount'];
                $unitCost = $stockCardQty > 0 ? $stockCardAmount / $stockCardQty : (float) $row['unit_cost'];

                $percountQty = $imEndings->has($itemCode) ? (float) $imEndings->get($itemCode) : null;
                $percountAmount = $percountQty === null ? null : round($percountQty * $unitCost, 4);

                return [
                    'item_name' => $row['item_description'],
                    'item_code' => $row['item_code'],
                    'stock_card_qty' => $stockCardQty,
                    'stock_card_amount' => $stockCardAmount,
                    'percount_qty' => $percountQty,
                    'percount_amount' => $percountAmount,
                    'variance_qty' => $percountQty === null ? null : round($percountQty - $stockCardQty, 5),
                    'variance_amount' => $percountAmount === null ? null : round($percountAmount - $stockCardAmount, 4),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function documentSummaryGroups(string $dateFrom, string $dateTo, int $categoryId): Collection
    {
        $groups = AccountingInventoryDocTran::query()
            ->where('category_id', $categoryId)
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
    public function purchaseRows(string $dateFrom, string $dateTo, int $categoryId): Collection
    {
        $rows = DB::table('accounting_inventory_doc_tran as dt')
            ->leftJoin('suppliers as s', 's.id', '=', 'dt.supplier_id')
            ->where('dt.doc_code', 'RR')
            ->where('dt.category_id', $categoryId)
            ->whereDate('dt.doc_date', '>=', $dateFrom)
            ->whereDate('dt.doc_date', '<=', $dateTo)
            ->orderBy('dt.doc_date')
            ->orderBy('dt.doc_no')
            ->get([
                'dt.doc_no',
                'dt.doc_date',
                'dt.po_no',
                'dt.party_name',
                's.name as supplier_name',
                'dt.item_code',
                'dt.qty',
                'dt.u_cost',
                'dt.amount',
            ]);

        $localItems = $this->localItemsByCode($rows->pluck('item_code')->all());

        return $rows->map(function (object $row) use ($localItems): array {
            $item = $localItems->get(strtoupper(trim((string) $row->item_code)));
            $supplierName = trim((string) ($row->party_name ?: $row->supplier_name ?: ''));

            return [
                'supplier_name' => $supplierName !== '' ? $supplierName : null,
                'po_number' => $row->po_no,
                'rr_number' => $row->doc_no,
                'date' => $row->doc_date,
                'currency' => 'IDR',
                'item_code' => $row->item_code,
                'item_name' => $item?->name,
                'unit' => $item?->unit?->name ?? '',
                'quantity' => abs((float) $row->qty),
                'unit_price' => abs((float) $row->u_cost),
                'amount' => abs((float) $row->amount),
            ];
        });
    }

    /**
     * IM ending stock as of a date, keyed by uppercase item code.
     * Uses last stock_balances.end per warehouse (same as IM stock inventory report),
     * falling back to current stock_inventories.balance when no ledger exists on/before as-of.
     *
     * @return Collection<string, float>
     */
    private function imEndingQtyByItemCode(int $categoryId, string $asOf): Collection
    {
        $endColumn = DB::getQueryGrammar()->wrap('sb.end');

        $ledgerEndings = DB::query()
            ->fromSub(
                DB::table('stock_balances as sb')
                    ->join('items as i', 'i.id', '=', 'sb.item_id')
                    ->whereNull('i.deleted_at')
                    ->where('i.category_id', $categoryId)
                    ->whereDate('sb.date', '<=', $asOf)
                    ->selectRaw(
                        "sb.item_id, i.code as item_code, sb.wh_code, {$endColumn} as ending_qty, ".
                        'ROW_NUMBER() OVER (PARTITION BY sb.item_id, sb.wh_code ORDER BY sb.date DESC, sb.id DESC) as rn'
                    ),
                'ranked'
            )
            ->where('rn', 1)
            ->get()
            ->groupBy(fn (object $row): string => strtoupper(trim((string) $row->item_code)))
            ->map(fn (Collection $rows): float => (float) $rows->sum('ending_qty'));

        $inventoryBalances = DB::table('stock_inventories as si')
            ->join('items as i', 'i.id', '=', 'si.item_id')
            ->whereNull('i.deleted_at')
            ->where('i.category_id', $categoryId)
            ->where('si.is_delete', false)
            ->groupBy('i.code')
            ->selectRaw('i.code as item_code, COALESCE(SUM(si.balance), 0) as balance')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                strtoupper(trim((string) $row->item_code)) => (float) $row->balance,
            ]);

        return $ledgerEndings->union($inventoryBalances);
    }

    /**
     * Legacy-style stock card snapshot: SUM(begining)/SUM(ending) grouped by item_code + u_cost.
     * Beginning amount uses the row u_cost (same as legacy AISystem register), not begining_u_cost.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stockCardSnapshotsForMonth(int $categoryId, string $monthStart, string $monthEnd): Collection
    {
        return DB::table('accounting_inventory_monthly')
            ->where('category_id', $categoryId)
            ->whereDate('tran_date', '>=', $monthStart)
            ->whereDate('tran_date', '<=', $monthEnd)
            ->groupBy('item_code', 'u_cost')
            ->orderBy('item_code')
            ->select([
                'item_code',
                'u_cost',
                DB::raw('SUM(ending) as ending'),
                DB::raw('SUM(begining) as beginning'),
            ])
            ->get()
            ->map(function (object $row): array {
                $endingQty = (float) $row->ending;
                $beginningQty = (float) $row->beginning;
                $unitCost = (float) $row->u_cost;
                $amount = $endingQty * $unitCost;
                $beginningAmount = $beginningQty * $unitCost;

                return [
                    'item_code' => (string) $row->item_code,
                    'qty' => $endingQty,
                    'unit_cost' => $unitCost,
                    'amount' => $amount,
                    'beginning_qty' => $beginningQty,
                    'beginning_unit_cost' => $unitCost,
                    'beginning_amount' => $beginningAmount,
                    'transaction' => $amount - $beginningAmount,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function docTranFallbackSnapshots(int $categoryId, string $monthEnd): Collection
    {
        $latestIds = AccountingInventoryDocTran::query()
            ->where('category_id', $categoryId)
            ->whereDate('tran_date', '<=', $monthEnd)
            ->selectRaw('MAX(id) as id')
            ->groupBy('item_code')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return collect();
        }

        return AccountingInventoryDocTran::query()
            ->whereIn('id', $latestIds)
            ->orderBy('item_code')
            ->get(['item_code', 'ave_cost', 'u_cost', 't_qty'])
            ->map(function (AccountingInventoryDocTran $row): array {
                $endingQty = (float) ($row->t_qty ?? 0);
                $unitCost = (float) ($row->ave_cost ?? $row->u_cost ?? 0);
                $amount = $endingQty * $unitCost;

                return [
                    'item_code' => (string) $row->item_code,
                    'qty' => $endingQty,
                    'unit_cost' => $unitCost,
                    'amount' => $amount,
                    'beginning_qty' => 0.0,
                    'beginning_unit_cost' => 0.0,
                    'beginning_amount' => 0.0,
                    'transaction' => $amount,
                ];
            })
            ->values();
    }

    /**
     * @param  list<mixed>  $itemCodes
     * @return Collection<string, Item>
     */
    private function localItemsByCode(array $itemCodes): Collection
    {
        $codes = collect($itemCodes)
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($codes === []) {
            return collect();
        }

        return collect($codes)
            ->chunk(1000)
            ->flatMap(function (Collection $chunk): Collection {
                $placeholders = implode(',', array_fill(0, $chunk->count(), '?'));

                return Item::query()
                    ->with('unit')
                    ->whereRaw('UPPER(code) IN ('.$placeholders.')', $chunk->values()->all())
                    ->get();
            })
            ->keyBy(fn (Item $item): string => strtoupper((string) $item->code));
    }
}
