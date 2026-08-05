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
            'label' => 'Approved PO Value',
            'value' => $metrics['po_approved_value_this_month'] ?? 0,
            'icon' => 'fa-money-bill-wave',
            'tone' => 'green',
            'prefix' => 'Rp ',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'RR ('.$dashboardMonthLabel.')',
            'value' => $metrics['rr_this_month'] ?? 0,
            'icon' => 'fa-boxes-stacked',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Deliveries ('.$dashboardMonthLabel.')',
            'value' => $metrics['deliveries_this_month'] ?? 0,
            'icon' => 'fa-truck',
            'tone' => 'red',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly PRS Trend (Last 12 Months)',
            'chartId' => 'chart-profile-visit',
            'col' => 'col-12 col-lg-8',
        ])
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'PRS Status Distribution',
            'chartId' => 'chart-visitors-profile',
            'col' => 'col-12 col-lg-4',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'PO Status Distribution',
            'chartId' => 'chart-po-status',
            'col' => 'col-12 col-lg-6',
        ])
    </div>

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent PRS',
            'icon' => 'fa-cart-shopping',
            'items' => $dashboard['lists']['recent_prs'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Purchase Orders',
            'icon' => 'fa-file-invoice',
            'items' => $dashboard['lists']['recent_po'] ?? [],
        ])
    </div>
@endsection
