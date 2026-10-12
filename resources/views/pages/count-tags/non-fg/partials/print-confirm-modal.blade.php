@php
    $modalId = $modalId ?? 'ctPrintConfirm-'.$tag->id;
    $suggestedNumber = $nextCtNumber ?? '';
    $ctNumberValue = old('count_tag_number', $tag->count_tag_number ?: $suggestedNumber);
    $shouldReopenPrintModal = $errors->has('count_tag_number')
        && (int) old('print_confirm_id') === (int) $tag->id;
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
            action="{{ route('count-tags.non-fg.print', $tag) }}"
            target="_blank"
            class="modal-content document-print-confirm-form"
            data-sync-from="{{ $syncFromInputId ?? '' }}"
        >
            @csrf
            <input type="hidden" name="print_confirm_id" value="{{ $tag->id }}">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="{{ $modalId }}Label">Confirm Count Tag Number</h5>
                    <small class="text-muted">Edit if the paper label number is different</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 small text-muted">
                    {{ $tag->item_code }}
                    @if ($tag->item?->name)
                        · {{ $tag->item->name }}
                    @endif
                </div>
                <label class="form-label" for="{{ $modalId }}-number">Count Tag Number</label>
                <input
                    type="text"
                    id="{{ $modalId }}-number"
                    name="count_tag_number"
                    class="form-control document-print-confirm-number @error('count_tag_number'){{ $shouldReopenPrintModal ? ' is-invalid' : '' }}@enderror"
                    value="{{ $shouldReopenPrintModal ? old('count_tag_number', $ctNumberValue) : ($tag->count_tag_number ?: $suggestedNumber) }}"
                    required
                    autocomplete="off"
                    aria-invalid="{{ $shouldReopenPrintModal ? 'true' : 'false' }}"
                >
                <input type="hidden" name="count_tag_number_suggested" value="{{ $suggestedNumber }}">
                @if ($shouldReopenPrintModal)
                    @error('count_tag_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                @endif
                <div class="form-text">
                    This number will be saved before the PDF opens in a new tab.
                </div>
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
