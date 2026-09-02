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
use Illuminate\View\View;

class AccountingInventoryTransactionController extends Controller
{
    use SearchesAccountingInventoryItems;

    public function __construct(
        private readonly AccountingInventoryQueueService $queueService,
        private readonly AccountingInventoryService $inventoryService,
    ) {}

    public function index(Request $request): View|\Illuminate\Http\Response
    {
        $filters = $this->resolveFilters($request);
        $pageData = $this->queueService->paginateDocumentsWithSummary($filters, 15);
        $documents = $pageData['documents'];
        $summary = $pageData['summary'];

        if ($request->ajax()) {
            return response()->view('pages.accounting.inventory-transactions.partials.results-panel', [
                'documents' => $documents,
                'filters' => $filters,
                'summary' => $summary,
            ]);
        }

        $categories = ItemCategory::query()->orderBy('name')->get(['id', 'name']);

        return view('pages.accounting.inventory-transactions.index', [
            'documents' => $documents,
            'filters' => $filters,
            'summary' => $summary,
            'categories' => $categories,
        ]);
    }

    public function create(Request $request): View
    {
        return view('pages.accounting.inventory-transactions.create', [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'name']),
            'itemSearchUrl' => route('accounting.inventory-transactions.items.search'),
            'selectedCategoryId' => (int) $request->query('category_id', 0),
        ]);
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

    public function show(Request $request, string $docType, int $id): View|RedirectResponse
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
            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', $exception->getMessage());
        }

        return $this->renderEncodeScreen($transaction);
    }

    public function showTransaction(AccountingInventoryTransaction $transaction): View
    {
        $transaction->load(['lines.item.unit', 'category', 'encodedBy', 'voidedBy']);

        return $this->renderEncodeScreen($transaction);
    }

    public function update(EncodeAccountingInventoryTransactionRequest $request, AccountingInventoryTransaction $transaction): RedirectResponse
    {
        if ($transaction->isEncoded()) {
            return redirect()
                ->route('accounting.inventory-transactions.index')
                ->with('error', 'This document is already encoded and cannot be edited.');
        }

        $transaction = $this->inventoryService->syncLines(
            $transaction,
            $request->validated('lines'),
            $request->user()?->id,
        );

        $this->inventoryService->encode($transaction, $request->user());

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

    private function renderEncodeScreen(AccountingInventoryTransaction $transaction): View
    {
        $transaction->loadMissing(['lines.item.unit', 'category', 'encodedBy', 'voidedBy']);
        $displayDocNumber = $this->queueService->displayDocNumber($transaction);

        return view('pages.accounting.inventory-transactions.show', [
            'transaction' => $transaction,
            'displayDocNumber' => $displayDocNumber,
            'canEncode' => $transaction->isDraft() && auth()->user()?->can('encode-accounting-inventory'),
            'canVoid' => $transaction->isEncoded() && auth()->user()?->can('void-accounting-inventory'),
        ]);
    }
}
