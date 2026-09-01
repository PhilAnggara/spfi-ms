@extends('layouts.app')
@section('title', ' | Print Calibration')

@push('addon-style')
    <style>
        .print-calibration-tab-panels {
            display: grid;
            isolation: isolate;
        }

        .print-calibration-tab-panel {
            grid-area: 1 / 1;
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
            will-change: opacity, transform;
        }

        .print-calibration-tab-panel.is-active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
            z-index: 1;
        }

        .print-calibration-tab-panels.is-switching .print-calibration-tab-panel.is-active {
            transition-duration: 0.16s;
        }
    </style>
@endpush

@section('content')
<div
    class="page-heading"
    data-print-calibration-index
    data-active-type="{{ $activeType }}"
    data-add-profile-url="{{ route('print-calibration-profiles.calibrate') }}"
>
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Print Calibration</h3>
                <p class="text-muted mb-0">Calibration profiles for RR &amp; TS pre-printed form alignment.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    @can('manage-print-calibration')
                    <a
                        href="{{ route('print-calibration-profiles.calibrate', ['type' => $activeType]) }}"
                        class="btn btn-sm icon icon-left btn-outline-success print-calibration-add-profile"
                    >
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Profile
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <ul class="nav nav-tabs mb-3" role="tablist">
            @foreach (\App\Models\PrintCalibrationProfile::DOCUMENT_TYPES as $type)
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        class="nav-link print-calibration-tab {{ $activeType === $type ? 'active' : '' }}"
                        data-type="{{ $type }}"
                        role="tab"
                        aria-selected="{{ $activeType === $type ? 'true' : 'false' }}"
                    >
                        {{ $type }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="print-calibration-tab-panels">
            @foreach ($tabs as $documentType => $tab)
                <div
                    class="print-calibration-tab-panel {{ $activeType === $documentType ? 'is-active' : '' }}"
                    data-type="{{ $documentType }}"
                    role="tabpanel"
                >
                    @include('pages.print-calibration-profiles.partials.tab-panel', [
                        'documentType' => $documentType,
                        'profiles' => $tab['profiles'],
                        'designAnchor' => $tab['designAnchor'],
                        'paperWidthMm' => $tab['paperWidthMm'],
                        'paperHeightMm' => $tab['paperHeightMm'],
                    ])
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/print-calibration-index.js') }}"></script>
@endpush
