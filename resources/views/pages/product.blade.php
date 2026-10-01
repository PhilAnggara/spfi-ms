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
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light-info text-info-emphasis" id="product-selected-count">0 selected</span>
                            <button type="button" class="btn btn-light-secondary btn-sm" id="product-select-all-btn">Select All Results</button>
                            <button type="button" class="btn btn-light-secondary btn-sm" id="product-clear-selection-btn">Clear Selection</button>
                            <button type="button" class="btn btn-primary btn-sm" id="product-print-selected-btn" disabled>
                                <i class="fa-light fa-qrcode me-1"></i>
                                Print Selected Labels
                            </button>
                            <span class="badge bg-light-primary" id="product-filter-result">0 records</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table
                            class="table table-striped align-middle po-table text-nowrap w-100"
                            id="product-table"
                            data-source="{{ route('product.datatables') }}"
                            data-barcode-show-route-template="{{ route('product.barcode.show', '__ID__') }}"
                            data-barcode-print-route="{{ route('product.barcodes.print') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-update-route-template="{{ route('product.update', '__ID__') }}"
                            data-destroy-route-template="{{ route('product.destroy', '__ID__') }}"
                            data-history-route-template="{{ $canViewPurchaseHistory ? route('product.purchase-history', '__ID__') : '' }}"
                            data-canvassing-history-route-template="{{ $canViewCanvassingHistory ? route('product.canvassing-history', '__ID__') : '' }}"
                            data-canvassing-history-export-route-template="{{ $canViewCanvassingHistory ? route('product.canvassing-history.export', '__ID__') : '' }}"
                            data-po-show-route-template="{{ route('purchase-orders.show', '__ID__') }}"
                            data-prs-show-route-template="{{ route('prs.show', '__ID__') }}"
                            data-can-manage="{{ $canManageProducts ? '1' : '0' }}"
                            data-can-create="{{ $canCreateProducts ? '1' : '0' }}"
                            data-can-view-po="{{ $canViewPurchaseOrders ? '1' : '0' }}"
                            data-can-view-purchase-history="{{ $canViewPurchaseHistory ? '1' : '0' }}"
                            data-can-view-canvassing-history="{{ $canViewCanvassingHistory ? '1' : '0' }}"
                            data-can-view-prs="{{ $canViewPrs ? '1' : '0' }}"
                            data-open-create-modal="{{ $errors->any() ? '1' : '0' }}"
                            data-editing-product-id="{{ (string) session('editing_product_id', '') }}">
                            <thead>
                                <tr>
                                    <th class="spfi-col-icon">
                                        <input type="checkbox" class="form-check-input" id="product-select-all-checkbox">
                                    </th>
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
@if ($canViewCanvassingHistory)
    @include('includes.modals.product-canvassing-history-modal')
@endif

<div class="modal fade" id="product-barcode-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="product-barcode-preview-title">Product QR Code</h5>
                    <small class="text-muted" id="product-barcode-preview-meta">-</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="product-barcode-preview-loading" class="py-4 text-muted">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <div class="mt-2">Loading QR code...</div>
                </div>
                <div id="product-barcode-preview-content" class="d-none">
                    <div class="d-inline-block border border-dark-subtle p-2 rounded" id="product-barcode-preview-qr"></div>
                    <div class="mt-3">
                        <div class="fw-semibold" id="product-barcode-preview-code"></div>
                        <small class="text-muted">Scan to identify product code</small>
                    </div>
                </div>
                <div id="product-barcode-preview-error" class="d-none text-danger py-3">Failed to load QR code.</div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="product-barcode-preview-print-btn" disabled>
                    <i class="fa-light fa-print me-1"></i>
                    Print Label
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="product-barcode-print-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('product.barcodes.print') }}" target="_blank" id="product-barcode-print-form">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Print Product QR Labels</h5>
                        <small class="text-muted" id="product-barcode-print-summary">Selected products: 0</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-muted">Open a printable PDF of QR labels for the selected products. Each label encodes the product code.</p>
                    <div id="product-barcode-hidden-inputs"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-light fa-print me-1"></i>
                        Print Labels
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/master-code-validation.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/product-index.js') }}"></script>
@endpush
