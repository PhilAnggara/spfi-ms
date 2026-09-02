@extends('layouts.app')
@section('title', ' | Encode Inventory Transaction')

@section('content')
@php
    $isReadOnly = $transaction->isEncoded() || $transaction->isVoided();
@endphp
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">
                        {{ $displayDocNumber }}
                        <span class="badge bg-light-secondary">{{ $transaction->doc_type }}</span>
                    </h3>
                    <p class="text-muted mb-0">{{ $transaction->category?->name }} · {{ $transaction->doc_date?->format('d M Y') }}</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="po-top-actions">
                    <a href="{{ route('accounting.inventory-transactions.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to Queue
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small text-uppercase">PO Number</div>
                        <div class="fw-semibold">{{ $transaction->po_number ?: '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small text-uppercase">Party</div>
                        <div class="fw-semibold">{{ $transaction->party_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small text-uppercase">Status</div>
                        <div>
                            @if ($transaction->isEncoded())
                                <span class="badge bg-light-success text-success">Encoded</span>
                            @elseif ($transaction->isVoided())
                                <span class="badge bg-light-danger text-danger">Voided</span>
                            @else
                                <span class="badge bg-light-warning text-warning">Pending</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small text-uppercase">Total</div>
                        <div class="fw-semibold font-monospace">{{ number_format((float) $transaction->total_amount, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('accounting.inventory-transactions.update', $transaction) }}" id="inventory-encode-form">
            @csrf
            @method('PUT')

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h5 class="card-title mb-0">Inventory Lines</h5>
                </div>
                <div class="card-body pt-3">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>UOM</th>
                                    <th class="text-end">Available</th>
                                    @if ($transaction->isManual())
                                        <th class="text-center">+/−</th>
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
                                    <tr @class(['table-warning' => $corrected && ! $isReadOnly])>
                                        <td>
                                            <div class="fw-semibold">{{ $line->item?->code }}</div>
                                            <div class="text-muted small">{{ $line->item?->name }}</div>
                                            <input type="hidden" name="lines[{{ $index }}][item_id]" value="{{ $line->item_id }}">
                                            <input type="hidden" name="lines[{{ $index }}][direction]" value="{{ $line->direction }}">
                                            <input type="hidden" name="lines[{{ $index }}][unit_of_measure_id]" value="{{ $line->unit_of_measure_id }}">
                                            <input type="hidden" name="lines[{{ $index }}][prefill_quantity]" value="{{ $line->prefill_quantity }}">
                                            <input type="hidden" name="lines[{{ $index }}][prefill_unit_cost]" value="{{ $line->prefill_unit_cost }}">
                                        </td>
                                        <td>{{ $line->item?->unit?->name ?? '—' }}</td>
                                        <td class="text-end font-monospace">{{ number_format((float) $line->available_qty_snapshot, 5, '.', ',') }}</td>
                                        @if ($transaction->isManual())
                                            <td class="text-center text-uppercase fw-bold">{{ $line->direction === 'in' ? '+' : '−' }}</td>
                                        @endif
                                        <td class="text-end">
                                            @if ($isReadOnly)
                                                <span class="font-monospace">{{ number_format((float) $line->quantity, 5, '.', ',') }}</span>
                                            @else
                                                <input type="number" step="0.00001" min="0" class="form-control text-end inv-qty" name="lines[{{ $index }}][quantity]" value="{{ old('lines.'.$index.'.quantity', $line->quantity) }}" data-index="{{ $index }}" required>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($isReadOnly)
                                                <span class="font-monospace">{{ number_format((float) $line->unit_cost, 4, '.', ',') }}</span>
                                            @else
                                                <input type="number" step="0.0001" min="0" class="form-control text-end inv-cost" name="lines[{{ $index }}][unit_cost]" value="{{ old('lines.'.$index.'.unit_cost', $line->unit_cost) }}" data-index="{{ $index }}" required>
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
                </div>
            </div>

            <div id="gl-preview-section" class="d-none" aria-hidden="true"></div>

            @if ($canEncode)
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success icon icon-left">
                        <i class="fa-regular fa-check"></i>
                        Encode
                    </button>
                </div>
            @endif
        </form>

        @if ($canVoid)
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Void Transaction</h5>
                    <form method="POST" action="{{ route('accounting.inventory-transactions.void', $transaction) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea name="void_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Void this encoded transaction?')">Void Transaction</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
@if (! $isReadOnly)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const recalc = (index) => {
        const qty = parseFloat(document.querySelector(`.inv-qty[data-index="${index}"]`)?.value || '0');
        const cost = parseFloat(document.querySelector(`.inv-cost[data-index="${index}"]`)?.value || '0');
        const amount = Math.round(qty * cost * 100) / 100;
        const amountEl = document.querySelector(`.inv-amount[data-index="${index}"]`);
        const amountInput = document.querySelector(`.inv-amount-input[data-index="${index}"]`);
        if (amountEl) {
            amountEl.textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (amountInput) {
            amountInput.value = amount.toFixed(2);
        }
    };

    document.querySelectorAll('.inv-qty, .inv-cost').forEach((input) => {
        input.addEventListener('input', () => recalc(input.dataset.index));
    });
});
</script>
@endif
@endpush
