@extends('pages.dashboard.layout')

@section('dashboard-content')
    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'User Accounts',
            'value' => $metrics['user_accounts'] ?? 0,
            'icon' => 'fa-users',
            'tone' => 'purple',
            'delay' => 0,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PRS ('.$dashboardMonthLabel.')',
            'value' => $metrics['prs_this_month'] ?? 0,
            'icon' => 'fa-cart-shopping',
            'tone' => 'blue',
            'delay' => 50,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Canvass Open',
            'value' => $metrics['canvass_open'] ?? 0,
            'icon' => 'fa-scale-balanced',
            'tone' => 'green',
            'delay' => 100,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PO Pending Approval',
            'value' => $metrics['po_pending_approval'] ?? 0,
            'icon' => 'fa-hourglass-half',
            'tone' => 'red',
            'delay' => 150,
        ])
    </div>

    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Approved PO Value',
            'value' => $metrics['po_approved_value_this_month'] ?? 0,
            'icon' => 'fa-money-bill-wave',
            'tone' => 'green',
            'prefix' => 'Rp ',
            'delay' => 0,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'RR ('.$dashboardMonthLabel.')',
            'value' => $metrics['rr_this_month'] ?? 0,
            'icon' => 'fa-boxes-stacked',
            'tone' => 'blue',
            'delay' => 50,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'SWS Open',
            'value' => $metrics['sws_open'] ?? 0,
            'icon' => 'fa-warehouse',
            'tone' => 'purple',
            'delay' => 100,
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'TS Pending',
            'value' => $metrics['ts_pending'] ?? 0,
            'icon' => 'fa-right-left',
            'tone' => 'red',
            'delay' => 150,
        ])
    </div>

    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Deliveries ('.$dashboardMonthLabel.')',
            'value' => $metrics['deliveries_this_month'] ?? 0,
            'icon' => 'fa-truck',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Departments',
            'value' => $metrics['department_count'] ?? 0,
            'icon' => 'fa-building',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Roles',
            'value' => $metrics['role_count'] ?? 0,
            'icon' => 'fa-user-shield',
            'tone' => 'green',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Users Online',
            'value' => $metrics['users_online'] ?? 0,
            'icon' => 'fa-circle',
            'tone' => 'green',
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
            'title' => 'Top Suppliers by PO Value',
            'chartId' => 'chart-top-suppliers',
            'col' => 'col-12 col-lg-8',
        ])
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'PO Status Distribution',
            'chartId' => 'chart-po-status',
            'col' => 'col-12 col-lg-4',
        ])
    </div>

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

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.active-users-widget', [
            'items' => $dashboard['lists']['active_users'] ?? collect(),
            'onlineCount' => $metrics['users_online'] ?? 0,
            'sessionCount' => $metrics['active_sessions'] ?? 0,
            'canManage' => auth()->user()?->hasAnyRole(['administrator', 'it-staff']) ?? false,
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
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Receiving Reports',
            'icon' => 'fa-boxes-stacked',
            'items' => $dashboard['lists']['recent_rr'] ?? [],
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Users',
            'icon' => 'fa-users',
            'items' => $dashboard['lists']['recent_users'] ?? [],
        ])
    </div>
@endsection
