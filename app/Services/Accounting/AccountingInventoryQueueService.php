<?php

namespace App\Services\Accounting;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Delivery;
use App\Models\ItemCategory;
use App\Models\ReceivingReport;
use App\Models\TransferSlip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Carbon;
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
        $status = (string) ($filters['status'] ?? 'all');

        if ($status === 'pending') {
            $pending = (int) $this->filteredDocumentsQuery($filters)->count();

            return [
                'total' => $pending,
                'pending' => $pending,
                'encoded' => 0,
            ];
        }

        if ($status === 'encoded') {
            $encoded = (int) $this->filteredDocumentsQuery($filters)->count();

            return [
                'total' => $encoded,
                'pending' => 0,
                'encoded' => $encoded,
            ];
        }

        $pending = (int) $this->filteredDocumentsQuery(array_merge($filters, ['status' => 'pending']))->count();
        $encoded = (int) $this->filteredDocumentsQuery(array_merge($filters, ['status' => 'encoded']))->count();

        return [
            'total' => $pending + $encoded,
            'pending' => $pending,
            'encoded' => $encoded,
        ];
    }

    /**
     * @return array{
     *     documents: LengthAwarePaginator<int, object>,
     *     summary: array{total: int, pending: int, encoded: int},
     *     firstPending: object|null
     * }
     */
    public function paginateDocumentsWithSummary(array $filters, int $perPage = 15): array
    {
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page') ?: 1));
        $status = (string) ($filters['status'] ?? 'all');
        $baseQuery = $this->filteredDocumentsQuery($filters);

        if ($status === 'all') {
            $summary = $this->summarizeStatuses($filters);
            $total = $summary['total'];
        } else {
            $total = (int) (clone $baseQuery)->count();
            $summary = $status === 'pending'
                ? ['total' => $total, 'pending' => $total, 'encoded' => 0]
                : ['total' => $total, 'pending' => 0, 'encoded' => $total];
        }

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
            'firstPending' => $status === 'pending'
                ? $this->resolveFirstPendingDocument($filters)
                : null,
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
            'transaction_id' => null,
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

    public function buildDocumentForSource(Model $source, int $categoryId): AccountingInventoryTransaction
    {
        if ($source instanceof ReceivingReport) {
            $source->loadMissing(['purchaseOrder.supplier']);
        } elseif ($source instanceof Delivery) {
            $source->loadMissing('supplier');
        }

        $payload = $this->prefiller->buildFromSource($source, $categoryId);
        if ($payload['lines'] === []) {
            throw new InvalidArgumentException('No inventory lines for the selected category on this document.');
        }

        $docType = (string) $payload['header']['doc_type'];
        $docNumber = (string) $payload['header']['doc_number'];
        $category = ItemCategory::query()->findOrFail($categoryId);
        $isEncoded = $this->inventoryService->isDocumentEncoded($docType, $docNumber, $categoryId);

        $document = AccountingInventoryTransaction::make([
            ...$payload['header'],
            'status' => $isEncoded
                ? AccountingInventoryTransaction::STATUS_ENCODED
                : AccountingInventoryTransaction::STATUS_DRAFT,
            'source_type' => $source::class,
            'source_id' => (int) $source->getKey(),
            'supplier_id' => $this->resolveSupplierId($source),
            'purchase_order_id' => $this->resolvePurchaseOrderId($source),
            'category' => $category,
        ]);

        if ($isEncoded) {
            return $this->hydrateEncodedDocument($document);
        }

        $this->inventoryService->hydrateDocumentLines($document, $payload['lines']);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function createAndEncodeManual(array $header, array $lines, User $user): AccountingInventoryTransaction
    {
        $docType = strtoupper(trim((string) ($header['doc_type'] ?? '')));
        if (! in_array($docType, AccountingInventoryTransaction::MANUAL_DOC_TYPES, true)) {
            throw new InvalidArgumentException('Manual documents only support CV and JV document types.');
        }

        $docNumber = trim((string) $header['doc_number']);
        $categoryId = (int) $header['category_id'];

        if ($this->inventoryService->isDocumentEncoded($docType, $docNumber, $categoryId)) {
            throw new InvalidArgumentException("Document {$docType} {$docNumber} is already encoded.");
        }

        $category = ItemCategory::query()->findOrFail($categoryId);
        $document = AccountingInventoryTransaction::make([
            'category_id' => $categoryId,
            'doc_type' => $docType,
            'doc_number' => $docNumber,
            'doc_date' => $header['doc_date'],
            'po_number' => null,
            'party_code' => $header['party_code'] ?? null,
            'party_name' => $header['party_name'] ?? null,
            'remarks' => $header['remarks'] ?? null,
            'status' => AccountingInventoryTransaction::STATUS_DRAFT,
            'category' => $category,
        ]);

        return $this->inventoryService->encodeDocument($document, $lines, $user);
    }

    public function buildManualDocument(string $docType, string $docNumber, int $categoryId): AccountingInventoryTransaction
    {
        $docType = strtoupper(trim($docType));
        $category = ItemCategory::query()->findOrFail($categoryId);
        $first = AccountingInventoryDocTran::query()
            ->where('doc_code', $docType)
            ->where('doc_no', $docNumber)
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->first();

        if ($first === null) {
            throw new InvalidArgumentException('Encoded document not found.');
        }

        $document = AccountingInventoryTransaction::make([
            'category_id' => $categoryId,
            'doc_type' => $docType,
            'doc_number' => $docNumber,
            'doc_date' => $first->doc_date,
            'po_number' => $first->po_no,
            'party_code' => $first->party_code,
            'party_name' => $first->party_name,
            'remarks' => $first->remarks,
            'status' => AccountingInventoryTransaction::STATUS_ENCODED,
            'source_type' => $first->source_type,
            'source_id' => $first->source_id,
            'supplier_id' => $first->supplier_id,
            'purchase_order_id' => $first->purchase_order_id,
            'encoded_by' => $first->encoded_by,
            'encoded_at' => $first->encoded_at,
            'is_corrected' => (bool) $first->is_corrected,
            'category' => $category,
            'encodedBy' => $first->encodedBy,
        ]);

        return $this->hydrateEncodedDocument($document);
    }

    public function displayDocNumber(AccountingInventoryTransaction $transaction): string
    {
        return $transaction->displayDocNumber();
    }

    private function hydrateEncodedDocument(AccountingInventoryTransaction $document): AccountingInventoryTransaction
    {
        $rows = AccountingInventoryDocTran::query()
            ->with(['item.unit', 'encodedBy'])
            ->where('doc_code', strtoupper($document->doc_type))
            ->where('doc_no', $document->displayDocNumber())
            ->where('category_id', $document->category_id)
            ->orderBy('id')
            ->get();

        $lines = $rows->map(function (AccountingInventoryDocTran $row, int $index): AccountingInventoryTransactionLine {
            $qty = abs((float) $row->qty);

            return AccountingInventoryTransactionLine::make([
                'id' => $row->id,
                'item_id' => (int) ($row->item_id ?? 0),
                'direction' => (float) $row->qty < 0
                    ? AccountingInventoryTransactionLine::DIRECTION_OUT
                    : AccountingInventoryTransactionLine::DIRECTION_IN,
                'quantity' => $qty,
                'unit_of_measure_id' => $row->item?->unit_of_measure_id,
                'unit_cost' => abs((float) $row->u_cost),
                'amount' => abs((float) $row->amount),
                'prefill_quantity' => $qty,
                'prefill_unit_cost' => abs((float) $row->u_cost),
                'available_qty_snapshot' => (float) ($row->t_qty ?? 0),
                'sort_order' => $index,
                'item' => $row->item,
            ]);
        });

        $first = $rows->first();
        $document->lines = $lines;
        $document->status = AccountingInventoryTransaction::STATUS_ENCODED;
        $document->total_amount = round($lines->sum(fn (AccountingInventoryTransactionLine $line): float => (float) $line->amount), 4);
        $document->is_corrected = (bool) ($first?->is_corrected ?? false);
        $document->encoded_by = $first?->encoded_by;
        $document->encoded_at = $first?->encoded_at;
        $document->encodedBy = $first?->encodedBy;
        $document->party_code = $first?->party_code ?? $document->party_code;
        $document->party_name = $first?->party_name ?? $document->party_name;
        $document->po_number = $first?->po_no ?? $document->po_number;
        $document->supplier_id = $first?->supplier_id ?? $document->supplier_id;
        $document->purchase_order_id = $first?->purchase_order_id ?? $document->purchase_order_id;

        return $document;
    }

    private function resolveSupplierId(Model $source): ?int
    {
        return match (true) {
            $source instanceof ReceivingReport => $source->purchaseOrder?->supplier_id
                ? (int) $source->purchaseOrder->supplier_id
                : null,
            $source instanceof Delivery => $source->supplier_id ? (int) $source->supplier_id : null,
            default => null,
        };
    }

    private function resolvePurchaseOrderId(Model $source): ?int
    {
        if ($source instanceof ReceivingReport && $source->purchase_order_id) {
            return (int) $source->purchase_order_id;
        }

        return null;
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function filteredDocumentsQuery(array $filters): QueryBuilder
    {
        $docTypeFilter = strtoupper(trim((string) ($filters['doc_type'] ?? 'all')));
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $queries = [];

        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'RR') {
            $queries[] = $this->receivingReportListQuery($filters, $categoryId);
        }
        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'TS') {
            $queries[] = $this->transferSlipListQuery($filters, $categoryId);
        }
        if ($docTypeFilter === 'ALL' || $docTypeFilter === 'DR') {
            $queries[] = $this->deliveryListQuery($filters, $categoryId);
        }
        if ($docTypeFilter === 'ALL' || in_array($docTypeFilter, ['CV', 'JV'], true)) {
            $queries[] = $this->manualDocumentListQuery($filters, $docTypeFilter, $categoryId);
        }

        if ($queries === []) {
            return DB::query()->fromSub(
                $this->receivingReportListQuery($filters, $categoryId)->whereRaw('1 = 0'),
                'documents',
            );
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'documents');
    }

    /**
     * @param  array{status?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     * @param  list<string>  $keywordColumns
     */
    private function applySourceBranchFilters(
        QueryBuilder $query,
        array $filters,
        string $dateColumn,
        array $keywordColumns,
        string $encodedExistsSql,
    ): void {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where($dateColumn, '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where($dateColumn, '<', Carbon::parse($dateTo)->addDay()->toDateString());
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'pending') {
            $query->whereRaw("NOT ({$encodedExistsSql})");
        } elseif ($status === 'encoded') {
            $query->whereRaw($encodedExistsSql);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '' && $keywordColumns !== []) {
            $like = '%'.$keyword.'%';
            $query->where(function (QueryBuilder $nested) use ($like, $keywordColumns): void {
                foreach ($keywordColumns as $index => $column) {
                    if ($index === 0) {
                        $nested->where($column, 'like', $like);
                    } else {
                        $nested->orWhere($column, 'like', $like);
                    }
                }
            });
        }
    }

    private function encodedExistsExpression(string $docCodeLiteral, string $docNoColumn, string $categoryIdColumn): string
    {
        return "EXISTS (
            SELECT 1 FROM accounting_inventory_doc_tran dt
            WHERE dt.doc_code = {$docCodeLiteral}
              AND dt.doc_no = {$docNoColumn}
              AND dt.category_id = {$categoryIdColumn}
        )";
    }

    private function encodedMetaSubquery(string $docCode): QueryBuilder
    {
        return DB::table('accounting_inventory_doc_tran as dt')
            ->where('dt.doc_code', $docCode)
            ->groupBy('dt.doc_no', 'dt.category_id')
            ->select([
                'dt.doc_no',
                'dt.category_id',
                DB::raw('SUM(ABS(dt.amount)) as encoded_amount'),
                DB::raw('MAX(dt.encoded_at) as encoded_at'),
                DB::raw('MAX(CASE WHEN dt.is_corrected = 1 THEN 1 ELSE 0 END) as is_corrected'),
            ]);
    }

    /**
     * @param  array{status?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function receivingReportListQuery(array $filters, int $categoryId): QueryBuilder
    {
        $lineAgg = DB::table('receiving_report_items as rri')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->whereNull('rri.deleted_at')
            ->where('rri.qty_good', '>', 0)
            ->groupBy('rri.receiving_report_id', 'i.category_id')
            ->select([
                'rri.receiving_report_id',
                'i.category_id',
                DB::raw('ROUND(SUM(COALESCE(rri.qty_good, 0) * COALESCE(poi.unit_price, 0)), 4) as line_amount'),
            ]);

        if ($categoryId > 0) {
            $lineAgg->where('i.category_id', $categoryId);
        }

        $encodedExists = $this->encodedExistsExpression("'RR'", 'rr.rr_number', 'ic.id');

        $encodedMeta = $this->encodedMetaSubquery('RR');

        $query = DB::table('receiving_reports as rr')
            ->joinSub($lineAgg, 'lines', function ($join): void {
                $join->on('lines.receiving_report_id', '=', 'rr.id');
            })
            ->join('item_categories as ic', 'ic.id', '=', 'lines.category_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'rr.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoinSub($encodedMeta, 'enc', function ($join): void {
                $join->on('enc.doc_no', '=', 'rr.rr_number')
                    ->on('enc.category_id', '=', 'ic.id');
            })
            ->whereNull('rr.deleted_at')
            ->select([
                DB::raw("'RR' as doc_type"),
                'rr.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                'rr.rr_number as doc_number',
                'rr.rr_number as sort_doc_number',
                'rr.received_date as doc_date',
                'po.po_number as reference',
                's.name as party_name',
                DB::raw('COALESCE(NULLIF(enc.encoded_amount, 0), lines.line_amount) as amount'),
                DB::raw("CASE WHEN {$encodedExists} THEN 'encoded' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN {$encodedExists} THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(enc.is_corrected, 0) as is_corrected'),
                DB::raw('NULL as encoded_by'),
                'enc.encoded_at as encoded_at',
                DB::raw('NULL as transaction_id'),
                DB::raw('0 as is_manual'),
            ]);

        $this->applySourceBranchFilters($query, $filters, 'rr.received_date', [
            'rr.rr_number',
            'po.po_number',
            's.name',
            'ic.name',
        ], $encodedExists);

        return $query;
    }

    /**
     * @param  array{status?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function transferSlipListQuery(array $filters, int $categoryId): QueryBuilder
    {
        $lineAgg = DB::table('transfer_slip_items as tsi')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->whereNull('tsi.deleted_at')
            ->where('tsi.quantity', '>', 0)
            ->groupBy('tsi.transfer_slip_id', 'i.category_id')
            ->select([
                'tsi.transfer_slip_id',
                'i.category_id',
            ]);

        if ($categoryId > 0) {
            $lineAgg->where('i.category_id', $categoryId);
        }

        $encodedExists = $this->encodedExistsExpression("'TS'", 'ts.ts_number', 'ic.id');

        $encodedMeta = $this->encodedMetaSubquery('TS');

        $query = DB::table('transfer_slips as ts')
            ->joinSub($lineAgg, 'lines', function ($join): void {
                $join->on('lines.transfer_slip_id', '=', 'ts.id');
            })
            ->join('item_categories as ic', 'ic.id', '=', 'lines.category_id')
            ->leftJoinSub($encodedMeta, 'enc', function ($join): void {
                $join->on('enc.doc_no', '=', 'ts.ts_number')
                    ->on('enc.category_id', '=', 'ic.id');
            })
            ->whereNull('ts.deleted_at')
            ->select([
                DB::raw("'TS' as doc_type"),
                'ts.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                'ts.ts_number as doc_number',
                'ts.ts_number as sort_doc_number',
                'ts.ts_date as doc_date',
                DB::raw('NULL as reference'),
                'ts.transfer_to as party_name',
                DB::raw('COALESCE(enc.encoded_amount, 0) as amount'),
                DB::raw("CASE WHEN {$encodedExists} THEN 'encoded' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN {$encodedExists} THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(enc.is_corrected, 0) as is_corrected'),
                DB::raw('NULL as encoded_by'),
                'enc.encoded_at as encoded_at',
                DB::raw('NULL as transaction_id'),
                DB::raw('0 as is_manual'),
            ]);

        $this->applySourceBranchFilters($query, $filters, 'ts.ts_date', [
            'ts.ts_number',
            'ts.transfer_to',
            'ic.name',
        ], $encodedExists);

        return $query;
    }

    /**
     * @param  array{status?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function deliveryListQuery(array $filters, int $categoryId): QueryBuilder
    {
        $lineAgg = DB::table('delivery_items as di')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->whereNull('di.deleted_at')
            ->where('di.quantity', '>', 0)
            ->groupBy('di.delivery_id', 'i.category_id')
            ->select([
                'di.delivery_id',
                'i.category_id',
            ]);

        if ($categoryId > 0) {
            $lineAgg->where('i.category_id', $categoryId);
        }

        $encodedExists = $this->encodedExistsExpression("'DR'", 'dr.dr_number', 'ic.id');

        $encodedMeta = $this->encodedMetaSubquery('DR');

        $query = DB::table('deliveries as dr')
            ->joinSub($lineAgg, 'lines', function ($join): void {
                $join->on('lines.delivery_id', '=', 'dr.id');
            })
            ->join('item_categories as ic', 'ic.id', '=', 'lines.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'dr.supplier_id')
            ->leftJoinSub($encodedMeta, 'enc', function ($join): void {
                $join->on('enc.doc_no', '=', 'dr.dr_number')
                    ->on('enc.category_id', '=', 'ic.id');
            })
            ->whereNull('dr.deleted_at')
            ->select([
                DB::raw("'DR' as doc_type"),
                'dr.id as source_id',
                'ic.id as category_id',
                'ic.name as category_name',
                'dr.dr_number as doc_number',
                'dr.dr_number as sort_doc_number',
                'dr.dr_date as doc_date',
                'dr.or_number as reference',
                DB::raw('COALESCE(s.name, dr.from_name) as party_name'),
                DB::raw('COALESCE(enc.encoded_amount, 0) as amount'),
                DB::raw("CASE WHEN {$encodedExists} THEN 'encoded' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN {$encodedExists} THEN 1 ELSE 0 END as is_encoded"),
                DB::raw('COALESCE(enc.is_corrected, 0) as is_corrected'),
                DB::raw('NULL as encoded_by'),
                'enc.encoded_at as encoded_at',
                DB::raw('NULL as transaction_id'),
                DB::raw('0 as is_manual'),
            ]);

        $this->applySourceBranchFilters($query, $filters, 'dr.dr_date', [
            'dr.dr_number',
            'dr.or_number',
            's.name',
            'dr.from_name',
            'ic.name',
        ], $encodedExists);

        return $query;
    }

    /**
     * @param  array{status?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function manualDocumentListQuery(array $filters, string $docTypeFilter, int $categoryId): QueryBuilder
    {
        $query = DB::table('accounting_inventory_doc_tran as dt')
            ->join('item_categories as ic', 'ic.id', '=', 'dt.category_id')
            ->leftJoin('users as u', 'u.id', '=', 'dt.encoded_by')
            ->whereIn('dt.doc_code', AccountingInventoryTransaction::MANUAL_DOC_TYPES)
            ->whereNull('dt.source_id')
            ->groupBy(
                'dt.doc_code',
                'dt.doc_no',
                'ic.id',
                'ic.name',
                'dt.doc_date',
                'dt.party_name',
                'u.name',
            )
            ->select([
                'dt.doc_code as doc_type',
                DB::raw('NULL as source_id'),
                'ic.id as category_id',
                'ic.name as category_name',
                'dt.doc_no as doc_number',
                'dt.doc_no as sort_doc_number',
                'dt.doc_date as doc_date',
                DB::raw('NULL as reference'),
                'dt.party_name as party_name',
                DB::raw('SUM(ABS(dt.amount)) as amount'),
                DB::raw("'encoded' as status"),
                DB::raw('1 as is_encoded'),
                DB::raw('MAX(CASE WHEN dt.is_corrected = 1 THEN 1 ELSE 0 END) as is_corrected'),
                'u.name as encoded_by',
                DB::raw('MAX(dt.encoded_at) as encoded_at'),
                DB::raw('NULL as transaction_id'),
                DB::raw('1 as is_manual'),
            ]);

        if ($docTypeFilter !== 'ALL' && in_array($docTypeFilter, ['CV', 'JV'], true)) {
            $query->where('dt.doc_code', $docTypeFilter);
        }

        if ($categoryId > 0) {
            $query->where('ic.id', $categoryId);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('dt.doc_date', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('dt.doc_date', '<', Carbon::parse($dateTo)->addDay()->toDateString());
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'pending') {
            $query->whereRaw('1 = 0');
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (QueryBuilder $nested) use ($like): void {
                $nested->where('dt.doc_no', 'like', $like)
                    ->orWhere('dt.party_name', 'like', $like)
                    ->orWhere('ic.name', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    public function countPendingByType(string $docType, array $filters): int
    {
        $docType = strtoupper(trim($docType));
        $pendingFilters = array_merge($filters, [
            'status' => 'pending',
            'doc_type' => strtolower($docType),
        ]);

        return (int) (clone $this->filteredDocumentsQuery($pendingFilters))->count();
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    public function resolveFirstPendingDocument(array $filters): ?object
    {
        $pendingFilters = array_merge($filters, ['status' => 'pending']);

        $row = (clone $this->filteredDocumentsQuery($pendingFilters))
            ->orderBy('doc_date')
            ->orderBy('sort_doc_number')
            ->first();

        return $row !== null ? $this->mapDocumentOpenUrl($row) : null;
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     * @return array{
     *     next: array<string, mixed>|null,
     *     queue_stats: array{pending: int, remaining_type: int, doc_type: string}
     * }
     */
    public function resolveEncodeQueueState(AccountingInventoryTransaction $transaction, array $filters): array
    {
        $docType = strtoupper(trim($transaction->doc_type));
        $sortDocNumber = $transaction->displayDocNumber();
        $docDate = $transaction->doc_date?->toDateString() ?? '';

        $pendingTypeFilters = array_merge($filters, [
            'status' => 'pending',
            'doc_type' => strtolower($docType),
        ]);
        $base = $this->filteredDocumentsQuery($pendingTypeFilters);

        $remainingType = (int) (clone $base)->count();

        $nextRow = (clone $base)
            ->where(function (QueryBuilder $query) use ($docDate, $sortDocNumber): void {
                $query->whereDate('doc_date', '>', $docDate)
                    ->orWhere(function (QueryBuilder $nested) use ($docDate, $sortDocNumber): void {
                        $nested->whereDate('doc_date', $docDate)
                            ->where('sort_doc_number', '>', $sortDocNumber);
                    });
            })
            ->orderBy('doc_date')
            ->orderBy('sort_doc_number')
            ->first();

        $filterDocType = strtolower((string) ($filters['doc_type'] ?? 'all'));
        $pending = $filterDocType === 'all' || $filterDocType === ''
            ? (int) $this->filteredDocumentsQuery(array_merge($filters, ['status' => 'pending', 'doc_type' => 'all']))->count()
            : $remainingType;

        return [
            'next' => $nextRow !== null ? $this->mapNextDocumentPreview($nextRow) : null,
            'queue_stats' => [
                'pending' => $pending,
                'remaining_type' => $remainingType,
                'doc_type' => $docType,
            ],
        ];
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     * @return array<string, mixed>|null
     */
    public function resolveNextPendingDocument(AccountingInventoryTransaction $encoded, array $filters): ?array
    {
        return $this->resolveEncodeQueueState($encoded, $filters)['next'];
    }

    /**
     * @param  array{status?: string, doc_type?: string, category_id?: int|string, keyword?: string, date_from?: string, date_to?: string}  $filters
     * @return array{pending: int, remaining_type: int, doc_type: string}
     */
    public function queueStatsForTransaction(AccountingInventoryTransaction $transaction, array $filters): array
    {
        return $this->resolveEncodeQueueState($transaction, $filters)['queue_stats'];
    }

    private function mapDocumentOpenUrl(object $row): object
    {
        $displayNumber = (string) $row->doc_number;
        $isManual = (bool) ($row->is_manual ?? false);

        $openUrl = $isManual
            ? route('accounting.inventory-transactions.manual', [
                'docType' => strtolower((string) $row->doc_type),
                'docNumber' => $displayNumber,
                'category_id' => (int) $row->category_id,
            ])
            : route('accounting.inventory-transactions.show', [
                'docType' => strtolower((string) $row->doc_type),
                'id' => (int) $row->source_id,
                'category_id' => (int) $row->category_id,
            ]);

        return (object) [
            'doc_type' => (string) $row->doc_type,
            'doc_number' => $displayNumber,
            'category_id' => (int) $row->category_id,
            'source_id' => $row->source_id !== null ? (int) $row->source_id : null,
            'transaction_id' => null,
            'is_manual' => $isManual,
            'open_url' => $openUrl,
            'title' => trim((string) $row->doc_type).' '.$displayNumber,
        ];
    }

    /**
     * @return array{
     *     url: string,
     *     title: string,
     *     doc_type: string,
     *     doc_number: string,
     *     category_name: string,
     *     party_name: string,
     *     doc_date: string,
     *     doc_date_label: string,
     *     amount: float,
     *     amount_label: string
     * }
     */
    private function mapNextDocumentPreview(object $row): array
    {
        $mapped = $this->mapDocumentOpenUrl($row);
        $docDate = $row->doc_date ?? null;
        $docDateLabel = '';

        if ($docDate !== null && $docDate !== '') {
            $docDateLabel = Carbon::parse($docDate)->format('d M Y');
        }

        $amount = (float) ($row->amount ?? 0);

        return [
            'url' => $mapped->open_url,
            'title' => $mapped->title,
            'doc_type' => $mapped->doc_type,
            'doc_number' => $mapped->doc_number,
            'category_name' => (string) ($row->category_name ?? ''),
            'party_name' => (string) ($row->party_name ?? ''),
            'doc_date' => $docDateLabel !== '' ? Carbon::parse($docDate)->toDateString() : '',
            'doc_date_label' => $docDateLabel,
            'amount' => $amount,
            'amount_label' => number_format($amount, 2, '.', ','),
        ];
    }
}
