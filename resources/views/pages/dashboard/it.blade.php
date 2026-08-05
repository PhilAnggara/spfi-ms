@extends('pages.dashboard.layout')

@section('dashboard-content')
    <div class="row g-3 mb-3 dashboard-kpi-row">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'User Accounts',
            'value' => $metrics['user_accounts'] ?? 0,
            'icon' => 'fa-users',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Departments',
            'value' => $metrics['department_count'] ?? 0,
            'icon' => 'fa-building',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Roles',
            'value' => $metrics['role_count'] ?? 0,
            'icon' => 'fa-user-shield',
            'tone' => 'green',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Users With Department',
            'value' => $metrics['users_with_department'] ?? 0,
            'icon' => 'fa-user-check',
            'tone' => 'red',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Users by Department',
            'chartId' => 'chart-users-by-department',
            'col' => 'col-12 col-lg-6',
        ])
    </div>

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Users',
            'icon' => 'fa-users',
            'col' => 'col-12',
            'items' => $dashboard['lists']['recent_users'] ?? [],
        ])
    </div>
@endsection
