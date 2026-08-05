@extends('pages.dashboard.layout')

@section('dashboard-content')
    @include('pages.dashboard.partials.quick-links', ['links' => $dashboard['quick_links']])

    <div class="row g-3 mb-3">
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
            'label' => 'TS Pending',
            'value' => $metrics['ts_pending'] ?? 0,
            'icon' => 'fa-right-left',
            'tone' => 'red',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Deliveries ('.$dashboardMonthLabel.')',
            'value' => $metrics['deliveries_this_month'] ?? 0,
            'icon' => 'fa-truck',
            'tone' => 'green',
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

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Receiving Reports',
            'rows' => collect($dashboard['lists']['recent_rr'] ?? [])->map(fn ($item) => [
                $item->rr_number ?? ('#'.$item->id),
                $item->received_date ? \Illuminate\Support\Carbon::parse($item->received_date)->format('d M Y') : '—',
                '',
            ])->all(),
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Transfer Slips',
            'rows' => collect($dashboard['lists']['recent_ts'] ?? [])->map(fn ($item) => [
                $item->ts_number ?? ('#'.$item->id),
                $item->ts_date ? \Illuminate\Support\Carbon::parse($item->ts_date)->format('d M Y') : '—',
                $item->approved_at ? 'Approved' : 'Pending',
            ])->all(),
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Deliveries',
            'col' => 'col-12',
            'rows' => collect($dashboard['lists']['recent_deliveries'] ?? [])->map(fn ($item) => [
                $item->dr_number ?? ('#'.$item->id),
                $item->dr_date ? \Illuminate\Support\Carbon::parse($item->dr_date)->format('d M Y') : '—',
                $item->to_location ?? '—',
            ])->all(),
        ])
    </div>
@endsection
