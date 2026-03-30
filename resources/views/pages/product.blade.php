@extends('layouts.app')
@section('title', ' | Product')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Product</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
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
                <table
                    class="table table-striped text-center text-nowrap"
                    id="product-table"
                    data-source="{{ route('product.datatables') }}"
                    data-csrf-token="{{ csrf_token() }}"
                    data-update-route-template="{{ route('product.update', '__ID__') }}"
                    data-destroy-route-template="{{ route('product.destroy', '__ID__') }}"
                    data-open-create-modal="{{ $errors->any() ? '1' : '0' }}"
                    data-editing-product-id="{{ (string) session('editing_product_id', '') }}">
                    <thead>
                        <tr>
                            <th class="text-center d-none">ID</th>
                            <th class="text-center">Product Code</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/product-index.js') }}"></script>
@endpush
