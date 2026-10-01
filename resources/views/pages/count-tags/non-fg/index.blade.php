@extends('layouts.app')
@section('title', ' | Count Tag Non-FG')

@section('content')
<div id="count-tag-non-fg-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Count Tag Non-FG</h3>
                    <p class="text-muted mb-0">Scan product QR codes on shelves to identify Non-Finish Good items for physical count.</p>
                </div>
            </div>
            @can('create-count-tag-non-fg')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <a href="{{ route('count-tags.non-fg.scan') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-camera-viewfinder"></i>
                            Scan Items
                        </a>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Count Tag List</h5>
                    <span class="badge bg-light-primary">0 records</span>
                </div>

                <div class="po-empty-state text-center text-muted py-5">
                    <i class="fa-duotone fa-solid fa-qrcode po-empty-icon"></i>
                    <p class="mb-0 mt-2 fw-semibold">No count tags yet.</p>
                    <small>Use Scan Items to verify product QR codes. Saving count documents comes next.</small>
                </div>
            </div>
        </div>
    </section>
</div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush
