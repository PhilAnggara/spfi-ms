@php
    $modalId = $modalId ?? 'poPrintConfirm-'.$purchaseOrder->id;
    $suggestedNumber = $nextPoNumber ?? '';
    $poNumberValue = old('po_number', $purchaseOrder->po_number ?: $suggestedNumber);
    $paperWidthMm = (int) config('purchase-order.paper.width_mm', 215);
    $paperHeightMm = (int) config('purchase-order.paper.height_mm', 160);
    $paperLabel = (string) config('purchase-order.paper.label', "PO Form {$paperWidthMm} x {$paperHeightMm} mm");
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
>
    <div class="modal-dialog">
        <form
            method="post"
            action="{{ route('purchase-orders.print', $purchaseOrder) }}"
            target="_blank"
            class="modal-content po-detail-modal po-print-confirm-form"
            data-sync-from="{{ $syncFromInputId ?? '' }}"
        >
            @csrf
            <div class="modal-header po-detail-modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}Label">Confirm PO Number</h5>
                    <small class="text-muted">Edit if the paper form number is different</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="{{ $modalId }}-number">PO Number</label>
                <input
                    type="text"
                    id="{{ $modalId }}-number"
                    name="po_number"
                    class="form-control po-print-confirm-number"
                    value="{{ $poNumberValue }}"
                    required
                    autocomplete="off"
                >
                <input type="hidden" name="po_number_suggested" value="{{ $suggestedNumber }}">
                <div class="form-text">
                    This number will be saved before the PDF opens in a new tab.
                </div>

                <div class="alert alert-light border mt-3 mb-0">
                    <div class="fw-semibold mb-1">Paper form</div>
                    <div class="mb-2">
                        <span class="badge bg-light-primary text-primary">{{ $paperLabel }}</span>
                        <span class="text-muted small ms-1">({{ $paperWidthMm }} &times; {{ $paperHeightMm }} mm)</span>
                    </div>
                    <div class="fw-semibold mb-1">Print checklist</div>
                    <ul class="mb-0 ps-3 small">
                        <li>Select paper/form <strong>{{ $paperWidthMm }} &times; {{ $paperHeightMm }} mm</strong> (Windows custom form if needed).</li>
                        <li>Scale: <strong>Actual size / 100%</strong> — do not use Fit to page.</li>
                        <li>Orientation: <strong>Portrait</strong> matching the pre-printed form.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer po-detail-modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-duotone fa-solid fa-print me-1"></i>
                    Confirm &amp; Print
                </button>
            </div>
        </form>
    </div>
</div>
