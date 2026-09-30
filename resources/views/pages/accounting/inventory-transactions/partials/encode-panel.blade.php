@php
    $canEncode = (bool) ($canEncode ?? false);
    $canUpdate = (bool) ($canUpdate ?? false);
    $canVoid = (bool) ($canVoid ?? false);
    $isEncoded = $transaction->isEncoded();
    $isVoided = $transaction->isVoided();
    $inModal = (bool) ($inModal ?? false);
    $queueStats = $queueStats ?? null;
    $sourceUrl = $sourceUrl ?? null;
    $nextDocument = $nextDocument ?? null;
    $voidUrl = $voidUrl ?? null;
    $partyLabel = $transaction->doc_type === 'TS' ? 'Transfer To' : 'Supplier';
@endphp

<div
    class="inv-encode-panel"
    data-inventory-encode-panel
    data-transaction-id="{{ $transaction->source_id ?? $transaction->doc_number }}"
    data-doc-type="{{ $transaction->doc_type }}"
    data-display-number="{{ $displayDocNumber }}"
    data-is-encoded="{{ $isEncoded ? '1' : '0' }}"
    data-can-encode="{{ $canEncode ? '1' : '0' }}"
    data-can-update="{{ $canUpdate ? '1' : '0' }}"
    data-can-void="{{ $canVoid ? '1' : '0' }}"
    @if ($voidUrl) data-void-url="{{ $voidUrl }}" @endif
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
                    @if ($isEncoded)
                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">Encoded</span>
                    @elseif ($isVoided)
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
                @if ($isEncoded && $transaction->encodedBy)
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
                    {{ $queueStats['doc_type'] }} &middot; {{ $queueStats['remaining_type'] }} pending
                </span>
            </div>
        @endif
    </div>

    @if ($isVoided)
        <div class="alert alert-secondary border-0 py-2 mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="fa-light fa-lock"></i>
            <span>This transaction is voided and read-only.</span>
        </div>
    @elseif ($isEncoded && $canUpdate)
        <div class="alert alert-secondary border-0 py-2 mb-3 d-flex align-items-center gap-2 inv-encode-locked-hint" role="alert" data-inv-edit-hint>
            <i class="fa-light fa-lock"></i>
            <span>This transaction is encoded. Click Edit to change qty or unit cost.</span>
        </div>
    @elseif ($isEncoded)
        <div class="alert alert-secondary border-0 py-2 mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="fa-light fa-lock"></i>
            <span>This transaction is read-only.</span>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $encodeUrl }}"
        id="inventory-encode-form"
        class="inv-encode-form"
        data-encode-url="{{ $encodeUrl }}"
        data-mode="{{ $canEncode ? 'encode' : ($canUpdate ? 'update' : 'view') }}"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="category_id" value="{{ $transaction->category_id }}">

        @if ($inModal && ($canEncode || $canUpdate))
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
                            $qtyValue = rtrim(rtrim(number_format((float) old('lines.'.$index.'.quantity', $line->quantity), 5, '.', ','), '0'), '.');
                            $costValue = rtrim(rtrim(number_format((float) old('lines.'.$index.'.unit_cost', $line->unit_cost), 5, '.', ','), '0'), '.');
                        @endphp
                        <tr @class(['table-warning' => $corrected && $canEncode, 'inv-encode-line-row' => true])>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1">
                                    @if ($corrected && $canEncode)
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
                            <td class="text-end font-monospace">{{ rtrim(rtrim(number_format((float) $line->available_qty_snapshot, 5, '.', ','), '0'), '.') }}</td>
                            @if ($transaction->isManual())
                                <td class="text-center">
                                    @include('pages.accounting.inventory-transactions.partials.direction-toggle', [
                                        'fieldName' => 'lines['.$index.'][direction]',
                                        'rowId' => $index,
                                        'selected' => old('lines.'.$index.'.direction', $line->direction),
                                        'readonly' => ! $canEncode,
                                    ])
                                </td>
                            @endif
                            <td class="text-end">
                                <span class="font-monospace inv-qty-display @if ($canEncode) d-none @endif" data-index="{{ $index }}">{{ $qtyValue }}</span>
                                @if ($canEncode || $canUpdate)
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        class="form-control form-control-sm text-end inv-qty @if ($isEncoded) d-none @endif"
                                        name="lines[{{ $index }}][quantity]"
                                        value="{{ $qtyValue }}"
                                        data-index="{{ $index }}"
                                        data-max-decimals="5"
                                        @if ($corrected) data-corrected="1" @endif
                                        @if ($isEncoded) disabled @endif
                                        required
                                    >
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="font-monospace inv-cost-display @if ($canEncode) d-none @endif" data-index="{{ $index }}">{{ $costValue }}</span>
                                @if ($canEncode || $canUpdate)
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        class="form-control form-control-sm text-end inv-cost @if ($isEncoded) d-none @endif"
                                        name="lines[{{ $index }}][unit_cost]"
                                        value="{{ $costValue }}"
                                        data-index="{{ $index }}"
                                        data-max-decimals="5"
                                        @if ($isEncoded) disabled @endif
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

        @if (! $inModal && ($canEncode || $canUpdate || $canVoid))
            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4" data-inv-page-actions>
                @if ($canUpdate)
                    <button type="button" class="btn btn-outline-primary icon icon-left" data-inv-edit-toggle>
                        <i class="fa-light fa-pen"></i>
                        Edit
                    </button>
                    <button type="button" class="btn btn-light-secondary d-none" data-inv-edit-cancel>Cancel</button>
                    <button type="submit" class="btn btn-success icon icon-left d-none" data-inv-update-submit>
                        <i class="fa-regular fa-check"></i>
                        Update
                    </button>
                @endif
                @if ($canEncode)
                    <button type="submit" class="btn btn-success icon icon-left">
                        <i class="fa-regular fa-check"></i>
                        Encode
                    </button>
                @endif
                @if ($canVoid && $voidUrl)
                    <button type="button" class="btn btn-outline-danger icon icon-left" data-inv-void-open>
                        <i class="fa-light fa-ban"></i>
                        Void
                    </button>
                @endif
            </div>
        @endif
    </form>

    @if (! $inModal && $canVoid && $voidUrl)
        <div class="card shadow-sm border-0 mt-4 d-none" data-inv-void-panel>
            <div class="card-body">
                <h5 class="card-title">Void Transaction</h5>
                <form method="POST" action="{{ $voidUrl }}" class="row g-3" data-inv-void-form>
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $transaction->category_id }}">
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <textarea name="void_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-danger">Confirm Void</button>
                        <button type="button" class="btn btn-light-secondary" data-inv-void-cancel>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($inModal && ($canEncode || $canUpdate || $canVoid))
        <div class="inv-encode-modal-footer-placeholder d-none"></div>
    @elseif ($inModal)
        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    @endif
</div>
