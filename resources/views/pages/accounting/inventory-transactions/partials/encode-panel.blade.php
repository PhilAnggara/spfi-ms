@php
    $isReadOnly = $transaction->isEncoded() || $transaction->isVoided();
    $inModal = (bool) ($inModal ?? false);
    $queueStats = $queueStats ?? null;
    $sourceUrl = $sourceUrl ?? null;
    $nextDocument = $nextDocument ?? null;
    $partyLabel = $transaction->doc_type === 'TS' ? 'Transfer To' : 'Supplier';
@endphp

<div
    class="inv-encode-panel"
    data-inventory-encode-panel
    data-transaction-id="{{ $transaction->id }}"
    data-doc-type="{{ $transaction->doc_type }}"
    data-display-number="{{ $displayDocNumber }}"
    @if ($nextDocument) data-next-document='@json($nextDocument)' @endif
>
    @if ($inModal)
        <div class="inv-encode-errors d-none alert alert-danger mb-3" role="alert"></div>
    @endif

    <div class="inv-encode-meta mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="inv-encode-doc-badge">{{ $transaction->doc_type }}</span>
                    @if ($sourceUrl && ! $inModal)
                        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="fs-5 fw-semibold text-decoration-none text-body">
                            {{ $displayDocNumber }}
                            <i class="fa-light fa-arrow-up-right-from-square ms-1 small text-muted"></i>
                        </a>
                    @else
                        <span class="fs-5 fw-semibold">{{ $displayDocNumber }}</span>
                    @endif
                    @if ($transaction->isEncoded())
                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">Encoded</span>
                    @elseif ($transaction->isVoided())
                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger">Voided</span>
                    @else
                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning">Pending</span>
                    @endif
                </div>
                <div class="text-muted small d-flex flex-wrap gap-2">
                    <span>{{ $transaction->category?->name }}</span>
                    @if ($transaction->doc_date)
                        <span>&middot;</span>
                        <span>{{ $transaction->doc_date->format('d M Y') }}</span>
                    @endif
                    @if ($transaction->po_number)
                        <span>&middot;</span>
                        <span>Ref {{ $transaction->po_number }}</span>
                    @endif
                </div>
            </div>
            <div class="text-md-end">
                @if (! $transaction->isManual() && $transaction->party_name)
                    <div class="text-muted small text-uppercase">{{ $partyLabel }}</div>
                    <div class="fw-semibold">{{ $transaction->party_name }}</div>
                @endif
                @if ($transaction->isEncoded() && $transaction->encodedBy)
                    <div class="text-muted small mt-2">
                        <i class="fa-light fa-user-check me-1"></i>
                        {{ $transaction->encodedBy->name }}
                        @if ($transaction->encoded_at)
                            &middot; {{ $transaction->encoded_at->format('d M Y H:i') }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
        @if ($queueStats)
            <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                <span class="inv-encode-progress-chip" data-inv-queue-progress>
                    {{ $queueStats['doc_type'] }} &middot; {{ $queueStats['position_type'] }} of {{ $queueStats['remaining_type'] }} pending
                </span>
            </div>
        @endif
    </div>

    @if ($isReadOnly)
        <div class="alert alert-secondary border-0 py-2 mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="fa-light fa-lock"></i>
            <span>This transaction is read-only.</span>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('accounting.inventory-transactions.update', $transaction) }}"
        id="inventory-encode-form"
        class="inv-encode-form"
        data-encode-url="{{ route('accounting.inventory-transactions.update', $transaction) }}"
    >
        @csrf
        @method('PUT')

        @if ($inModal && $canEncode)
            <input type="hidden" name="queue_doc_type" value="{{ $queueFilters['doc_type'] ?? 'all' }}" class="inv-queue-filter" data-filter="doc_type">
            <input type="hidden" name="queue_category_id" value="{{ (int) ($queueFilters['category_id'] ?? 0) }}" class="inv-queue-filter" data-filter="category_id">
            <input type="hidden" name="queue_keyword" value="{{ $queueFilters['keyword'] ?? '' }}" class="inv-queue-filter" data-filter="keyword">
            <input type="hidden" name="queue_date_from" value="{{ $queueFilters['date_from'] ?? '' }}" class="inv-queue-filter" data-filter="date_from">
            <input type="hidden" name="queue_date_to" value="{{ $queueFilters['date_to'] ?? '' }}" class="inv-queue-filter" data-filter="date_to">
        @endif

        <div class="inv-encode-table-wrap">
            <table class="table table-sm table-hover align-middle mb-0 inv-encode-lines-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>UOM</th>
                        <th class="text-end">Available</th>
                        @if ($transaction->isManual())
                            <th class="text-center">Direction</th>
                        @endif
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transaction->lines as $index => $line)
                        @php
                            $corrected = $line->wasCorrected();
                        @endphp
                        <tr @class(['table-warning' => $corrected && ! $isReadOnly, 'inv-encode-line-row' => true])>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1">
                                    @if ($corrected && ! $isReadOnly)
                                        <i class="fa-light fa-pen-to-square text-warning" title="Corrected from prefill"></i>
                                    @endif
                                    {{ $line->item?->code }}
                                </div>
                                <div class="text-muted small">{{ $line->item?->name }}</div>
                                <input type="hidden" name="lines[{{ $index }}][item_id]" value="{{ $line->item_id }}">
                                <input type="hidden" name="lines[{{ $index }}][unit_of_measure_id]" value="{{ $line->unit_of_measure_id }}">
                                <input type="hidden" name="lines[{{ $index }}][prefill_quantity]" value="{{ $line->prefill_quantity }}">
                                <input type="hidden" name="lines[{{ $index }}][prefill_unit_cost]" value="{{ $line->prefill_unit_cost }}">
                                @if (! $transaction->isManual())
                                    <input type="hidden" name="lines[{{ $index }}][direction]" value="{{ $line->direction }}">
                                @endif
                            </td>
                            <td>{{ $line->item?->unit?->name ?? '—' }}</td>
                            <td class="text-end font-monospace">{{ number_format((float) $line->available_qty_snapshot, 5, '.', ',') }}</td>
                            @if ($transaction->isManual())
                                <td class="text-center">
                                    @include('pages.accounting.inventory-transactions.partials.direction-toggle', [
                                        'fieldName' => 'lines['.$index.'][direction]',
                                        'rowId' => $index,
                                        'selected' => old('lines.'.$index.'.direction', $line->direction),
                                        'readonly' => $isReadOnly,
                                    ])
                                </td>
                            @endif
                            <td class="text-end">
                                @if ($isReadOnly)
                                    <span class="font-monospace">{{ number_format((float) $line->quantity, 5, '.', ',') }}</span>
                                @else
                                    <input
                                        type="number"
                                        step="0.00001"
                                        min="0"
                                        class="form-control form-control-sm text-end inv-qty"
                                        name="lines[{{ $index }}][quantity]"
                                        value="{{ old('lines.'.$index.'.quantity', $line->quantity) }}"
                                        data-index="{{ $index }}"
                                        @if ($corrected) data-corrected="1" @endif
                                        required
                                    >
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($isReadOnly)
                                    <span class="font-monospace">{{ number_format((float) $line->unit_cost, 4, '.', ',') }}</span>
                                @else
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="form-control form-control-sm text-end inv-cost"
                                        name="lines[{{ $index }}][unit_cost]"
                                        value="{{ old('lines.'.$index.'.unit_cost', $line->unit_cost) }}"
                                        data-index="{{ $index }}"
                                        required
                                    >
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="font-monospace inv-amount fw-semibold" data-index="{{ $index }}">{{ number_format((float) $line->amount, 2, '.', ',') }}</span>
                                <input type="hidden" class="inv-amount-input" name="lines[{{ $index }}][amount]" value="{{ old('lines.'.$index.'.amount', $line->amount) }}" data-index="{{ $index }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($canEncode && ! $inModal)
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-success icon icon-left">
                    <i class="fa-regular fa-check"></i>
                    Encode
                </button>
            </div>
        @endif
    </form>

    @if ($inModal && $canEncode)
        <div class="inv-encode-modal-footer-placeholder d-none"></div>
    @elseif ($inModal)
        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    @endif
</div>
