@extends('layouts.app')
@section('title', ' | PRS')

@section('content')
<div class="page-heading prs-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="prs-hero">
                    <h3 class="mb-1">Purchase Requisition Slip</h3>
                    <p class="text-muted mb-0">Kelola pengajuan PRS lebih cepat dengan filter lengkap dan tampilan yang responsif.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-top-actions">
                    <button type="button" class="btn btn-outline-primary icon icon-left" data-bs-toggle="modal" data-bs-target="#export-modal">
                        <i class="fa-duotone fa-solid fa-file-pdf"></i>
                        Export PDF
                    </button>
                    <a href="{{ route('prs.create') }}" class="btn btn-success icon icon-left">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Create PRS
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end prs-filter-grid" id="prs-filter-form">
                    <div class="col-12 col-md-6 col-xl-{{ $canFilterDepartment ? 3 : 2 }}">
                        <label for="filter-keyword" class="form-label mb-1">Cari PRS</label>
                        <input type="text" id="filter-keyword" class="form-control" placeholder="PRS number / remarks{{ $canFilterDepartment ? ' / department' : '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-status" class="form-label mb-1">Status</label>
                        <select id="filter-status" class="form-select">
                            <option value="">Semua</option>
                            <option value="DRAFT">DRAFT</option>
                            <option value="SUBMITTED">SUBMITTED</option>
                            <option value="ON_HOLD">ON_HOLD</option>
                            <option value="RESUBMITTED">RESUBMITTED</option>
                            <option value="CANVASING">CANVASING</option>
                            <option value="DELIVERY_PENDING">DELIVERY_PENDING</option>
                            <option value="PARTIAL_DELIVERY">PARTIAL_DELIVERY</option>
                            <option value="DELIVERY_COMPLETE">DELIVERY_COMPLETE</option>
                            <option value="REJECTED">REJECTED</option>
                        </select>
                    </div>
                    @if ($canFilterDepartment)
                        <div class="col-6 col-md-3 col-xl-3">
                            <label for="filter-department" class="form-label mb-1">Department</label>
                            <select id="filter-department" class="form-select">
                                <option value="">Semua</option>
                                @foreach ($filterDepartments as $department)
                                    <option value="{{ $department->name }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-prs-start" class="form-label mb-1">PRS Date (from)</label>
                        <input type="date" id="filter-prs-start" class="form-control">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-prs-end" class="form-label mb-1">PRS Date (to)</label>
                        <input type="date" id="filter-prs-end" class="form-control">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-needed-start" class="form-label mb-1">Needed (from)</label>
                        <input type="date" id="filter-needed-start" class="form-control">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-needed-end" class="form-label mb-1">Needed (to)</label>
                        <input type="date" id="filter-needed-end" class="form-control">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="d-flex gap-2">
                            <button type="button" id="reset-prs-filter" class="btn btn-light-secondary w-100">
                                <i class="fa-regular fa-rotate-left me-1"></i>
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">PRS Data</h5>
                    <span class="badge bg-light-primary" id="prs-filter-result">{{ $items->count() }} data</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped text-center text-nowrap" id="table1">
                        <thead>
                            <tr>
                                <th class="text-center">PRS Number</th>
                                <th class="text-center">Charged to Department</th>
                                <th class="text-center">PRS Date</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Remarks</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="prs-table-body">
                            @foreach ($items as $item)
                                @php
                                    $isDeliveryPhase = in_array($item->status, ['APPROVED', 'DELIVERY_COMPLETE'], true);
                                    if ($isDeliveryPhase) {
                                        $deliveryStatus = $item->overall_delivery_status;
                                        $primaryStatusText = match($deliveryStatus) {
                                            'RECEIVED' => 'DELIVERY_COMPLETE',
                                            'PARTIAL' => 'PARTIAL_DELIVERY',
                                            default => 'DELIVERY_PENDING',
                                        };
                                        $primaryStatusColor = match($deliveryStatus) {
                                            'RECEIVED' => 'bg-light-success text-success',
                                            'PARTIAL' => 'bg-light-warning text-warning',
                                            default => 'bg-light-danger text-danger',
                                        };
                                        $primaryStatusIcon = match($deliveryStatus) {
                                            'RECEIVED' => 'fa-solid fa-boxes-packing',
                                            'PARTIAL' => 'fa-solid fa-truck-ramp-box',
                                            default => 'fa-solid fa-inbox',
                                        };
                                    } else {
                                        $primaryStatusText = $item->status;
                                        $primaryStatusColor = status_badge_color($item->status);
                                        $primaryStatusIcon = status_badge_icon($item->status);
                                    }
                                @endphp
                                <tr
                                    data-prs-number="{{ strtolower($item->prs_number) }}"
                                    data-department="{{ strtolower($item->department->name) }}"
                                    data-status="{{ strtoupper($primaryStatusText) }}"
                                    data-prs-date="{{ $item->prs_date }}"
                                    data-needed-date="{{ $item->date_needed }}"
                                    data-remarks="{{ strtolower($item->remarks ?? '') }}">
                                    <td>
                                        <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $item->prs_number }}')">
                                            <i class="fa-solid fa-regular fa-clipboard"></i>
                                            {{ $item->prs_number }}
                                        </button>
                                    </td>
                                    <td>{{ $item->department->name }}</td>
                                    <td><i class="fa-duotone fa-solid fa-calendar-days text-danger"></i> {{ tgl($item->prs_date) }}</td>
                                    <td>
                                        <span class="badge {{ $primaryStatusColor }}">
                                            <i class="{{ $primaryStatusIcon }}"></i>
                                            {{ $primaryStatusText }}
                                        </span>
                                    </td>
                                    <td>{{ $item->remarks ? Str::limit($item->remarks, 40, '...') : '-' }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#detail-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                                <i class="fa-light fa-eye text-primary"></i>
                                            </button>
                                            @if ($item->status === 'DRAFT' || $item->status === 'ON_HOLD' || $item->status === 'SUBMITTED' || $item->status === 'RESUBMITTED')
                                                <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="fa-light fa-edit text-primary"></i>
                                                </button>
                                                <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $item->id }}, 'Delete PRS', 'Are you sure want to delete PRS {{ $item->prs_number }}?')">
                                                    <i class="fa-light fa-trash text-secondary"></i>
                                                </button>
                                                <form action="{{ route('prs.destroy', $item->id) }}" id="hapus-{{ $item->id }}" method="POST">
                                                    @method('delete')
                                                    @csrf
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@include('includes.modals.prs-modal')
@include('includes.modals.prs-export')
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/prs-modern.js') }}"></script>
@endpush

{{-- New Version DataTables --}}
{{-- @push('addon-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.6/css/dataTables.dataTables.css" />
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.3.6/js/dataTables.js"></script>
    <script>
        let table = new DataTable('#table1');
    </script>
@endpush --}}
