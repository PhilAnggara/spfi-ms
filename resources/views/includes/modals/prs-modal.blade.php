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
                    $holdLog = $item->logs?->firstWhere('action', 'HOLD');
                    $isDeliveryPhase = in_array($item->status, ['APPROVED', 'DELIVERY_COMPLETE'], true);
                    $headerProgress = $isDeliveryPhase
                        ? (int) $item->overall_delivery_progress
                        : match ($item->status) {
                            'DRAFT' => 0,
                            'SUBMITTED' => 35,
                            'ON_HOLD' => 35,
                            'RESUBMITTED' => 50,
                            'CANVASING' => 65,
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
                                                'PENDING' => 'bg-light-danger text-danger',
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
                                    <td>{{ $itemInfo->canvaser?->name ?? '-' }}</td>
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


<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $item->id }}" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit PRS - ({{ $item->prs_number }})</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <form action="{{ route('prs.update', $item->id) }}" method="post" class="form">
                @csrf
                @method('PUT')
                <div class="modal-body">

                    @if ($item->status === 'ON_HOLD' && $holdLog)
                        <div class="alert alert-warning" role="alert">
                            <strong>Hold Reason:</strong> {{ $holdLog->message }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="edit-department-{{ $item->id }}">Charged to Department</label>
                                <fieldset class="form-group">
                                    <select class="form-select" id="edit-department-{{ $item->id }}" name="department_id" required>
                                        <option value="" disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ $item->department_id == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
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

                    <livewire:prs-item :existing-items="$item->items" wire:key="prs-item-edit-{{ $item->id }}" />

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-pen me-1"></i>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


@endforeach
