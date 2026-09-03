@extends('layouts.app')
@section('title', ' | Accounting Inventory')

@section('content')
<div id="inventory-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Accounting Inventory</h3>
                    <p class="text-muted mb-0">Validate pre-filled operational documents or create CV/JV vouchers, then encode to the accounting inventory ledger.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    @can('create-accounting-inventory')
                        <button
                            type="button"
                            class="btn btn-success icon icon-left"
                            id="inventory-manual-create-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#inventory-manual-create-modal"
                            data-create-url="{{ route('accounting.inventory-transactions.create') }}"
                        >
                            <i class="fa-duotone fa-solid fa-boxes-stacked"></i>
                            Create CV / JV
                        </button>
                    @endcan
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

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="inventory-filter-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="filter-inventory-keyword" class="form-label mb-1">Search</label>
                        <input type="search" id="filter-inventory-keyword" class="form-control" placeholder="Doc no, supplier, reference..." value="{{ $filters['keyword'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-inventory-category" class="form-label mb-1">Category</label>
                        <select id="filter-inventory-category" class="form-select">
                            <option value="">All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-inventory-doc-type" class="form-label mb-1">Document Type</label>
                        <select id="filter-inventory-doc-type" class="form-select">
                            <option value="all" @selected(($filters['doc_type'] ?? 'all') === 'all')>All</option>
                            @foreach (['RR', 'TS', 'DR', 'CV', 'JV'] as $type)
                                <option value="{{ $type }}" @selected(($filters['doc_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-inventory-status" class="form-label mb-1">Status</label>
                        <select id="filter-inventory-status" class="form-select">
                            <option value="pending" @selected(($filters['status'] ?? 'pending') === 'pending')>Pending</option>
                            <option value="encoded" @selected(($filters['status'] ?? '') === 'encoded')>Encoded</option>
                            <option value="all" @selected(($filters['status'] ?? '') === 'all')>All</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-inventory-date-from" class="form-label mb-1">Date From</label>
                        <input type="date" id="filter-inventory-date-from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-inventory-date-to" class="form-label mb-1">Date To</label>
                        <input type="date" id="filter-inventory-date-to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-inventory-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="inventory-page-results">
            @include('pages.accounting.inventory-transactions.partials.results-panel')
        </div>
    </section>
</div>

<div class="modal fade" id="inventory-manual-create-modal" tabindex="-1" aria-labelledby="inventory-manual-create-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inventory-manual-create-title">Create CV / JV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="inventory-manual-create-body">
                <div class="text-center text-muted py-5">Loading...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="inventory-encode-modal" tabindex="-1" aria-labelledby="inventory-encode-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered inv-encode-modal-dialog">
        <div class="modal-content border-0 shadow inv-encode-modal-content">
            <div class="modal-header inv-encode-modal-header border-0">
                <div class="min-w-0">
                    <h5 class="modal-title mb-0" id="inventory-encode-modal-title">Encode Inventory</h5>
                    <div class="inv-encode-modal-subtitle" id="inventory-encode-modal-subtitle">Review and encode lines</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3 inv-encode-modal-body" id="inventory-encode-body">
                <div class="inv-encode-body-stage" data-inv-encode-stage>
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2">Loading document...</div>
                    </div>
                </div>
            </div>
            <div class="inv-encode-modal-footer d-none" id="inv-encode-modal-footer">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="inv-encode-footer-left">
                        <div class="inv-encode-footer-total-label">Total Amount</div>
                        <div class="inv-encode-footer-total-value font-monospace" id="inv-encode-footer-total">0.00</div>
                        <div class="inv-encode-shortcut-hint mt-1">
                            <kbd>Ctrl</kbd>+<kbd>Enter</kbd> encode &amp; next · <kbd>Esc</kbd> close
                        </div>
                    </div>
                    <div class="inv-encode-next-up d-none" id="inv-encode-next-up" aria-live="polite">
                        <div class="inv-encode-next-up-label">
                            <i class="fa-regular fa-forward-step me-1"></i>
                            Next after Encode &amp; Next
                        </div>
                        <div class="inv-encode-next-up-body">
                            <span class="inv-encode-doc-badge inv-encode-doc-badge--sm" id="inv-encode-next-type"></span>
                            <span class="inv-encode-next-doc-number" id="inv-encode-next-number"></span>
                        </div>
                        <div class="inv-encode-next-up-meta text-truncate" id="inv-encode-next-meta"></div>
                    </div>
                    <div class="inv-encode-footer-actions d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-success" id="inv-encode-submit-close" disabled>
                            <i class="fa-regular fa-check me-1"></i>
                            Encode &amp; Close
                        </button>
                        <button type="button" class="btn btn-success" id="inv-encode-submit-next" disabled>
                            <i class="fa-regular fa-forward me-1"></i>
                            Encode &amp; Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="inventory-encode-toast-container" aria-live="polite" aria-atomic="true"></div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/accounting-inventory-encode.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stock-correction-item-search.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-encode.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-create.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-index.js') }}"></script>
@endpush
