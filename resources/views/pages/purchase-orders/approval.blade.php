@extends('layouts.app')
@section('title', ' | PO Approval')

@section('content')
<div id="po-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Purchase Order Approval</h3>
                    <p class="text-muted mb-0">Review and process pending purchase orders with instant filtering and clean navigation.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-primary icon icon-left">
                        <i class="fa-duotone fa-solid fa-list-check"></i>
                        Open PO List
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
                            <option value="PENDING_APPROVAL" @selected(request('status') === 'PENDING_APPROVAL')>PENDING_APPROVAL</option>
                            <option value="CHANGES_REQUESTED" @selected(request('status') === 'CHANGES_REQUESTED')>CHANGES_REQUESTED</option>
                            <option value="APPROVED" @selected(request('status') === 'APPROVED')>APPROVED</option>
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

        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="po-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Approval Queue</h5>
                    <span class="badge bg-light-primary">{{ $purchaseOrders->total() }} records</span>
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
                    <button type="button" class="po-status-chip {{ request('status') === 'CHANGES_REQUESTED' ? 'active' : '' }}" data-status-value="CHANGES_REQUESTED">
                        <i class="fa-light fa-arrows-rotate"></i>
                        Changes Requested
                    </button>
                    <button type="button" class="po-status-chip {{ request('status') === 'APPROVED' ? 'active' : '' }}" data-status-value="APPROVED">
                        <i class="fa-light fa-circle-check"></i>
                        Approved
                    </button>
                </div>

                @if ($purchaseOrders->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-inbox po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No purchase orders found in this approval queue.</p>
                        <small>Adjust your filters to explore other approval records.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table text-nowrap">
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
                                        $statusClass = match($po->status) {
                                            'APPROVED' => 'bg-light-success text-success',
                                            'PENDING_APPROVAL' => 'bg-light-warning text-warning',
                                            'CHANGES_REQUESTED' => 'bg-light-danger text-danger',
                                            default => 'bg-light-info text-info',
                                        };

                                        $statusIcon = match($po->status) {
                                            'APPROVED' => 'fa-solid fa-circle-check',
                                            'PENDING_APPROVAL' => 'fa-solid fa-hourglass-half',
                                            'CHANGES_REQUESTED' => 'fa-solid fa-arrows-rotate',
                                            default => 'fa-solid fa-circle-info',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $po->po_number ?: 'PO-' . str_pad((string) $po->id, 5, '0', STR_PAD_LEFT) }}</div>
                                            <small class="text-muted">#{{ $po->id }}</small>
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
                                        <td class="fw-semibold">{{ number_format((float) $po->total, 2) }}</td>
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
                                            <div class="d-flex flex-wrap gap-2 po-approval-actions">
                                                @if ($po->status === 'PENDING_APPROVAL')
                                                    <form method="post" action="{{ route('purchase-orders.approve', $po) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fa-light fa-circle-check me-1"></i>
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#requestChanges-{{ $po->id }}">
                                                        <i class="fa-light fa-arrows-rotate me-1"></i>
                                                        Request Changes
                                                    </button>
                                                @endif
                                                <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fa-light fa-eye me-1"></i>
                                                    Detail
                                                </a>
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
                        <div class="modal fade" id="requestChanges-{{ $po->id }}" tabindex="-1" aria-labelledby="requestChangesLabel-{{ $po->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="post" action="{{ route('purchase-orders.request-changes', $po) }}" class="modal-content">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="requestChangesLabel-{{ $po->id }}">Request Changes</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label" for="message-{{ $po->id }}">Message</label>
                                        <textarea id="message-{{ $po->id }}" name="message" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">Send</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
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
    <script>
        (function () {
            let isLoading = false;

            function setLoading(active) {
                const loadingEl = document.getElementById('po-page-loading');
                if (!loadingEl) {
                    return;
                }

                loadingEl.classList.toggle('d-none', !active);
                loadingEl.classList.toggle('d-flex', active);
            }

            async function replacePageContent(url, pushState = true) {
                if (isLoading) {
                    return;
                }

                isLoading = true;
                setLoading(true);

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.querySelector('#po-page-container');
                    const currentContainer = document.querySelector('#po-page-container');

                    if (!newContainer || !currentContainer) {
                        window.location.href = url;
                        return;
                    }

                    currentContainer.replaceWith(newContainer);

                    if (pushState) {
                        window.history.pushState({}, '', url);
                    }

                    if (typeof initPurchaseOrderFilters === 'function') {
                        initPurchaseOrderFilters();
                    }

                    if (window.feather && typeof window.feather.replace === 'function') {
                        window.feather.replace();
                    }
                } catch (_) {
                    window.location.href = url;
                } finally {
                    isLoading = false;
                    setLoading(false);
                }
            }

            window.poReplacePageContent = replacePageContent;

            document.addEventListener('click', function (event) {
                const link = event.target.closest('#po-page-container a[href*="page="]');
                if (!link) return;

                event.preventDefault();
                replacePageContent(link.href, true);
            });

            window.addEventListener('popstate', function () {
                replacePageContent(window.location.href, false);
            });
        })();
    </script>
@endpush
