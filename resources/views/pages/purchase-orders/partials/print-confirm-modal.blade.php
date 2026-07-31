@php
    $modalId = $modalId ?? 'poPrintConfirm-'.$purchaseOrder->id;
    $suggestedNumber = $nextPoNumber ?? '';
    $poNumberValue = old('po_number', $purchaseOrder->po_number ?: $suggestedNumber);
    $paperWidthMm = (int) config('purchase-order.paper.width_mm', 215);
    $paperHeightMm = (int) config('purchase-order.paper.height_mm', 160);
    $paperLabel = (string) config('purchase-order.paper.label', "PO Form {$paperWidthMm} x {$paperHeightMm} mm");
    $decimalPlacesDefault = (int) config('purchase-order.print.decimal_places.default', 2);
    $decimalPlacesOptions = config('purchase-order.print.decimal_places.options', range(0, 10));
    $decimalPlacesValue = (int) old('decimal_places', $decimalPlacesDefault);
    $cameFromPrintConfirm = session()->hasOldInput('decimal_places');
    $shouldReopenPrintModal = $cameFromPrintConfirm && ($errors->has('po_number') || $errors->has('decimal_places'));
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
    @if ($shouldReopenPrintModal) data-auto-show="1" @endif
>
    <div class="modal-dialog">
        <form
            method="post"
            action="{{ route('purchase-orders.print', $purchaseOrder) }}"
            target="_blank"
            class="modal-content po-detail-modal document-print-confirm-form po-print-confirm-form"
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
                    class="form-control document-print-confirm-number po-print-confirm-number @error('po_number') is-invalid @enderror"
                    value="{{ $poNumberValue }}"
                    required
                    autocomplete="off"
                    aria-invalid="{{ $errors->has('po_number') ? 'true' : 'false' }}"
                >
                <input type="hidden" name="po_number_suggested" value="{{ $suggestedNumber }}">
                @error('po_number')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    This number will be saved before the PDF opens in a new tab.
                </div>

                <div class="mt-3">
                    <label class="form-label" for="{{ $modalId }}-decimal-places">Decimal places</label>
                    <select
                        id="{{ $modalId }}-decimal-places"
                        name="decimal_places"
                        class="form-select @error('decimal_places') is-invalid @enderror"
                    >
                        @foreach ($decimalPlacesOptions as $option)
                            <option
                                value="{{ $option }}"
                                @selected((int) $option === $decimalPlacesValue)
                            >
                                {{ $option }}@if ((int) $option === $decimalPlacesDefault) (default)@endif
                            </option>
                        @endforeach
                    </select>
                    @error('decimal_places')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Number of digits after the decimal separator for prices and amounts on the printed PO.
                    </div>
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
