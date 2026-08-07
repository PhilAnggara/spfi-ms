@php
    $hasBaseline = array_key_exists('dept_prs_total', $metrics ?? []);
@endphp

@if ($hasBaseline)
    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly Department PRS (Last 12 Months)',
            'chartId' => 'chart-dept-monthly-prs',
            'col' => 'col-12 col-lg-8',
        ])
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Department PRS Status',
            'chartId' => 'chart-dept-prs-status',
            'col' => 'col-12 col-lg-4',
        ])
    </div>
@endif
