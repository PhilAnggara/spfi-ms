@extends('layouts.app')
@section('title', ' | Calibrate Print Profile')

@section('content')
@php
    $isEdit = $profile !== null;
    $measuredX = old('measured_anchor_x_mm', $profile?->measured_anchor_x_mm ?? $designAnchor['x']);
    $measuredY = old('measured_anchor_y_mm', $profile?->measured_anchor_y_mm ?? $designAnchor['y']);
@endphp

<div
    class="page-heading"
    data-print-calibration-admin
    data-preview-url="{{ route('print-calibration-profiles.preview-sample') }}"
    data-document-type="{{ $documentType }}"
>
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-8 order-md-1">
                <h3>{{ $isEdit ? 'Edit' : 'Create' }} {{ $documentType }} Calibration Profile</h3>
                <p class="text-muted mb-0">Measure the table position on physical paper, preview, then save the profile.</p>
            </div>
            <div class="col-12 col-md-4 order-md-2">
                <div class="float-md-end">
                    <a href="{{ route('print-calibration-profiles.index', ['type' => $documentType]) }}" class="btn btn-sm btn-outline-secondary">
                        Back to list
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form
                            method="post"
                            action="{{ $isEdit ? route('print-calibration-profiles.update', $profile) : route('print-calibration-profiles.store') }}"
                            class="print-calibration-admin-form"
                        >
                            @csrf
                            @if ($isEdit)
                                @method('put')
                            @endif

                            <input type="hidden" name="document_type" value="{{ $documentType }}">

                            <div class="mb-3">
                                <label class="form-label" for="profile-name">Profile name</label>
                                <input
                                    type="text"
                                    id="profile-name"
                                    name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $profile?->name) }}"
                                    required
                                >
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <p class="small text-muted">
                                Measure from the top-left corner of the paper to {{ $designAnchor['label'] }}.
                                Design reference: {{ number_format($designAnchor['x'], 2) }} / {{ number_format($designAnchor['y'], 2) }} mm.
                            </p>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label" for="measured-x">Distance to the right (mm)</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        id="measured-x"
                                        name="measured_anchor_x_mm"
                                        class="form-control print-calibration-measured-x @error('measured_anchor_x_mm') is-invalid @enderror"
                                        value="{{ $measuredX }}"
                                        required
                                    >
                                    @error('measured_anchor_x_mm')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="measured-y">Distance downward (mm)</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        id="measured-y"
                                        name="measured_anchor_y_mm"
                                        class="form-control print-calibration-measured-y @error('measured_anchor_y_mm') is-invalid @enderror"
                                        value="{{ $measuredY }}"
                                        required
                                    >
                                    @error('measured_anchor_y_mm')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <span class="small text-muted">Fine-tune measured values:</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="x" data-delta="-0.5">&larr;</button>
                                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="x" data-delta="0.5">&rarr;</button>
                                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="y" data-delta="-0.5">&uarr;</button>
                                    <button type="button" class="btn btn-outline-secondary print-calibration-nudge" data-axis="y" data-delta="0.5">&darr;</button>
                                </div>
                                <span class="small text-muted">±0.5 mm per click</span>
                            </div>

                            <div class="form-check mb-3">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_default"
                                    id="is-default"
                                    value="1"
                                    @checked(old('is_default', $profile?->is_default))
                                >
                                <label class="form-check-label" for="is-default">Set as default profile for {{ $documentType }}</label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="description">Description</label>
                                <textarea
                                    id="description"
                                    name="description"
                                    class="form-control"
                                    rows="2"
                                >{{ old('description', $profile?->description) }}</textarea>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary print-calibration-preview">
                                    Open preview in new tab
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    {{ $isEdit ? 'Update' : 'Save' }} Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @include('partials.print-setup-guide', [
                    'paperWidthMm' => (int) $paperConfig['width_mm'],
                    'paperHeightMm' => (int) $paperConfig['height_mm'],
                    'documentType' => $documentType,
                    'collapseSuffix' => 'admin-calibrate',
                ])
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <span>Live preview</span>
                        <span class="small text-muted print-calibration-preview-status">Loading…</span>
                    </div>
                    <div class="card-body p-0 position-relative">
                        <iframe
                            title="Calibration preview"
                            class="w-100 print-calibration-preview-frame"
                            style="min-height: 520px; border: 0;"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/document-print-calibration.js') }}"></script>
@endpush
