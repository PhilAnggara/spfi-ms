@extends('layouts.app')
@section('title', ' | PO List')

@section('content')
<div id="po-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Purchase Order</h3>
                    <p class="text-muted mb-0">Track PO status quickly with instant search, dynamic filters, and real-time pagination.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <a href="{{ route('purchase-orders.draft') }}" class="btn btn-success icon icon-left">
                        <i class="fa-duotone fa-solid fa-bag-shopping-plus"></i>
                        Create PO
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="po-filter-form">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-po-keyword" class="form-label mb-1">Search PO</label>
                        <input type="text" id="filter-po-keyword" class="form-control" value="{{ request('keyword') }}" placeholder="PO number / supplier / requester / PO ID">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3">
                        <label for="filter-po-status" class="form-label mb-1">Status</label>
                        <select id="filter-po-status" class="form-select">
                            <option value="" @selected(request('status') === null || request('status') === '')>All Status</option>
                            <option value="DRAFT" @selected(request('status') === 'DRAFT')>DRAFT</option>
                            <option value="PENDING_APPROVAL" @selected(request('status') === 'PENDING_APPROVAL')>PENDING_APPROVAL</option>
                            <option value="APPROVED" @selected(request('status') === 'APPROVED')>APPROVED</option>
                            <option value="CHANGES_REQUESTED" @selected(request('status') === 'CHANGES_REQUESTED')>CHANGES_REQUESTED</option>
                            <option value="REJECTED" @selected(request('status') === 'REJECTED')>REJECTED</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-po-created-start" class="form-label mb-1">Created (from)</label>
                        <input type="date" id="filter-po-created-start" class="form-control" value="{{ request('created_start') }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-po-created-end" class="form-label mb-1">Created (to)</label>
                        <input type="date" id="filter-po-created-end" class="form-control" value="{{ request('created_end') }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-po-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="po-page-results">
        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="po-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">PO Data</h5>
                    <span class="badge bg-light-primary" id="po-filter-result">{{ $purchaseOrders->total() }} records</span>
                </div>

                <div class="po-status-chip-group mb-3">
                    <button type="button" class="po-status-chip {{ request('status') === '' || request('status') === null ? 'active' : '' }}" data-status-value="">
                        <i class="fa-light fa-layer-group"></i>
                        All
                    </button>
                    <button type="button" class="po-status-chip {{ request('status') === 'PENDING_APPROVAL' ? 'active' : '' }}" data-status-value="PENDING_APPROVAL">
                        <i class="fa-light fa-hourglass-half"></i>
                        Pending Approval
                    </button>
                    <button type="button" class="po-status-chip {{ request('status') === 'APPROVED' ? 'active' : '' }}" data-status-value="APPROVED">
                        <i class="fa-light fa-circle-check"></i>
                        Approved
                    </button>
                    <button type="button" class="po-status-chip {{ request('status') === 'CHANGES_REQUESTED' ? 'active' : '' }}" data-status-value="CHANGES_REQUESTED">
                        <i class="fa-light fa-arrows-rotate"></i>
                        Changes Requested
                    </button>
                    <button type="button" class="po-status-chip {{ request('status') === 'DRAFT' ? 'active' : '' }}" data-status-value="DRAFT">
                        <i class="fa-light fa-file-pen"></i>
                        Draft
                    </button>
                </div>

                @if ($purchaseOrders->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No purchase orders found.</p>
                        <small>Try changing your keyword or filters to see more results.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table text-nowrap" id="po-table">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th class="d-none d-lg-table-cell">Requester</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th class="d-none d-md-table-cell">Created At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseOrders as $po)
                                    @php
                                        $poNumber = trim((string) ($po->po_number ?? ''));
                                        $statusClass = match($po->status) {
                                            'APPROVED' => 'bg-light-success text-success',
                                            'PENDING_APPROVAL' => 'bg-light-warning text-warning',
                                            'CHANGES_REQUESTED' => 'bg-light-danger text-danger',
                                            'DRAFT' => 'bg-light-secondary text-secondary',
                                            default => 'bg-light-info text-info',
                                        };

                                        $statusIcon = match($po->status) {
                                            'APPROVED' => 'fa-solid fa-circle-check',
                                            'PENDING_APPROVAL' => 'fa-solid fa-hourglass-half',
                                            'CHANGES_REQUESTED' => 'fa-solid fa-arrows-rotate',
                                            'DRAFT' => 'fa-solid fa-file-pen',
                                            default => 'fa-solid fa-circle-info',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($poNumber !== '')
                                                <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $poNumber }}')">
                                                    <i class="fa-solid fa-regular fa-clipboard"></i>
                                                    {{ $poNumber }}
                                                </button>
                                            @else
                                                <span class="badge bg-light-secondary">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="po-cell-icon text-primary"><i class="fa-duotone fa-solid fa-truck-field"></i></span>
                                                <span>{{ $po->supplier?->name ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="d-none d-lg-table-cell">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="po-cell-icon text-info"><i class="fa-duotone fa-solid fa-user"></i></span>
                                                <span>{{ $po->createdBy?->name ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td>{{ itemOrItems($po->items_count) }}</td>
                                        <td class="fw-semibold">{{ format_po_decimal($po->total) }}</td>
                                        <td class="d-none d-md-table-cell">
                                            <i class="fa-duotone fa-solid fa-calendar-days text-danger"></i>
                                            {{ tgl($po->created_at) }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusClass }}">
                                                <i class="{{ $statusIcon }}"></i>
                                                {{ $po->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#poDetail-{{ $po->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                                    <i class="fa-light fa-eye"></i>
                                                </button>
                                                @if ($po->status === 'APPROVED')
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poPrintConfirm-{{ $po->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Print">
                                                        <i class="fa-light fa-print"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-primary disabled" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Print" disabled>
                                                        <i class="fa-light fa-print"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $purchaseOrders->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>

                    @foreach ($purchaseOrders as $po)
                        @php
                            $authUser = auth()->user();
                            $canEdit = in_array($po->status, ['DRAFT', 'CHANGES_REQUESTED'], true);
                            $canCancel = $authUser
                                && $po->status === 'APPROVED'
                                && (int) ($po->receiving_reports_count ?? 0) === 0
                                && (
                                    $authUser->hasAnyRole(['administrator', 'purchasing-manager'])
                                    || (int) $po->created_by === (int) $authUser->id
                                );
                        @endphp
                        <div class="modal fade" id="poDetail-{{ $po->id }}" tabindex="-1" aria-labelledby="poDetailLabel-{{ $po->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                <div class="modal-content po-detail-modal">
                                    <div class="modal-header po-detail-modal-header">
                                        <div>
                                            <h5 class="modal-title" id="poDetailLabel-{{ $po->id }}">
                                                {{ $po->po_number ?: 'PO-' . str_pad((string) $po->id, 5, '0', STR_PAD_LEFT) }}
                                            </h5>
                                            <small class="text-muted">Purchase Order Detail</small>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        @include('pages.purchase-orders.partials.detail-body', [
                                            'purchaseOrder' => $po,
                                            'showHeaderMeta' => true,
                                        ])
                                    </div>
                                    <div class="modal-footer po-detail-modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-outline-primary">
                                            <i class="fa-light fa-arrow-up-right-from-square me-1"></i>
                                            Open
                                        </a>
                                        @if ($canEdit)
                                            <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-primary">
                                                <i class="fa-light fa-pen-to-square me-1"></i>
                                                Edit
                                            </a>
                                        @endif
                                        @if ($canCancel)
                                            <form id="cancel-po-{{ $po->id }}" method="post" action="{{ route('purchase-orders.cancel', $po) }}" class="d-inline">
                                                @csrf
                                            </form>
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmCancelPo('cancel-po-{{ $po->id }}')">
                                                <i class="fa-duotone fa-solid fa-ban me-1"></i>
                                                Cancel PO
                                            </button>
                                        @endif
                                        @if ($po->status === 'APPROVED')
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poPrintConfirm-{{ $po->id }}">
                                                <i class="fa-light fa-print me-1"></i>
                                                Print
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($po->status === 'APPROVED')
                            @include('pages.purchase-orders.partials.print-confirm-modal', [
                                'purchaseOrder' => $po,
                                'nextPoNumber' => $nextPoNumber ?? '',
                            ])
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
        </div>
    </section>
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/purchase-orders-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/purchase-orders-index.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/document-print-confirm.js') }}"></script>
@endpush
