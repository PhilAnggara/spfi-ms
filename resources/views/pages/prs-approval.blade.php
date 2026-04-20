@extends('layouts.app')
@section('title', ' | Canvasser Assignment')

@section('content')
<div id="prs-approval-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Canvasser Assignment</h3>
                    <p class="text-muted mb-0">Assign canvassers faster with instant search, status filters, and dynamic pagination.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end po-filter-grid" id="prs-approval-filter-form">
                <div class="col-12 col-md-6 col-xl-5">
                    <label for="filter-prs-approval-keyword" class="form-label mb-1">Search PRS</label>
                    <input type="text" id="filter-prs-approval-keyword" class="form-control" placeholder="PRS number / department / requester / remarks" value="{{ $filters['keyword'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-prs-approval-status" class="form-label mb-1">Status</label>
                    <select id="filter-prs-approval-status" class="form-select">
                        <option value="">All Status</option>
                        @foreach ($statusOptions as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(($filters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-prs-approval-date-start" class="form-label mb-1">PRS Date (from)</label>
                    <input type="date" id="filter-prs-approval-date-start" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-prs-approval-date-end" class="form-label mb-1">PRS Date (to)</label>
                    <input type="date" id="filter-prs-approval-date-end" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                    <button type="button" id="reset-prs-approval-filter" class="btn btn-light-secondary w-100">
                        <i class="fa-regular fa-rotate-left me-1"></i>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="prs-approval-page-results">
    <section class="section">
        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="prs-approval-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Canvasser Assignment Data</h5>
                    <span class="badge bg-light-primary" id="prs-approval-filter-result">{{ number_format($items->total()) }} records</span>
                </div>

                @if ($items->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No PRS found.</p>
                        <small>Try changing your keyword, status, or date filters to see more results.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped text-center text-nowrap" id="prs-approval-table">
                            <thead>
                                <tr>
                                    <th class="text-center">PRS Number</th>
                                    <th class="text-center">Charged to Department</th>
                                    <th class="text-center">PRS Date</th>
                                    <th class="text-center">Remarks</th>
                                    <th class="text-center">Details</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>
                                            <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $item->prs_number }}')">
                                                <i class="fa-solid fa-regular fa-clipboard"></i>
                                                {{ $item->prs_number }}
                                            </button>
                                        </td>
                                        <td>{{ $item->department->name }}</td>
                                        <td><i class="fa-duotone fa-solid fa-calendar-days text-danger"></i> {{ tgl($item->prs_date) }}</td>
                                        <td>{{ Str::limit($item->remarks, 20, '...') ?? '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm icon icon-left" data-bs-toggle="modal" data-bs-target="#detail-modal-{{ $item->id }}">
                                                <i class="fa-light fa-eye text-primary"></i>
                                                View Details
                                            </button>
                                        </td>
                                        <td>
                                            @if ($item->status === 'SUBMITTED' || $item->status === 'RESUBMITTED')
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#approve-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Process" @disabled($item->status === 'DRAFT')>
                                                        <i class="fa-duotone fa-solid fa-circle-check text-success"></i>
                                                    </button>
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#hold-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Hold" @disabled($item->status === 'DRAFT' || $item->status === 'APPROVED')>
                                                        <i class="fa-duotone fa-solid fa-circle-pause text-warning"></i>
                                                    </button>
                                                </div>
                                            @else
                                                <span class="badge {{ status_badge_color($item->status) }}">
                                                    <i class="{{ status_badge_icon($item->status) }}"></i>
                                                    {{ $item->status }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $items->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </section>

@foreach ($items as $item)
    <div class="modal fade text-left modal-borderless" id="approve-modal-{{ $item->id }}" tabindex="-1"
        role="dialog" aria-labelledby="approveModalLabel-{{ $item->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel-{{ $item->id }}">Assign Canvasser</h5>
                    <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form action="{{ route('prs.approve', $item->id) }}" method="post" class="form">
                    @csrf
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Assign Canvasser</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item->items as $index => $itemInfo)
                                        <tr>
                                            <td>{{ $itemInfo->item->code }} - {{ $itemInfo->item->name }}</td>
                                            <td>{{ $itemInfo->quantity }} {{ $itemInfo->item->unit?->name ?? 'PCS' }}</td>
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $itemInfo->id }}">
                                                <select name="items[{{ $index }}][canvasser_id]" class="form-select" required>
                                                    <option value="" disabled {{ $itemInfo->canvasser_id ? '' : 'selected' }}>-- Select Canvasser --</option>
                                                    @foreach ($canvassers as $canvasser)
                                                        <option value="{{ $canvasser->id }}" @selected($itemInfo->canvasser_id == $canvasser->id)>{{ $canvasser->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                            <i class="fa-thin fa-xmark"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn icon icon-left btn-success ms-1">
                            <i class="fa-thin fa-check me-1"></i>
                            Process
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade text-left modal-borderless" id="hold-modal-{{ $item->id }}" tabindex="-1"
        role="dialog" aria-labelledby="holdModalLabel-{{ $item->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holdModalLabel-{{ $item->id }}">Hold PRS</h5>
                    <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <form action="{{ route('prs.hold', $item->id) }}" method="post" class="form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-floating">
                            <textarea class="form-control" placeholder="Reason" id="hold-message-{{ $item->id }}" name="message" required></textarea>
                            <label for="hold-message-{{ $item->id }}">Reason for hold</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                            <i class="fa-thin fa-xmark"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn icon icon-left btn-warning ms-1">
                            <i class="fa-thin fa-pause me-1"></i>
                            Hold
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade text-left modal-borderless" id="detail-modal-{{ $item->id }}" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail PRS - ({{ $item->prs_number }})</h5>
                    <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                        aria-label="Close">
                        <i data-feather="x"></i>
                    </button>
                </div>
                <div class="modal-body">

                    @php
                        $holdLog = $item->logs?->firstWhere('action', 'HOLD');
                        $isDeliveryPhase = in_array($item->status, ['APPROVED', 'DELIVERY_COMPLETE'], true);
                        $headerProgress = $isDeliveryPhase
                            ? (int) $item->overall_delivery_progress
                            : match ($item->status) {
                                'DRAFT' => 0,
                                'SUBMITTED' => 35,
                                'ON_HOLD' => 35,
                                'RESUBMITTED' => 50,
                                'CANVASSING' => 65,
                                'APPROVED' => 80,
                                'DELIVERY_COMPLETE' => 100,
                                'REJECTED' => 100,
                                default => 0,
                            };

                        if ($isDeliveryPhase) {
                            $headerStatusText = match ($item->overall_delivery_status) {
                                'RECEIVED' => 'DELIVERY_COMPLETE',
                                'PARTIAL' => 'PARTIAL_DELIVERY',
                                default => 'DELIVERY_PENDING',
                            };
                            $headerStatusClass = match ($item->overall_delivery_status) {
                                'RECEIVED' => 'bg-light-success text-success',
                                'PARTIAL' => 'bg-light-warning text-warning',
                                default => 'bg-light-danger text-danger',
                            };
                            $headerStatusIcon = match ($item->overall_delivery_status) {
                                'RECEIVED' => 'fa-solid fa-boxes-packing',
                                'PARTIAL' => 'fa-solid fa-truck-ramp-box',
                                default => 'fa-solid fa-inbox',
                            };
                        } else {
                            $headerStatusText = $item->status;
                            $headerStatusClass = status_badge_color($item->status);
                            $headerStatusIcon = status_badge_icon($item->status);
                        }

                        $headerProgressClass = $headerProgress >= 100
                            ? 'bg-success'
                            : ($headerProgress > 0 ? 'bg-warning' : 'bg-secondary');
                    @endphp

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <span class="badge {{ $headerStatusClass }} px-3 py-2">
                            <i class="{{ $headerStatusIcon }}"></i>
                            {{ $headerStatusText }}
                        </span>
                        <span class="fw-semibold text-muted">Progress {{ $headerProgress }}%</span>
                    </div>

                    <div class="progress mb-4" style="height: 10px;">
                        <div class="progress-bar {{ $headerProgressClass }}" role="progressbar" style="width: {{ $headerProgress }}%" aria-valuenow="{{ $headerProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <small class="text-muted d-block mb-1">Submitted by</small>
                                <div class="fw-semibold"><i class="fa-duotone fa-solid fa-circle-user text-secondary"></i> {{ $item->user->name }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <small class="text-muted d-block mb-1">Department</small>
                                <div class="fw-semibold"><i class="fa-duotone fa-solid fa-building-user text-secondary"></i> {{ $item->department->name }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <small class="text-muted d-block mb-1">PRS Date</small>
                                <div class="fw-semibold"><i class="fa-duotone fa-solid fa-calendar-days text-danger"></i> {{ tgl($item->prs_date) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <small class="text-muted d-block mb-1">Date Needed</small>
                                <div class="fw-semibold">
                                    <i class="fa-duotone fa-solid fa-calendar-star text-primary"></i> {{ tgl($item->date_needed) }}
                                    @if (!Carbon\Carbon::parse($item->date_needed)->isPast())
                                        <small class="text-muted"> ({{ human_time($item->date_needed) }})</small>
                                    @else
                                        <span class="badge bg-light-danger ms-1">Overdue</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                <small class="text-muted d-block mb-1">Remarks</small>
                                <div class="fw-semibold"><i class="fa-duotone fa-solid fa-circle-info text-secondary"></i> {{ $item->remarks ? $item->remarks : '-' }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($item->status === 'ON_HOLD' && $holdLog)
                        <div class="alert alert-warning" role="alert">
                            <strong>Hold Reason:</strong> {{ $holdLog->message }}
                        </div>
                    @endif

                    <div class="divider">
                        <div class="divider-text fw-bold">Items</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 text-center">
                            <thead>
                                <tr>
                                    <th class="text-uppercase small">Item Code</th>
                                    <th class="text-uppercase small">Item Name</th>
                                    <th class="text-uppercase small">Stock on Hand</th>
                                    <th class="text-uppercase small">Qty Ordered</th>
                                    <th class="text-uppercase small">Qty Delivered</th>
                                    <th class="text-uppercase small">Delivery Status</th>
                                    <th class="text-uppercase small">Canvasser</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item->items as $itemInfo)
                                    <tr>
                                        <td>
                                            <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $itemInfo->item->code }}')">
                                                {{ $itemInfo->item->code }}
                                            </button>
                                        </td>
                                        <td>{{ $itemInfo->item->name }}</td>
                                        <td>{{ $itemInfo->item->stock_on_hand }}</td>
                                        <td>{{ $itemInfo->quantity }} {{ $itemInfo->item->unit?->name ?? 'PCS' }}</td>
                                        <td>{{ $itemInfo->delivered_quantity }} {{ $itemInfo->item->unit?->name ?? 'PCS' }}</td>
                                        <td>
                                            @php
                                                $status = $itemInfo->delivery_status;
                                                $statusColor = match($status) {
                                                    'RECEIVED' => 'bg-light-success text-success',
                                                    'PARTIAL' => 'bg-light-warning text-warning',
                                                    'PENDING' => 'bg-light-secondary text-secondary',
                                                    default => 'bg-light-secondary text-secondary'
                                                };
                                                $statusIcon = match($status) {
                                                    'RECEIVED' => 'fa-solid fa-circle-check',
                                                    'PARTIAL' => 'fa-solid fa-hourglass-end',
                                                    'PENDING' => 'fa-solid fa-circle-xmark',
                                                    default => 'fa-solid fa-circle-question'
                                                };
                                            @endphp
                                            <span class="badge {{ $statusColor }}">
                                                <i class="{{ $statusIcon }}"></i> {{ $status }}
                                            </span>
                                        </td>
                                        <td>{{ $itemInfo->canvasser?->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- QR Code Section -->
                    <div class="text-center my-4">
                        <div class="d-inline-block border border-dark-subtle p-2 rounded">
                            {!! QrCode::size(150)->generate($item->prs_number) !!}
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">Scan to verify PRS Number</small>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endforeach
</div>
</div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/prs-approval-index.js') }}"></script>
@endpush
