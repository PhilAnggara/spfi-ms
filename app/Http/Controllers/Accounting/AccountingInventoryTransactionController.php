<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\EncodeAccountingInventoryTransactionRequest;
use App\Http\Requests\StoreAccountingInventoryTransactionRequest;
use App\Http\Requests\VoidAccountingInventoryTransactionRequest;
use App\Models\AccountingInventoryTransaction;
use App\Models\ItemCategory;
use App\Services\Accounting\AccountingInventoryQueueService;
use App\Services\Accounting\AccountingInventoryService;
use App\Support\Concerns\SearchesAccountingInventoryItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingInventoryTransactionController extends Controller
{
    use SearchesAccountingInventoryItems;

    public function __construct(
        private readonly AccountingInventoryQueueService $queueService,
        private readonly AccountingInventoryService $inventoryService,
    ) {}

    public function index(Request $request): View|Response
    {
        $filters = $this->resolveFilters($request);
        $pageData = $this->queueService->paginateDocumentsWithSummary($filters, 15);
        $documents = $pageData['documents'];
        $summary = $pageData['summary'];
        $firstPending = ($filters['status'] ?? 'pending') === 'pending'
            ? $this->queueService->resolveFirstPendingDocument($filters)
            : null;

        if ($request->ajax()) {
            return response()->view('pages.accounting.inventory-transactions.partials.results-panel', [
                'documents' => $documents,
                'filters' => $filters,
                'summary' => $summary,
                'firstPending' => $firstPending,
            ]);
        }

        $categories = ItemCategory::query()->orderBy('name')->get(['id', 'name']);

        return view('pages.accounting.inventory-transactions.index', [
            'documents' => $documents,
            'filters' => $filters,
            'summary' => $summary,
            'categories' => $categories,
            'firstPending' => $firstPending,
        ]);
    }

    public function create(Request $request): View|Response
    {
        $payload = [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'name']),
            'itemSearchUrl' => route('accounting.inventory-transactions.items.search'),
            'selectedCategoryId' => (int) $request->query('category_id', 0),
            'formId' => $request->ajax() || $request->boolean('modal') ? 'inv-modal-create-form' : 'inv-create-form',
            'isModal' => $request->ajax() || $request->boolean('modal'),
        ];

        if ($request->ajax() || $request->boolean('modal')) {
            return response()->view('pages.accounting.inventory-transactions.partials.manual-create-form', $payload);
        }

        return view('pages.accounting.inventory-transactions.create', $payload);
    }

    public function store(StoreAccountingInventoryTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $transaction = $this->queueService->createManualDraft(
            [
                'category_id' => $validated['category_id'],
                'doc_type' => $validated['doc_type'],
                'doc_number' => $validated['doc_number'],
                'doc_date' => $validated['doc_date'],
                'party_name' => $validated['party_name'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ],
            $validated['lines'],
            $request->user(),
        );

        return redirect()
            ->route('accounting.inventory-transactions.transaction', $transaction)
            ->with('success', 'Manual transaction draft created. Review and encode when ready.');
    }

    public function show(Request $request, string $docType, int $id): View|RedirectResponse|Response|JsonResponse
    {
        $categoryId = (int) $request->query('category_id');
        abort_if($categoryId <= 0, 404);

        try {
            $source = $this->queueService->resolveSourceModel($docType, $id);
            $transaction = $this->queueService->findOrCreateDraftForSource(
                $source,
                $categoryId,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $exception) {
            if ($request->ajax() || $request->boolean('modal')) {
                return response()->json(['message' => $exception->getMessage()], 400);
            }

            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', $exception->getMessage());
        }

        return $this->renderEncodeScreen($request, $transaction);
    }

    public function showTransaction(Request $request, AccountingInventoryTransaction $transaction): View|Response
    {
        $transaction->load(['lines.item.unit', 'category', 'encodedBy', 'voidedBy']);

        return $this->renderEncodeScreen($request, $transaction);
    }

    public function update(EncodeAccountingInventoryTransactionRequest $request, AccountingInventoryTransaction $transaction): RedirectResponse|JsonResponse
    {
        if ($transaction->isEncoded()) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'This document is already encoded and cannot be edited.'], 409);
            }

            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', 'This document is already encoded and cannot be edited.');
        }

        try {
            $transaction = $this->inventoryService->syncLines(
                $transaction,
                $request->validated('lines'),
                $request->user()?->id,
            );

            $this->inventoryService->encode($transaction, $request->user());
        } catch (ValidationException $exception) {
            if ($request->ajax() || $request->expectsJson()) {
                throw $exception;
            }

            return redirect()
                ->back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        if ($request->ajax() || $request->expectsJson()) {
            $queueFilters = $this->resolveQueueFiltersFromRequest($request);
            $next = $this->queueService->resolveNextPendingDocument($transaction->fresh(), $queueFilters);
            $summary = $this->queueService->summarizeStatuses($queueFilters);

            return response()->json([
                'success' => true,
                'message' => 'Accounting inventory transaction encoded successfully.',
                'encoded' => [
                    'doc_type' => $transaction->doc_type,
                    'doc_number' => $this->queueService->displayDocNumber($transaction),
                ],
                'next' => $next,
                'queue_stats' => [
                    'pending' => $summary['pending'],
                    'encoded' => $summary['encoded'],
                    'remaining_type' => $this->queueService->countPendingByType($transaction->doc_type, $queueFilters),
                    'doc_type' => $transaction->doc_type,
                ],
                'close_after' => $request->boolean('close_after'),
            ]);
        }

        return redirect()
            ->route('accounting.inventory-transactions.index', ['status' => 'encoded'])
            ->with('success', 'Accounting inventory transaction encoded successfully.');
    }

    public function void(VoidAccountingInventoryTransactionRequest $request, AccountingInventoryTransaction $transaction): RedirectResponse
    {
        $this->inventoryService->voidTransaction(
            $transaction,
            $request->user(),
            $request->validated('void_reason'),
        );

        return redirect()
            ->route('accounting.inventory-transactions.index')
            ->with('success', 'Transaction voided successfully.');
    }

    public function bulkEncode(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('encode-accounting-inventory'), 403);

        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:accounting_inventory_transactions,id'],
        ]);

        $encoded = 0;
        foreach ($validated['transaction_ids'] as $transactionId) {
            $transaction = AccountingInventoryTransaction::query()
                ->with('lines')
                ->find($transactionId);

            if ($transaction === null || $transaction->isEncoded() || $transaction->lines->isEmpty()) {
                continue;
            }

            if ($transaction->is_corrected) {
                continue;
            }

            $this->inventoryService->encode($transaction, $request->user());
            $encoded++;
        }

        return redirect()
            ->route('accounting.inventory-transactions.index', ['status' => 'encoded'])
            ->with('success', "{$encoded} transaction(s) encoded successfully.");
    }

    public function searchItems(Request $request): JsonResponse
    {
        return $this->searchAccountingInventoryItems($request, $this->inventoryService);
    }

    /**
     * @return array{status: string, doc_type: string, category_id: int, keyword: string, date_from: string, date_to: string}
     */
    private function resolveFilters(Request $request): array
    {
        $docType = strtoupper($request->string('doc_type')->toString());

        return [
            'status' => in_array($request->string('status')->toString(), ['all', 'pending', 'encoded'], true)
                ? $request->string('status')->toString()
                : 'pending',
            'doc_type' => in_array($docType, ['ALL', 'RR', 'TS', 'DR', 'CV', 'JV'], true)
                ? ($docType === 'ALL' ? 'all' : $docType)
                : 'all',
            'category_id' => (int) $request->query('category_id', 0),
            'keyword' => trim($request->string('keyword')->toString()),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];
    }

    /**
     * @return array{status: string, doc_type: string, category_id: int, keyword: string, date_from: string, date_to: string}
     */
    private function resolveQueueFiltersFromRequest(Request $request): array
    {
        $docType = strtoupper(trim((string) ($request->input('queue_doc_type') ?? $request->query('queue_doc_type', 'all'))));

        return [
            'status' => 'pending',
            'doc_type' => in_array($docType, ['ALL', 'RR', 'TS', 'DR', 'CV', 'JV'], true)
                ? ($docType === 'ALL' ? 'all' : $docType)
                : 'all',
            'category_id' => (int) ($request->input('queue_category_id') ?? $request->query('queue_category_id', 0)),
            'keyword' => trim((string) ($request->input('queue_keyword') ?? $request->query('queue_keyword', ''))),
            'date_from' => (string) ($request->input('queue_date_from') ?? $request->query('queue_date_from', '')),
            'date_to' => (string) ($request->input('queue_date_to') ?? $request->query('queue_date_to', '')),
        ];
    }

    private function renderEncodeScreen(Request $request, AccountingInventoryTransaction $transaction): View|Response
    {
        $transaction->loadMissing(['lines.item.unit', 'category', 'encodedBy', 'voidedBy']);
        $displayDocNumber = $this->queueService->displayDocNumber($transaction);
        $queueFilters = $this->resolveQueueFiltersFromRequest($request);
        $nextDocument = $transaction->isDraft()
            ? $this->queueService->resolveNextPendingDocument($transaction, $queueFilters)
            : null;

        $payload = [
            'transaction' => $transaction,
            'displayDocNumber' => $displayDocNumber,
            'canEncode' => $transaction->isDraft() && $request->user()?->can('encode-accounting-inventory'),
            'canVoid' => $transaction->isEncoded() && $request->user()?->can('void-accounting-inventory'),
            'inModal' => $request->ajax() || $request->boolean('modal'),
            'queueStats' => $this->queueService->queueStatsForTransaction($transaction, $queueFilters),
            'queueFilters' => $queueFilters,
            'sourceUrl' => $this->resolveSourceDocumentUrl($transaction),
            'nextDocument' => $nextDocument,
        ];

        if ($payload['inModal']) {
            return response()->view('pages.accounting.inventory-transactions.partials.encode-panel', $payload);
        }

        return view('pages.accounting.inventory-transactions.show', $payload);
    }

    private function resolveSourceDocumentUrl(AccountingInventoryTransaction $transaction): ?string
    {
        if ($transaction->source_id === null || $transaction->source_id <= 0) {
            return null;
        }

        return match (strtoupper($transaction->doc_type)) {
            'RR' => route('receiving-reports.print', [
                'receivingReport' => $transaction->source_id,
                'mode' => 'preview',
            ]),
            'TS' => null,
            'DR' => route('deliveries.print', $transaction->source_id),
            default => null,
        };
    }
}
