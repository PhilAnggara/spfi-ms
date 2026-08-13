@extends('layouts.app')
@section('title', ' | Product')

@section('content')
<div id="product-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Product</h3>
                    <p class="text-muted mb-0">Browse products with instant search, live filters, and purchase history for canvassing.</p>
                </div>
            </div>
            @if ($canCreateProducts)
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <button type="button" class="btn btn-success icon icon-left" data-bs-toggle="modal" data-bs-target="#create-modal">
                            <i class="fa-duotone fa-solid fa-plus"></i>
                            Add Product
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="product-filter-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="filter-product-keyword" class="form-label mb-1">Search Product</label>
                        <input type="text" id="filter-product-keyword" class="form-control" value="{{ $filters['keyword'] }}" placeholder="Product code / name">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-product-category" class="form-label mb-1">Category</label>
                        <select id="filter-product-category" class="form-select">
                            <option value="">All Categories</option>
                            @foreach ($itemCategories as $category)
                                <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-product-unit" class="form-label mb-1">Unit</label>
                        <select id="filter-product-unit" class="form-select">
                            <option value="">All Units</option>
                            @foreach ($itemUnits as $unit)
                                <option value="{{ $unit->id }}" @selected((string) $filters['unit_id'] === (string) $unit->id)>
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-product-type" class="form-label mb-1">Type</label>
                        <select id="filter-product-type" class="form-select">
                            <option value="">All Types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-8 col-md-6 col-xl-3">
                        <label for="filter-product-sort" class="form-label mb-1">Sort By</label>
                        <select id="filter-product-sort" class="form-select">
                            <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A–Z</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z–A</option>
                            <option value="code_asc" @selected($filters['sort'] === 'code_asc')>Product Code A–Z</option>
                            <option value="code_desc" @selected($filters['sort'] === 'code_desc')>Product Code Z–A</option>
                            <option value="category_asc" @selected($filters['sort'] === 'category_asc')>Category A–Z</option>
                            <option value="category_desc" @selected($filters['sort'] === 'category_desc')>Category Z–A</option>
                            <option value="stock_asc" @selected($filters['sort'] === 'stock_asc')>Stock: Low → High</option>
                            <option value="stock_desc" @selected($filters['sort'] === 'stock_desc')>Stock: High → Low</option>
                            @if ($canViewPurchaseHistory)
                                <option value="avg_unit_price_asc" @selected($filters['sort'] === 'avg_unit_price_asc')>Avg Unit Price: Low → High</option>
                                <option value="avg_unit_price_desc" @selected($filters['sort'] === 'avg_unit_price_desc')>Avg Unit Price: High → Low</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-4 col-md-3 col-xl-2">
                        <button type="button" id="reset-product-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="product-page-results">
            <div class="card shadow-sm border-0">
                <div class="card-body position-relative">
                    <div id="product-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">Product List</h5>
                        <span class="badge bg-light-primary" id="product-filter-result">0 records</span>
                    </div>

                    <div class="table-responsive">
                        <table
                            class="table table-striped align-middle po-table text-nowrap w-100"
                            id="product-table"
                            data-source="{{ route('product.datatables') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-update-route-template="{{ route('product.update', '__ID__') }}"
                            data-destroy-route-template="{{ route('product.destroy', '__ID__') }}"
                            data-history-route-template="{{ $canViewPurchaseHistory ? route('product.purchase-history', '__ID__') : '' }}"
                            data-po-show-route-template="{{ route('purchase-orders.show', '__ID__') }}"
                            data-can-manage="{{ $canManageProducts ? '1' : '0' }}"
                            data-can-create="{{ $canCreateProducts ? '1' : '0' }}"
                            data-can-view-po="{{ $canViewPurchaseOrders ? '1' : '0' }}"
                            data-can-view-purchase-history="{{ $canViewPurchaseHistory ? '1' : '0' }}"
                            data-open-create-modal="{{ $errors->any() ? '1' : '0' }}"
                            data-editing-product-id="{{ (string) session('editing_product_id', '') }}">
                            <thead>
                                <tr>
                                    <th class="d-none">ID</th>
                                    <th>Product Code</th>
                                    <th>Name</th>
                                    <th class="text-end">Stock</th>
                                    <th>Unit</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    @if ($canViewPurchaseHistory)
                                        <th class="text-end">Avg Unit Price</th>
                                    @endif
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
</div>

@if ($canCreateProducts || $canManageProducts)
    @include('includes.modals.product-modal')
@endif
@if ($canViewPurchaseHistory)
    @include('includes.modals.product-purchase-history-modal')
@endif
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    @if ($canCreateProducts || $canManageProducts)
        <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
        <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
    @endif
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/master-code-validation.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/product-index.js') }}"></script>
@endpush
