<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingInventoryReportService
{
    /**
     * Report dropdown labels that differ from stored Category values (AISystem / local master).
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_NAME_ALIASES = [
        'SPARE PARTS' => ['SPARE PARTS', 'PARTS'],
        'CHEMICAL' => ['CHEMICAL', 'CHEM'],
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
     * @return Collection<int, array<string, mixed>>
     */
    public function stockCardRows(string $month, string $categoryName): Collection
    {
        $categoryKeys = $this->categoryKeysForFilter($categoryName);
        $selectedMonth = Carbon::createFromFormat('Y-m', $month);
        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();

        $legacyRows = $this->monthlyRowsForMonth($categoryKeys, $monthStart, $monthEnd);

        if ($legacyRows->isEmpty()) {
            $legacyRows = $this->docTranFallbackRowsForMonth($categoryKeys, $monthEnd);
        }

        $legacyRows = $legacyRows->filter(function (object $row): bool {
            return (float) $row->ending > 0 || (float) $row->beginning > 0;
        })->values();

        $localItems = $this->localItemsByCode(
            $legacyRows->pluck('item_code')->all()
        );

        return $legacyRows
            ->map(function (object $row) use ($localItems): array {
                $itemCode = strtoupper(trim((string) $row->item_code));
                $item = $localItems->get($itemCode);
                $ending = (float) $row->ending;
                $beginning = (float) $row->beginning;
                $unitCost = (float) $row->u_cost;

                $amount = $ending * $unitCost;
                $beginningAmount = $beginning * $unitCost;

                return [
                    'item_code' => (string) $row->item_code,
                    'item_description' => $item?->name,
                    'unit' => $item?->unit?->name,
                    'qty' => $ending,
                    'unit_cost' => $unitCost,
                    'amount' => $amount,
                    'beginning_amount' => $beginningAmount,
                    'transaction' => $amount - $beginningAmount,
                ];
            })
            ->sortBy('item_code')
            ->values();
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
        $categoryKeys = $this->categoryKeysForFilter($categoryName);

        $groups = AccountingInventoryDocTran::query()
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $categoryKeys))
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
        $categoryKeys = $this->categoryKeysForFilter($categoryName);

        $rows = DB::table('accounting_inventory_doc_tran as dt')
            ->where('dt.doc_code', 'RR')
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $categoryKeys, 'dt.category'))
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
                'dt.qty',
                'dt.u_cost',
                'dt.amount',
            ]);

        $localItems = $this->localItemsByCode($rows->pluck('item_code')->all());

        return $rows->map(function (object $row) use ($localItems): array {
            $item = $localItems->get(strtoupper(trim((string) $row->item_code)));

            return [
                'supplier_name' => $row->party_name,
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
     * @return Collection<int, array<string, mixed>>
     */
    public function documentSummaryPerItem(string $dateFrom, string $dateTo, string $categoryName): Collection
    {
        $categoryKeys = $this->categoryKeysForFilter($categoryName);

        $rows = DB::table('accounting_inventory_doc_tran as dt')
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $categoryKeys, 'dt.category'))
            ->whereDate('dt.doc_date', '>=', $dateFrom)
            ->whereDate('dt.doc_date', '<=', $dateTo)
            ->groupBy('dt.item_code')
            ->orderBy('dt.item_code')
            ->select([
                'dt.item_code as item_code',
                DB::raw('SUM(CASE WHEN dt.qty > 0 THEN dt.qty ELSE 0 END) as qty_in'),
                DB::raw('SUM(CASE WHEN dt.qty < 0 THEN ABS(dt.qty) ELSE 0 END) as qty_out'),
                DB::raw('SUM(dt.amount) as net_amount'),
            ])
            ->get();

        $localItems = $this->localItemsByCode($rows->pluck('item_code')->all());

        return $rows->map(function (object $row) use ($localItems): array {
            $item = $localItems->get(strtoupper(trim((string) $row->item_code)));

            return [
                'item_code' => $row->item_code,
                'item_name' => $item?->name,
                'unit' => $item?->unit?->name,
                'qty_in' => (float) $row->qty_in,
                'qty_out' => (float) $row->qty_out,
                'net_amount' => (float) $row->net_amount,
            ];
        });
    }

    /**
     * @param  list<string>  $categoryKeys
     * @return Collection<int, object{item_code: string, u_cost: float|int|string, ending: float|int|string, beginning: float|int|string}>
     */
    private function monthlyRowsForMonth(array $categoryKeys, string $monthStart, string $monthEnd): Collection
    {
        return DB::table('accounting_inventory_monthly')
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $categoryKeys))
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
            ->get();
    }

    /**
     * @param  list<string>  $categoryKeys
     * @return Collection<int, object{item_code: string, u_cost: float|int|string, ending: float|int|string, beginning: float|int|string}>
     */
    private function docTranFallbackRowsForMonth(array $categoryKeys, string $monthEnd): Collection
    {
        $latestIds = AccountingInventoryDocTran::query()
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $categoryKeys))
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
            ->map(fn (AccountingInventoryDocTran $row): object => (object) [
                'item_code' => $row->item_code,
                'u_cost' => (float) ($row->ave_cost ?? $row->u_cost ?? 0),
                'ending' => (float) ($row->t_qty ?? 0),
                'beginning' => 0.0,
            ]);
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

    /**
     * @return list<string>
     */
    private function categoryKeysForFilter(string $categoryName): array
    {
        $normalized = mb_strtoupper(trim($categoryName));
        $aliases = self::CATEGORY_NAME_ALIASES[$normalized] ?? [$normalized];

        return array_values(array_unique(array_map(
            fn (string $name): string => mb_strtoupper(trim($name)),
            $aliases,
        )));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\AccountingInventoryDocTran>|\Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $categoryKeys
     */
    private function applyCategoryFilter(mixed $query, array $categoryKeys, string $column = 'category'): void
    {
        $placeholders = implode(',', array_fill(0, count($categoryKeys), '?'));

        $query->whereRaw('UPPER(LTRIM(RTRIM('.$column.'))) IN ('.$placeholders.')', $categoryKeys);
    }
}
