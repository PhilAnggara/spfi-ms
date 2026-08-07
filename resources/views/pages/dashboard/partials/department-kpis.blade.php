@php
    $hasBaseline = array_key_exists('dept_prs_total', $metrics ?? []);
@endphp

@if ($hasBaseline)
    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept PRS ('.$dashboardMonthLabel.')',
            'value' => $metrics['dept_prs_this_month'] ?? 0,
            'icon' => 'fa-cart-shopping',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept Open PRS',
            'value' => $metrics['dept_prs_open'] ?? 0,
            'icon' => 'fa-folder-open',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept PRS On Hold',
            'value' => $metrics['dept_prs_on_hold'] ?? 0,
            'icon' => 'fa-pause',
            'tone' => 'red',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept Completed PRS',
            'value' => $metrics['dept_prs_completed'] ?? 0,
            'icon' => 'fa-circle-check',
            'tone' => 'green',
        ])
    </div>

    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept Total PRS',
            'value' => $metrics['dept_prs_total'] ?? 0,
            'icon' => 'fa-list',
            'tone' => 'red',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept SWS ('.$dashboardMonthLabel.')',
            'value' => $metrics['dept_sws_this_month'] ?? 0,
            'icon' => 'fa-warehouse',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Dept SWS Open',
            'value' => $metrics['dept_sws_open'] ?? 0,
            'icon' => 'fa-box-open',
            'tone' => 'purple',
        ])
    </div>
@endif
