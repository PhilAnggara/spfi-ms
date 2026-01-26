@extends('layouts.app')
@section('title', ' | PRS')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>PRS Approval</h3>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
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
                                        <button type="button" class="btn icon icon-left btn-outline-success" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $item->id }}, 'Delete PRS', 'Are you sure want to delete PRS {{ $item->prs_number }}?')">
                                            <i class="fa-duotone fa-solid fa-file-pdf"></i>
                                            Approve
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
