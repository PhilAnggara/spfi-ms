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
            'label' => 'Canvass Open',
            'value' => $metrics['canvass_open'] ?? 0,
            'icon' => 'fa-scale-balanced',
            'tone' => 'purple',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PO Pending Approval',
            'value' => $metrics['po_pending_approval'] ?? 0,
            'icon' => 'fa-hourglass-half',
            'tone' => 'red',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Approved PO Value',
            'value' => $metrics['po_approved_value_this_month'] ?? 0,
            'icon' => 'fa-money-bill-wave',
            'tone' => 'green',
            'prefix' => 'Rp ',
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

    <div class="row g-3">
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent PRS',
            'rows' => collect($dashboard['lists']['recent_prs'] ?? [])->map(fn ($item) => [
                $item->prs_number ?? ('#'.$item->id),
                str_replace('_', ' ', (string) $item->status),
                $item->prs_date ? \Illuminate\Support\Carbon::parse($item->prs_date)->format('d M Y') : '—',
            ])->all(),
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Purchase Orders',
            'rows' => collect($dashboard['lists']['recent_po'] ?? [])->map(fn ($item) => [
                $item->po_number ?? ('#'.$item->id),
                str_replace('_', ' ', (string) $item->status),
                'Rp '.number_format((float) $item->total, 0, ',', '.'),
            ])->all(),
        ])
    </div>
@endsection
