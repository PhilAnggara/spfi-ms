@extends('pages.dashboard.layout')

@section('dashboard-content')
    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'RR ('.$dashboardMonthLabel.')',
            'value' => $metrics['rr_this_month'] ?? 0,
            'icon' => 'fa-boxes-stacked',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'SWS Open',
            'value' => $metrics['sws_open'] ?? 0,
            'icon' => 'fa-warehouse',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Deliveries ('.$dashboardMonthLabel.')',
            'value' => $metrics['deliveries_this_month'] ?? 0,
            'icon' => 'fa-truck',
            'tone' => 'green',
        ])
    </div>

    @include('pages.dashboard.partials.department-kpis')

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly Receiving Reports',
            'chartId' => 'chart-monthly-rr',
            'col' => 'col-12 col-lg-6',
        ])
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly Outbound (TS + Delivery)',
            'chartId' => 'chart-monthly-outbound',
            'col' => 'col-12 col-lg-6',
        ])
    </div>

    @include('pages.dashboard.partials.department-charts')

    <div class="row g-3 dashboard-recent-row">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Receiving Reports',
            'icon' => 'fa-boxes-stacked',
            'items' => $dashboard['lists']['recent_rr'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Transfer Slips',
            'icon' => 'fa-right-left',
            'items' => $dashboard['lists']['recent_ts'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Deliveries',
            'icon' => 'fa-truck',
            'items' => $dashboard['lists']['recent_deliveries'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Department PRS',
            'icon' => 'fa-cart-shopping',
            'items' => $dashboard['lists']['dept_recent_prs'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent SWS',
            'icon' => 'fa-warehouse',
            'items' => $dashboard['lists']['dept_recent_sws'] ?? [],
        ])
    </div>
@endsection
