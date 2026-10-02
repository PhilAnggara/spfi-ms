@extends('layouts.app')
@section('title', ' | Scan Count Tag Non-FG')

@section('content')
<div
    id="count-tag-scan-page"
    class="count-tag-scan-page page-heading po-page"
    data-lookup-url="{{ $lookupUrl }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">Scan Product QR</h3>
                    <p class="text-muted mb-0">Point the camera at a product label, or type the product code manually.</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="po-top-actions text-lg-end">
                    <a href="{{ route('count-tags.non-fg.index') }}" class="btn btn-light-secondary icon icon-left">
                        <i class="fa-regular fa-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="count-tag-scan-layout">
        <section class="count-tag-scan-camera-card card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h5 class="card-title mb-0">Camera</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="count-tag-start-camera">
                            <i class="fa-light fa-camera me-1"></i>
                            Start Camera
                        </button>
                        <button type="button" class="btn btn-light-secondary btn-sm" id="count-tag-stop-camera" disabled>
                            Stop
                        </button>
                    </div>
                </div>

                <div id="count-tag-qr-reader" class="count-tag-qr-reader"></div>
                <div id="count-tag-camera-status" class="count-tag-scan-status text-muted mt-2">
                    Camera is off. Tap Start Camera to scan a QR label.
                </div>
            </div>
        </section>

        <section class="count-tag-scan-side">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">Manual Lookup</h5>
                    <label for="count-tag-manual-code" class="form-label">Product Code</label>
                    <div class="input-group count-tag-manual-group">
                        <input
                            type="text"
                            id="count-tag-manual-code"
                            class="form-control form-control-lg"
                            placeholder="Enter product code"
                            autocomplete="off"
                            inputmode="text"
                            autocapitalize="characters"
                        >
                        <button type="button" class="btn btn-primary" id="count-tag-manual-lookup">
                            Lookup
                        </button>
                    </div>
                    <div class="form-text">Press Enter after typing a code if the camera is unavailable.</div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">Last Scanned Item</h5>

                    <div id="count-tag-result-empty" class="count-tag-result-empty text-muted text-center py-4">
                        <i class="fa-duotone fa-solid fa-barcode-read count-tag-result-empty-icon"></i>
                        <p class="mb-0 mt-2">No item scanned yet.</p>
                    </div>

                    <div id="count-tag-result-loading" class="d-none text-center py-4 text-muted">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2">Looking up product...</div>
                    </div>

                    <div id="count-tag-result-error" class="d-none alert alert-danger mb-0" role="alert"></div>

                    <div
                        id="count-tag-result-card"
                        class="d-none count-tag-result-card count-tag-result-card-clickable"
                        role="button"
                        tabindex="0"
                        aria-label="Open last scanned item"
                        aria-disabled="true"
                    >
                        <div class="count-tag-result-code" id="count-tag-result-code"></div>
                        <div class="count-tag-result-name" id="count-tag-result-name"></div>
                        <div class="count-tag-result-meta" id="count-tag-result-meta"></div>
                        <div class="count-tag-result-hint">Tap to open</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="count-tag-entry-modal" tabindex="-1" aria-labelledby="count-tag-entry-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content count-tag-entry-modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="count-tag-entry-modal-title">Count Tag Entry</h5>
                    <small class="text-muted">Review the scanned product. Quantity input comes next.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="count-tag-modal-item">
                    <div class="count-tag-modal-code" id="count-tag-modal-code">-</div>
                    <div class="count-tag-modal-name" id="count-tag-modal-name">-</div>
                    <div class="count-tag-modal-meta" id="count-tag-modal-meta">-</div>
                </div>
                <div class="count-tag-modal-placeholder mt-3">
                    <div class="text-muted small">Quantity and save controls will appear here.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/count-tag-scan.css') }}">
@endpush

@push('addon-script')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="{{ url('assets/scripts/modules/count-tag-non-fg-scan.js') }}"></script>
@endpush
