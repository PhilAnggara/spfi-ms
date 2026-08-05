@extends('pages.dashboard.layout')

@section('dashboard-content')
    @include('pages.dashboard.partials.quick-links', ['links' => $dashboard['quick_links']])

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'Approved PO Value',
            'value' => $metrics['po_approved_value_this_month'] ?? 0,
            'icon' => 'fa-money-bill-wave',
            'tone' => 'green',
            'prefix' => 'Rp ',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PO Pending Approval',
            'value' => $metrics['po_pending_approval'] ?? 0,
            'icon' => 'fa-hourglass-half',
            'tone' => 'red',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'RR ('.$dashboardMonthLabel.')',
            'value' => $metrics['rr_this_month'] ?? 0,
            'icon' => 'fa-boxes-stacked',
            'tone' => 'blue',
        ])
        @include('pages.dashboard.partials.kpi-card', [
            'label' => 'PRS ('.$dashboardMonthLabel.')',
            'value' => $metrics['prs_this_month'] ?? 0,
            'icon' => 'fa-cart-shopping',
            'tone' => 'purple',
        ])
    </div>

    <div class="row g-3 mb-3">
        @include('pages.dashboard.partials.chart-card', [
            'title' => 'Monthly Approved PO Value',
            'chartId' => 'chart-monthly-po-value',
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
            'title' => 'Recent Purchase Orders',
            'rows' => collect($dashboard['lists']['recent_po'] ?? [])->map(fn ($item) => [
                $item->po_number ?? ('#'.$item->id),
                str_replace('_', ' ', (string) $item->status),
                'Rp '.number_format((float) $item->total, 0, ',', '.'),
            ])->all(),
        ])
        @include('pages.dashboard.partials.recent-list', [
            'title' => 'Recent Receiving Reports',
            'rows' => collect($dashboard['lists']['recent_rr'] ?? [])->map(fn ($item) => [
                $item->rr_number ?? ('#'.$item->id),
                $item->received_date ? \Illuminate\Support\Carbon::parse($item->received_date)->format('d M Y') : '—',
                '',
            ])->all(),
        ])
    </div>
@endsection
