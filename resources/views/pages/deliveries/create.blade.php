@extends('layouts.app')
@section('title', ' | Create Delivery')

@section('content')
@php
    $selectedSupplierId = (string) old('supplier_id', '');
    $selectedSupplier = collect($suppliers ?? [])->firstWhere('id', (int) $selectedSupplierId);
    $selectedSupplierName = old('to_name_display', $selectedSupplier->name ?? '');
    $supplierPickerData = collect($suppliers ?? [])->map(function ($supplier) {
        return [
            'id' => (int) $supplier->id,
            'name' => $supplier->name,
            'address' => $supplier->address,
        ];
    })->values();
@endphp
<div class="page-heading prs-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <h3 class="mb-1">Create Delivery</h3>
                <p class="text-muted mb-0">Search items, set quantities, and add them to the delivery cart before submitting DR.</p>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('deliveries.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                    <button type="button" class="btn btn-outline-primary icon icon-left" id="toggle-delivery-cart">
                        <i class="fa-regular fa-cart-shopping"></i>
                        Cart
                        <span class="prs-cart-badge" id="delivery-cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 mb-3">
            <div class="fw-semibold mb-1">Delivery could not be saved.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="section">
        <form action="{{ route('deliveries.store') }}" method="POST" class="prs-create-form" id="delivery-create-form">
            @csrf
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="prs-catalog-toolbar" id="delivery-catalog-filter-form" data-base-url="{{ route('deliveries.create') }}">
                                <div class="prs-catalog-field prs-catalog-search-field">
                                    <label for="delivery-item-search" class="form-label mb-1">Search Item</label>
                                    <input type="text" class="form-control" id="delivery-item-search" name="search" value="{{ $search ?? '' }}" placeholder="Item name or code">
                                </div>
                                <div class="prs-catalog-field">
                                    <label for="delivery-category-filter" class="form-label mb-1">Category</label>
                                    <select class="form-select" id="delivery-category-filter" name="category">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($selectedCategory ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="prs-catalog-reset-field">
                                    <button type="button" class="btn btn-light-secondary w-100" id="delivery-reset-filter">Reset</button>
                                </div>
                            </div>
                            <div class="prs-item-grid" id="delivery-item-grid">
                                @forelse ($items as $item)
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
                                @empty
                                    <div class="prs-catalog-empty-state">
                                        <i class="fa-duotone fa-solid fa-box-open prs-catalog-empty-icon"></i>
                                        <p class="mb-0 mt-2 fw-semibold">No items found.</p>
                                        <small>Try changing your keyword or category filter to see more results.</small>
                                    </div>
                                @endforelse
                            </div>
                            <div class="mt-4 prs-pagination" id="delivery-pagination" data-current-page="{{ $items->currentPage() }}" data-last-page="{{ $items->lastPage() }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary prs-mobile-cart-btn" id="toggle-delivery-cart-mobile">
                <i class="fa-regular fa-cart-shopping me-1"></i>
                Cart Items
            </button>

            <aside class="prs-cart-popup is-hidden" id="delivery-cart-popup" aria-hidden="true">
                <div class="prs-cart-header">
                    <div>
                        <h5 class="mb-0">Delivery Cart</h5>
                        <small class="text-muted">Fill delivery header and review your cart items</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="hide-delivery-cart">
                        <i class="fa-light fa-xmark"></i>
                    </button>
                </div>
                <div class="prs-cart-body">
                    <div id="delivery-stock-rule-hint" class="alert alert-warning py-2 mb-3 d-none" role="alert"></div>

                    <div class="delivery-cart-header-fields mb-3">
                        <h6 class="mb-2">Delivery Header</h6>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label for="delivery-dr-number" class="form-label">DR Number</label>
                                <input type="text" class="form-control" id="delivery-dr-number" name="dr_number" value="{{ old('dr_number') }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-dr-date" class="form-label">DR Date</label>
                                <input type="date" class="form-control" id="delivery-dr-date" name="dr_date" value="{{ old('dr_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-from-name" class="form-label">From</label>
                                <input type="text" class="form-control" id="delivery-from-name" name="from_name" value="{{ old('from_name', 'IM - PT. SPFI') }}" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-from-location" class="form-label">From Location</label>
                                <input type="text" class="form-control" id="delivery-from-location" name="from_location" value="{{ old('from_location') }}" placeholder="From location">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-to-name-display" class="form-label">To</label>
                                <input type="hidden" id="delivery-supplier-id" name="supplier_id" value="{{ $selectedSupplierId }}" required>
                                <input type="text" class="form-control" id="delivery-to-name-display" name="to_name_display" value="{{ $selectedSupplierName }}" placeholder="Choose supplier" readonly required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-to-location" class="form-label">To Location</label>
                                <input type="text" class="form-control" id="delivery-to-location" name="to_location" value="{{ old('to_location') }}" placeholder="To location">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-or-number" class="form-label">OR No.</label>
                                <input type="text" class="form-control" id="delivery-or-number" name="or_number" value="{{ old('or_number') }}" placeholder="Optional">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="delivery-dm-number" class="form-label">DM No.</label>
                                <input type="text" class="form-control" id="delivery-dm-number" name="dm_number" value="{{ old('dm_number') }}" placeholder="Optional">
                            </div>
                            <div class="col-12">
                                <label for="delivery-remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="delivery-remarks" name="remarks" rows="2" placeholder="Add notes if needed">{{ old('remarks') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="delivery-cart-items">
                        <h6 class="mb-2">Items</h6>
                        <div id="delivery-cart-list"></div>
                        <div class="prs-cart-empty text-center" id="delivery-cart-empty">
                            <i class="fa-light fa-basket-shopping fa-2x text-muted mb-2"></i>
                            <p class="mb-0 text-muted">Cart is empty. Add items from the catalog.</p>
                        </div>
                        <div id="delivery-cart-hidden-inputs"></div>
                    </div>
                </div>
                <div class="prs-cart-footer">
                    <button type="submit" class="btn btn-success w-100 icon icon-left">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Submit Delivery
                    </button>
                </div>
            </aside>
        </form>
    </section>

    <div class="modal fade" id="deliverySupplierPickerModal" tabindex="-1" aria-labelledby="deliverySupplierPickerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deliverySupplierPickerModalLabel">Choose Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="delivery-supplier-picker-search" placeholder="Search supplier...">
                    </div>
                    <div id="delivery-supplier-picker-list" class="list-group"></div>
                </div>
            </div>
        </div>
    </div>

    <script id="delivery-supplier-data" type="application/json">@json($supplierPickerData)</script>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/deliveries-create.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/deliveries-create.js') }}"></script>
@endpush
