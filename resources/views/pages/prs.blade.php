@extends('layouts.app')
@section('title', ' | PRS')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Purchase Requisition Slip</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="float-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Create PRS
                    </button>
                </div>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card">
            {{-- <div class="card-header">
                <h5 class="card-title">
                    PRS Data
                </h5>
            </div> --}}
            <div class="card-body">
                <table class="table table-striped text-center" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">PRS Number</th>
                            <th class="text-center">Charged to Department</th>
                            <th class="text-center">PRS Date</th>
                            <th class="text-center">Date Needed</th>
                            <th class="text-center">No. of Items</th>
                            <th class="text-center">Remarks</th>
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
                                <td>
                                    <span class="badge bg-light-secondary">{{ $item->items->count() }}</span>
                                </td>
                                <td>{{ Str::limit($item->remarks, 20, '...') ?? '-' }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#detail-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                            <i class="fa-light fa-eye text-primary"></i>
                                        </button>
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
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </section>
</div>
@include('includes.modals.prs-modal')
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
