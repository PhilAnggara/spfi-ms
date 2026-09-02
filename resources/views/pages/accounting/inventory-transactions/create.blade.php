@extends('layouts.app')
@section('title', ' | Create CV / JV')

@section('content')
<div class="page-heading sc-page" id="inv-create-page" data-search-url="{{ $itemSearchUrl }}">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <h3 class="mb-1">Create CV / JV</h3>
                <p class="text-muted mb-0">Manual accounting inventory voucher with quantity, unit cost, and direction.</p>
            </div>
            <div class="col-12 col-lg-4 text-lg-end">
                <a href="{{ route('accounting.inventory-transactions.index') }}" class="btn btn-light-secondary icon icon-left">
                    <i class="fa-light fa-arrow-left"></i>
                    Back to Queue
                </a>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('accounting.inventory-transactions.store') }}" id="inv-create-form">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="inv-category-id" class="form-select" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $selectedCategoryId) === (int) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select name="doc_type" class="form-select" required>
                            <option value="CV" @selected(old('doc_type') === 'CV')>CV</option>
                            <option value="JV" @selected(old('doc_type') === 'JV')>JV</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" name="doc_number" class="form-control" value="{{ old('doc_number') }}" required maxlength="50">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="doc_date" class="form-control" value="{{ old('doc_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Party</label>
                        <input type="text" name="party_name" class="form-control" value="{{ old('party_name') }}" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="2000">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Items</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="inv-add-row">Add Item</button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="min-width: 280px;">Item</th>
                            <th class="text-end">Available</th>
                            <th class="text-center">+/−</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="inv-lines"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save Draft</button>
        </div>
    </form>
</div>

<template id="inv-row-template">
    <tr>
        <td>
            <div class="sc-item-picker">
                <input type="text" class="form-control sc-item-search" placeholder="Search item..." autocomplete="off">
                <div class="sc-item-results"></div>
                <div class="sc-item-selected d-none">
                    <div>
                        <div class="sc-item-selected-title"></div>
                        <div class="sc-item-selected-sub"></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-secondary sc-item-clear">Change</button>
                </div>
                <input type="hidden" class="sc-item-id" name="lines[__INDEX__][item_id]" value="">
                <input type="hidden" name="lines[__INDEX__][unit_of_measure_id]" class="inv-uom-id" value="">
            </div>
        </td>
        <td class="text-end font-monospace inv-available">0.00000</td>
        <td class="text-center">
            <select name="lines[__INDEX__][direction]" class="form-select form-select-sm inv-direction">
                <option value="in">+</option>
                <option value="out">−</option>
            </select>
        </td>
        <td><input type="number" step="0.00001" min="0.00001" class="form-control text-end inv-qty" name="lines[__INDEX__][quantity]" required></td>
        <td><input type="number" step="0.0001" min="0" class="form-control text-end inv-cost" name="lines[__INDEX__][unit_cost]" required></td>
        <td class="text-end font-monospace inv-amount">0.00</td>
        <td><input type="hidden" class="inv-amount-input" name="lines[__INDEX__][amount]" value="0"><button type="button" class="btn btn-sm btn-outline-danger inv-remove-row">Remove</button></td>
    </tr>
</template>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stock-correction-item-search.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-create.js') }}"></script>
@endpush
