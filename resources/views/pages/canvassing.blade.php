@extends('layouts.app')
@section('title', ' | Canvassing')

@section('content')
    <div id="canvassing-page-container">
        <div class="page-heading po-page">
            <div class="page-title mb-4">
                <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-7">
                    <div class="po-hero">
                        <h3 class="mb-1">Canvassing</h3>
                        <p class="text-muted mb-0">Manage supplier quotes quickly with unified filters, clean table view, and dynamic pagination.</p>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions">
                        <a href="{{ route('canvassing.index') }}" class="btn btn-light-secondary icon icon-left" id="reset-canvassing-top">
                            <i class="fa-light fa-rotate-left"></i>
                            Reset View
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end po-filter-grid" id="canvassing-filter-form">
                        <div class="col-12 col-md-6 col-xl-4">
                            <label for="filter-canvassing-keyword" class="form-label mb-1">Search Canvassing</label>
                            <input
                                type="text"
                                id="filter-canvassing-keyword"
                                class="form-control"
                                value="{{ $filters['keyword'] ?? '' }}"
                                placeholder="PRS number / item code / item name">
                        </div>
                        <div class="col-6 col-md-3 col-xl-3">
                            <label for="filter-canvassing-department" class="form-label mb-1">Department</label>
                            <select id="filter-canvassing-department" class="form-select">
                                <option value="">All Department</option>
                                @foreach ($departmentOptions as $department)
                                    <option value="{{ $department->code }}" @selected(($filters['department'] ?? '') === $department->code)>
                                        {{ $department->code }} - {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="filter-canvassing-date-start" class="form-label mb-1">Date Needed (from)</label>
                            <input type="date" id="filter-canvassing-date-start" class="form-control" value="{{ $filters['date_needed_start'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="filter-canvassing-date-end" class="form-label mb-1">Date Needed (to)</label>
                            <input type="date" id="filter-canvassing-date-end" class="form-control" value="{{ $filters['date_needed_end'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-3 col-xl-1">
                            <button type="button" id="reset-canvassing-filter" class="btn btn-light-secondary w-100">
                                <i class="fa-regular fa-rotate-left me-1"></i>
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="canvassing-page-results">
                <div class="card shadow-sm border-0">
                    <div class="card-body position-relative">
                        <div id="canvassing-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                <div class="mt-2 text-muted">Loading data...</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="card-title mb-0">Canvassing Data</h5>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-light-info text-info-emphasis" id="canvassing-selected-count">0 selected</span>
                                <button type="button" class="btn btn-light-secondary btn-sm" id="canvassing-clear-selection-btn">Clear Selection</button>
                                <button type="button" class="btn btn-danger btn-sm" id="canvassing-print-selected-btn" disabled>
                                    <i class="fa-light fa-file-pdf me-1"></i>
                                    Print Selected Reports
                                </button>
                                <span class="badge bg-light-primary" id="canvassing-filter-result">{{ $prsItems->total() }} records</span>
                            </div>
                        </div>

                        @if ($prsItems->isEmpty())
                            <div class="po-empty-state text-center text-muted py-5">
                                <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                                <p class="mb-0 mt-2 fw-semibold">No canvassing items found.</p>
                                <small>Try changing your keyword or filters to see more results.</small>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-striped align-middle po-table text-nowrap" id="canvassing-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 44px;">
                                                <input type="checkbox" class="form-check-input" id="canvassing-select-page-checkbox" title="Select printable items on this page">
                                            </th>
                                            <th>PRS Number</th>
                                            <th>Department</th>
                                            <th>Item Code</th>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Date Needed</th>
                                            <th>Suppliers</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($prsItems as $prsItem)
                                            @php
                                                $prs = $prsItem->prs;
                                                $department = $prs?->department;
                                                $item = $prsItem->item;
                                                $prsNumber = $prs?->prs_number ?? '-';
                                                $departmentCode = $department?->code ?? '-';
                                                $departmentName = $department?->name ?? 'Department not available';
                                                $itemCode = $item?->code ?? 'N/A';
                                                $itemName = $item?->name ?? 'Item not found';
                                                $hasQuotes = $prsItem->canvassingItems->isNotEmpty();
                                            @endphp
                                            <tr>
                                                <td class="canvassing-select-cell">
                                                    <div class="canvassing-select-cell-inner">
                                                        <input
                                                            id="canvassing-select-{{ $prsItem->id }}"
                                                            type="checkbox"
                                                            class="form-check-input canvassing-select-checkbox"
                                                            value="{{ $prsItem->id }}"
                                                            data-prs-number="{{ $prsNumber }}"
                                                            data-item-code="{{ $itemCode }}"
                                                            data-item-name="{{ $itemName }}"
                                                            @disabled(! $hasQuotes)
                                                            @if (! $hasQuotes)
                                                                data-bstooltip-toggle="tooltip"
                                                                data-bs-placement="top"
                                                                title="No supplier data"
                                                            @endif
                                                        >
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $prsNumber }}')">
                                                        <i class="fa-solid fa-regular fa-clipboard"></i>
                                                        {{ $prsNumber }}
                                                    </button>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light-primary" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $departmentName }}">{{ $departmentCode }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $itemCode }}')">{{ $itemCode }}</span>
                                                </td>
                                                <td data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $itemName }}">{{ Str::limit($itemName, 40) }}</td>
                                                <td>
                                                    <span class="fw-semibold">{{ \App\Support\PdfFormatters::qty($prsItem->quantity) }}</span>
                                                    <small class="text-muted">{{ $item?->unit?->name ?? 'PCS' }}</small>
                                                </td>
                                                <td>
                                                    <i class="fa-duotone fa-solid fa-calendar-star text-primary"></i>
                                                    {{ $prs?->date_needed ? tgl($prs->date_needed) : '-' }}
                                                </td>
                                                <td>
                                                    <div class="small text-muted">Quotes: {{ $prsItem->canvassingItems->count() }}</div>
                                                    <div class="fw-semibold" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $prsItem->selectedCanvassingItem?->supplier?->name ?? 'Not selected' }}">
                                                        <span class="{{ $prsItem->selectedCanvassingItem?->supplier?->name ? 'text-primary' : 'text-muted' }}">{{ Str::limit($prsItem->selectedCanvassingItem?->supplier?->name ?? 'Not selected', 18) }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('canvassing.show', $prsItem->id) }}" class="btn icon {{ $hasQuotes ? 'btn-outline-primary' : '' }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $hasQuotes ? 'Manage Suppliers' : 'Add Supplier' }}">
                                                            <i class="fa-light fa-pen-to-square"></i>
                                                        </a>
                                                        @if (!$prsItem->purchase_order_id)
                                                            <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#canvasser-hold-modal-{{ $prsItem->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Request Quantity Revision">
                                                                <i class="fa-light fa-circle-pause text-warning"></i>
                                                            </button>
                                                            <form action="{{ route('canvassing.toggle-direct-purchase', $prsItem->id) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="is_direct_purchase" value="{{ $prsItem->is_direct_purchase ? '0' : '1' }}">
                                                                <button type="submit" class="btn icon {{ $prsItem->is_direct_purchase ? 'btn-outline-info' : '' }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $prsItem->is_direct_purchase ? 'Revert to Needs PO' : 'Mark as Direct Purchase' }}">
                                                                    <i class="fa-light fa-basket-shopping"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 canvassing-pagination-wrap">
                                {{ $prsItems->onEachSide(1)->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    @foreach ($prsItems as $prsItem)
        <div class="modal fade" id="canvasser-hold-modal-{{ $prsItem->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('canvassing.hold', $prsItem->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Request Quantity Revision</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">This will hold the entire PRS so the requester can adjust quantities only.</p>
                            <div class="form-group">
                                <label for="hold-message-{{ $prsItem->id }}" class="form-label">Reason</label>
                                <textarea id="hold-message-{{ $prsItem->id }}" name="message" class="form-control" rows="4" required placeholder="Explain why the requested item/specification needs quantity revision"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Submit Hold</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="canvassing-print-modal" tabindex="-1" aria-labelledby="canvassing-print-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <form
                method="GET"
                action="{{ route('canvassing.reports.print') }}"
                target="_blank"
                class="modal-content po-detail-modal document-print-confirm-form"
                id="canvassing-print-form"
            >
                <div class="modal-header po-detail-modal-header">
                    <div>
                        <h5 class="modal-title" id="canvassing-print-modal-label">Confirm Print Reports</h5>
                        <small class="text-muted" id="canvassing-print-summary">Selected items: 0</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-text mb-3">
                        Selected items will be combined into one PDF before it opens in a new tab.
                    </div>
                    <div class="alert alert-light border mb-0">
                        <div class="fw-semibold mb-2">Selected items</div>
                        <ul class="list-unstyled mb-0 canvassing-print-list" id="canvassing-print-list"></ul>
                    </div>
                    <div id="canvassing-print-hidden-inputs"></div>
                </div>
                <div class="modal-footer po-detail-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-duotone fa-solid fa-print me-1"></i>
                        Confirm &amp; Print
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/canvassing-index.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/canvassing-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/canvassing-index.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/document-print-confirm.js') }}"></script>
@endpush
