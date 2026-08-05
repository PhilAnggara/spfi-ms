@php
    $title = $title ?? 'Recent';
    $items = $items ?? [];
    $empty = $empty ?? 'No recent records yet.';
    $col = $col ?? 'col-12 col-lg-6';
    $icon = $icon ?? 'fa-clock';
@endphp

<div class="{{ $col }}">
    <div class="card shadow-sm border-0 h-100 dashboard-recent-card">
        <div class="card-header border-0 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="dashboard-recent-icon">
                    <i class="fa-regular {{ $icon }}"></i>
                </span>
                <h5 class="mb-0">{{ $title }}</h5>
            </div>
            <span class="badge bg-light-primary text-primary">{{ count($items) }}</span>
        </div>
        <div class="card-body pt-0">
            @if (count($items) === 0)
                <div class="dashboard-recent-empty text-center py-4">
                    <i class="fa-regular fa-inbox text-muted mb-2"></i>
                    <p class="text-muted mb-0 small">{{ $empty }}</p>
                </div>
            @else
                <div class="dashboard-recent-list">
                    @foreach ($items as $item)
                        <div class="dashboard-recent-item">
                            <div class="dashboard-recent-main">
                                <div class="dashboard-recent-title">{{ $item['title'] ?? '—' }}</div>
                                <div class="dashboard-recent-subtitle">{{ $item['subtitle'] ?? '' }}</div>
                            </div>
                            <div class="dashboard-recent-side">
                                <span class="badge bg-light-{{ $item['tone'] ?? 'secondary' }} text-{{ $item['tone'] ?? 'secondary' }}">
                                    {{ $item['status_label'] ?? ($item['status'] ?? '') }}
                                </span>
                                <div class="dashboard-recent-meta">{{ $item['meta'] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
