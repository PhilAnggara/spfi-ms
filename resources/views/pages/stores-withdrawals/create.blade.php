@extends('layouts.app')
@section('title', ' | Create Stores Withdrawal')

@section('content')
<div class="page-heading prs-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <h3 class="mb-1">Create Stores Withdrawal</h3>
                <p class="text-muted mb-0">Search items, set quantities, and add them to the cart before submitting.</p>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('stores-withdrawals.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                    <button type="button" class="btn btn-outline-primary icon icon-left" id="toggle-sws-cart">
                        <i class="fa-regular fa-cart-shopping"></i>
                        Cart
                        <span class="prs-cart-badge" id="sws-cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <form action="{{ route('stores-withdrawals.store') }}" method="POST" class="prs-create-form" id="sws-create-form">
            @csrf
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="alert alert-info py-2 mb-3" role="alert">
                                <strong>Confirmatory</strong> type allows selecting zero-stock items so withdrawal can still be recorded before RR is posted.
                            </div>
                            <div class="prs-catalog-toolbar" id="sws-catalog-filter-form" data-base-url="{{ route('stores-withdrawals.create') }}">
                                <div class="prs-search">
                                    <i class="fa-regular fa-magnifying-glass"></i>
                                    <input type="text" class="form-control" id="sws-item-search" name="search" value="{{ $search ?? '' }}" placeholder="Search by item name or code">
                                </div>
                                <div class="prs-filter d-flex gap-2 align-items-center">
                                    <select class="form-select" id="sws-category-filter" name="category">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($selectedCategory ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-light-secondary" id="sws-reset-filter">Reset</button>
                                </div>
                            </div>
                            <div class="prs-item-grid" id="sws-item-grid">
                                @foreach ($items as $item)
                                    <div class="prs-item-card"
                                        data-item-id="{{ $item->id }}"
                                        data-name="{{ strtolower($item->name) }}"
                                        data-code="{{ strtolower($item->code) }}"
                                        data-category="{{ strtolower($item->category?->name ?? '') }}"
                                        data-stock="{{ (float) $item->stock_on_hand }}"
                                        data-unit="{{ $item->unit?->name ?? 'PCS' }}">
                                        <div class="prs-item-body">
                                            <div class="prs-item-title" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $item->name }}">{{ $item->name }}</div>
                                            <div class="prs-item-meta">
                                                <span class="badge bg-light-primary">{{ $item->code }}</span>
                                                <span class="text-muted">Stock {{ $item->stock_on_hand }} {{ $item->unit?->name ?? 'PCS' }}</span>
                                            </div>
                                            <div class="prs-item-meta text-muted">{{ $item->category?->name ?? 'Uncategorized' }}</div>
                                            <div class="prs-item-actions">
                                                <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity">
                                                    <i class="fa-light fa-minus"></i>
                                                </button>
                                                <input type="number" min="1" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                                                <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Increase quantity">
                                                    <i class="fa-light fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="{{ $item->id }}">
                                                    <i class="fa-light fa-plus"></i>
                                                    Add
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 prs-pagination" id="sws-pagination" data-current-page="{{ $items->currentPage() }}" data-last-page="{{ $items->lastPage() }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary prs-mobile-cart-btn" id="toggle-sws-cart-mobile">
                <i class="fa-regular fa-cart-shopping me-1"></i>
                Cart Items
            </button>

            <aside class="prs-cart-popup is-hidden" id="sws-cart-popup" aria-hidden="true">
                <div class="prs-cart-header">
                    <div>
                        <h5 class="mb-0">Stores Withdrawal Cart</h5>
                        <small class="text-muted">Manage your withdrawal items</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="hide-sws-cart">
                        <i class="fa-light fa-xmark"></i>
                    </button>
                </div>
                <div class="prs-cart-body">
                    <div id="sws-stock-rule-hint" class="alert alert-warning py-2 mb-3 d-none" role="alert"></div>
                    <div class="prs-cart-layout">
                        <div class="prs-cart-layout-header">
                            <h6 class="mb-2">Stores Withdrawal Header</h6>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label for="sws-department" class="form-label">Charged to Department</label>
                                    <select class="form-select" id="sws-department" name="department_id" required>
                                        <option value="" disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ auth()->user()->department_id == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="sws-date" class="form-label">SWS Date</label>
                                    <input type="date" id="sws-date" class="form-control" name="sws_date" value="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="sws-type" class="form-label">Type</label>
                                    <select class="form-select" id="sws-type" name="type" required>
                                        <option value="NORMAL" selected>Normal</option>
                                        <option value="CONFIRMATORY">Confirmatory</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="sws-info" class="form-label">Info / Remarks</label>
                                    <textarea class="form-control" id="sws-info" name="info" rows="2" placeholder="Add notes if needed"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="prs-cart-layout-items">
                            <div id="sws-cart-list"></div>
                            <div class="prs-cart-empty text-center" id="sws-cart-empty">
                                <i class="fa-light fa-basket-shopping fa-2x text-muted mb-2"></i>
                                <p class="mb-0 text-muted">Cart is empty. Add items from the catalog.</p>
                            </div>
                            <div id="sws-cart-hidden-inputs"></div>
                        </div>
                    </div>
                </div>
                <div class="prs-cart-footer">
                    <button type="submit" class="btn btn-success w-100 icon icon-left">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Submit Stores Withdrawal
                    </button>
                </div>
            </aside>
        </form>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stores-withdrawals-create.js') }}"></script>
@endpush
