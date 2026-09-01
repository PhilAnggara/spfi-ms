@php
    $modalId = $modalId ?? 'tsPrintConfirm-'.$transferSlip->id;
    $suggestedNumber = $nextTsNumber ?? '';
    $tsNumberValue = old('ts_number', $transferSlip->ts_number ?: $suggestedNumber);
    $paperWidthMm = (int) config('transfer-slip.paper.width_mm', 215);
    $paperHeightMm = (int) config('transfer-slip.paper.height_mm', 105);
    $shouldReopenPrintModal = $errors->has('ts_number')
        && (int) old('print_confirm_id') === (int) $transferSlip->id;
    $calibrationProfiles = $tsCalibrationProfiles ?? collect();
    $designAnchor = $tsDesignAnchor ?? ['x' => 0, 'y' => 0, 'label' => 'Top-left corner of the background table'];
    $previewBaseUrl = route('transfer-slips.print', ['transferSlip' => $transferSlip->id, 'mode' => 'preview']);
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
    @if ($shouldReopenPrintModal) data-auto-show="1" @endif
>
    <div class="modal-dialog modal-lg">
        <form
            method="post"
            action="{{ route('transfer-slips.print', ['transferSlip' => $transferSlip->id, 'mode' => 'print']) }}"
            target="_blank"
            class="modal-content document-print-confirm-form"
            data-sync-from="{{ $syncFromInputId ?? '' }}"
        >
            @csrf
            <input type="hidden" name="print_confirm_id" value="{{ $transferSlip->id }}">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}Label">Confirm TS Number</h5>
                    <small class="text-muted">Edit if the paper form number is different</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="{{ $modalId }}-number">TS Number</label>
                <input
                    type="text"
                    id="{{ $modalId }}-number"
                    name="ts_number"
                    class="form-control document-print-confirm-number @error('ts_number'){{ $shouldReopenPrintModal ? ' is-invalid' : '' }}@enderror"
                    value="{{ $shouldReopenPrintModal ? old('ts_number', $tsNumberValue) : ($transferSlip->ts_number ?: $suggestedNumber) }}"
                    required
                    autocomplete="off"
                    aria-invalid="{{ $shouldReopenPrintModal ? 'true' : 'false' }}"
                >
                <input type="hidden" name="ts_number_suggested" value="{{ $suggestedNumber }}">
                @if ($shouldReopenPrintModal)
                    @error('ts_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                @endif
                <div class="form-text">
                    This number will be saved before the PDF opens in a new tab.
                </div>

                @include('partials.print-calibration-fields', [
                    'documentType' => 'TS',
                    'modalId' => $modalId,
                    'calibrationProfiles' => $calibrationProfiles,
                    'designAnchor' => $designAnchor,
                    'previewBaseUrl' => $previewBaseUrl,
                    'paperWidthMm' => $paperWidthMm,
                    'paperHeightMm' => $paperHeightMm,
                    'defaultCalibrationProfile' => $calibrationProfiles->firstWhere('is_default', true),
                ])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-duotone fa-solid fa-print me-1"></i>
                    Confirm &amp; Print
                </button>
            </div>
        </form>
    </div>
</div>
