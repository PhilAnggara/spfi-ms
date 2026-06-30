@extends('layouts.app')
@section('title', ' | Edit PRS')

@section('content')
<div class="page-heading prs-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <h3 class="mb-1">Edit Purchase Requisition Slip</h3>
                <p class="text-muted mb-0">{{ $prs->prs_number }} — search items, update the cart, then save your changes.</p>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('prs.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                    <button type="button" class="btn btn-outline-primary icon icon-left" id="toggle-prs-cart">
                        <i class="fa-regular fa-cart-shopping"></i>
                        Cart
                        <span class="prs-cart-badge" id="prs-cart-count">{{ $prs->items->count() }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <form action="{{ route('prs.update', $prs) }}" method="POST" class="prs-create-form" id="prs-edit-form">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-12">
                    @if ($holdLog)
                        <div class="alert alert-warning mb-3" role="alert">
                            <strong>Hold Reason:</strong> {{ $holdLog->message }}
                        </div>
                    @endif

                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="prs-catalog-toolbar" id="prs-catalog-filter-form" data-base-url="{{ route('prs.edit', $prs) }}">
                                <div class="prs-catalog-field prs-catalog-search-field">
                                    <label for="prs-item-search" class="form-label mb-1">Search Item</label>
                                    <input type="text" class="form-control" id="prs-item-search" name="search" value="{{ $search ?? '' }}" placeholder="Item name, code, category, or unit">
                                </div>
                                <div class="prs-catalog-field">
                                    <label for="prs-category-filter" class="form-label mb-1">Category</label>
                                    <select class="form-select" id="prs-category-filter" name="category">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($selectedCategory ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="prs-catalog-field">
                                    <label for="prs-stock-filter" class="form-label mb-1">Stock</label>
                                    <select class="form-select" id="prs-stock-filter" name="stock">
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
                                    <button type="button" class="btn btn-light-secondary w-100" id="prs-reset-filter">Reset</button>
                                </div>
                            </div>
                            <div class="prs-item-grid" id="prs-item-grid" data-layout="grid">
                                @forelse ($items as $item)
                                    <div class="prs-item-card" data-name="{{ strtolower($item->name) }}" data-code="{{ strtolower($item->code) }}" data-category="{{ strtolower($item->category?->name ?? '') }}" data-item-id="{{ $item->id }}">
                                        <div class="prs-item-body">
                                            <div class="prs-item-title fon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $item->name }}">{{ $item->name }}</div>
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
                            <div class="mt-4 prs-pagination" id="prs-pagination" data-current-page="{{ $items->currentPage() }}" data-last-page="{{ $items->lastPage() }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary prs-mobile-cart-btn" id="toggle-prs-cart-mobile">
                <i class="fa-regular fa-cart-shopping me-1"></i>
                Cart Items
            </button>

            <aside class="prs-cart-popup is-hidden" id="prs-cart-popup" aria-hidden="true" data-auto-open-cart="1">
                <div class="prs-cart-header">
                    <div>
                        <h5 class="mb-0">Item Cart</h5>
                        <small class="text-muted">Manage your PRS items</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-secondary" id="hide-prs-cart">
                        <i class="fa-light fa-xmark"></i>
                    </button>
                </div>
                <div class="prs-cart-body">
                    <div class="prs-cart-layout">
                        <div class="prs-cart-layout-header">
                            <h6 class="mb-2">PRS Header</h6>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label for="department" class="form-label">Charged to Department</label>
                                    <select class="form-select" id="department" name="department_id" required>
                                        <option value="" disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected((int) old('department_id', $prs->department_id) === (int) $department->id)>
                                                {{ $department->code }} - {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="date-needed" class="form-label">Date Needed</label>
                                    <input type="date" id="date-needed" class="form-control" name="date_needed" value="{{ old('date_needed', $prs->date_needed) }}" required>
                                </div>
                                <div class="col-12">
                                    @php
                                        $selectedCapex = (string) old('is_capex', $prs->is_capex ? '1' : '0');
                                    @endphp
                                    <div class="prs-accounting-choice">
                                        <label class="form-label mb-1">Accounting Category</label>
                                        <div class="prs-accounting-toggle" role="radiogroup" aria-label="Accounting category">
                                            <input type="radio" class="btn-check" name="is_capex" id="is-capex-no" value="0" @checked($selectedCapex === '0') required>
                                            <label class="btn btn-outline-secondary" for="is-capex-no">
                                                <i class="fa-regular fa-circle-check me-1"></i>
                                                Non-CAPEX
                                            </label>

                                            <input type="radio" class="btn-check" name="is_capex" id="is-capex-yes" value="1" @checked($selectedCapex === '1') required>
                                            <label class="btn btn-outline-primary" for="is-capex-yes">
                                                <i class="fa-regular fa-building-columns me-1"></i>
                                                CAPEX
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">Keep one PRS for one accounting treatment.</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Add notes if needed">{{ old('remarks', $prs->remarks) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="prs-cart-layout-items">
                            <div id="prs-cart-component">
                                <livewire:prs-item :existing-items="$prs->items" mode="cart" wire:key="prs-item-edit-{{ $prs->id }}" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="prs-cart-footer">
                    <button type="submit" class="btn btn-primary w-100 icon icon-left">
                        <i class="fa-thin fa-file-pen me-1"></i>
                        Update PRS
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
    <script src="{{ url('assets/scripts/modules/prs-modern.js') }}"></script>
@endpush
