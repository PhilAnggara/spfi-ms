@extends('layouts.app')
@section('title', ' | Create Stock Adjustment')

@section('content')
<div
    class="page-heading po-page sc-page prs-create-page"
    id="sa-create-page"
    data-search-url="{{ $itemSearchUrl }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Create Stock Adjustment</h3>
                    <p class="text-muted mb-0">Search items, set the new on-hand balance, and post the difference to the ledger as ADJ.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="sc-callout mb-3">
        <div class="fw-semibold">When to use</div>
        <ul>
            <li>Correct current on-hand without rewriting RR / TS / DR history</li>
            <li>Each line posts the delta to the ADJ ledger bucket</li>
            <li>Later movements stay as-is if you reverse — only on-hand is adjusted back</li>
        </ul>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 mb-3">
            <div class="fw-semibold mb-1">Stock adjustment could not be saved.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('stock-adjustments.store') }}" id="sa-create-form">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <span class="sc-section-title"><i class="fa-regular fa-file-lines"></i> Document</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">SA Number</label>
                        <input type="text" name="sa_number" class="form-control" value="{{ old('sa_number', $nextSaNumber) }}">
                        <input type="hidden" name="sa_number_suggested" value="{{ $nextSaNumber }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="sa_date" class="form-control" value="{{ old('sa_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="2000" placeholder="Why is this adjustment needed?">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="sc-section-title"><i class="fa-regular fa-boxes-stacked"></i> Items</span>
                <button type="button" class="btn btn-sm btn-outline-primary icon icon-left" id="sa-add-row">
                    <i class="fa-regular fa-plus"></i>
                    Add Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle sc-lines-table">
                    <thead>
                        <tr>
                            <th style="min-width: 320px;">Item</th>
                            <th class="text-end">Current</th>
                            <th class="text-end" style="min-width: 140px;">New Balance</th>
                            <th class="text-end">Delta</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="sa-lines"></tbody>
                </table>
            </div>
        </div>

        <div class="sc-sticky-footer d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="sa-confirmed" @checked(old('confirmed')) required>
                <label class="form-check-label" for="sa-confirmed">I confirm posting this stock adjustment to the ledger.</label>
            </div>
            <button type="submit" class="btn btn-success icon icon-left" id="sa-submit-btn" disabled>
                <i class="fa-regular fa-floppy-disk"></i>
                Post Adjustment
            </button>
        </div>
    </form>
</div>

<template id="sa-row-template">
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
                <input type="hidden" class="sc-item-id" name="items[__INDEX__][item_id]" value="">
                <input type="hidden" name="items[__INDEX__][wh_code]" value="MAIN">
            </div>
        </td>
        <td class="text-end sa-current">0.00</td>
        <td>
            <input type="number" step="0.00001" min="0" class="form-control text-end sa-new-balance" name="items[__INDEX__][new_balance]" required>
        </td>
        <td class="text-end"><span class="sc-delta is-zero sa-delta">0.00</span></td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger sa-remove-row">Remove</button>
        </td>
    </tr>
</template>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stock-correction-item-search.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/stock-adjustments-create.js') }}"></script>
@endpush
