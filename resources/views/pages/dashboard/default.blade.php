@extends('pages.dashboard.layout')

@section('dashboard-content')
    @include('pages.dashboard.partials.quick-links', ['links' => $dashboard['quick_links']])

    <div class="row g-3 mb-3">
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
            'title' => 'Department PRS Status',
            'chartId' => 'chart-visitors-profile',
            'col' => 'col-12 col-lg-6',
        ])
    </div>

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Department PRS',
            'col' => 'col-12',
            'rows' => collect($dashboard['lists']['recent_prs'] ?? [])->map(fn ($item) => [
                $item->prs_number ?? ('#'.$item->id),
                str_replace('_', ' ', (string) $item->status),
                $item->prs_date ? \Illuminate\Support\Carbon::parse($item->prs_date)->format('d M Y') : '—',
            ])->all(),
        ])
    </div>
@endsection
