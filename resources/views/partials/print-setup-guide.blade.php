@php
    $paperWidthMm = (int) ($paperWidthMm ?? 215);
    $paperHeightMm = (int) ($paperHeightMm ?? 160);
    $documentType = strtoupper((string) ($documentType ?? 'RR'));
    $formName = $documentType === 'RR' ? 'RR Form' : 'TS Form';
    $widthInches = number_format($paperWidthMm / 25.4, 2);
    $heightInches = number_format($paperHeightMm / 25.4, 2);
    $collapseId = 'printSetupGuide-'.strtolower($documentType).'-'.($collapseSuffix ?? 'default');
@endphp

<div class="alert alert-light border mt-3 mb-0">
    <div class="fw-semibold mb-1">Paper form</div>
    <div class="mb-2">
        <span class="badge bg-light-primary text-primary">{{ $formName }} {{ $paperWidthMm }} &times; {{ $paperHeightMm }} mm</span>
        <span class="text-muted small ms-1">({{ $paperWidthMm }} &times; {{ $paperHeightMm }} mm)</span>
    </div>

    <div class="fw-semibold mb-1">Print checklist</div>
    <ul class="mb-2 ps-3 small">
        <li>Scale: <strong>Actual size / 100%</strong> — do not use Fit to page.</li>
        <li>Orientation: <strong>Portrait</strong> matching the pre-printed form.</li>
        <li>
            Paper size: use <strong>Custom Form {{ $paperWidthMm }} &times; {{ $paperHeightMm }} mm</strong>
            (not Letter/Legal).
        </li>
    </ul>

    <button
        class="btn btn-link btn-sm p-0 text-decoration-none"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#{{ $collapseId }}"
        aria-expanded="false"
        aria-controls="{{ $collapseId }}"
    >
        Windows custom paper form setup (Epson LX-310)
    </button>

    <div class="collapse mt-2" id="{{ $collapseId }}">
        <ol class="small ps-3 mb-0">
            <li>Open <strong>Control Panel → Devices and Printers</strong>.</li>
            <li>Click <strong>Print Server Properties</strong> (File menu if needed).</li>
            <li>Open the <strong>Forms</strong> tab and check <strong>Create a new form</strong>.</li>
            <li>
                Create <strong>{{ $formName }}</strong>:
                width <strong>{{ $paperWidthMm }} mm ({{ $widthInches }}")</strong>,
                height <strong>{{ $paperHeightMm }} mm ({{ $heightInches }}")</strong>.
            </li>
            <li>In <strong>Printer Preferences</strong> for the Epson LX-310, select the custom form.</li>
            <li>When printing the PDF: <strong>100% / Actual size</strong>, Portrait, do not use Fit to page.</li>
        </ol>
        <p class="small text-muted mt-2 mb-0">
            If only Letter/Legal is available, create the custom form first. Without the correct paper size,
            print alignment will be wrong even when calibration values are correct.
        </p>
    </div>
</div>
