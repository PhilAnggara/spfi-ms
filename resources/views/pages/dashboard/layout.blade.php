{{-- Shared dashboard shell --}}
@extends('layouts.app')
@section('title', ' | Dashboard')

@section('content')
<div
    class="page-heading po-page dashboard-page"
    data-dashboard-key="{{ $dashboard['key'] }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">{{ $dashboard['title'] }}</h3>
                    <p class="text-muted mb-0">{{ $dashboard['subtitle'] }}</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="dashboard-meta text-lg-end">
                    @if ($dashboard['department_name'])
                        <span class="dashboard-pill">
                            <i class="fa-regular fa-building"></i>
                            {{ $dashboard['department_alias'] ? $dashboard['department_alias'].' · ' : '' }}{{ $dashboard['department_name'] }}
                        </span>
                    @endif
                    <div class="text-muted small mt-2">{{ $dashboard['month_label'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @yield('dashboard-content')
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/compiled/css/iconly.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/dashboard-modern.css') }}">
@endpush

@push('addon-script')
    <script>
        window.dashboardData = @json($dashboardData);
        window.dashboardHeatmapUrl = @json(route('dashboard.charts.open-prs-heatmap'));
    </script>
    <script src="{{ url('assets/extensions/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/dashboard-index.js') }}"></script>
@endpush
