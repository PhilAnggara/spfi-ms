
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
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
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </fieldset>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="form-group">
                                <label for="date-needed">Date Needed</label>
                                <input type="date" id="date-needed" class="form-control" placeholder="Date Needed" name="date_needed" required>
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

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-primary" data-bs-dismiss="modal">
                        <i class="bx bx-x d-block d-sm-none"></i>
                        <span class="d-none d-sm-block">Cancel</span>
                    </button>
                    <button type="submit" class="btn btn-primary ms-1">
                        <i class="bx bx-check d-block d-sm-none"></i>
                        <span class="d-none d-sm-block">Save</span>
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

                <div class="progress progress-primary my-4">
                    <div class="progress-bar progress-label" role="progressbar" style="width: 35%" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Progress</th>
                                <td><span class="badge bg-light-info">{{ $item->status }}</span></td>
                            </tr>
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
                    <table class="table table-bordered mb-0 text-center">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Stock on Hand</th>
                                <th>Quantity</th>
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
                                    <td>{{ $itemInfo->quantity }} {{ $itemInfo->item->unit }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>


<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $item->id }}" tabindex="-1"
    role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit PRS - ({{ $item->prs_number }})</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                ------
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-primary" data-bs-dismiss="modal">
                    <i class="bx bx-x d-block d-sm-none"></i>
                    <span class="d-none d-sm-block">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary ms-1" data-bs-dismiss="modal">
                    <i class="bx bx-check d-block d-sm-none"></i>
                    <span class="d-none d-sm-block">Save</span>
                </button>
            </div>
        </div>
    </div>
</div>


@endforeach
