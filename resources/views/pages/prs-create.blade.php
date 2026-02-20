@extends('layouts.app')
@section('title', ' | Create PRS')

@section('content')
<div class="page-heading prs-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <h3 class="mb-1">Create Purchase Requisition Slip</h3>
                <p class="text-muted mb-0">Cari item, atur qty, lalu tambahkan ke cart sebelum submit PRS.</p>
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
                        <span class="prs-cart-badge" id="prs-cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <form action="{{ route('prs.store') }}" method="POST" class="prs-create-form" id="prs-create-form">
            @csrf
            <div class="row g-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="prs-catalog-toolbar" id="prs-catalog-filter-form" data-base-url="{{ route('prs.create') }}">
                                <div class="prs-search">
                                    <i class="fa-regular fa-magnifying-glass"></i>
                                    <input type="text" class="form-control" id="prs-item-search" name="search" value="{{ $search ?? '' }}" placeholder="Cari item berdasarkan nama atau kode">
                                </div>
                                <div class="prs-filter d-flex gap-2 align-items-center">
                                    <select class="form-select" id="prs-category-filter" name="category">
                                        <option value="">Semua kategori</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($selectedCategory ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-light-secondary" id="prs-reset-filter">Reset</button>
                                </div>
                            </div>
                            <div class="prs-item-grid" id="prs-item-grid">
                                @foreach ($items as $item)
                                    <div class="prs-item-card" data-name="{{ strtolower($item->name) }}" data-code="{{ strtolower($item->code) }}" data-category="{{ strtolower($item->category?->name ?? '') }}" data-item-id="{{ $item->id }}">
                                        {{-- <div class="prs-item-thumb" data-category="{{ category_data_attr($item->category?->name) }}">
                                            <div class="prs-item-thumb-icon">
                                                <i class="fa-duotone fa-solid {{ category_icon($item->category?->name) }}"></i>
                                            </div>
                                        </div> --}}
                                        <div class="prs-item-body">
                                            <div class="prs-item-title fon">{{ $item->name }}</div>
                                            <div class="prs-item-meta">
                                                <span class="badge bg-light-primary">{{ $item->code }}</span>
                                                <span class="text-muted">Stock {{ $item->stock_on_hand }} {{ $item->unit?->name ?? 'PCS' }}</span>
                                            </div>
                                            <div class="prs-item-meta text-muted">{{ $item->category?->name ?? 'Uncategorized' }}</div>
                                            <div class="prs-item-actions">
                                                <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Kurangi qty">
                                                    <i class="fa-light fa-minus"></i>
                                                </button>
                                                <input type="number" min="1" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                                                <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Tambah qty">
                                                    <i class="fa-light fa-plus"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="{{ $item->id }}">
                                                    <i class="fa-light fa-plus"></i>
                                                    Add
                                                </button>
                                                <span class="prs-in-cart-label d-none">Sudah di cart</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            {{-- <div class="mt-4 prs-pagination" id="prs-pagination" data-current-page="{{ $items->currentPage() }}" data-last-page="{{ $items->lastPage() }}">
                            </div> --}}
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary prs-mobile-cart-btn" id="toggle-prs-cart-mobile">
                <i class="fa-regular fa-cart-shopping me-1"></i>
                Cart Items
            </button>

            <aside class="prs-cart-popup is-hidden" id="prs-cart-popup" aria-hidden="true">
                <div class="prs-cart-header">
                    <div>
                        <h5 class="mb-0">Item Cart</h5>
                        <small class="text-muted">Kelola item PRS Anda</small>
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
                                            <option value="{{ $department->id }}" {{ auth()->user()->department_id == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="date-needed" class="form-label">Date Needed</label>
                                    <input type="date" id="date-needed" class="form-control" name="date_needed" value="{{ \Carbon\Carbon::now()->addDays(7)->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-12">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Tambahkan catatan bila diperlukan"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="prs-cart-layout-items">
                            <div id="prs-cart-component">
                                <livewire:prs-item mode="cart" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="prs-cart-footer">
                    <button type="submit" class="btn btn-success w-100 icon icon-left">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Submit PRS
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
    <script src="{{ url('assets/js/prs-modern.js') }}"></script>
@endpush
