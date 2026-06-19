@extends('layouts.app')
@section('title', ' | Supplier Comparison')

@section('content')
@php
    $totalItems = method_exists($prsItems, 'total') ? $prsItems->total() : $prsItems->count();
    $keyword = $filters['keyword'] ?? '';
@endphp
<div id="supplier-comparison-page-container" data-highlight-prs-item-id="{{ $highlightPrsItemId ?: '' }}">
<div id="supplier-comparison-page" class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero sc-hero">
                    <span class="sc-eyebrow">Procurement</span>
                    <h3 class="mb-1">Supplier Comparison</h3>
                    <p class="text-muted mb-0">Select the winning supplier quote per item.</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="sc-page-summary">
                    <span class="sc-summary-icon">
                        <i class="fa-duotone fa-solid fa-scale-balanced"></i>
                    </span>
                    <span id="supplier-comparison-total-summary">{{ number_format($totalItems) }} item{{ $totalItems === 1 ? '' : 's' }} ready for review</span>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 sc-filter-card">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="supplier-comparison-filter-form">
                    <div class="col-12 col-lg-10">
                        <label for="supplier-comparison-keyword" class="form-label mb-1">Search Supplier Comparison</label>
                        <div class="sc-search-control">
                            <i class="fa-light fa-magnifying-glass"></i>
                            <input
                                type="text"
                                id="supplier-comparison-keyword"
                                class="form-control"
                                value="{{ $keyword }}"
                                placeholder="PRS number / item code / item name">
                        </div>
                    </div>
                    <div class="col-12 col-lg-2">
                        <button type="button" id="reset-supplier-comparison-filter" class="btn btn-light-secondary w-100 icon icon-left">
                            <i class="fa-light fa-rotate-left"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="supplier-comparison-page-results">
            <div class="sc-results-shell position-relative">
                <div id="supplier-comparison-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                @if ($prsItems->isEmpty())
                    <div class="card shadow-sm border-0 sc-empty-card">
                        <div class="card-body text-center text-muted py-5">
                            <i class="fa-duotone fa-solid fa-inbox sc-empty-icon"></i>
                            <p class="mb-0 mt-2 fw-semibold">No canvassing items available for comparison.</p>
                            @if ($keyword !== '')
                                <small>Try changing or clearing your search keyword.</small>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="sc-comparison-list">
                        @foreach ($prsItems as $prsItem)
                            @php
                                $item = $prsItem->item;
                                $unitName = $item?->unit?->name ?? 'PCS';
                                $selectedSupplier = $prsItem->selectedCanvassingItem?->supplier?->name;
                                $isLocked = (bool) $prsItem->purchase_order_id;
                                $hasSelectedSupplier = (bool) $prsItem->selected_canvassing_item_id;
                            @endphp
                            <div class="card shadow-sm border-0 sc-comparison-card" id="supplier-comparison-item-{{ $prsItem->id }}" data-prs-item-id="{{ $prsItem->id }}">
                                <div class="card-body">
                                    <div class="sc-card-header">
                                        <div class="sc-item-heading">
                                            <span class="badge bg-light-primary text-primary sc-prs-badge">{{ $prsItem->prs?->prs_number ?? '-' }}</span>
                                            <h5 class="mb-1">{{ $item?->name ?? 'Item not found' }}</h5>
                                            <div class="sc-meta-line">
                                                <span>{{ $item?->code ?? 'No code' }}</span>
                                                <span>{{ $prsItem->canvassingItems->count() }} supplier quote{{ $prsItem->canvassingItems->count() === 1 ? '' : 's' }}</span>
                                            </div>
                                        </div>
                                        <div class="sc-status-wrap">
                                            <span class="sc-status-chip {{ $isLocked ? 'sc-status-locked' : 'sc-status-open' }}">
                                                <i class="{{ $isLocked ? 'fa-duotone fa-solid fa-lock' : 'fa-duotone fa-solid fa-circle-check' }}"></i>
                                                {{ $isLocked ? 'PO Created' : 'Open' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="sc-info-grid">
                                        <div class="sc-info-item">
                                            <span class="sc-info-label">Quantity</span>
                                            <span class="sc-info-value">{{ $prsItem->quantity }} {{ $unitName }}</span>
                                        </div>
                                        <div class="sc-info-item">
                                            <span class="sc-info-label">Canvasser</span>
                                            <span class="sc-info-value">{{ $prsItem->canvasser?->name ?? '-' }}</span>
                                        </div>
                                        <div class="sc-info-item sc-info-selected">
                                            <span class="sc-info-label">Selected Supplier</span>
                                            <span class="sc-info-value {{ $selectedSupplier ? 'text-primary' : 'text-muted' }}">{{ $selectedSupplier ?? 'Not selected' }}</span>
                                        </div>
                                    </div>

                                    <form method="post" action="{{ route('procurement.supplier-comparison.select', $prsItem) }}" id="form-{{ $prsItem->id }}" class="sc-selection-form {{ $isLocked ? 'opacity-75' : '' }}" data-selection-form>
                                        @csrf
                                        <input type="hidden" name="selection_reason" id="reason-{{ $prsItem->id }}">
                                        <div class="table-responsive sc-table-responsive">
                                            <table class="table align-middle po-table sc-table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 76px;">Select</th>
                                                        <th>Supplier</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-center">Lead Time</th>
                                                        <th>Term of Payment</th>
                                                        <th>Term of Delivery</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($prsItem->canvassingItems as $canvassing)
                                                        @php
                                                            $isSelectedQuote = $prsItem->selected_canvassing_item_id === $canvassing->id;
                                                        @endphp
                                                        <tr class="sc-supplier-row {{ $isLocked ? 'sc-supplier-row-disabled' : '' }} {{ $isSelectedQuote ? 'sc-selected-row' : '' }}" data-supplier-row tabindex="{{ $isLocked ? '-1' : '0' }}" aria-disabled="{{ $isLocked ? 'true' : 'false' }}">
                                                            <td class="text-center sc-select-cell" data-label="Select">
                                                                <input class="form-check-input sc-radio" type="radio" name="canvassing_item_id" value="{{ $canvassing->id }}" @checked($isSelectedQuote) @if ($loop->first) required @endif @disabled($isLocked)>
                                                            </td>
                                                            <td data-label="Supplier">
                                                                <div class="sc-supplier-cell">
                                                                    <span>{{ $canvassing->supplier?->name ?? '-' }}</span>
                                                                    <span class="badge bg-light-success text-success {{ $isSelectedQuote ? '' : 'd-none' }}" data-selection-badge>Selected</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-end" data-label="Unit Price">
                                                                <span class="sc-price">{{ number_format($canvassing->unit_price, 2) }}</span>
                                                            </td>
                                                            <td class="text-center" data-label="Lead Time">{{ $canvassing->lead_time_days ?? '-' }}</td>
                                                            <td data-label="Term of Payment">
                                                                @php
                                                                    $payment = trim(($canvassing->term_of_payment ? $canvassing->term_of_payment . ' ' : '') . ($canvassing->term_of_payment_type ?? ''));
                                                                @endphp
                                                                {{ $payment !== '' ? $payment : '-' }}
                                                            </td>
                                                            <td data-label="Term of Delivery">{{ $canvassing->term_of_delivery ?? '-' }}</td>
                                                            <td data-label="Notes">
                                                                <span class="sc-note-text">{{ $canvassing->notes ?? '-' }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="sc-card-actions">
                                            @if ($prsItem->selected_canvassing_item_id)
                                                <a href="{{ route('procurement.supplier-comparison.report', $prsItem->id) }}" target="_blank" rel="noopener" class="btn btn-outline-danger icon icon-left sc-action-btn">
                                                    <i class="fa-duotone fa-solid fa-file-pdf"></i>
                                                    Export PDF
                                                </a>
                                            @endif
                                            <button type="button" class="btn btn-primary icon icon-left sc-action-btn" data-bs-toggle="modal" data-bs-target="#reasonModal-{{ $prsItem->id }}" data-save-selection-button @disabled($isLocked || ! $hasSelectedSupplier)>
                                                <i class="fa-duotone fa-solid fa-floppy-disk"></i>
                                                Save Selection
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="modal fade sc-modal" id="reasonModal-{{ $prsItem->id }}" tabindex="-1" aria-labelledby="reasonModalLabel-{{ $prsItem->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="reasonModalLabel-{{ $prsItem->id }}">Selection Reason (Optional)</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <label for="reasonText-{{ $prsItem->id }}" class="form-label">Reason</label>
                                            <textarea class="form-control" id="reasonText-{{ $prsItem->id }}" rows="4" placeholder="Enter reason for selecting this supplier (optional)">{{ $prsItem->selection_reason }}</textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary icon icon-left" onclick="submitWithReason({{ $prsItem->id }})">
                                                <i class="fa-duotone fa-solid fa-floppy-disk"></i>
                                                Save Selection
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 sc-pagination-wrap">
                        {{ $prsItems->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
</div>

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/supplier-comparison.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/supplier-comparison-index.js') }}"></script>
@endpush
@endsection
