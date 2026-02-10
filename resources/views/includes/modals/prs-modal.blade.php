
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create PRS</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('prs.store') }}" class="form" method="post">
                @csrf
                <div class="modal-body">

                    <div class="row">
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="department">Charged to Department</label>
                                <fieldset class="form-group">
                                    <select class="form-select" id="department" name="department_id" required>
                                        <option value="" selected disabled>-- Select Department --</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ auth()->user()->department_id == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </fieldset>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="date-needed">Date Needed</label>
                                <input type="date" id="date-needed" class="form-control" placeholder="Date Needed" name="date_needed" value="{{ \Carbon\Carbon::now()->addDays(7)->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" placeholder="Leave a comment here"
                                    id="floatingTextarea" name="remarks"></textarea>
                                <label for="floatingTextarea">Remarks</label>
                            </div>
                        </div>
                        {{-- <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="country-floating">Country</label>
                                <input type="text" id="country-floating" class="form-control"
                                    name="country-floating" placeholder="Country">
                            </div>
                        </div> --}}
                    </div>

                    <div class="divider">
                        <div class="divider-text">PRS Items</div>
                    </div>

                    <livewire:prs-item/>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Save
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>


@foreach ($items as $item)


<div class="modal fade text-left modal-borderless" id="detail-modal-{{ $item->id }}" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
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
                @endphp

                <div class="progress progress-primary my-4">
                    <div class="progress-bar progress-label" role="progressbar" style="width: 35%" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Progress</th>
                                <td>
                                    <span class="badge {{ status_badge_color($item->status) }}">
                                        <i class="{{ status_badge_icon($item->status) }}"></i>
                                        {{ $item->status }}
                                    </span>
                                </td>
                            </tr>
                            @if ($item->status === 'ON_HOLD' && $holdLog)
                                <tr>
                                    <th>Hold Reason</th>
                                    <td>
                                        {{ $holdLog->message }}
                                    </td>
                                </tr>
                            @else
                                -
                            @endif
                            <tr>
                                <th>Submitted by</th>
                                <td><i class="fa-duotone fa-solid fa-circle-user text-secondary"></i> {{ $item->user->name }}</td>
                            </tr>
                            <tr>
                                <th>Department</th>
                                <td><i class="fa-duotone fa-solid fa-building-user text-secondary"></i> {{ $item->department->name }}</td>
                            </tr>
                            <tr>
                                <th>PRS Date</th>
                                <td><i class="fa-duotone fa-solid fa-calendar-days text-danger"></i> {{ tgl($item->prs_date) }}</td>
                            </tr>
                            <tr>
                                <th>Date Needed</th>
                                <td>
                                    <i class="fa-duotone fa-solid fa-calendar-star text-primary"></i>
                                    {{ tgl($item->date_needed) }}
                                    @if (!Carbon\Carbon::parse($item->date_needed)->isPast())
                                        <small class="text-muted"> - ({{ human_time($item->date_needed) }})</small>
                                    @else
                                        <span class="badge bg-light-danger ms-2">Overdue</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Remarks</th>
                                <td><i class="fa-duotone fa-solid fa-circle-info text-secondary"></i> {{ $item->remarks ? $item->remarks : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divider">
                    <div class="divider-text fw-bold">Items</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0 text-center text-nowrap">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Stock on Hand</th>
                                <th>Quantity</th>
                                <th>Canvasser</th>
                                <th>Canvasing Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item->items as $itemInfo)
                                <tr>
                                    <td>
                                        <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $itemInfo->item->code }}')">
                                            <i class="fa-solid fa-regular fa-clipboard"></i>
                                            {{ $itemInfo->item->code }}
                                        </button>
                                    </td>
                                    <td>{{ $itemInfo->item->name }}</td>
                                    <td>{{ $itemInfo->item->stock_on_hand }}</td>
                                    <td>{{ $itemInfo->quantity }} {{ $itemInfo->item->unit?->name ?? 'PCS' }}</td>
                                    <td>{{ $itemInfo->canvaser?->name ?? '-' }}</td>
                                    <td>{{ $itemInfo->canvasingItem?->created_at ? tgl($itemInfo->canvasingItem->created_at) : '-' }}</td>
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
