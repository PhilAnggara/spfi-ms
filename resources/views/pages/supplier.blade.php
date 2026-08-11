@extends('layouts.app')
@section('title', ' | Supplier')

@section('content')
<div id="supplier-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Supplier</h3>
                    <p class="text-muted mb-0">Browse suppliers with instant search, live filters, and purchase history for canvassing.</p>
                </div>
            </div>
            @if ($canManageSuppliers)
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <button type="button" class="btn btn-success icon icon-left" data-bs-toggle="modal" data-bs-target="#create-modal">
                            <i class="fa-duotone fa-solid fa-plus"></i>
                            Add Supplier
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="supplier-filter-form">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-supplier-keyword" class="form-label mb-1">Search Supplier</label>
                        <input type="text" id="filter-supplier-keyword" class="form-control" value="{{ $filters['keyword'] }}" placeholder="Code / name / address / phone / email / contact">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-supplier-has-po" class="form-label mb-1">PO History</label>
                        <select id="filter-supplier-has-po" class="form-select">
                            <option value="" @selected($filters['has_po'] === '')>All Suppliers</option>
                            <option value="1" @selected($filters['has_po'] === '1')>With PO</option>
                            <option value="0" @selected($filters['has_po'] === '0')>Without PO</option>
                        </select>
                    </div>
                    <div class="col-8 col-md-6 col-xl-4">
                        <label for="filter-supplier-sort" class="form-label mb-1">Sort By</label>
                        <select id="filter-supplier-sort" class="form-select">
                            <option value="name_asc" @selected($filters['sort'] === 'name_asc')>Name A–Z</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>Name Z–A</option>
                            <option value="code_asc" @selected($filters['sort'] === 'code_asc')>Supplier Code A–Z</option>
                            <option value="code_desc" @selected($filters['sort'] === 'code_desc')>Supplier Code Z–A</option>
                            <option value="po_count_asc" @selected($filters['sort'] === 'po_count_asc')>PO Count: Low → High</option>
                            <option value="po_count_desc" @selected($filters['sort'] === 'po_count_desc')>PO Count: High → Low</option>
                            <option value="total_amount_asc" @selected($filters['sort'] === 'total_amount_asc')>Total Amount: Low → High</option>
                            <option value="total_amount_desc" @selected($filters['sort'] === 'total_amount_desc')>Total Amount: High → Low</option>
                            <option value="last_po_date_asc" @selected($filters['sort'] === 'last_po_date_asc')>Last PO Date: Oldest → Newest</option>
                            <option value="last_po_date_desc" @selected($filters['sort'] === 'last_po_date_desc')>Last PO Date: Newest → Oldest</option>
                        </select>
                    </div>
                    <div class="col-4 col-md-3 col-xl-2">
                        <button type="button" id="reset-supplier-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="supplier-page-results">
            <div class="card shadow-sm border-0">
                <div class="card-body position-relative">
                    <div id="supplier-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">Supplier List</h5>
                        <span class="badge bg-light-primary" id="supplier-filter-result">0 records</span>
                    </div>

                    <div class="table-responsive">
                        <table
                            class="table table-striped align-middle po-table text-nowrap w-100"
                            id="supplier-table"
                            data-source="{{ route('supplier.datatables') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-update-route-template="{{ route('supplier.update', '__ID__') }}"
                            data-destroy-route-template="{{ route('supplier.destroy', '__ID__') }}"
                            data-history-route-template="{{ route('supplier.purchase-history', '__ID__') }}"
                            data-po-show-route-template="{{ route('purchase-orders.show', '__ID__') }}"
                            data-can-manage="{{ $canManageSuppliers ? '1' : '0' }}"
                            data-can-delete="{{ $canDeleteSuppliers ? '1' : '0' }}"
                            data-can-view-po="{{ $canViewPurchaseOrders ? '1' : '0' }}"
                            data-open-create-modal="{{ $errors->any() && !session('editing_supplier_id') ? '1' : '0' }}"
                            data-editing-supplier-id="{{ (string) session('editing_supplier_id', '') }}">
                            <thead>
                                <tr>
                                    <th class="d-none">ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th class="text-end">PO Count</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Last PO Date</th>
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

@if ($canManageSuppliers)
    @include('includes.modals.supplier-modal')
@endif
@include('includes.modals.supplier-purchase-history-modal')
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/master-code-validation.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/supplier-index.js') }}"></script>
@endpush
