@php
    $label = $label ?? 'Metric';
    $value = $value ?? 0;
    $icon = $icon ?? 'fa-chart-simple';
    $tone = $tone ?? 'blue';
    $prefix = $prefix ?? '';
    $delay = $delay ?? 0;
    $col = $col ?? 'col-6 col-md';
@endphp

<div class="{{ $col }}" data-aos="fade-up" data-aos-delay="{{ $delay }}">
    <div class="card shadow-sm border-0 dashboard-kpi-card h-100">
        <div class="card-body px-4 py-4">
            <div class="d-flex align-items-start gap-3">
                <div class="stats-icon {{ $tone }} mb-0">
                    <i class="fa-regular {{ $icon }}"></i>
                </div>
                <div class="min-w-0">
                    <h6 class="text-muted font-semibold mb-1">{{ $label }}</h6>
                    <h5 class="font-extrabold mb-0 text-truncate">
                        {{ $prefix }}{{ is_numeric($value) ? number_format((float) $value, 0, ',', '.') : $value }}
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>
