@extends('pages.dashboard.layout')

@section('dashboard-content')
    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PRS ('.$dashboardMonthLabel.')',
            'value' => $metrics['prs_this_month'] ?? 0,
            'icon' => 'fa-cart-shopping',
            'tone' => 'blue',
            'col' => 'col-6 col-lg-3',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Open PRS',
            'value' => $metrics['prs_open'] ?? 0,
            'icon' => 'fa-folder-open',
            'tone' => 'purple',
            'col' => 'col-6 col-lg-3',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Completed PRS',
            'value' => $metrics['prs_completed'] ?? 0,
            'icon' => 'fa-circle-check',
            'tone' => 'green',
            'col' => 'col-6 col-lg-3',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Total PRS',
            'value' => $metrics['prs_total'] ?? 0,
            'icon' => 'fa-list',
            'tone' => 'red',
            'col' => 'col-6 col-lg-3',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'SWS ('.$dashboardMonthLabel.')',
            'value' => $metrics['sws_this_month'] ?? 0,
            'icon' => 'fa-warehouse',
            'tone' => 'blue',
            'col' => 'col-6',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'SWS Open',
            'value' => $metrics['sws_open'] ?? 0,
            'icon' => 'fa-box-open',
            'tone' => 'purple',
            'col' => 'col-6',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly Department PRS (Last 12 Months)',
            'chartId' => 'chart-profile-visit',
            'col' => 'col-12 col-lg-8',
        ])
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Department PRS Status',
            'chartId' => 'chart-visitors-profile',
            'col' => 'col-12 col-lg-4',
        ])
    </div>

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Department PRS',
            'icon' => 'fa-cart-shopping',
            'items' => $dashboard['lists']['recent_prs'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Department SWS',
            'icon' => 'fa-warehouse',
            'items' => $dashboard['lists']['recent_sws'] ?? [],
        ])
    </div>
@endsection
