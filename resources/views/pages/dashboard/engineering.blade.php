@extends('pages.dashboard.layout')

@section('dashboard-content')
    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PRS ('.$dashboardMonthLabel.')',
            'value' => $metrics['prs_this_month'] ?? 0,
            'icon' => 'fa-cart-shopping',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Open PRS',
            'value' => $metrics['prs_open'] ?? 0,
            'icon' => 'fa-folder-open',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Completed PRS',
            'value' => $metrics['prs_completed'] ?? 0,
            'icon' => 'fa-circle-check',
            'tone' => 'green',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Total PRS',
            'value' => $metrics['prs_total'] ?? 0,
            'icon' => 'fa-list',
            'tone' => 'red',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Engineering PRS Status',
            'chartId' => 'chart-visitors-profile',
            'col' => 'col-12',
        ])
    </div>

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Engineering PRS',
            'icon' => 'fa-cart-shopping',
            'col' => 'col-12',
            'items' => $dashboard['lists']['recent_prs'] ?? [],
        ])
    </div>
@endsection
