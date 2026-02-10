@extends('layouts.app')
@section('title', ' | PRS')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>PRS Approval</h3>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped text-center text-nowrap" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">PRS Number</th>
                            <th class="text-center">Charged to Department</th>
                            <th class="text-center">PRS Date</th>
                            <th class="text-center">Date Needed</th>
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
                                <td><i class="fa-duotone fa-solid fa-calendar-star text-primary"></i> {{ tgl($item->date_needed) }}</td>
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

                                            {{-- <button type="button" class="btn icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#approve-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Process" @disabled($item->status === 'DRAFT')>
                                                <i class="fa-duotone fa-solid fa-circle-check"></i>
                                                Process
                                            </button>
                                            <button type="button" class="btn icon icon-left btn-outline-warning" data-bs-toggle="modal" data-bs-target="#hold-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Hold" @disabled($item->status === 'DRAFT' || $item->status === 'APPROVED')>
                                                <i class="fa-duotone fa-solid fa-circle-pause"></i>
                                                Hold
                                            </button> --}}
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
        </div>

    </section>
</div>

@foreach ($items as $item)
    <div class="modal fade text-left modal-borderless" id="approve-modal-{{ $item->id }}" tabindex="-1"
        role="dialog" aria-labelledby="approveModalLabel-{{ $item->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel-{{ $item->id }}">Approve & Assign Canvasser</h5>
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
                                                <select name="items[{{ $index }}][canvaser_id]" class="form-select" required>
                                                    <option value="" disabled {{ $itemInfo->canvaser_id ? '' : 'selected' }}>-- Select Canvasser --</option>
                                                    @foreach ($canvasers as $canvaser)
                                                        <option value="{{ $canvaser->id }}" @selected($itemInfo->canvaser_id == $canvaser->id)>{{ $canvaser->name }}</option>
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
                        <table class="table table-bordered mb-0 text-center">
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
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush
