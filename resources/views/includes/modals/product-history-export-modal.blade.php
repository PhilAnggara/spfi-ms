@php
    $canViewPurchaseHistory = $canViewPurchaseHistory ?? false;
    $canViewCanvassingHistory = $canViewCanvassingHistory ?? false;
    $defaultHistoryType = $canViewPurchaseHistory ? 'purchase' : 'canvassing';
    $defaultDateFrom = now()->subYears(5)->toDateString();
    $defaultDateTo = now()->toDateString();
    $historyTypeCount = (int) $canViewPurchaseHistory + (int) $canViewCanvassingHistory;
@endphp

<div class="modal fade" id="product-history-export-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content product-history-export-modal">
            <form method="POST" action="{{ route('product.history.export') }}" id="product-history-export-form">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title mb-1">Export History</h5>
                        <small class="text-muted" id="product-history-export-summary">Selected products: 0</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">What do you want to export?</label>
                            <div class="product-history-type-grid {{ $historyTypeCount === 1 ? 'is-single' : '' }}">
                                @if ($canViewPurchaseHistory)
                                    <label class="product-history-type-card" for="product-history-type-purchase">
                                        <input
                                            class="product-history-type-input"
                                            type="radio"
                                            name="history_type"
                                            id="product-history-type-purchase"
                                            value="purchase"
                                            @checked($defaultHistoryType === 'purchase')
                                            required>
                                        <span class="product-history-type-body">
                                            <span class="product-history-type-icon is-purchase">
                                                <i class="fa-duotone fa-solid fa-file-invoice-dollar"></i>
                                            </span>
                                            <span class="product-history-type-copy">
                                                <span class="product-history-type-title">Purchase History</span>
                                                <span class="product-history-type-desc">PO lines, prices, and suppliers by PO date</span>
                                            </span>
                                            <span class="product-history-type-check" aria-hidden="true">
                                                <i class="fa-solid fa-check"></i>
                                            </span>
                                        </span>
                                    </label>
                                @endif
                                @if ($canViewCanvassingHistory)
                                    <label class="product-history-type-card" for="product-history-type-canvassing">
                                        <input
                                            class="product-history-type-input"
                                            type="radio"
                                            name="history_type"
                                            id="product-history-type-canvassing"
                                            value="canvassing"
                                            @checked($defaultHistoryType === 'canvassing')
                                            required>
                                        <span class="product-history-type-body">
                                            <span class="product-history-type-icon is-canvassing">
                                                <i class="fa-duotone fa-solid fa-scale-balanced"></i>
                                            </span>
                                            <span class="product-history-type-copy">
                                                <span class="product-history-type-title">Canvassing History</span>
                                                <span class="product-history-type-desc">Supplier quotes and selection by canvass date</span>
                                            </span>
                                            <span class="product-history-type-check" aria-hidden="true">
                                                <i class="fa-solid fa-check"></i>
                                            </span>
                                        </span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">Period</label>
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small text-muted mb-1" for="product-history-date-from">From</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="product-history-date-from"
                                        name="date_from"
                                        value="{{ $defaultDateFrom }}"
                                        required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small text-muted mb-1" for="product-history-date-to">To</label>
                                    <input
                                        type="date"
                                        class="form-control"
                                        id="product-history-date-to"
                                        name="date_to"
                                        value="{{ $defaultDateTo }}"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="product-history-export-hidden-inputs"></div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex flex-wrap gap-2 justify-content-between">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="format" value="pdf" formtarget="_blank" class="btn btn-sm icon icon-left btn-outline-secondary">
                            <i class="fa-thin fa-file-pdf"></i>
                            Export PDF
                        </button>
                        <button type="submit" name="format" value="excel" class="btn btn-sm icon icon-left btn-success">
                            <i class="fa-thin fa-file-spreadsheet"></i>
                            Export Excel
                        </button>
                    </div>
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .product-history-type-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .product-history-type-grid.is-single {
        grid-template-columns: 1fr;
    }

    .product-history-type-card {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .product-history-type-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .product-history-type-body {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-height: 100%;
        padding: 0.9rem 0.85rem;
        border: 1.5px solid #e5e7eb;
        border-radius: 0.85rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease, background 0.18s ease;
    }

    .product-history-type-card:hover .product-history-type-body {
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }

    .product-history-type-input:checked + .product-history-type-body {
        border-color: #435ebe;
        background: linear-gradient(180deg, #f5f7ff 0%, #eef2ff 100%);
        box-shadow: 0 0 0 3px rgba(67, 94, 190, 0.12);
    }

    .product-history-type-input:focus-visible + .product-history-type-body {
        outline: 2px solid #435ebe;
        outline-offset: 2px;
    }

    .product-history-type-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.4rem;
        height: 2.4rem;
        border-radius: 0.75rem;
        flex-shrink: 0;
        font-size: 1.05rem;
    }

    .product-history-type-icon.is-purchase {
        color: #1d4ed8;
        background: rgba(67, 94, 190, 0.12);
    }

    .product-history-type-icon.is-canvassing {
        color: #0f766e;
        background: rgba(13, 148, 136, 0.12);
    }

    .product-history-type-input:checked + .product-history-type-body .product-history-type-icon.is-purchase {
        color: #fff;
        background: #435ebe;
    }

    .product-history-type-input:checked + .product-history-type-body .product-history-type-icon.is-canvassing {
        color: #fff;
        background: #0f766e;
    }

    .product-history-type-copy {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 0;
        padding-right: 1.1rem;
    }

    .product-history-type-title {
        font-weight: 650;
        font-size: 0.92rem;
        color: #111827;
        line-height: 1.25;
    }

    .product-history-type-desc {
        font-size: 0.75rem;
        color: #6b7280;
        line-height: 1.35;
    }

    .product-history-type-check {
        position: absolute;
        top: 0.65rem;
        right: 0.65rem;
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        color: #fff;
        background: #435ebe;
        opacity: 0;
        transform: scale(0.8);
        transition: opacity 0.15s ease, transform 0.15s ease;
    }

    .product-history-type-input:checked + .product-history-type-body .product-history-type-check {
        opacity: 1;
        transform: scale(1);
    }

    @media (max-width: 575.98px) {
        .product-history-type-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
