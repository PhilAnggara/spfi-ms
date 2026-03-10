@extends('layouts.app')
@section('title', ' | Receiving Reports')

@section('content')
<div class="page-heading prs-page" id="rr-page" data-po-lookup-url="{{ route('receiving-reports.po-by-number') }}">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="prs-hero">
                    <h3 class="mb-1">Receiving Reports</h3>
                    <p class="text-muted mb-0">Track all incoming goods, review PO details, and manage receiving reports in one place.</p>
                </div>
            </div>
            @role('administrator|im-manager|im-supervisor|im-staff')
                <div class="col-12 col-lg-5 text-lg-end">
                    <button type="button" class="btn btn-success icon icon-left" data-bs-toggle="modal" data-bs-target="#create-rr-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Create RR
                    </button>
                </div>
            @endrole
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="card shadow-sm mb-0"><div class="card-body"><div class="text-muted small">Total RR</div><div class="fs-4 fw-bold">{{ number_format($totalRr) }}</div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm mb-0"><div class="card-body"><div class="text-muted small">RR Today</div><div class="fs-4 fw-bold">{{ number_format($todayRr) }}</div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm mb-0"><div class="card-body"><div class="text-muted small">Qty Good</div><div class="fs-4 fw-bold text-success">{{ number_format($totalGood, 2) }}</div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm mb-0"><div class="card-body"><div class="text-muted small">Qty Bad</div><div class="fs-4 fw-bold text-danger">{{ number_format($totalBad, 2) }}</div></div></div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('receiving-reports.index') }}" id="rr-filter-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label for="filter-rr-keyword" class="form-label mb-1">Search RR</label>
                            <input type="text" id="filter-rr-keyword" name="keyword" class="form-control" placeholder="RR Number / PO Number / Supplier / Creator" value="{{ request('keyword') }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="filter-rr-date-start" class="form-label mb-1">Received Date (from)</label>
                            <input type="date" id="filter-rr-date-start" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label for="filter-rr-date-end" class="form-label mb-1">Received Date (to)</label>
                            <input type="date" id="filter-rr-date-end" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-6 col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                        <div class="col-6 col-md-1 d-grid">
                            <a href="{{ route('receiving-reports.index') }}" class="btn btn-light-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">RR Records</h5>
                    <span class="badge bg-light-primary" id="rr-filter-result">{{ number_format($receivingReports->total()) }} records</span>
                </div>

                @if ($receivingReports->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fa-duotone fa-solid fa-inbox"></i>
                        <p class="mb-0 mt-2">No receiving report found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped text-center text-nowrap" id="rr-table">
                            <thead>
                                <tr>
                                    <th>RR Number</th>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th>Received Date</th>
                                    <th>Items</th>
                                    <th>Qty Good</th>
                                    <th>Qty Bad</th>
                                    <th>Created By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="rr-table-body">
                                @foreach ($receivingReports as $rr)
                                    @php
                                        $qtyGood = (float) $rr->items->sum('qty_good');
                                        $qtyBad = (float) $rr->items->sum('qty_bad');
                                        $po = $rr->purchaseOrder;
                                    @endphp
                                    <tr>
                                        <td>{{ $rr->rr_number ?? ('#' . $rr->id) }}</td>
                                        <td>
                                            @if ($po)
                                                <button type="button" class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#po-detail-modal-{{ $po->id }}">
                                                    <i class="fa-solid fa-file-lines"></i>
                                                    {{ $po->po_number ?? ('PO#' . $po->id) }}
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $po?->supplier?->name ?? '-' }}</td>
                                        <td>{{ optional($rr->received_date)->format('d M Y') }}</td>
                                        <td>{{ itemOrItems($rr->items->count()) }}</td>
                                        <td>{{ number_format($qtyGood, 2) }}</td>
                                        <td>{{ number_format($qtyBad, 2) }}</td>
                                        <td>{{ $rr->createdBy?->name ?? '-' }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#rr-view-modal-{{ $rr->id }}" title="View">
                                                    <i class="fa-light fa-eye text-primary"></i>
                                                </button>

                                                @role('administrator|im-manager|im-supervisor|im-staff')
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#rr-edit-modal-{{ $rr->id }}" title="Edit">
                                                        <i class="fa-light fa-edit text-primary"></i>
                                                    </button>
                                                    <button type="button" class="btn icon" onclick="confirmDeleteRr({{ $rr->id }}, '{{ $rr->rr_number ?? ('#' . $rr->id) }}')" title="Delete">
                                                        <i class="fa-light fa-trash text-secondary"></i>
                                                    </button>
                                                    <form action="{{ route('receiving-reports.destroy', $rr) }}" id="hapus-rr-{{ $rr->id }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                @endrole
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $receivingReports->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    @role('administrator|im-manager|im-supervisor|im-staff')
        <div class="modal fade" id="create-rr-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="{{ route('receiving-reports.store') }}" id="create-rr-form">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Create Receiving Report</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="purchase_order_id" id="create_purchase_order_id">

                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label">RR Number <span class="text-danger">*</span></label>
                                    <input type="text" name="rr_number" class="form-control" placeholder="e.g. RR-001" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label">PO Number <span class="text-danger">*</span></label>
                                    <input type="text" id="create_po_number" class="form-control" placeholder="e.g. PO-2026-001">
                                </div>
                                <div class="col-12 col-md-2 d-grid">
                                    <button type="button" id="create-load-po" class="btn btn-outline-primary">Load PO</button>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label">Received Date <span class="text-danger">*</span></label>
                                    <input type="date" name="received_date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label">Notes</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Optional">
                                </div>
                            </div>

                            <div class="rr-modern-block mt-3" id="create-customs-section">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <h6 class="mb-1">Customs Document Setup</h6>
                                        <p class="text-muted small mb-0">Choose whether this receiving report requires customs document details.</p>
                                    </div>
                                </div>

                                <div class="rr-customs-toggle" role="radiogroup" aria-label="Customs document requirement">
                                    <input type="radio" class="btn-check create-customs-choice" name="requires_customs_document" id="create-customs-no" value="0" checked>
                                    <label class="btn btn-outline-secondary" for="create-customs-no">
                                        <i class="fa-regular fa-circle-xmark me-1"></i>
                                        No Customs Document
                                    </label>

                                    <input type="radio" class="btn-check create-customs-choice" name="requires_customs_document" id="create-customs-yes" value="1">
                                    <label class="btn btn-outline-primary" for="create-customs-yes">
                                        <i class="fa-regular fa-file-lines me-1"></i>
                                        Requires Customs Document
                                    </label>
                                </div>

                                <div class="d-none mt-3" id="create-customs-fields">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Number <span class="text-danger">*</span></label>
                                            <input type="text" name="customs_document_number" id="create_customs_document_number" class="form-control" placeholder="e.g. BC-123456">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Type <span class="text-danger">*</span></label>
                                            <select name="customs_document_type_id" id="create_customs_document_type_id" class="form-select">
                                                <option value="">Select type</option>
                                                @foreach ($customsDocumentTypes as $customsDocumentType)
                                                    <option value="{{ $customsDocumentType->id }}">{{ $customsDocumentType->code ? ' (' . $customsDocumentType->code . ') ' : '' }}{{ $customsDocumentType->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Date <span class="text-danger">*</span></label>
                                            <input type="date" name="customs_document_date" id="create_customs_document_date" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="create-po-error" class="alert alert-danger mt-3 d-none"></div>

                            <div id="create-po-details" class="mt-4 d-none">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div class="small text-muted">Select received items, then enter good and bad quantities.</div>
                                    <div class="d-flex gap-2">
                                        <button type="button" id="create-select-all" class="btn btn-sm btn-outline-success">Select All</button>
                                        <button type="button" id="create-clear-all" class="btn btn-sm btn-outline-secondary">Clear</button>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-4"><div class="border rounded p-2"><small class="text-muted">PO Number</small><div class="fw-semibold" id="create-po-detail-number">-</div></div></div>
                                    <div class="col-12 col-md-4"><div class="border rounded p-2"><small class="text-muted">Supplier</small><div class="fw-semibold" id="create-po-detail-supplier">-</div></div></div>
                                    <div class="col-12 col-md-4"><div class="border rounded p-2"><small class="text-muted">PO Date</small><div class="fw-semibold" id="create-po-detail-date">-</div></div></div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped align-middle text-nowrap" id="create-po-items-table">
                                        <thead>
                                            <tr>
                                                <th>Pick</th>
                                                <th>Item</th>
                                                <th>Code</th>
                                                <th>Unit</th>
                                                <th class="text-end">Qty PO</th>
                                                <th class="text-end">Qty Received</th>
                                                <th class="text-end">Qty Remaining</th>
                                                <th class="text-end">Qty Good</th>
                                                <th class="text-end">Qty Bad</th>
                                            </tr>
                                        </thead>
                                        <tbody id="create-po-items-body"></tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end gap-3 mt-2 small">
                                    <div>Selected: <span class="fw-semibold" id="create-summary-items">0</span></div>
                                    <div>Good: <span class="fw-semibold text-success" id="create-summary-good">0.00</span></div>
                                    <div>Bad: <span class="fw-semibold text-danger" id="create-summary-bad">0.00</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="create-save-btn" class="btn btn-primary" disabled>Save RR</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endrole

    @php $renderedPoModal = []; @endphp
    @foreach ($receivingReports as $rr)
        @php
            $po = $rr->purchaseOrder;
            $rrGood = (float) $rr->items->sum('qty_good');
            $rrBad = (float) $rr->items->sum('qty_bad');
            $customsDocType = $rr->customsDocumentType;
        @endphp

        <div class="modal fade" id="rr-view-modal-{{ $rr->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">RR Detail - {{ $rr->rr_number ?? ('#' . $rr->id) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">PO Number</small><div class="fw-semibold">{{ $po?->po_number ?? '-' }}</div></div></div>
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Supplier</small><div class="fw-semibold">{{ $po?->supplier?->name ?? '-' }}</div></div></div>
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Received Date</small><div class="fw-semibold">{{ optional($rr->received_date)->format('d M Y') }}</div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Need Customs Document</small><div class="fw-semibold">{{ $rr->requires_customs_document ? 'Yes' : 'No' }}</div></div></div>
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Customs Document Number</small><div class="fw-semibold">{{ $rr->customs_document_number ?: '-' }}</div></div></div>
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Customs Document Type</small><div class="fw-semibold">{{ $customsDocType ? ($customsDocType->code . ' - ' . $customsDocType->name) : '-' }}</div></div></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Customs Document Date</small><div class="fw-semibold">{{ optional($rr->customs_document_date)->format('d M Y') ?: '-' }}</div></div></div>
                        </div>
                        <div class="mb-3"><small class="text-muted">Notes</small><div class="fw-semibold">{{ $rr->notes ?: '-' }}</div></div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Code</th>
                                        <th>Unit</th>
                                        <th class="text-end">Qty Good</th>
                                        <th class="text-end">Qty Bad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rr->items as $rrItem)
                                        <tr>
                                            <td>{{ $rrItem->purchaseOrderItem?->item?->name ?? '-' }}</td>
                                            <td>{{ $rrItem->purchaseOrderItem?->item?->code ?? '-' }}</td>
                                            <td>{{ $rrItem->purchaseOrderItem?->item?->unit?->name ?? 'PCS' }}</td>
                                            <td class="text-end">{{ number_format((float) $rrItem->qty_good, 2) }}</td>
                                            <td class="text-end">{{ number_format((float) $rrItem->qty_bad, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end gap-3 small">
                            <div>Total Good: <span class="fw-semibold text-success">{{ number_format($rrGood, 2) }}</span></div>
                            <div>Total Bad: <span class="fw-semibold text-danger">{{ number_format($rrBad, 2) }}</span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('receiving-reports.print', $rr) }}" target="_blank" rel="noopener" class="btn btn-outline-danger">
                            <i class="fa-duotone fa-solid fa-file-pdf"></i>
                            Print PDF
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        @role('administrator|im-manager|im-supervisor|im-staff')
            <div class="modal fade" id="rr-edit-modal-{{ $rr->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="post" action="{{ route('receiving-reports.update', $rr) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit RR - {{ $rr->rr_number ?? ('#' . $rr->id) }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3"><label class="form-label">RR Number</label><input type="text" class="form-control" value="{{ $rr->rr_number ?? '-' }}" disabled></div>
                                    <div class="col-md-3"><label class="form-label">PO Number</label><input type="text" class="form-control" value="{{ $po?->po_number ?? '-' }}" disabled></div>
                                    <div class="col-md-3"><label class="form-label">Supplier</label><input type="text" class="form-control" value="{{ $po?->supplier?->name ?? '-' }}" disabled></div>
                                    <div class="col-md-3"><label class="form-label">Received Date</label><input type="date" name="received_date" class="form-control" value="{{ optional($rr->received_date)->format('Y-m-d') }}" required></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" name="notes" class="form-control" value="{{ $rr->notes }}">
                                </div>

                                <div class="rr-modern-block mb-3 rr-edit-customs-section" data-rr-id="{{ $rr->id }}">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                        <div>
                                            <h6 class="mb-1">Customs Document Setup</h6>
                                            <p class="text-muted small mb-0">Choose whether this receiving report requires customs document details.</p>
                                        </div>
                                    </div>

                                    <div class="rr-customs-toggle rr-edit-customs-toggle" data-target="#edit-customs-fields-{{ $rr->id }}" role="radiogroup" aria-label="Customs document requirement">
                                        <input type="radio" class="btn-check rr-edit-customs-choice" name="requires_customs_document" id="edit-customs-no-{{ $rr->id }}" value="0" @checked(! $rr->requires_customs_document)>
                                        <label class="btn btn-outline-secondary" for="edit-customs-no-{{ $rr->id }}">
                                            <i class="fa-regular fa-circle-xmark me-1"></i>
                                            No Customs Document
                                        </label>

                                        <input type="radio" class="btn-check rr-edit-customs-choice" name="requires_customs_document" id="edit-customs-yes-{{ $rr->id }}" value="1" @checked($rr->requires_customs_document)>
                                        <label class="btn btn-outline-primary" for="edit-customs-yes-{{ $rr->id }}">
                                            <i class="fa-regular fa-file-lines me-1"></i>
                                            Requires Customs Document
                                        </label>
                                    </div>

                                    <div class="row g-3 mt-1 {{ $rr->requires_customs_document ? '' : 'd-none' }}" id="edit-customs-fields-{{ $rr->id }}">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Number <span class="text-danger">*</span></label>
                                            <input type="text" name="customs_document_number" class="form-control rr-edit-customs-number" value="{{ $rr->customs_document_number }}">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Type <span class="text-danger">*</span></label>
                                            <select name="customs_document_type_id" class="form-select rr-edit-customs-type">
                                                <option value="">Select type</option>
                                                @foreach ($customsDocumentTypes as $customsDocumentType)
                                                    <option value="{{ $customsDocumentType->id }}" @selected((int) $rr->customs_document_type_id === (int) $customsDocumentType->id)>{{ $customsDocumentType->code ? ' (' . $customsDocumentType->code . ') ' : '' }}{{ $customsDocumentType->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Customs Document Date <span class="text-danger">*</span></label>
                                            <input type="date" name="customs_document_date" class="form-control rr-edit-customs-date" value="{{ optional($rr->customs_document_date)->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped align-middle text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Code</th>
                                                <th>Unit</th>
                                                <th class="text-end">Qty Good</th>
                                                <th class="text-end">Qty Bad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rr->items as $idx => $rrItem)
                                                <tr>
                                                    <td>
                                                        {{ $rrItem->purchaseOrderItem?->item?->name ?? '-' }}
                                                        <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $rrItem->purchase_order_item_id }}">
                                                        <input type="hidden" name="items[{{ $idx }}][selected]" value="1">
                                                    </td>
                                                    <td>{{ $rrItem->purchaseOrderItem?->item?->code ?? '-' }}</td>
                                                    <td>{{ $rrItem->purchaseOrderItem?->item?->unit?->name ?? 'PCS' }}</td>
                                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="items[{{ $idx }}][qty_good]" value="{{ (float) $rrItem->qty_good }}" required></td>
                                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="items[{{ $idx }}][qty_bad]" value="{{ (float) $rrItem->qty_bad }}" required></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endrole

        @if ($po && ! in_array($po->id, $renderedPoModal, true))
            @php $renderedPoModal[] = $po->id; @endphp
            <div class="modal fade" id="po-detail-modal-{{ $po->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">PO Detail - {{ $po->po_number ?? ('PO#' . $po->id) }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Supplier</small><div class="fw-semibold">{{ $po->supplier?->name ?? '-' }}</div></div></div>
                                <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">Status</small><div class="fw-semibold">{{ $po->status ?? '-' }}</div></div></div>
                                <div class="col-md-4"><div class="border rounded p-2"><small class="text-muted">PO Date</small><div class="fw-semibold">{{ optional($po->created_at)->format('d M Y') }}</div></div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped align-middle text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Code</th>
                                            <th>Unit</th>
                                            <th class="text-end">Qty Ordered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($po->items as $poItem)
                                            <tr>
                                                <td>{{ $poItem->item?->name ?? '-' }}</td>
                                                <td>{{ $poItem->item?->code ?? '-' }}</td>
                                                <td>{{ $poItem->item?->unit?->name ?? 'PCS' }}</td>
                                                <td class="text-end">{{ number_format((float) $poItem->quantity, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/rr-modern.js') }}"></script>
@endpush
