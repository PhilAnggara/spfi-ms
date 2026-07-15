<?php

namespace App\Services\Accounting;

use App\Models\AccountingCode;
use App\Models\AccountingDocTransaction;
use App\Models\AccountingDocTransactionLine;
use App\Models\Delivery;
use App\Models\ReceivingReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingDocTransactionService
{
    /**
     * @var list<string>
     */
    public const DOC_TYPES = ['RR', 'DR'];

    public function __construct(
        private readonly ReceivingReportEntryGenerator $entryGenerator,
    ) {}

    public function isDocumentEncoded(string $docType, string $docNumber): bool
    {
        $docType = strtoupper(trim($docType));
        $docNumber = trim($docNumber);

        if ($docType === '' || $docNumber === '') {
            return false;
        }

        return AccountingDocTransaction::query()
            ->where('doc_type', $docType)
            ->where('doc_number', $docNumber)
            ->where('status', 'encoded')
            ->exists();
    }

    public function findEncodedTransaction(string $docType, string $docNumber): ?AccountingDocTransaction
    {
        return AccountingDocTransaction::query()
            ->where('doc_type', strtoupper(trim($docType)))
            ->where('doc_number', trim($docNumber))
            ->where('status', 'encoded')
            ->with(['lines', 'encodedBy'])
            ->first();
    }

    public function findOrCreateDraftForDocument(Model $source, ?int $userId = null): AccountingDocTransaction
    {
        [$docType, $docNumber] = $this->resolveDocIdentity($source);

        $existing = AccountingDocTransaction::query()
            ->where('doc_type', $docType)
            ->where('doc_number', $docNumber)
            ->with('lines')
            ->first();

        if ($existing) {
            if ($existing->source_id === null || $existing->source_type === null) {
                $existing->update([
                    'source_type' => $source::class,
                    'source_id' => $source->getKey(),
                ]);
            }

            return $existing->fresh(['lines', 'encodedBy']) ?? $existing;
        }

        if ($docType === 'RR' && $source instanceof ReceivingReport) {
            return $this->createDraftFromReceivingReport($source, $userId);
        }

        return $this->createEmptyDraft($source, $docType, $docNumber, $userId);
    }

    /**
     * @param  list<array{group_code?: string|null, cost_center?: string|null, account_code?: string|null, description?: string|null, debit?: mixed, credit?: mixed}>  $lines
     */
    public function syncLines(AccountingDocTransaction $transaction, array $lines, ?int $userId = null): AccountingDocTransaction
    {
        return DB::transaction(function () use ($transaction, $lines, $userId): AccountingDocTransaction {
            $transaction->lines()->delete();

            $normalizedLines = collect($lines)
                ->values()
                ->map(function (array $line, int $index): array {
                    $accountCode = trim((string) ($line['account_code'] ?? ''));
                    $description = trim((string) ($line['description'] ?? ''));
                    if ($description === '' && $accountCode !== '') {
                        $description = (string) ($this->entryGenerator->resolveAccountDescription($accountCode) ?? '');
                    }

                    $groupCode = trim((string) ($line['group_code'] ?? $line['cost_center'] ?? ''));

                    return [
                        'line_no' => $index + 1,
                        'group_code' => $groupCode,
                        'account_code' => $accountCode,
                        'description' => $description !== '' ? $description : null,
                        'debit' => round(max(0, (float) ($line['debit'] ?? 0)), 4),
                        'credit' => round(max(0, (float) ($line['credit'] ?? 0)), 4),
                    ];
                })
                ->filter(fn (array $line): bool => $line['account_code'] !== '' || $line['debit'] > 0 || $line['credit'] > 0)
                ->values()
                ->all();

            $this->persistGeneratedLines($transaction, $normalizedLines);
            $this->recalculateTotals($transaction, $userId);

            return $transaction->fresh(['lines', 'encodedBy', 'updatedBy']) ?? $transaction;
        });
    }

    public function encode(AccountingDocTransaction $transaction, User $user): AccountingDocTransaction
    {
        $transaction->update([
            'status' => 'encoded',
            'encoded_by' => $user->id,
            'encoded_at' => now(),
            'updated_by' => $user->id,
        ]);

        return $transaction->fresh(['lines', 'encodedBy', 'updatedBy']) ?? $transaction;
    }

    public function recalculateTotals(AccountingDocTransaction $transaction, ?int $userId = null): void
    {
        $transaction->loadMissing('lines');
        $linePayload = $transaction->lines
            ->map(fn (AccountingDocTransactionLine $line): array => [
                'group_code' => $line->group_code ?? '',
                'account_code' => $line->account_code,
                'description' => $line->description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ])
            ->all();

        $totals = $this->entryGenerator->calculateTotals($linePayload);

        $transaction->update([
            'cost_code_total' => $totals['cost_code_total'],
            'acct_code_total' => $totals['acct_code_total'],
            'total_debit' => $totals['total_debit'],
            'total_credit' => $totals['total_credit'],
            'variance' => $totals['variance'],
            'updated_by' => $userId ?? $transaction->updated_by,
        ]);
    }

    /**
     * @return array{total: int, pending: int, encoded: int}
     */
    public function summarizeStatuses(?string $docType = null): array
    {
        $row = $this->filteredDocumentsQuery([
            'status' => 'all',
            'doc_type' => $docType && $docType !== 'all' ? strtoupper($docType) : 'all',
            'keyword' => '',
            'date_from' => '',
            'date_to' => '',
        ])
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
     * @return LengthAwarePaginator<int, object>
     */
    public function paginateDocuments(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page') ?: 1));
        $total = (int) $this->filteredDocumentsQuery($filters)->count();

        $items = $this->filteredDocumentsQuery($filters)
            ->orderByDesc('doc_date')
            ->orderByDesc('doc_number')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): object => (object) [
                'doc_type' => (string) $row->doc_type,
                'source_id' => $row->source_id !== null ? (int) $row->source_id : null,
                'doc_number' => (string) $row->doc_number,
                'doc_date' => $row->doc_date,
                'reference' => $row->reference,
                'party_code' => $row->party_code,
                'party_name' => $row->party_name,
                'cost_total' => (float) $row->amount,
                'status' => (string) $row->status,
                'is_encoded' => (bool) $row->is_encoded,
                'encoded_by' => $row->encoded_by,
                'encoded_at' => $row->encoded_at,
                'transaction_id' => $row->transaction_id !== null ? (int) $row->transaction_id : null,
                'is_orphan' => (bool) ($row->is_orphan ?? false),
            ]);

        return (new ConcretePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        ))->appends(collect($filters)->except('page')->all());
    }

    public function resolveSourceModel(string $docType, int $id): Model
    {
        $docType = strtoupper(trim($docType));

        return match ($docType) {
            'RR' => ReceivingReport::query()->findOrFail($id),
            'DR' => Delivery::query()->findOrFail($id),
            default => throw new InvalidArgumentException("Unsupported document type [{$docType}]."),
        };
    }

    /**
     * @return list<array{code: string, description: string}>
     */
    public function lookupAccounts(string $term, int $limit = 15): array
    {
        $normalizedTerm = trim($term);
        if ($normalizedTerm === '') {
            return [];
        }

        $like = '%'.$normalizedTerm.'%';

        return AccountingCode::query()
            ->where(function (Builder $query) use ($like): void {
                $query->whereRaw('TRIM(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(desc) LIKE ?', [mb_strtolower($like)]);
            })
            ->orderBy('code')
            ->limit($limit)
            ->get(['code', 'desc'])
            ->map(fn (AccountingCode $code): array => [
                'code' => trim((string) $code->code),
                'description' => trim((string) $code->desc),
            ])
            ->values()
            ->all();
    }

    private function createDraftFromReceivingReport(ReceivingReport $receivingReport, ?int $userId = null): AccountingDocTransaction
    {
        $payload = $this->entryGenerator->generate($receivingReport);

        return DB::transaction(function () use ($payload, $receivingReport, $userId): AccountingDocTransaction {
            $transaction = AccountingDocTransaction::query()->create([
                'doc_type' => 'RR',
                'source_type' => ReceivingReport::class,
                'source_id' => $receivingReport->id,
                'doc_number' => $payload['header']['doc_number'],
                'doc_date' => $payload['header']['doc_date'],
                'po_number' => $payload['header']['po_number'],
                'supplier_code' => $payload['header']['supplier_code'],
                'supplier_name' => $payload['header']['supplier_name'],
                'cost_code_total' => $payload['totals']['cost_code_total'],
                'acct_code_total' => $payload['totals']['acct_code_total'],
                'total_debit' => $payload['totals']['total_debit'],
                'total_credit' => $payload['totals']['total_credit'],
                'variance' => $payload['totals']['variance'],
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->persistGeneratedLines($transaction, $payload['lines']);

            return $transaction->load('lines');
        });
    }

    private function createEmptyDraft(Model $source, string $docType, string $docNumber, ?int $userId = null): AccountingDocTransaction
    {
        $header = $this->snapshotHeader($source, $docType, $docNumber);

        return AccountingDocTransaction::query()->create([
            'doc_type' => $docType,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'doc_number' => $docNumber,
            'doc_date' => $header['doc_date'],
            'po_number' => $header['po_number'],
            'supplier_code' => $header['supplier_code'],
            'supplier_name' => $header['supplier_name'],
            'cost_code_total' => 0,
            'acct_code_total' => 0,
            'total_debit' => 0,
            'total_credit' => 0,
            'variance' => 0,
            'status' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
        ])->load('lines');
    }

    /**
     * @return array{doc_date: string, po_number: ?string, supplier_code: ?string, supplier_name: ?string}
     */
    private function snapshotHeader(Model $source, string $docType, string $docNumber): array
    {
        if ($source instanceof ReceivingReport) {
            $source->loadMissing(['purchaseOrder.supplier']);

            return [
                'doc_date' => $source->received_date?->toDateString() ?? now()->toDateString(),
                'po_number' => $source->purchaseOrder?->po_number,
                'supplier_code' => $source->purchaseOrder?->supplier?->code,
                'supplier_name' => $source->purchaseOrder?->supplier?->name,
            ];
        }

        if ($source instanceof Delivery) {
            $source->loadMissing('supplier');

            return [
                'doc_date' => $source->dr_date?->toDateString() ?? now()->toDateString(),
                'po_number' => null,
                'supplier_code' => $source->supplier?->code,
                'supplier_name' => $source->supplier?->name ?? $source->from_name,
            ];
        }

        return [
            'doc_date' => now()->toDateString(),
            'po_number' => null,
            'supplier_code' => null,
            'supplier_name' => null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDocIdentity(Model $source): array
    {
        if ($source instanceof ReceivingReport) {
            return ['RR', trim((string) $source->rr_number)];
        }

        if ($source instanceof Delivery) {
            return ['DR', trim((string) $source->dr_number)];
        }

        throw new InvalidArgumentException('Unsupported source model ['.$source::class.'].');
    }

    /**
     * @param  array{status?: string, doc_type?: string, keyword?: string, date_from?: string, date_to?: string}  $filters
     */
    private function filteredDocumentsQuery(array $filters): QueryBuilder
    {
        $docTypeFilter = strtolower(trim((string) ($filters['doc_type'] ?? 'all')));
        $status = (string) ($filters['status'] ?? 'all');
        $queries = [];

        if ($docTypeFilter === 'all' || $docTypeFilter === 'rr') {
            $queries[] = $this->receivingReportListQuery();
        }

        if ($docTypeFilter === 'all' || $docTypeFilter === 'dr') {
            $queries[] = $this->deliveryListQuery();
        }

        // Import rows from legacy GL that have no matching SPFI RR/DR yet.
        if ($status === 'all' || $status === 'encoded') {
            if ($docTypeFilter === 'all' || $docTypeFilter === 'rr') {
                $queries[] = $this->orphanEncodedListQuery('RR');
            }

            if ($docTypeFilter === 'all' || $docTypeFilter === 'dr') {
                $queries[] = $this->orphanEncodedListQuery('DR');
            }
        }

        if ($queries === []) {
            return DB::query()
                ->fromSub($this->receivingReportListQuery()->whereRaw('1 = 0'), 'documents');
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
                    ->orWhereRaw('LOWER(COALESCE(party_code, ?)) LIKE ?', ['', $like]);
            });
        }

        return $wrapped;
    }

    private function receivingReportListQuery(): QueryBuilder
    {
        return DB::table('receiving_reports as rr')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'rr.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('accounting_doc_transactions as adt', function ($join): void {
                $join->on('adt.doc_number', '=', 'rr.rr_number')
                    ->where('adt.doc_type', '=', 'RR');
            })
            ->leftJoin('users as u', 'u.id', '=', 'adt.encoded_by')
            ->whereNull('rr.deleted_at')
            ->select([
                DB::raw("'RR' as doc_type"),
                'rr.id as source_id',
                'rr.rr_number as doc_number',
                'rr.received_date as doc_date',
                'po.po_number as reference',
                's.code as party_code',
                's.name as party_name',
                DB::raw('COALESCE(NULLIF(adt.total_debit, 0), (
                    SELECT ROUND(SUM((COALESCE(rri.qty_good, 0) + COALESCE(rri.qty_bad, 0)) * COALESCE(poi.unit_price, 0)), 4)
                    FROM receiving_report_items as rri
                    INNER JOIN purchase_order_items as poi ON poi.id = rri.purchase_order_item_id
                    WHERE rri.receiving_report_id = rr.id
                      AND rri.deleted_at IS NULL
                ), 0) as amount'),
                DB::raw("CASE WHEN adt.status = 'encoded' THEN 'encoded' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN adt.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                'u.name as encoded_by',
                'adt.encoded_at as encoded_at',
                'adt.id as transaction_id',
                DB::raw('0 as is_orphan'),
            ]);
    }

    private function deliveryListQuery(): QueryBuilder
    {
        return DB::table('deliveries as dr')
            ->leftJoin('suppliers as s', 's.id', '=', 'dr.supplier_id')
            ->leftJoin('accounting_doc_transactions as adt', function ($join): void {
                $join->on('adt.doc_number', '=', 'dr.dr_number')
                    ->where('adt.doc_type', '=', 'DR');
            })
            ->leftJoin('users as u', 'u.id', '=', 'adt.encoded_by')
            ->whereNull('dr.deleted_at')
            ->select([
                DB::raw("'DR' as doc_type"),
                'dr.id as source_id',
                'dr.dr_number as doc_number',
                'dr.dr_date as doc_date',
                'dr.or_number as reference',
                's.code as party_code',
                DB::raw('COALESCE(s.name, dr.from_name) as party_name'),
                DB::raw('COALESCE(NULLIF(adt.total_debit, 0), 0) as amount'),
                DB::raw("CASE WHEN adt.status = 'encoded' THEN 'encoded' ELSE 'pending' END as status"),
                DB::raw("CASE WHEN adt.status = 'encoded' THEN 1 ELSE 0 END as is_encoded"),
                'u.name as encoded_by',
                'adt.encoded_at as encoded_at',
                'adt.id as transaction_id',
                DB::raw('0 as is_orphan'),
            ]);
    }

    /**
     * Encoded import rows with no matching SPFI operational document.
     */
    private function orphanEncodedListQuery(string $docType): QueryBuilder
    {
        $query = DB::table('accounting_doc_transactions as adt')
            ->leftJoin('users as u', 'u.id', '=', 'adt.encoded_by')
            ->where('adt.doc_type', $docType)
            ->where('adt.status', 'encoded')
            ->select([
                DB::raw("'{$docType}' as doc_type"),
                DB::raw('NULL as source_id'),
                'adt.doc_number as doc_number',
                'adt.doc_date as doc_date',
                'adt.po_number as reference',
                'adt.supplier_code as party_code',
                'adt.supplier_name as party_name',
                DB::raw('COALESCE(NULLIF(adt.total_debit, 0), 0) as amount'),
                DB::raw("'encoded' as status"),
                DB::raw('1 as is_encoded'),
                'u.name as encoded_by',
                'adt.encoded_at as encoded_at',
                'adt.id as transaction_id',
                DB::raw('1 as is_orphan'),
            ]);

        if ($docType === 'RR') {
            $query->whereNotExists(function (QueryBuilder $sub): void {
                $sub->select(DB::raw('1'))
                    ->from('receiving_reports as rr')
                    ->whereColumn('rr.rr_number', 'adt.doc_number')
                    ->whereNull('rr.deleted_at');
            });
        }

        if ($docType === 'DR') {
            $query->whereNotExists(function (QueryBuilder $sub): void {
                $sub->select(DB::raw('1'))
                    ->from('deliveries as dr')
                    ->whereColumn('dr.dr_number', 'adt.doc_number')
                    ->whereNull('dr.deleted_at');
            });
        }

        return $query;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function persistGeneratedLines(AccountingDocTransaction $transaction, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $groupCode = trim((string) ($line['group_code'] ?? $line['cost_center'] ?? ''));

            AccountingDocTransactionLine::query()->create([
                'accounting_doc_transaction_id' => $transaction->id,
                'line_no' => (int) ($line['line_no'] ?? ($index + 1)),
                'group_code' => $groupCode !== '' ? $groupCode : null,
                'account_code' => (string) ($line['account_code'] ?? ''),
                'description' => $line['description'] ?? $this->entryGenerator->resolveAccountDescription((string) ($line['account_code'] ?? '')),
                'debit' => round((float) ($line['debit'] ?? 0), 4),
                'credit' => round((float) ($line['credit'] ?? 0), 4),
            ]);
        }
    }
}
