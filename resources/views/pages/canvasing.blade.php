@extends('layouts.app')
@section('title', ' | Canvasing')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Canvasing</h3>
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
                            <th class="text-center">Department</th>
                            <th class="text-center">PRS Date</th>
                            <th class="text-center">Date Needed</th>
                            <th class="text-center">No. of Items</th>
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
                                    <span class="badge bg-light-secondary">{{ $item->items_count }}</span>
                                </td>
                                <td>
                                    @php
                                        $isComplete = $item->items_count > 0 && $item->canvased_items_count >= $item->items_count;
                                    @endphp
                                    <a href="{{ route('canvasing.show', $item->id) }}" class="btn btn-sm icon icon-left {{ $isComplete ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa-duotone fa-solid fa-pen-to-square"></i>
                                        {{ $isComplete ? 'Edit Canvasing' : 'Fill Canvasing' }}
                                    </a>
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

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush
