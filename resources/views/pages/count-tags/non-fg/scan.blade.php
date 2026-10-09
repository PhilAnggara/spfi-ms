@extends('layouts.app')
@section('title', ' | Scan Count Tag Non-FG')

@section('content')
<div
    id="count-tag-scan-page"
    class="count-tag-scan-page page-heading po-page sc-page"
    data-lookup-url="{{ $lookupUrl }}"
    data-store-url="{{ $storeUrl }}"
    data-locations-url="{{ $locationsUrl }}"
    data-today="{{ $today }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">Scan Product QR</h3>
                    <p class="text-muted mb-0">Point the camera at a shelf label, or enter the product code manually.</p>
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
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="card-title mb-0">Camera</h5>
                        <span id="count-tag-camera-pill" class="count-tag-status-pill is-off">Off</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" id="count-tag-start-camera">
                            <i class="fa-light fa-camera me-1"></i>
                            Start
                        </button>
                        <button type="button" class="btn btn-light-secondary btn-sm" id="count-tag-stop-camera" disabled>
                            Stop
                        </button>
                    </div>
                </div>

                <div class="count-tag-qr-frame">
                    <div id="count-tag-qr-reader" class="count-tag-qr-reader"></div>
                </div>
                <div id="count-tag-camera-status" class="count-tag-scan-status text-muted mt-2">
                    Camera is off. Tap Start to scan a QR label.
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
                    <div class="form-text">Press Enter if the camera is unavailable.</div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">Last Scanned</h5>

                    <div id="count-tag-result-empty" class="count-tag-result-empty text-muted text-center py-4">
                        <i class="fa-duotone fa-solid fa-barcode-read count-tag-result-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No item scanned yet</p>
                        <small>Scan a label or look up a code to continue.</small>
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
                        <div class="count-tag-result-hint">
                            Tap to continue
                            <i class="fa-regular fa-arrow-right ms-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="count-tag-entry-modal" tabindex="-1" aria-labelledby="count-tag-entry-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content count-tag-entry-modal-content">
            <form id="count-tag-entry-form" novalidate>
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title mb-1" id="count-tag-entry-modal-title">Count Tag Entry</h5>
                        <small class="text-muted">Confirm location and quantity, then save.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="count-tag-modal-item mb-3">
                        <div class="count-tag-modal-code" id="count-tag-modal-code">-</div>
                        <div class="count-tag-modal-name" id="count-tag-modal-name">-</div>
                        <div class="count-tag-modal-meta" id="count-tag-modal-meta">-</div>
                    </div>

                    <div id="count-tag-form-error" class="alert alert-danger d-none" role="alert"></div>

                    <input type="hidden" id="count-tag-item-id" name="item_id" value="">

                    <div class="count-tag-form-section">
                        <div class="count-tag-form-section-title">Location</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="count-tag-location" class="form-label">Location <span class="text-danger">*</span></label>
                                <select id="count-tag-location" name="location_id" class="form-select" required>
                                    <option value="">Select location</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="location_id"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="count-tag-section" class="form-label">Section <span class="text-danger">*</span></label>
                                <select id="count-tag-section" name="section_id" class="form-select" required disabled>
                                    <option value="">Select section</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="section_id"></div>
                            </div>
                        </div>
                    </div>

                    <div class="count-tag-form-section">
                        <div class="count-tag-form-section-title">Position</div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label for="count-tag-row" class="form-label">Row</label>
                                <input type="number" min="1" id="count-tag-row" name="row" class="form-control" inputmode="numeric">
                                <div class="invalid-feedback" data-error-for="row"></div>
                            </div>
                            <div class="col-4">
                                <label for="count-tag-col" class="form-label">Col</label>
                                <input type="number" min="1" id="count-tag-col" name="col" class="form-control" inputmode="numeric">
                                <div class="invalid-feedback" data-error-for="col"></div>
                            </div>
                            <div class="col-4">
                                <label for="count-tag-level" class="form-label">Level</label>
                                <input type="number" min="1" id="count-tag-level" name="level" class="form-control" inputmode="numeric">
                                <div class="invalid-feedback" data-error-for="level"></div>
                            </div>
                        </div>
                        <div class="form-text mt-2" id="count-tag-section-hint"></div>
                    </div>

                    <div class="count-tag-form-section">
                        <div class="count-tag-form-section-title">Count</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="count-tag-qty" class="form-label">Qty <span class="text-danger">*</span></label>
                                <input type="number" min="0.00001" step="any" id="count-tag-qty" name="qty" class="form-control form-control-lg count-tag-qty-input" required inputmode="decimal">
                                <div class="invalid-feedback" data-error-for="qty"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="count-tag-condition" class="form-label">Condition</label>
                                <select id="count-tag-condition" name="condition" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($conditions as $condition)
                                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="condition"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="count-tag-size" class="form-label">Size</label>
                                <input type="text" id="count-tag-size" name="size" class="form-control" maxlength="100" autocomplete="off">
                                <div class="invalid-feedback" data-error-for="size"></div>
                            </div>
                        </div>
                    </div>

                    <div class="count-tag-form-section is-secondary">
                        <div class="count-tag-form-section-title">Dates</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="count-tag-date" class="form-label">Count Tag Date <span class="text-danger">*</span></label>
                                <input type="date" id="count-tag-date" name="count_tag_date" class="form-control" value="{{ $today }}" required>
                                <div class="invalid-feedback" data-error-for="count_tag_date"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="count-tag-tran-date" class="form-label">Tran Date <span class="text-danger">*</span></label>
                                <input type="date" id="count-tag-tran-date" name="tran_date" class="form-control" value="{{ $today }}" required>
                                <div class="invalid-feedback" data-error-for="tran_date"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer count-tag-entry-footer">
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="count-tag-save-btn">
                        <span class="count-tag-save-label">Save Count Tag</span>
                        <span class="count-tag-save-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/count-tag-scan.css') }}">
@endpush

@push('addon-script')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="{{ url('assets/scripts/modules/count-tag-non-fg-scan.js') }}"></script>
@endpush
