@extends('layouts.app')
@section('title', ' | Supplier')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Supplier</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="float-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Supplier
                    </button>
                </div>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped text-center text-nowrap" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">Action</th>
                            <th class="text-center">Code</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Address</th>
                            <th class="text-center">Phone</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Contact Person</th>
                            <th class="text-center">Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $supplier->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                            <i class="fa-light fa-edit text-primary"></i>
                                        </button>
                                        <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $supplier->id }}, 'Delete Supplier', 'Are you sure want to delete {{ $supplier->name }}?')">
                                            <i class="fa-light fa-trash text-secondary"></i>
                                        </button>
                                        <form action="{{ route('supplier.destroy', $supplier->id) }}" id="hapus-{{ $supplier->id }}" method="POST">
                                            @method('delete')
                                            @csrf
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $supplier->code }}')">{{ $supplier->code }}</span>
                                </td>
                                <td>{{ $supplier->name }}</td>
                                <td>{{ $supplier->address }}</td>
                                <td>
                                    @if ($supplier->phone)
                                        <i class="fa-light fa-phone"></i> {{ $supplier->phone }}
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->email)
                                        <a href="mailto:{{ $supplier->email }}" target="_blank" class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill">
                                            <i class="far fa-envelope text-danger"></i>
                                            {{ $supplier->email }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->contact_person)
                                        <i class="fa-duotone fa-light fa-address-card"></i>
                                        {{ $supplier->contact_person }}
                                    @endif
                                </td>
                                <td>
                                    <small>
                                        <i class="fa-duotone fa-light fa-user"></i>
                                        {{ $supplier->creator->name ?? '-' }}
                                    </small>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </section>
</div>
@include('includes.modals.supplier-modal')
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
    <script src="{{ url('assets/scripts/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_supplier_id'))
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_supplier_id") }}'));
                editModal.show();
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
