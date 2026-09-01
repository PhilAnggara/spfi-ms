@php
    $documentType = strtoupper((string) ($documentType ?? 'RR'));
    $calibrationProfiles = $calibrationProfiles ?? collect();
    $defaultProfile = $defaultCalibrationProfile ?? $calibrationProfiles->firstWhere('is_default', true);
    $designAnchor = $designAnchor ?? ['x' => 0, 'y' => 0, 'label' => 'Top-left corner of the background table'];
    $previewBaseUrl = $previewBaseUrl ?? '';
    $modalId = $modalId ?? 'printConfirm';
    $storageKey = "spfi-print-calibration-{$documentType}";
    $initialMeasuredX = old('measured_anchor_x_mm', $defaultProfile?->measured_anchor_x_mm ?? $designAnchor['x']);
    $initialMeasuredY = old('measured_anchor_y_mm', $defaultProfile?->measured_anchor_y_mm ?? $designAnchor['y']);
    $initialProfileId = old('calibration_profile_id', $defaultProfile?->id);
    $collapseId = $modalId.'-calibration-collapse';
@endphp

@once
    @push('addon-style')
        <style>
            .print-calibration-panel {
                overflow: hidden;
                border-left-width: 4px !important;
            }

            .print-calibration-toggle {
                width: 100%;
                border: 0;
                background-color: rgba(var(--bs-primary-rgb), 0.08);
                text-align: left;
                cursor: pointer;
                transition: background-color 0.15s ease, box-shadow 0.15s ease;
            }

            .print-calibration-toggle:hover {
                background-color: rgba(var(--bs-primary-rgb), 0.14);
            }

            .print-calibration-toggle:focus-visible {
                background-color: rgba(var(--bs-primary-rgb), 0.14);
                outline: 2px solid rgba(var(--bs-primary-rgb), 0.45);
                outline-offset: -2px;
                box-shadow: inset 0 0 0 1px rgba(var(--bs-primary-rgb), 0.2);
            }

            .print-calibration-toggle-action {
                border: 1px solid rgba(var(--bs-primary-rgb), 0.35);
                background-color: #fff;
                color: var(--bs-primary);
                box-shadow: 0 1px 2px rgba(var(--bs-primary-rgb), 0.12);
                transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
            }

            .print-calibration-toggle:hover .print-calibration-toggle-action,
            .print-calibration-toggle:focus-visible .print-calibration-toggle-action {
                background-color: var(--bs-primary);
                border-color: var(--bs-primary);
                color: #fff;
            }

            .print-calibration-chevron {
                transition: transform 0.2s ease;
            }

            .print-calibration-toggle[aria-expanded="false"] .print-calibration-chevron {
                animation: print-calibration-chevron-hint 2.4s ease-in-out infinite;
            }

            .print-calibration-toggle[aria-expanded="true"] .print-calibration-chevron {
                transform: rotate(180deg);
                animation: none;
            }

            @keyframes print-calibration-chevron-hint {
                0%, 100% {
                    transform: translateY(0);
                }

                50% {
                    transform: translateY(2px);
                }
            }

            .print-calibration-body {
                background-color: #f9f9f9;
            }
        </style>
    @endpush
@endonce

<div
    class="print-calibration-panel border border-primary rounded mt-3 shadow-sm bg-light-primary"
    data-print-calibration-panel
    data-document-type="{{ $documentType }}"
    data-storage-key="{{ $storageKey }}"
    data-preview-base-url="{{ $previewBaseUrl }}"
    data-design-x="{{ $designAnchor['x'] }}"
    data-design-y="{{ $designAnchor['y'] }}"
