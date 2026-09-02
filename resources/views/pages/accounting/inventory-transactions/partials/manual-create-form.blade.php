@php
    $formId = $formId ?? 'inv-create-form';
    $isModal = (bool) ($isModal ?? false);
@endphp

@if ($errors->any())
    <div class="alert alert-danger {{ $isModal ? 'mb-3' : '' }}">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('accounting.inventory-transactions.store') }}" id="{{ $formId }}" class="inv-manual-create-form" data-search-url="{{ $itemSearchUrl }}">
    @csrf
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
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
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="doc_date" class="form-control" value="{{ old('doc_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="2000">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="sc-section-title fw-semibold"><i class="fa-regular fa-boxes-stacked"></i> Items</span>
            <button type="button" class="btn btn-sm btn-outline-primary icon icon-left inv-add-row" id="inv-add-row" disabled>
                <i class="fa-regular fa-plus"></i>
                Add Item
            </button>
        </div>
        <p class="text-muted small px-3 pt-2 mb-0 inv-category-hint">Select a category before adding items.</p>
        <div class="table-responsive">
            <table class="table mb-0 align-middle sc-lines-table">
                <thead>
                    <tr>
                        <th style="min-width: 320px;">Item</th>
                        <th class="text-end">Available</th>
                        <th class="text-center">Direction</th>
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

    <div class="d-flex justify-content-end gap-2">
        @if ($isModal)
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
        @endif
        <button type="submit" class="btn btn-primary icon icon-left">
            <i class="fa-regular fa-floppy-disk"></i>
            Save Draft
        </button>
    </div>
</form>

<template id="inv-row-template">
    <tr>
        <td>
            <div class="sc-item-picker">
                <input type="text" class="form-control sc-item-search" placeholder="Type at least 2 characters (code or name)…" autocomplete="off">
                <div class="sc-item-results"></div>
                <div class="sc-item-selected">
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
            @include('pages.accounting.inventory-transactions.partials.direction-toggle', [
                'fieldName' => 'lines[__INDEX__][direction]',
                'rowId' => '__INDEX__',
                'selected' => 'in',
            ])
        </td>
        <td><input type="number" step="0.00001" min="0.00001" class="form-control text-end inv-qty" name="lines[__INDEX__][quantity]" required></td>
        <td><input type="number" step="0.0001" min="0" class="form-control text-end inv-cost" name="lines[__INDEX__][unit_cost]" required></td>
        <td class="text-end font-monospace inv-amount">0.00</td>
        <td class="text-end">
            <input type="hidden" class="inv-amount-input" name="lines[__INDEX__][amount]" value="0">
            <button type="button" class="btn btn-sm btn-outline-danger inv-remove-row">Remove</button>
        </td>
    </tr>
</template>
