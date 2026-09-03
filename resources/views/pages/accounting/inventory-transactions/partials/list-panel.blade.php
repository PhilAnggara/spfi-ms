@can('encode-accounting-inventory')
    @if (($filters['status'] ?? 'pending') === 'pending')
        <form method="POST" action="{{ route('accounting.inventory-transactions.bulk-encode') }}" id="bulk-encode-form" class="mb-3 p-3 border rounded bg-light">
            @csrf
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-success btn-sm" id="bulk-encode-submit" disabled>
                    <i class="fa-regular fa-check-double"></i>
                    Bulk Encode Selected
                </button>
                <span class="text-muted small">Only uncorrected draft transactions can be bulk encoded.</span>
            </div>
        </form>
    @endif
@endcan

@if ($documents->isEmpty())
    <div class="po-empty-state text-center text-muted py-5">
        <i class="fa-duotone fa-solid fa-boxes-stacked po-empty-icon"></i>
        <p class="mb-0 mt-2 fw-semibold">No documents match the current filters.</p>
        <small>Try changing keyword, category, or status to see more results.</small>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-striped align-middle po-table text-nowrap mb-0">
            <thead>
                <tr>
                    @can('encode-accounting-inventory')
                        @if (($filters['status'] ?? 'pending') === 'pending')
                            <th class="text-center" style="width: 2.5rem;">
                                <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Select all eligible documents">
                            </th>
                        @endif
                    @endcan
                    <th>Type</th>
                    <th>Doc No</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th class="d-none d-lg-table-cell">Reference</th>
                    <th>Supplier / Transfer To</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                    @php
                        $displayNumber = $document->doc_number;
                        if (preg_match('/^(RR|TS|DR)\|(.+)\|\d+$/', $document->doc_number, $matches)) {
                            $displayNumber = $matches[2];
                        }

                        $openUrl = $document->is_manual || $document->transaction_id
                            ? route('accounting.inventory-transactions.transaction', $document->transaction_id)
                            : route('accounting.inventory-transactions.show', [
                                'docType' => strtolower($document->doc_type),
                                'id' => $document->source_id,
                                'category_id' => $document->category_id,
                            ]);
                        $actionLabel = $document->is_encoded ? 'View' : 'Process';
                        $canBulkSelect = ! $document->is_encoded
                            && $document->transaction_id
                            && ! $document->is_corrected
                            && ($filters['status'] ?? 'pending') === 'pending';
                    @endphp
                    <tr>
                        @can('encode-accounting-inventory')
                            @if (($filters['status'] ?? 'pending') === 'pending')
                                <td class="text-center">
                                    @if ($canBulkSelect)
                                        <input
                                            type="checkbox"
                                            class="form-check-input bulk-encode-checkbox"
                                            name="transaction_ids[]"
                                            value="{{ $document->transaction_id }}"
                                            form="bulk-encode-form"
                                            aria-label="Select {{ $displayNumber }}"
                                        >
                                    @endif
                                </td>
                            @endif
                        @endcan
                        <td><span class="badge bg-light-secondary">{{ $document->doc_type }}</span></td>
                        <td class="fw-semibold">{{ $displayNumber }}</td>
                        <td>{{ $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $document->category_name }}</td>
                        <td class="d-none d-lg-table-cell">{{ $document->reference ?: '—' }}</td>
                        <td>{{ $document->party_name ?: '—' }}</td>
                        <td class="text-end font-monospace fw-semibold">
                            @if ((float) $document->amount > 0)
                                {{ number_format((float) $document->amount, 2, '.', ',') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($document->status === 'encoded')
                                <span class="badge bg-light-success text-success">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Encoded
                                </span>
                                @if ($document->is_corrected)
                                    <span class="badge bg-light-warning text-warning">Corrected</span>
                                @endif
                            @elseif ($document->status === 'voided')
                                <span class="badge bg-light-danger text-danger">Voided</span>
                            @else
                                <span class="badge bg-light-warning text-warning">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a
                                href="{{ $openUrl }}"
                                class="btn btn-sm {{ $document->is_encoded ? 'btn-outline-secondary' : 'btn-primary' }}"
                                data-inventory-encode-open
                                data-title="{{ $actionLabel }} {{ $document->doc_type }} {{ $displayNumber }}"
                                data-doc-type="{{ $document->doc_type }}"
                                data-transaction-id="{{ $document->transaction_id }}"
                            >
                                <i class="fa-light {{ $document->is_encoded ? 'fa-eye' : 'fa-pen-to-square' }} me-1"></i>
                                {{ $actionLabel }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($documents->hasPages())
        <div class="mt-3 d-flex justify-content-end">
            {{ $documents->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endif
