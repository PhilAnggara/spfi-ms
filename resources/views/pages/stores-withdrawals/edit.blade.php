@extends('layouts.app')
@section('title', ' | Edit Stores Withdrawal')

@section('content')
@php
    $initialMode = ($withdrawalMode ?? 'normal') === 'capex' ? 'capex' : 'normal';
    $resolvedType = strtoupper((string) ($typeValue ?? ($initialMode === 'capex' ? 'CAPEX' : 'NORMAL')));
@endphp
<div
    class="page-heading prs-create-page"
    id="sws-edit-page"
    data-initial-mode="{{ $initialMode }}"
    data-mode-locked="1"
    data-auto-open-cart="1"
    data-exclude-store-withdrawal-id="{{ $storeWithdrawal->id }}"
    data-existing-cart='@json($existingCartItems)'
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <h3 class="mb-1">Edit Stores Withdrawal</h3>
                <p class="text-muted mb-0">{{ $storeWithdrawal->sws_number }} — search items, update the cart, then save your changes.</p>
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
                        <span class="prs-cart-badge" id="sws-cart-count">{{ count($existingCartItems) }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <form action="{{ route('stores-withdrawals.update', $storeWithdrawal->id) }}" method="POST" class="prs-create-form" id="sws-edit-form">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="sws-withdrawal-mode-switch sws-withdrawal-mode-toggle mb-3" role="group" aria-label="Withdrawal mode">
                                <button type="button"
                                    class="sws-mode-option {{ $initialMode === 'normal' ? 'active' : '' }}"
                                    data-withdrawal-mode="normal"
                                    aria-pressed="{{ $initialMode === 'normal' ? 'true' : 'false' }}"
                                    disabled>
                                    <span class="sws-mode-option-icon">
                                        <i class="fa-light fa-boxes-stacked"></i>
                                    </span>
                                    <span class="sws-mode-option-body">
                                        <span class="sws-mode-option-title">Normal Withdrawal</span>
                                        <span class="sws-mode-option-desc">Withdraw from warehouse stock (Normal or Confirmatory).</span>
                                    </span>
                                </button>
                                <button type="button"
                                    class="sws-mode-option {{ $initialMode === 'capex' ? 'active' : '' }}"
                                    data-withdrawal-mode="capex"
                                    aria-pressed="{{ $initialMode === 'capex' ? 'true' : 'false' }}"
                                    disabled>
                                    <span class="sws-mode-option-icon">
                                        <i class="fa-light fa-building-columns"></i>
                                    </span>
                                    <span class="sws-mode-option-body">
                                        <span class="sws-mode-option-title">CAPEX Withdrawal</span>
                                        <span class="sws-mode-option-desc">Withdraw from CAPEX RR lines not yet taken.</span>
                                    </span>
                                </button>
                            </div>

                            <div class="alert alert-info py-2 mb-3" id="sws-normal-mode-hint" @if($initialMode === 'capex') style="display:none" @endif role="alert">
                                Mode is locked for this document. You can still change item quantities and lines in the cart.
                            </div>
                            <div class="alert alert-warning py-2 mb-3" id="sws-capex-mode-hint" @if($initialMode !== 'capex') style="display:none" @endif role="alert">
                                CAPEX mode is locked. Remaining RR balance excludes this SWS so you can keep or adjust existing lines.
                            </div>

                            <div class="prs-catalog-toolbar {{ $initialMode === 'capex' ? 'sws-catalog-toolbar--capex' : '' }}" id="sws-catalog-filter-form"
                                data-base-url="{{ route('stores-withdrawals.edit', $storeWithdrawal->id) }}"
                                data-capex-url="{{ route('stores-withdrawals.capex-lines') }}">
                                <div class="prs-catalog-field prs-catalog-search-field">
                                    <label for="sws-item-search" class="form-label mb-1">Search Item</label>
                                    <input type="text" class="form-control" id="sws-item-search" name="search" value="{{ $search ?? '' }}" placeholder="Item name, code, category, or unit">
                                </div>
                                <div class="prs-catalog-field sws-normal-only-filter">
                                    <label for="sws-category-filter" class="form-label mb-1">Category</label>
                                    <select class="form-select" id="sws-category-filter" name="category">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($selectedCategory ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="prs-catalog-field sws-normal-only-filter">
                                    <label for="sws-stock-filter" class="form-label mb-1">Stock</label>
                                    <select class="form-select" id="sws-stock-filter" name="stock">
                                        <option value="">All stock</option>
                                        <option value="in_stock" @selected(($selectedStockFilter ?? '') === 'in_stock')>In stock</option>
                                        <option value="zero_stock" @selected(($selectedStockFilter ?? '') === 'zero_stock')>Zero stock</option>
                                    </select>
                                </div>
                                <div class="prs-catalog-layout-field">
                                    <label class="form-label mb-1">Layout</label>
                                    <div class="btn-group prs-layout-toggle w-100" role="group" aria-label="Catalog layout">
                                        <button type="button" class="btn btn-light-secondary active" data-layout="grid" aria-pressed="true" title="Grid view">
                                            <i class="fa-light fa-grid-2"></i>
                                            <span class="d-none d-xl-inline ms-1">Grid</span>
                                        </button>
                                        <button type="button" class="btn btn-light-secondary" data-layout="list" aria-pressed="false" title="List view">
                                            <i class="fa-light fa-list"></i>
                                            <span class="d-none d-xl-inline ms-1">List</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="prs-catalog-reset-field">
                                    <button type="button" class="btn btn-light-secondary w-100" id="sws-reset-filter">Reset</button>
                                </div>
                            </div>
                            <div class="prs-item-grid" id="sws-item-grid" data-layout="grid">
                                @if ($initialMode === 'normal')
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
                                                    <input type="number" min="0.00001" step="0.00001" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
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
                                @else
                                    <div class="prs-catalog-empty-state">
                                        <i class="fa-duotone fa-solid fa-building-columns prs-catalog-empty-icon"></i>
                                        <p class="mb-0 mt-2 fw-semibold">Loading CAPEX RR lines…</p>
                                        <small>Available balance excludes this SWS so existing cart lines remain editable.</small>
                                    </div>
                                @endif
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
                        <small class="text-muted">Update withdrawal items for {{ $storeWithdrawal->sws_number }}</small>
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
                                <div class="col-12" id="sws-cart-department-field">
                                    <label for="sws-department" class="form-label">Charged to Department</label>
                                    <select class="form-select" id="sws-department" name="department_id" required @disabled($initialMode === 'capex')>
                                        <option value="" disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected((string) ($selectedDepartmentId ?? '') === (string) $department->id)>
                                                {{ $department->code }} - {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($initialMode === 'capex')
                                        <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                                    @endif
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="sws-date" class="form-label">SWS Date</label>
                                    <input type="date" id="sws-date" class="form-control" name="sws_date" value="{{ \Carbon\Carbon::parse($storeWithdrawal->sws_date)->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-12 col-md-6" id="sws-type-field">
                                    <label for="sws-type" class="form-label">Type</label>
                                    <select class="form-select" id="sws-type" name="type" required @disabled($initialMode === 'capex')>
                                        <option value="NORMAL" @selected($resolvedType === 'NORMAL')>Normal</option>
                                        <option value="CONFIRMATORY" @selected($resolvedType === 'CONFIRMATORY')>Confirmatory</option>
                                        <option value="CAPEX" @selected($resolvedType === 'CAPEX')>CAPEX</option>
                                    </select>
                                    @if ($initialMode === 'capex')
                                        <input type="hidden" name="type" value="CAPEX">
                                    @endif
                                </div>
                                <div class="col-12">
                                    <label for="sws-info" class="form-label">Info / Remarks</label>
                                    <textarea class="form-control" id="sws-info" name="info" rows="2" placeholder="Add notes if needed">{{ $storeWithdrawal->info }}</textarea>
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
                    <button type="submit" class="btn btn-primary w-100 icon icon-left">
                        <i class="fa-thin fa-floppy-disk me-1"></i>
                        Update Stores Withdrawal
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
    <script src="{{ url('assets/scripts/modules/catalog-layout.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/stores-withdrawals-create.js') }}"></script>
@endpush