>
    <button
        type="button"
        class="print-calibration-toggle d-flex align-items-center gap-3 px-3 py-3"
        data-bs-toggle="collapse"
        data-bs-target="#{{ $collapseId }}"
        aria-expanded="false"
        aria-controls="{{ $collapseId }}"
        title="Open paper position adjustment options"
    >
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white flex-shrink-0 shadow-sm" style="width: 2.5rem; height: 2.5rem;">
            <i class="fa-duotone fa-solid fa-ruler-combined" aria-hidden="true"></i>
        </span>

        <span class="flex-grow-1 min-w-0">
            <span class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <span class="fw-semibold text-primary">Paper position adjustment</span>
                <span class="badge bg-white text-primary border border-primary-subtle">Optional</span>
            </span>
            <span class="d-block small text-body-secondary">
                Print misaligned?
                <span class="text-primary fw-semibold text-decoration-underline">Click here</span>
                to measure or fine-tune before printing.
            </span>
        </span>

        <span class="print-calibration-toggle-action d-inline-flex align-items-center gap-2 flex-shrink-0 rounded-pill px-3 py-2 small fw-semibold">
            <i class="fa-solid fa-hand-pointer d-none d-md-inline" aria-hidden="true"></i>
            <span class="print-calibration-toggle-label">Show options</span>
            <i class="fa-solid fa-chevron-down print-calibration-chevron" aria-hidden="true"></i>
        </span>
    </button>

    <div class="collapse border-top" id="{{ $collapseId }}">
        <div class="p-3 print-calibration-body">
            <p class="small text-muted mb-2">
                Measure from the <strong>top-left corner of the paper</strong> to the
                <strong>{{ $designAnchor['label'] }}</strong>, then enter the distance in mm.
            </p>

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="{{ $modalId }}-measured-x">Distance to the right (mm)</label>
                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        class="form-control form-control-sm print-calibration-measured-x"
                        id="{{ $modalId }}-measured-x"
                        name="measured_anchor_x_mm"
                        value="{{ $initialMeasuredX }}"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1" for="{{ $modalId }}-measured-y">Distance downward (mm)</label>
                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        class="form-control form-control-sm print-calibration-measured-y"
                        id="{{ $modalId }}-measured-y"
                        name="measured_anchor_y_mm"
                        value="{{ $initialMeasuredY }}"
                    >
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1" for="{{ $modalId }}-calibration-profile">Saved profile</label>
                <select
                    class="form-select form-select-sm print-calibration-profile"
                    id="{{ $modalId }}-calibration-profile"
                    name="calibration_profile_id"
                >
                    <option value="">— Manual measurement —</option>
                    @foreach ($calibrationProfiles as $profile)
                        <option
                            value="{{ $profile->id }}"
                            data-measured-x="{{ $profile->measured_anchor_x_mm }}"
                            data-measured-y="{{ $profile->measured_anchor_y_mm }}"
                            @selected((string) $initialProfileId === (string) $profile->id)
                        >
                            {{ $profile->name }}@if ($profile->is_default) (default)@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="small text-muted">Fine-tune measured values:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Fine-tune calibration">
                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="x" data-delta="-0.5" title="Move left 0.5 mm">&larr;</button>
                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="x" data-delta="0.5" title="Move right 0.5 mm">&rarr;</button>
                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="y" data-delta="-0.5" title="Move up 0.5 mm">&uarr;</button>
                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="y" data-delta="0.5" title="Move down 0.5 mm">&darr;</button>
                </div>
                <span class="small text-muted">±0.5 mm per click</span>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary print-calibration-preview">
                <i class="fa-duotone fa-solid fa-eye me-1"></i>
                Preview adjustment
            </button>
        </div>
    </div>
</div>

@include('partials.print-setup-guide', [
    'paperWidthMm' => $paperWidthMm ?? (int) config($documentType === 'RR' ? 'receiving-report.paper.width_mm' : 'transfer-slip.paper.width_mm', 215),
    'paperHeightMm' => $paperHeightMm ?? (int) config($documentType === 'RR' ? 'receiving-report.paper.height_mm' : 'transfer-slip.paper.height_mm', 160),
    'documentType' => $documentType,
])
