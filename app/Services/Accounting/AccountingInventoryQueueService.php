<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryTransaction;
use App\Models\Delivery;
use App\Models\ReceivingReport;
use App\Models\TransferSlip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingInventoryQueueService
{
    public function __construct(
        private readonly AccountingInventoryPrefiller $prefiller,
        private readonly AccountingInventoryService $inventoryService,
    ) {}

    /**
     * @return array{total: int, pending: int, encoded: int}
     */
    public function summarizeStatuses(array $filters): array
    {
        $row = $this->filteredDocumentsQuery($filters)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_encoded = 1 THEN 1 ELSE 0 END) as encoded')
            ->first();

        $total = (int) ($row->total ?? 0);
        $encoded = (int) ($row->encoded ?? 0);

        return [
            'total' => $total,
            'pending' => max(0, $total - $encoded),
            'encoded' => $encoded,
        ];
    }

    /**
     * @return array{documents: LengthAwarePaginator<int, object>, summary: array{total: int, pending: int, encoded: int}}
     */
    public function paginateDocumentsWithSummary(array $filters, int $perPage = 15): array
    {
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page') ?: 1));
        $baseQuery = $this->filteredDocumentsQuery($filters);

        $summaryRow = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_encoded = 1 THEN 1 ELSE 0 END) as encoded')
            ->first();

        $total = (int) ($summaryRow->total ?? 0);
        $encoded = (int) ($summaryRow->encoded ?? 0);
        $summary = [
            'total' => $total,
            'pending' => max(0, $total - $encoded),
            'encoded' => $encoded,
        ];

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            $startRow = (($page - 1) * $perPage) + 1;
            $endRow = $page * $perPage;

            $items = DB::query()
                ->fromSub(
                    (clone $baseQuery)->selectRaw('*, ROW_NUMBER() OVER (ORDER BY doc_date DESC, sort_doc_number DESC) as row_num'),
                    'ranked_documents',
                )
                ->whereBetween('row_num', [$startRow, $endRow])
                ->orderBy('row_num')
                ->get()
                ->map(fn (object $row): object => $this->mapDocumentRow($row));
        } else {
            $items = (clone $baseQuery)
                ->orderByDesc('doc_date')
                ->orderByDesc('sort_doc_number')
                ->forPage($page, $perPage)
                ->get()
                ->map(fn (object $row): object => $this->mapDocumentRow($row));
        }

        $documents = (new ConcretePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        ))->appends(collect($filters)->except('page')->all());

        return [
            'documents' => $documents,
            'summary' => $summary,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginateDocuments(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paginateDocumentsWithSummary($filters, $perPage)['documents'];
    }

    private function mapDocumentRow(object $row): object
    {
        return (object) [
            'doc_type' => (string) $row->doc_type,
            'source_id' => $row->source_id !== null ? (int) $row->source_id : null,
            'category_id' => (int) $row->category_id,
            'category_name' => (string) $row->category_name,
            'doc_number' => (string) $row->doc_number,
            'doc_date' => $row->doc_date,
            'reference' => $row->reference,
            'party_name' => $row->party_name,
            'amount' => (float) $row->amount,
            'status' => (string) $row->status,
            'is_encoded' => (bool) $row->is_encoded,
            'is_corrected' => (bool) ($row->is_corrected ?? false),
            'encoded_by' => $row->encoded_by,
            'encoded_at' => $row->encoded_at,
            'transaction_id' => $row->transaction_id !== null ? (int) $row->transaction_id : null,
            'is_manual' => (bool) ($row->is_manual ?? false),
        ];
    }

    public function resolveSourceModel(string $docType, int $id): Model
    {
        $docType = strtoupper(trim($docType));

        return match ($docType) {
            'RR' => ReceivingReport::query()->findOrFail($id),
            'TS' => TransferSlip::query()->findOrFail($id),
            'DR' => Delivery::query()->findOrFail($id),
            default => throw new InvalidArgumentException("Unsupported document type [{$docType}]."),
        };
    }

    public function findOrCreateDraftForSource(Model $source, int $categoryId, ?int $userId = null): AccountingInventoryTransaction
    {
        $payload = $this->prefiller->buildFromSource($source, $categoryId);
        if ($payload['lines'] === []) {
            throw new InvalidArgumentException('No inventory lines for the selected category on this document.');
        }
        $docType = (string) $payload['header']['doc_type'];
        $docNumber = (string) $payload['header']['doc_number'];

        $scopedNumber = $this->scopedDocNumber($docType, $docNumber, $categoryId);

        $existing = AccountingInventoryTransaction::query()
            ->where('doc_type', $docType)
            ->where('doc_number', $scopedNumber)
            ->with('lines.item.unit')
            ->first();

        if ($existing) {
            if ($existing->source_id === null) {
                $existing->update([
                    'source_type' => $source::class,
                    'source_id' => $source->getKey(),
                ]);
            }

            return $existing->fresh(['lines.item.unit', 'category']) ?? $existing;
        }

        return DB::transaction(function () use ($payload, $source, $scopedNumber, $userId): AccountingInventoryTransaction {
            $transaction = AccountingInventoryTransaction::query()->create([
                ...$payload['header'],
                'doc_number' => $scopedNumber,
                'status' => AccountingInventoryTransaction::STATUS_DRAFT,
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'gl_status' => 'not_required',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($payload['lines'] as $index => $line) {
                $availableQty = $this->inventoryService->getAvailableQty($transaction->category_id, (int) $line['item_id']);
                if ($line['direction'] === 'out') {
                    $availableQty += (float) ($line['prefill_quantity'] ?? 0);
                }

                $transaction->lines()->create([
                    ...$line,
                    'available_qty_snapshot' => round($availableQty, 5),
                    'sort_order' => $index,
                ]);
            }

            $transaction->update([
                'total_amount' => round(collect($payload['lines'])->sum('amount'), 4),
            ]);

            return $transaction->load(['lines.item.unit', 'category']);
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function createManualDraft(array $header, array $lines, User $user): AccountingInventoryTransaction
    {
        $docType = strtoupper(trim((string) ($header['doc_type'] ?? '')));
        if (! in_array($docType, AccountingInventoryTransaction::MANUAL_DOC_TYPES, true)) {
            throw new InvalidArgumentException('Manual drafts only support CV and JV document types.');
        }

        return DB::transaction(function () use ($header, $lines, $user, $docType): AccountingInventoryTransaction {
            $transaction = AccountingInventoryTransaction::query()->create([
                'category_id' => (int) $header['category_id'],
                'doc_type' => $docType,
                'doc_number' => trim((string) $header['doc_number']),
                'doc_date' => $header['doc_date'],
                'po_number' => null,
                'party_code' => $header['party_code'] ?? null,
                'party_name' => $header['party_name'] ?? null,
                'remarks' => $header['remarks'] ?? null,
                'status' => AccountingInventoryTransaction::STATUS_DRAFT,
                'gl_status' => 'not_required',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $this->inventoryService->syncLines($transaction, $lines, $user->id);
        });
    }

    public function scopedDocNumber(string $docType, string $docNumber, int $categoryId): string
    {
        return strtoupper(trim($docType)).'|'.trim($docNumber).'|'.$categoryId;
    }

    public function displayDocNumber(AccountingInventoryTransaction $transaction): string
    {
        $parts = explode('|', $transaction->doc_number, 3);
        if (count($parts) === 3 && in_array($parts[0], ['RR', 'TS', 'DR'], true)) {
            return $parts[1];
        }

        return $transaction->doc_number;
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function filteredDocumentsQuery(array $filters): QueryBuilder
    {
        $docTypeFilter = strtoupper(trim((string) ($filters['doc_type'] ?? 'all')));
        $status = (string) ($filters['status'] ?? 'all');
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $queries = [];

        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'RR') {
            $queries[] = $this->receivingReportListQuery($categoryId);
        }
        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'TS') {
            $queries[] = $this->transferSlipListQuery($categoryId);
        }
        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'DR') {
            $queries[] = $this->deliveryListQuery($categoryId);
        }
        if ($docTypeFilter === 'ALL' || in_array($docTypeFilter, ['CV', 'JV'], true)) {
            $queries[] = $this->manualTransactionListQuery($docTypeFilter, $categoryId);
        }

        if ($queries === []) {
            return DB::query()->fromSub($this->receivingReportListQuery($categoryId)->whereRaw('1 = 0'), 'documents');
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $wrapped = DB::query()->fromSub($union, 'documents');

        if ($status === 'pending') {
            $wrapped->where('is_encoded', 0);
        } elseif ($status === 'encoded') {
            $wrapped->where('is_encoded', 1);
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $wrapped->whereDate('doc_date', '>=', $dateFrom);
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $wrapped->whereDate('doc_date', '<=', $dateTo);
        }

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $wrapped->where(function (QueryBuilder $query) use ($like): void {
                $query->whereRaw('LOWER(doc_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(reference, ?)) LIKE ?', ['', $like])
                    ->orWhereRaw('LOWER(COALESCE(party_name, ?)) LIKE ?', ['', $like])
                    ->orWhereRaw('LOWER(COALESCE(category_name, ?)) LIKE ?', ['', $like]);
            });
        }

        if ($categoryId > 0) {
            $wrapped->where('category_id', $categoryId);
        }

        return $wrapped;
    }

    private function scopedDocNumberExpression(string $docTypeColumn, string $docNumberColumn, string $categoryIdColumn): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "{$docTypeColumn} || '|' || {$docNumberColumn} || '|' || {$categoryIdColumn}";
        }

        if ($driver === 'sqlsrv') {
            return "CAST({$docTypeColumn} AS NVARCHAR(MAX)) + '|' + CAST({$docNumberColumn} AS NVARCHAR(MAX)) + '|' + CAST({$categoryIdColumn} AS NVARCHAR(MAX))";
        }

        return "CONCAT({$docTypeColumn}, '|', {$docNumberColumn}, '|', {$categoryIdColumn})";
    }

    private function receivingReportListQuery(int $categoryId): QueryBuilder
    {
        $query = DB::table('receiving_reports as rr')
            ->join('receiving_report_items as rri', 'rri.receiving_report_id', '=', 'rr.id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'rr.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('accounting_inventory_transactions as ait', function ($join): void {
                $join->on('ait.source_id', '=', 'rr.id')
                    ->where('ait.source_type', '=', ReceivingReport::class)
                    ->whereColumn('ait.category_id', 'ic.id')
                    ->where('ait.doc_type', '=', 'RR');
            })
            ->leftJoin('users as u', 'u.id', '=', 'ait.encoded_by')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->where('rri.qty_good', '>', 0)
            ->groupBy(
                'rr.id',
                'rr.rr_number',
                'rr.received_date',
                'po.po_number',
                's.name',
                'ic.id',
                'ic.name',
                'ait.id',
                'ait.status',
                'ait.total_amount',
                'ait.is_corrected',
                'ait.encoded_at',
                'u.name',
            )
            ->select([
                DB::raw("'RR' as doc_type"),
                'rr.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                DB::raw($this->scopedDocNumberExpression("'RR'", 'rr.rr_number', 'ic.id').' as doc_number'),
                'rr.rr_number as sort_doc_number',
                'rr.received_date as doc_date',
                'po.po_number as reference',
                's.name as party_name',
                DB::raw('COALESCE(NULLIF(ait.total_amount, 0), ROUND(SUM(COALESCE(rri.qty_good, 0) * COALESCE(poi.unit_price, 0)), 4)) as amount'),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 'encoded' WHEN ait.status = 'voided' THEN 'voided' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(ait.is_corrected, 0) as is_corrected'),
                'u.name as encoded_by',
                'ait.encoded_at as encoded_at',
                'ait.id as transaction_id',
                DB::raw('0 as is_manual'),
            ]);

        if ($categoryId > 0) {
            $query->where('ic.id', $categoryId);
        }

        return $query;
    }

    private function transferSlipListQuery(int $categoryId): QueryBuilder
    {
        $query = DB::table('transfer_slips as ts')
            ->join('transfer_slip_items as tsi', 'tsi.transfer_slip_id', '=', 'ts.id')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('accounting_inventory_transactions as ait', function ($join): void {
                $join->on('ait.source_id', '=', 'ts.id')
                    ->where('ait.source_type', '=', TransferSlip::class)
                    ->whereColumn('ait.category_id', 'ic.id')
                    ->where('ait.doc_type', '=', 'TS');
            })
            ->leftJoin('users as u', 'u.id', '=', 'ait.encoded_by')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->where('tsi.quantity', '>', 0)
            ->groupBy(
                'ts.id',
                'ts.ts_number',
                'ts.ts_date',
                'ts.transfer_to',
                'ic.id',
                'ic.name',
                'ait.id',
                'ait.status',
                'ait.total_amount',
                'ait.is_corrected',
                'ait.encoded_at',
                'u.name',
            )
            ->select([
                DB::raw("'TS' as doc_type"),
                'ts.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                DB::raw($this->scopedDocNumberExpression("'TS'", 'ts.ts_number', 'ic.id').' as doc_number'),
                'ts.ts_number as sort_doc_number',
                'ts.ts_date as doc_date',
                DB::raw('NULL as reference'),
                'ts.transfer_to as party_name',
                DB::raw('COALESCE(ait.total_amount, 0) as amount'),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 'encoded' WHEN ait.status = 'voided' THEN 'voided' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(ait.is_corrected, 0) as is_corrected'),
                'u.name as encoded_by',
                'ait.encoded_at as encoded_at',
                'ait.id as transaction_id',
                DB::raw('0 as is_manual'),
            ]);

        if ($categoryId > 0) {
            $query->where('ic.id', $categoryId);
        }

        return $query;
    }

    private function deliveryListQuery(int $categoryId): QueryBuilder
    {
        $query = DB::table('deliveries as dr')
            ->join('delivery_items as di', 'di.delivery_id', '=', 'dr.id')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'dr.supplier_id')
            ->leftJoin('accounting_inventory_transactions as ait', function ($join): void {
                $join->on('ait.source_id', '=', 'dr.id')
                    ->where('ait.source_type', '=', Delivery::class)
                    ->whereColumn('ait.category_id', 'ic.id')
                    ->where('ait.doc_type', '=', 'DR');
            })
            ->leftJoin('users as u', 'u.id', '=', 'ait.encoded_by')
            ->whereNull('dr.deleted_at')
            ->whereNull('di.deleted_at')
            ->where('di.quantity', '>', 0)
            ->groupBy(
                'dr.id',
                'dr.dr_number',
                'dr.dr_date',
                'dr.or_number',
                's.name',
                'dr.from_name',
                'ic.id',
                'ic.name',
                'ait.id',
                'ait.status',
                'ait.total_amount',
                'ait.is_corrected',
                'ait.encoded_at',
                'u.name',
            )
            ->select([
                DB::raw("'DR' as doc_type"),
                'dr.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                DB::raw($this->scopedDocNumberExpression("'DR'", 'dr.dr_number', 'ic.id').' as doc_number'),
                'dr.dr_number as sort_doc_number',
                'dr.dr_date as doc_date',
                'dr.or_number as reference',
                DB::raw('COALESCE(s.name, dr.from_name) as party_name'),
                DB::raw('COALESCE(ait.total_amount, 0) as amount'),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 'encoded' WHEN ait.status = 'voided' THEN 'voided' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(ait.is_corrected, 0) as is_corrected'),
                'u.name as encoded_by',
                'ait.encoded_at as encoded_at',
                'ait.id as transaction_id',
                DB::raw('0 as is_manual'),
            ]);

        if ($categoryId > 0) {
            $query->where('ic.id', $categoryId);
        }

        return $query;
    }

    private function manualTransactionListQuery(string $docTypeFilter, int $categoryId): QueryBuilder
    {
        $query = DB::table('accounting_inventory_transactions as ait')
            ->join('item_categories as ic', 'ic.id', '=', 'ait.category_id')
            ->leftJoin('users as u', 'u.id', '=', 'ait.encoded_by')
            ->whereIn('ait.doc_type', AccountingInventoryTransaction::MANUAL_DOC_TYPES)
            ->whereNull('ait.source_id')
            ->select([
                'ait.doc_type as doc_type',
                DB::raw('NULL as source_id'),
                'ic.id as category_id',
                'ic.name as category_name',
                'ait.doc_number as doc_number',
                'ait.doc_number as sort_doc_number',
                'ait.doc_date as doc_date',
                DB::raw('NULL as reference'),
                'ait.party_name as party_name',
                'ait.total_amount as amount',
                'ait.status as status',
                DB::raw("CASE WHEN ait.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                'ait.is_corrected as is_corrected',
                'u.name as encoded_by',
                'ait.encoded_at as encoded_at',
                'ait.id as transaction_id',
                DB::raw('1 as is_manual'),
            ]);

        if ($docTypeFilter !== 'ALL' && in_array($docTypeFilter, ['CV', 'JV'], true)) {
            $query->where('ait.doc_type', $docTypeFilter);
        }

        if ($categoryId > 0) {
            $query->where('ic.id', $categoryId);
        }

        return $query;
    }
}
