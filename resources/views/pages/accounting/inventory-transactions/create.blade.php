@extends('layouts.app')
@section('title', ' | Create CV / JV')

@section('content')
<div class="page-heading po-page sc-page" id="inv-create-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Create CV / JV</h3>
                    <p class="text-muted mb-0">Manual accounting inventory voucher with quantity, unit cost, and direction.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <a href="{{ route('accounting.inventory-transactions.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-light fa-arrow-left"></i>
                        Back to Queue
                    </a>
                </div>
            </div>
        </div>
    </div>

    @include('pages.accounting.inventory-transactions.partials.manual-create-form', [
        'categories' => $categories,
        'itemSearchUrl' => $itemSearchUrl,
        'selectedCategoryId' => $selectedCategoryId,
        'formId' => 'inv-create-form',
        'isModal' => false,
    ])
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/prs-modern.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stock-correction-item-search.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-encode.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/accounting-inventory-create.js') }}"></script>
@endpush
