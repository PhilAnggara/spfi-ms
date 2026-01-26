@extends('layouts.app')
@section('title', ' | Product')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Product</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="float-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Product
                    </button>
                </div>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            {{-- <div class="card-header">
                <h5 class="card-title">
                    PRS Data
                </h5>
            </div> --}}
            <div class="card-body">
                <table class="table table-striped text-center" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">Product Code</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $item->code }}')">
                                        <i class="fa-solid fa-regular fa-clipboard"></i>
                                        {{ $item->code }}
                                    </button>
                                </td>
                                <td>{{ $item->name }}</td>
                                <td>
                                    <span class="badge bg-light-secondary">{{ $item->unit }}</span>
                                </td>
                                <td>{{ $item->category }}</td>
                                <td>{{ $item->type }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $item->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                            <i class="fa-light fa-edit text-primary"></i>
                                        </button>
                                        <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $item->id }}, 'Delete Product', 'Are you sure want to delete {{ $item->name }}?')">
                                            <i class="fa-light fa-trash text-secondary"></i>
                                        </button>
                                        <form action="{{ route('product.destroy', $item->id) }}" id="hapus-{{ $item->id }}" method="POST">
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
@include('includes.modals.product-modal')
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

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_product_id'))
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_product_id") }}'));
                editModal.show();
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
