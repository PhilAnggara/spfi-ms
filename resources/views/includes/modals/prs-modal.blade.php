@foreach ($items as $item)


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
                    $holdLog = $item->latestPurchasingHoldLog();
                    $canvasserHoldLog = $item->latestCanvasserHoldLog();
                    $isDeliveryPhase = in_array($item->status, ['PO_CREATED', 'APPROVED', 'DELIVERY_COMPLETE'], true);
                    $deliveryProgressRaw = max(0, min(100, (int) $item->overall_delivery_progress));

                    if ($isDeliveryPhase) {
                        $headerStatusText = match ($item->overall_delivery_status) {
                            'RECEIVED' => 'RECEIVED',
                            'PARTIAL' => 'PARTIALLY_RECEIVED',
                            default => 'PO_CREATED',
                        };
                        $headerStatusClass = match ($item->overall_delivery_status) {
                            'RECEIVED' => 'bg-light-success text-success',
                            'PARTIAL' => 'bg-light-success text-success',
                            default => 'bg-light-primary text-primary',
                        };
                        $headerStatusIcon = match ($item->overall_delivery_status) {
                            'RECEIVED' => 'fa-solid fa-boxes-packing text-success',
                            'PARTIAL' => 'fa-solid fa-truck-ramp-box text-warning',
                            default => 'fa-solid fa-inbox text-primary',
                        };

                        $headerProgress = match ($item->overall_delivery_status) {
                            'RECEIVED' => 100,
                            'PARTIAL' => min(99, 70 + (int) round(($deliveryProgressRaw / 100) * 29)),
                            default => 70,
                        };
                    } else {
                        $headerStatusText = $item->status;
                        $headerStatusClass = status_badge_color($item->status);
                        $headerStatusIcon = status_badge_icon($item->status);

                        $headerProgress = match ($item->status) {
                            'REQUESTED' => 15,
                            'ON_HOLD' => 15,
                            'CANVASSER_HOLD' => 45,
                            'REVISED' => 30,
                            'CANVASSING' => 50,
                            'PO_CREATED' => 70,
                            'DELIVERY_COMPLETE' => 100,
                            'REJECTED' => 100,
                            default => 0,
                        };
                    }

                    $headerProgressClass = match (true) {
                        $headerStatusText === 'REQUESTED',
                        $headerStatusText === 'REVISED' => 'bg-secondary',
                        $headerStatusText === 'CANVASSING' => 'bg-info',
                        $headerStatusText === 'PO_CREATED' => 'bg-primary',
                        $headerStatusText === 'RECEIVED',
                        $headerStatusText === 'PARTIALLY_RECEIVED' => 'bg-success',
                        default => 'bg-secondary',
                    };
                @endphp

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge {{ $headerStatusClass }} px-3 py-2">
                            <i class="{{ $headerStatusIcon }}"></i>
                            {{ $headerStatusText }}
                        </span>
                        @if ($item->is_capex)
                            <span class="badge bg-light-primary px-3 py-2">CAPEX</span>
                        @endif
                    </div>
                    <span class="fw-semibold text-muted">Progress {{ $headerProgress }}%</span>
                </div>

                <div class="progress mb-4" style="height: 10px;">
                    <div class="progress-bar {{ $headerProgressClass }}" role="progressbar" style="width: {{ $headerProgress }}%" aria-valuenow="{{ $headerProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                @if ($isDeliveryPhase)
                    <small class="text-muted d-block mb-3"><i class="fa-solid fa-box-open text-secondary"></i> Delivered: {{ $deliveryProgressRaw }}%</small>
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <small class="text-muted d-block mb-1">Requested by</small>
                            <div class="fw-semibold"><i class="fa-duotone fa-solid fa-circle-user text-secondary"></i> {{ $item->user?->name ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <small class="text-muted d-block mb-1">Department</small>
                            <div class="fw-semibold"><i class="fa-duotone fa-solid fa-building-user text-secondary"></i> {{ $item->department?->name ?? '-' }}</div>
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

                @if ($item->status === 'CANVASSER_HOLD' && $canvasserHoldLog)
                    <div class="alert alert-warning" role="alert">
                        <strong>Canvasser Hold Reason:</strong> {{ $canvasserHoldLog->message }}
                    </div>
                @endif

                @php
                    $totalItems = $item->items->count();
                    $assignedItemsCount = $item->items->whereNotNull('canvasser_id')->count();
                    $pendingAssignmentCount = max(0, $totalItems - $assignedItemsCount);
                    $receivedItemsCount = $item->items->filter(fn ($prsItem) => $prsItem->delivery_status === 'RECEIVED')->count();
                    $poNumbers = $item->items
                        ->map(function ($prsItem) {
                            return trim((string) ($prsItem->purchaseOrder?->po_number
                                ?? $prsItem->purchaseOrderItem?->purchaseOrder?->po_number
                                ?? ''));
                        })
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();
                    $poNumbersText = $poNumbers->isNotEmpty() ? $poNumbers->implode(', ') : 'Pending';
                    $rrNumbers = $item->items
                        ->flatMap(function ($prsItem) {
                            $reportItems = $prsItem->purchaseOrderItem?->receivingReportItems;
                            if (! $reportItems) {
                                return [];
                            }

                            return $reportItems->map(function ($reportItem) {
                                return trim((string) ($reportItem->receivingReport?->rr_number ?? ''));
                            });
                        })
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();
                    $rrNumbersText = $rrNumbers->isNotEmpty() ? $rrNumbers->implode(', ') : 'Pending';
                @endphp

                <div class="prs-detail-summary mb-4">
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">Total Items</span>
                        <span class="prs-detail-summary-value">{{ $totalItems }}</span>
                    </div>
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">Assigned</span>
                        <span class="prs-detail-summary-value">{{ $assignedItemsCount }}</span>
                    </div>
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">Pending Assignment</span>
                        <span class="prs-detail-summary-value">{{ $pendingAssignmentCount }}</span>
                    </div>
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">Received</span>
                        <span class="prs-detail-summary-value">{{ $receivedItemsCount }}</span>
                    </div>
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">PO Number(s)</span>
                        <span class="prs-detail-summary-value" title="{{ $poNumbersText }}">{{ $poNumbersText }}</span>
                    </div>
                    <div class="prs-detail-summary-card">
                        <span class="prs-detail-summary-label">RR Number(s)</span>
                        <span class="prs-detail-summary-value" title="{{ $rrNumbersText }}">{{ $rrNumbersText }}</span>
                    </div>
                </div>

                <div class="divider">
                    <div class="divider-text fw-bold">Items</div>
                </div>

                <div class="table-responsive prs-detail-table-wrap">
                    <table class="table align-middle mb-0 prs-detail-items-table">
                        <thead>
                            <tr>
                                <th class="text-uppercase small text-start">Item</th>
                                <th class="text-uppercase small text-center">Stock</th>
                                <th class="text-uppercase small text-center">Quantity</th>
                                <th class="text-uppercase small text-center">Delivery</th>
                                <th class="text-uppercase small text-center">Documents</th>
                                <th class="text-uppercase small text-start">Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item->items as $itemInfo)
                                @php
                                    $catalogItem = $itemInfo->item;
                                    $itemCode = $catalogItem?->code;
                                    $itemName = $catalogItem?->name;
                                    $itemStockOnHand = $catalogItem?->stock_on_hand;
                                    $itemUnitName = $catalogItem?->unit?->name ?? 'PCS';
                                    $assignedAt = $itemInfo->assigned_canvasser_at?->format('d M Y H:i');
                                    $itemPoNumber = trim((string) ($itemInfo->purchaseOrder?->po_number
                                        ?? $itemInfo->purchaseOrderItem?->purchaseOrder?->po_number
                                        ?? ''));
                                    $itemRrNumbers = $itemInfo->purchaseOrderItem?->receivingReportItems
                                        ?->map(fn ($reportItem) => trim((string) ($reportItem->receivingReport?->rr_number ?? '')))
                                        ->filter()
                                        ->unique()
                                        ->sort()
                                        ->values();
                                    $itemRrNumbersText = ($itemRrNumbers && $itemRrNumbers->isNotEmpty())
                                        ? $itemRrNumbers->implode(', ')
                                        : 'Pending';
                                @endphp
                                <tr>
                                    <td class="text-start">
                                        <div class="prs-detail-item-main">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                @if ($itemCode)
                                                    <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $itemCode }}')">
                                                        {{ $itemCode }}
                                                    </button>
                                                @else
                                                    <span class="badge bg-light-secondary">No code</span>
                                                @endif
                                                <span class="prs-detail-inline-unit">{{ $itemUnitName }}</span>
                                            </div>
                                            <div class="prs-detail-item-name">{{ $itemName ?? '(item unavailable)' }}</div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="prs-detail-metric-value">{{ $itemStockOnHand ?? '-' }}</div>
                                        <div class="prs-detail-metric-label">Available {{ $itemUnitName }}</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="prs-detail-metric-stack">
                                            <div>
                                                <span class="prs-detail-metric-value">{{ $itemInfo->quantity }}</span>
                                                <span class="prs-detail-metric-label">Ordered</span>
                                            </div>
                                            <div>
                                                <span class="prs-detail-metric-value">{{ $itemInfo->delivered_quantity }}</span>
                                                <span class="prs-detail-metric-label">Delivered</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
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
                                        <div class="prs-detail-delivery-cell">
                                            <span class="badge {{ $statusColor }}">
                                                <i class="{{ $statusIcon }}"></i> {{ $status }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-start">
                                        <div class="d-flex flex-column gap-1">
                                            <div>
                                                <small class="text-muted me-1">PO</small>
                                                @if ($itemPoNumber !== '')
                                                    <span class="badge bg-light-primary text-primary">{{ $itemPoNumber }}</span>
                                                @else
                                                    <span class="text-muted small fst-italic">Pending</span>
                                                @endif
                                            </div>
                                            <div>
                                                <small class="text-muted me-1">RR</small>
                                                @if ($itemRrNumbersText !== 'Pending')
                                                    <span class="badge bg-light-success text-success">{{ $itemRrNumbersText }}</span>
                                                @else
                                                    <span class="text-muted small fst-italic">Pending</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-start">
                                        <div class="prs-detail-assignment {{ $itemInfo->canvasser?->name ? 'is-assigned' : 'is-pending' }}">
                                            <div class="prs-detail-assignment-name">{{ $itemInfo->canvasser?->name ?? 'Not assigned yet' }}</div>
                                            <div class="prs-detail-assignment-time">{{ $assignedAt ? 'Assigned on '.$assignedAt : 'Waiting for canvasser assignment' }}</div>
                                        </div>
                                    </td>
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
            <div class="modal-footer d-flex justify-content-center">
                <a href="{{ route('prs.print', $item->id) }}" target="_blank" class="btn icon icon-left btn-outline-primary">
                    <i class="fa-duotone fa-solid fa-print"></i>
                    Print for GM Approval
                </a>
            </div>
        </div>
    </div>
</div>


@php
    $canManagePrs = auth()->id() === $item->user_id || auth()->user()->hasRole('administrator');
    $isQuantityOnlyEdit = $item->status === 'CANVASSER_HOLD';
    $canShowEditModal = $canManagePrs && (
        $isQuantityOnlyEdit
        || in_array($item->status, ['REQUESTED', 'ON_HOLD', 'REVISED'], true)
    );
    $holdLog = $item->latestPurchasingHoldLog();
    $canvasserHoldLog = $item->latestCanvasserHoldLog();
@endphp
@if ($canShowEditModal)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $item->id }}" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    @if ($isQuantityOnlyEdit)
                        Revise Quantities - ({{ $item->prs_number }})
                    @else
                        Edit PRS - ({{ $item->prs_number }})
                    @endif
                </h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <form action="{{ route('prs.update', $item->id) }}" method="post" class="form">
                @csrf
                @method('PUT')
                <div class="modal-body">

                    @if ($isQuantityOnlyEdit && $canvasserHoldLog)
                        <div class="alert alert-warning" role="alert">
                            <strong>Canvasser Hold Reason:</strong> {{ $canvasserHoldLog->message }}
                        </div>
                        <p class="text-muted small">You may only adjust quantities. Items cannot be added, removed, or replaced.</p>
                    @elseif ($item->status === 'ON_HOLD' && $holdLog)
                        <div class="alert alert-warning" role="alert">
                            <strong>Hold Reason:</strong> {{ $holdLog->message }}
                        </div>
                    @endif

                    @if ($isQuantityOnlyEdit)
                        <div class="divider">
                            <div class="divider-text">PRS Items (Quantity Only)</div>
                        </div>

                        <livewire:prs-item
                            :existing-items="$item->items"
                            mode="quantity-only"
                            :context-id="(string) $item->id"
                            wire:key="prs-item-qty-edit-{{ $item->id }}"
                        />
                    @else
                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="edit-department-{{ $item->id }}">Charged to Department</label>
                                <fieldset class="form-group">
                                    <select class="form-select" id="edit-department-{{ $item->id }}" name="department_id" required>
                                        <option value="" disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ $item->department_id == $department->id ? 'selected' : '' }}>{{ $department->code }} - {{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </fieldset>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="edit-date-needed-{{ $item->id }}">Date Needed</label>
                                <input type="date" id="edit-date-needed-{{ $item->id }}" class="form-control" placeholder="Date Needed" name="date_needed" value="{{ $item->date_needed }}" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                @php
                                    $selectedCapex = (string) old('is_capex', $item->is_capex ? '1' : '0');
                                @endphp
                                <label class="form-label mb-1">Accounting Category</label>
                                <div class="prs-accounting-toggle" role="radiogroup" aria-label="Accounting category">
                                    <input type="radio" class="btn-check" name="is_capex" id="edit-is-capex-no-{{ $item->id }}" value="0" @checked($selectedCapex === '0') required>
                                    <label class="btn btn-outline-secondary" for="edit-is-capex-no-{{ $item->id }}">
                                        <i class="fa-regular fa-circle-check me-1"></i>
                                        Non-CAPEX
                                    </label>

                                    <input type="radio" class="btn-check" name="is_capex" id="edit-is-capex-yes-{{ $item->id }}" value="1" @checked($selectedCapex === '1') required>
                                    <label class="btn btn-outline-primary" for="edit-is-capex-yes-{{ $item->id }}">
                                        <i class="fa-regular fa-building-columns me-1"></i>
                                        CAPEX
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">Keep one PRS for one accounting treatment.</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" placeholder="Leave a comment here"
                                    id="edit-remarks-{{ $item->id }}" name="remarks">{{ $item->remarks }}</textarea>
                                <label for="edit-remarks-{{ $item->id }}">Remarks</label>
                            </div>
                        </div>
                    </div>

                    <div class="divider">
                        <div class="divider-text">PRS Items</div>
                    </div>

                    <livewire:prs-item
                        :existing-items="$item->items"
                        mode="form"
                        :context-id="(string) $item->id"
                        wire:key="prs-item-edit-{{ $item->id }}"
                    />
                    @endif

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-pen me-1"></i>
                        @if ($isQuantityOnlyEdit)
                            Update Quantities
                        @else
                            Update
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


@endforeach
