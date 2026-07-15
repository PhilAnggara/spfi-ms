<div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
        <thead>
            <tr>
                <th>Type</th>
                <th>Doc No</th>
                <th>Date</th>
                <th>Reference</th>
                <th>Party / Supplier</th>
                <th class="text-end" title="Encoded: total debit. Pending RR: estimated from received qty × unit price.">Amount</th>
                <th>Status</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                @php
                    $isOrphan = (bool) ($document->is_orphan ?? false);
                    $openUrl = $isOrphan
                        ? route('accounting.doc-entries.transaction', $document->transaction_id)
                        : route('accounting.doc-entries.show', [
                            'docType' => strtolower($document->doc_type),
                            'id' => $document->source_id,
                        ]);
                    $actionLabel = $document->status === 'encoded' ? 'View' : 'Process';
                    $sourceUrl = null;
                    if (! $isOrphan && $document->source_id) {
                        $sourceUrl = match ($document->doc_type) {
                            'RR' => route('receiving-reports.print', [
                                'receivingReport' => $document->source_id,
                                'mode' => 'preview',
                            ]),
                            'DR' => route('deliveries.print', $document->source_id),
                            default => null,
                        };
                    }
                @endphp
                <tr>
                    <td>
                        <span class="badge bg-light-secondary">{{ $document->doc_type }}</span>
                    </td>
                    <td class="fw-semibold">
                        @if ($sourceUrl)
                            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="text-decoration-none">
                                {{ $document->doc_number }}
                                <i class="fa-light fa-arrow-up-right-from-square ms-1 small text-muted"></i>
                            </a>
                        @else
                            {{ $document->doc_number }}
                        @endif
                    </td>
                    <td>{{ $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->format('d M Y') : '—' }}</td>
                    <td>{{ $document->reference ?: '—' }}</td>
                    <td>{{ $document->party_name ?: '—' }}</td>
                    <td class="text-end font-monospace">
                        @if ((float) $document->cost_total > 0)
                            {{ number_format((float) $document->cost_total, 2, '.', ',') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($document->status === 'encoded')
                            <span class="badge bg-light-success text-success">Encoded</span>
                        @else
                            <span class="badge bg-light-warning text-warning">Pending Entry</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ $openUrl }}"
                           class="btn btn-sm {{ $document->status === 'encoded' ? 'btn-light-secondary' : 'btn-primary' }}"
                           data-doc-entry-open
                           data-title="{{ $actionLabel }} {{ $document->doc_type }} {{ $document->doc_number }}">
                            {{ $actionLabel }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">No documents match the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($documents->hasPages())
    <div class="mt-3 d-flex justify-content-end">
        {{ $documents->withQueryString()->links() }}
    </div>
@endif
