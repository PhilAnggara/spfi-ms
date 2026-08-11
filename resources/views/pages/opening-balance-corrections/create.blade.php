@extends('layouts.app')
@section('title', ' | Correct Opening Balance')

@section('content')
<div
    class="page-heading po-page sc-page prs-create-page"
    id="obc-create-page"
    data-search-url="{{ $itemSearchUrl }}"
    data-preview-url="{{ route('opening-balance-corrections.preview') }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Correct Opening Balance</h3>
                    <p class="text-muted mb-0">Set the correct beginning for a period, then rebuild RR/TS/DR for the selected items.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="prs-create-actions">
                    <a href="{{ route('opening-balance-corrections.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="sc-callout mb-3">
        <div class="fw-semibold">What this does</div>
        <ul>
            <li>Purges ledger rows for each selected item from the month start (including SA in that window)</li>
            <li>Sets the new beginning balance</li>
            <li>Replays RR / TS / DR chronologically so the stock card stays consistent</li>
        </ul>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 mb-3">
            <div class="fw-semibold mb-1">Opening balance correction could not be saved.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('opening-balance-corrections.store') }}" id="obc-create-form">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent">
                <span class="fw-semibold">Document</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">OBC Number</label>
                        <input type="text" name="obc_number" class="form-control" value="{{ old('obc_number', $nextObcNumber) }}">
                        <input type="hidden" name="obc_number_suggested" value="{{ $nextObcNumber }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Period (month)</label>
                        <input type="month" name="period_month" id="obc-period" class="form-control" value="{{ old('period_month', $defaultPeriod) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="2000" placeholder="e.g. Align August beginning with Excel ending July">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_negative_balance" value="1" id="obc-force" @checked(old('allow_negative_balance'))>
                            <label class="form-check-label" for="obc-force">Allow negative balances while replaying issues (use carefully)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Items</span>
                <button type="button" class="btn btn-sm btn-outline-primary icon icon-left" id="obc-add-row">
                    <i class="fa-regular fa-plus"></i>
                    Add Item
                </button>
            </div>
            <div class="sc-preview-summary" id="obc-preview-summary">
                <span class="sc-preview-pill">Total delta: <span id="obc-summary-delta">0.00</span></span>
                <span class="sc-preview-pill">Movements to replay: <span id="obc-summary-replay">0</span></span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle sc-lines-table">
                    <thead>
                        <tr>
                            <th style="min-width: 320px;">Item</th>
                            <th class="text-end">Current On-hand</th>
                            <th class="text-end" style="min-width: 140px;">New Beginning</th>
                            <th class="text-end" title="Beginning currently implied by the system = on-hand minus net movements since period start">Implied Begin</th>
                            <th class="text-end">Delta</th>
                            <th class="text-end">Replay</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="obc-lines"></tbody>
                </table>
            </div>
        </div>

        <div class="sc-sticky-footer d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="obc-confirmed" @checked(old('confirmed')) required>
                <label class="form-check-label" for="obc-confirmed">I understand this rebuilds stock movements from the period start for the selected items.</label>
            </div>
            <button type="submit" class="btn btn-success icon icon-left">
                <i class="fa-regular fa-floppy-disk"></i>
                Apply Correction
            </button>
        </div>
    </form>
</div>

<template id="obc-row-template">
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
        <td class="text-end obc-current">0.00</td>
        <td>
            <input type="number" step="0.00001" min="0" class="form-control text-end obc-new-beginning" name="items[__INDEX__][new_beginning]" required>
        </td>
        <td class="text-end obc-implied">-</td>
        <td class="text-end"><span class="sc-delta is-zero obc-delta">-</span></td>
        <td class="text-end obc-replay">-</td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger obc-remove-row">Remove</button>
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
    <script src="{{ url('assets/scripts/modules/opening-balance-corrections-create.js') }}"></script>
@endpush
