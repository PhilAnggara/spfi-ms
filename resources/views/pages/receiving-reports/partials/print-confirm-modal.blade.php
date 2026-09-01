@php
    $modalId = $modalId ?? 'rrPrintConfirm-'.$receivingReport->id;
    $suggestedNumber = $nextRrNumber ?? '';
    $rrNumberValue = old('rr_number', $receivingReport->rr_number ?: $suggestedNumber);
    $paperWidthMm = (int) config('receiving-report.paper.width_mm', 215);
    $paperHeightMm = (int) config('receiving-report.paper.height_mm', 160);
    $shouldReopenPrintModal = $errors->has('rr_number')
        && (int) old('print_confirm_id') === (int) $receivingReport->id;
    $calibrationProfiles = $rrCalibrationProfiles ?? collect();
    $designAnchor = $rrDesignAnchor ?? ['x' => 0, 'y' => 0, 'label' => 'Top-left corner of the background table'];
    $previewBaseUrl = route('receiving-reports.print', ['receivingReport' => $receivingReport, 'mode' => 'preview']);
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
            action="{{ route('receiving-reports.print', ['receivingReport' => $receivingReport, 'mode' => 'print']) }}"
            target="_blank"
            class="modal-content document-print-confirm-form"
            data-sync-from="{{ $syncFromInputId ?? '' }}"
        >
            @csrf
            <input type="hidden" name="print_confirm_id" value="{{ $receivingReport->id }}">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}Label">Confirm RR Number</h5>
                    <small class="text-muted">Edit if the paper form number is different</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="{{ $modalId }}-number">RR Number</label>
                <input
                    type="text"
                    id="{{ $modalId }}-number"
                    name="rr_number"
                    class="form-control document-print-confirm-number @error('rr_number'){{ $shouldReopenPrintModal ? ' is-invalid' : '' }}@enderror"
                    value="{{ $shouldReopenPrintModal ? old('rr_number', $rrNumberValue) : ($receivingReport->rr_number ?: $suggestedNumber) }}"
                    required
                    autocomplete="off"
                    aria-invalid="{{ $shouldReopenPrintModal ? 'true' : 'false' }}"
                >
                <input type="hidden" name="rr_number_suggested" value="{{ $suggestedNumber }}">
                @if ($shouldReopenPrintModal)
                    @error('rr_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                @endif
                <div class="form-text">
                    This number will be saved before the PDF opens in a new tab.
                </div>

                @include('partials.print-calibration-fields', [
                    'documentType' => 'RR',
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
