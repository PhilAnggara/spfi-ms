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
        $firstPending = $pageData['firstPending'] ?? null;

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
        $document = $this->queueService->createAndEncodeManual(
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
            ->route('accounting.inventory-transactions.manual', [
                'docType' => strtolower($document->doc_type),
                'docNumber' => $document->displayDocNumber(),
                'category_id' => $document->category_id,
            ])
            ->with('success', 'Manual inventory document encoded successfully.');
    }

    public function show(Request $request, string $docType, int $id): View|RedirectResponse|Response|JsonResponse
    {
        $categoryId = (int) $request->query('category_id');
        abort_if($categoryId <= 0, 404);

        try {
            $source = $this->queueService->resolveSourceModel($docType, $id);
            $document = $this->queueService->buildDocumentForSource($source, $categoryId);
        } catch (\InvalidArgumentException $exception) {
            if ($request->ajax() || $request->boolean('modal')) {
                return response()->json(['message' => $exception->getMessage()], 400);
            }

            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', $exception->getMessage());
        }

        return $this->renderEncodeScreen($request, $document);
    }

    public function showManual(Request $request, string $docType, string $docNumber): View|RedirectResponse|Response
    {
        $categoryId = (int) $request->query('category_id');
        abort_if($categoryId <= 0, 404);

        try {
            $document = $this->queueService->buildManualDocument($docType, $docNumber, $categoryId);
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', $exception->getMessage());
        }

        return $this->renderEncodeScreen($request, $document);
    }

    public function update(EncodeAccountingInventoryTransactionRequest $request, string $docType, int $id): RedirectResponse|JsonResponse
    {
        $categoryId = (int) ($request->input('category_id') ?: $request->query('category_id'));
        abort_if($categoryId <= 0, 404);

        try {
            $source = $this->queueService->resolveSourceModel($docType, $id);
            $document = $this->queueService->buildDocumentForSource($source, $categoryId);
        } catch (\InvalidArgumentException $exception) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 400);
            }

            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', $exception->getMessage());
        }

        if ($document->isEncoded()) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'This document is already encoded and cannot be edited.'], 409);
            }

            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', 'This document is already encoded and cannot be edited.');
        }

        try {
            $document = $this->inventoryService->encodeDocument(
                $document,
                $request->validated('lines'),
                $request->user(),
            );
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
            $queueState = $this->queueService->resolveEncodeQueueState($document, $queueFilters);

            return response()->json([
                'success' => true,
                'message' => 'Accounting inventory transaction encoded successfully.',
                'encoded' => [
                    'doc_type' => $document->doc_type,
                    'doc_number' => $document->displayDocNumber(),
                ],
                'next' => $queueState['next'],
                'queue_stats' => $queueState['queue_stats'],
                'close_after' => $request->boolean('close_after'),
            ]);
        }

        return redirect()
            ->route('accounting.inventory-transactions.index', ['status' => 'encoded'])
            ->with('success', 'Accounting inventory transaction encoded successfully.');
    }

    public function void(VoidAccountingInventoryTransactionRequest $request, string $docType, int $id): RedirectResponse
    {
        $categoryId = (int) ($request->input('category_id') ?: $request->query('category_id'));
        abort_if($categoryId <= 0, 404);

        $source = $this->queueService->resolveSourceModel($docType, $id);
        $document = $this->queueService->buildDocumentForSource($source, $categoryId);

        $this->inventoryService->voidDocument(
            $document,
            $request->user(),
            $request->validated('void_reason'),
        );

        return redirect()
            ->route('accounting.inventory-transactions.index')
            ->with('success', 'Transaction voided successfully.');
    }

    public function voidManual(VoidAccountingInventoryTransactionRequest $request, string $docType, string $docNumber): RedirectResponse
    {
        $categoryId = (int) ($request->input('category_id') ?: $request->query('category_id'));
        abort_if($categoryId <= 0, 404);

        $document = $this->queueService->buildManualDocument($docType, $docNumber, $categoryId);

        $this->inventoryService->voidDocument(
            $document,
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
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.doc_type' => ['required', 'string', 'in:RR,TS,DR'],
            'documents.*.source_id' => ['required', 'integer'],
            'documents.*.category_id' => ['required', 'integer'],
        ]);

        $encoded = 0;
        foreach ($validated['documents'] as $row) {
            try {
                $source = $this->queueService->resolveSourceModel($row['doc_type'], (int) $row['source_id']);
                $document = $this->queueService->buildDocumentForSource($source, (int) $row['category_id']);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($document->isEncoded() || $document->lines->isEmpty() || $document->is_corrected) {
                continue;
            }

            $lines = $document->lines->map(fn ($line): array => [
                'item_id' => $line->item_id,
                'direction' => $line->direction,
                'quantity' => $line->quantity,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'unit_cost' => $line->unit_cost,
                'amount' => $line->amount,
                'prefill_quantity' => $line->prefill_quantity,
                'prefill_unit_cost' => $line->prefill_unit_cost,
            ])->all();

            $this->inventoryService->encodeDocument($document, $lines, $request->user());
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
        $defaultDateFrom = now()->subDays(90)->toDateString();

        return [
            'status' => in_array($request->string('status')->toString(), ['all', 'pending', 'encoded'], true)
                ? $request->string('status')->toString()
                : 'pending',
            'doc_type' => in_array($docType, ['ALL', 'RR', 'TS', 'DR', 'CV', 'JV'], true)
                ? ($docType === 'ALL' ? 'all' : $docType)
                : 'all',
            'category_id' => (int) $request->query('category_id', 0),
            'keyword' => trim($request->string('keyword')->toString()),
            'date_from' => $request->query->has('date_from')
                ? $request->string('date_from')->toString()
                : $defaultDateFrom,
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
        $displayDocNumber = $this->queueService->displayDocNumber($transaction);
        $queueFilters = $this->resolveQueueFiltersFromRequest($request);
        $queueState = $this->queueService->resolveEncodeQueueState($transaction, $queueFilters);

        $payload = [
            'transaction' => $transaction,
            'displayDocNumber' => $displayDocNumber,
            'canEncode' => $transaction->isDraft() && $request->user()?->can('encode-accounting-inventory'),
            'canVoid' => $transaction->isEncoded() && $request->user()?->can('void-accounting-inventory'),
            'inModal' => $request->ajax() || $request->boolean('modal'),
            'queueStats' => $queueState['queue_stats'],
            'queueFilters' => $queueFilters,
            'sourceUrl' => $this->resolveSourceDocumentUrl($transaction),
            'nextDocument' => $transaction->isDraft() ? $queueState['next'] : null,
            'encodeUrl' => $this->resolveEncodeUrl($transaction),
            'voidUrl' => $this->resolveVoidUrl($transaction),
        ];

        if ($payload['inModal']) {
            return response()->view('pages.accounting.inventory-transactions.partials.encode-panel', $payload);
        }

        return view('pages.accounting.inventory-transactions.show', $payload);
    }

    private function resolveEncodeUrl(AccountingInventoryTransaction $transaction): string
    {
        if ($transaction->isManual()) {
            return route('accounting.inventory-transactions.manual', [
                'docType' => strtolower($transaction->doc_type),
                'docNumber' => $transaction->displayDocNumber(),
                'category_id' => $transaction->category_id,
            ]);
        }

        return route('accounting.inventory-transactions.update', [
            'docType' => strtolower($transaction->doc_type),
            'id' => $transaction->source_id,
            'category_id' => $transaction->category_id,
        ]);
    }

    private function resolveVoidUrl(AccountingInventoryTransaction $transaction): ?string
    {
        if ($transaction->isManual()) {
            return route('accounting.inventory-transactions.void-manual', [
                'docType' => strtolower($transaction->doc_type),
                'docNumber' => $transaction->displayDocNumber(),
                'category_id' => $transaction->category_id,
            ]);
        }

        if ($transaction->source_id === null) {
            return null;
        }

        return route('accounting.inventory-transactions.void', [
            'docType' => strtolower($transaction->doc_type),
            'id' => $transaction->source_id,
            'category_id' => $transaction->category_id,
        ]);
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
