<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingDocTransaction;
use App\Services\Accounting\AccountingDocTransactionService;
use Illuminate\Http\Request;

class AccountingDocEntryController extends Controller
{
    public function __construct(
        private readonly AccountingDocTransactionService $docTransactionService,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $documents = $this->docTransactionService->paginateDocuments($filters, 15);
        $summary = $this->docTransactionService->summarizeStatuses(
            ($filters['doc_type'] ?? 'all') === 'all' ? null : $filters['doc_type']
        );

        if ($request->ajax()) {
            return view('pages.accounting.doc-entries.partials.list-panel', [
                'documents' => $documents,
                'filters' => $filters,
            ]);
        }

        return view('pages.accounting.doc-entries.index', [
            'documents' => $documents,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, string $docType, int $id)
    {
        $source = $this->docTransactionService->resolveSourceModel($docType, $id);
        $transaction = $this->docTransactionService->findOrCreateDraftForDocument(
            $source,
            $request->user()?->id,
        );

        return $this->renderEntry($request, $transaction, strtoupper($docType));
    }

    public function showTransaction(Request $request, AccountingDocTransaction $transaction)
    {
        abort_unless($transaction->isEncoded(), 404);

        return $this->renderEntry($request, $transaction->load(['lines', 'encodedBy']), $transaction->doc_type);
    }

    public function update(Request $request, AccountingDocTransaction $transaction)
    {
        if ($transaction->isEncoded()) {
            return redirect()
                ->route('accounting.doc-entries.index')
                ->with('error', 'This document is already encoded and cannot be edited.');
        }

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.group_code' => ['nullable', 'string', 'max:30'],
            'lines.*.account_code' => ['nullable', 'string', 'max:20'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $transaction = $this->docTransactionService->syncLines(
            $transaction,
            $validated['lines'],
            $request->user()?->id,
        );

        $this->docTransactionService->encode($transaction, $request->user());

        return redirect()
            ->route('accounting.doc-entries.index')
            ->with('success', 'Accounting entries have been encoded successfully.');
    }

    public function lookupAccount(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'data' => $this->docTransactionService->lookupAccounts((string) ($validated['q'] ?? '')),
        ]);
    }

    /**
     * @return array{status: string, doc_type: string, keyword: string, date_from: string, date_to: string}
     */
    private function resolveFilters(Request $request): array
    {
        $docType = strtoupper($request->string('doc_type')->toString());

        return [
            'status' => in_array($request->string('status')->toString(), ['all', 'pending', 'encoded'], true)
                ? $request->string('status')->toString()
                : 'all',
            'doc_type' => in_array($docType, ['ALL', 'RR', 'DR'], true)
                ? ($docType === 'ALL' ? 'all' : $docType)
                : 'all',
            'keyword' => trim($request->string('keyword')->toString()),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];
    }

    private function renderEntry(Request $request, AccountingDocTransaction $transaction, string $docType)
    {
        $isEncoded = $transaction->isEncoded();
        $payload = [
            'transaction' => $transaction->loadMissing(['lines', 'encodedBy']),
            'isEncoded' => $isEncoded,
            'canEdit' => ! $isEncoded,
            'docType' => strtoupper($docType),
            'inModal' => $request->ajax() || $request->boolean('modal'),
            'sourceUrl' => $this->resolveSourceDocumentUrl(
                strtoupper($docType),
                $transaction->source_id !== null ? (int) $transaction->source_id : null,
            ),
        ];

        if ($payload['inModal']) {
            return view('pages.accounting.doc-entries.partials.entry-panel', $payload);
        }

        return view('pages.accounting.doc-entries.show', $payload);
    }

    private function resolveSourceDocumentUrl(string $docType, ?int $sourceId): ?string
    {
        if ($sourceId === null || $sourceId <= 0) {
            return null;
        }

        return match ($docType) {
            'RR' => route('receiving-reports.print', [
                'receivingReport' => $sourceId,
                'mode' => 'preview',
            ]),
            'DR' => route('deliveries.print', $sourceId),
            default => null,
        };
    }
}
