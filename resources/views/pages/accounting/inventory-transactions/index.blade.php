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
                        <a href="{{ route('accounting.inventory-transactions.create', ['category_id' => $filters['category_id'] ?? null]) }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-boxes-stacked"></i>
                            Create CV / JV
                        </a>
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
                        <input type="search" id="filter-inventory-keyword" class="form-control" placeholder="Doc no, party, reference..." value="{{ $filters['keyword'] ?? '' }}">
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
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-inventory-doc-type" class="form-label mb-1">Document Type</label>
                        <select id="filter-inventory-doc-type" class="form-select">
                            <option value="all" @selected(($filters['doc_type'] ?? 'all') === 'all')>All</option>
                            @foreach (['RR', 'TS', 'DR', 'CV', 'JV'] as $type)
                                <option value="{{ $type }}" @selected(($filters['doc_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
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
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/accounting-inventory-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-index.js') }}"></script>
@endpush
