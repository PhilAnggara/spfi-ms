@php
    $fieldName = $fieldName ?? 'direction';
    $rowId = $rowId ?? '0';
    $selected = $selected ?? 'in';
    $readonly = (bool) ($readonly ?? false);
    $isOut = $selected === 'out';
@endphp

@if ($readonly)
    <span
        class="inv-direction-badge inv-direction-badge--{{ $isOut ? 'out' : 'in' }}"
        title="{{ $isOut ? 'Stock out' : 'Stock in' }}"
        aria-label="{{ $isOut ? 'Out' : 'In' }}"
    >
        <i class="fa-solid {{ $isOut ? 'fa-arrow-up-from-bracket' : 'fa-arrow-down-to-bracket' }}"></i>
    </span>
@else
    <div class="inv-direction-toggle" role="radiogroup" aria-label="Direction">
        <input
            type="radio"
            class="btn-check inv-direction-input inv-direction-in"
            name="{{ $fieldName }}"
            id="inv-dir-in-{{ $rowId }}"
            value="in"
            @checked(! $isOut)
        >
        <label class="inv-direction-btn inv-direction-btn-in" for="inv-dir-in-{{ $rowId }}">
            <i class="fa-solid fa-plus"></i>
            <span class="inv-direction-label">In</span>
        </label>

        <input
            type="radio"
            class="btn-check inv-direction-input inv-direction-out"
            name="{{ $fieldName }}"
            id="inv-dir-out-{{ $rowId }}"
            value="out"
            @checked($isOut)
        >
        <label class="inv-direction-btn inv-direction-btn-out" for="inv-dir-out-{{ $rowId }}">
            <i class="fa-solid fa-minus"></i>
            <span class="inv-direction-label">Out</span>
        </label>
    </div>
@endif
