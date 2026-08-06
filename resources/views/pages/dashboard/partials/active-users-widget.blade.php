@php
    $items = $items ?? collect();
    $onlineCount = $onlineCount ?? 0;
    $sessionCount = $sessionCount ?? 0;
    $canManage = $canManage ?? false;
@endphp

<div class="col-12" data-aos="fade-up">
    <div class="card shadow-sm border-0 dashboard-active-users-card">
        <div class="card-header border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="dashboard-recent-icon dashboard-online-icon">
                    <i class="fa-solid fa-circle"></i>
                </span>
                <div>
                    <h5 class="mb-0">Active Users</h5>
                    <small class="text-muted">Currently online across the system</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success">{{ number_format($onlineCount) }} online</span>
                <span class="badge bg-light-secondary text-secondary">{{ number_format($sessionCount) }} sessions</span>
                @if ($canManage)
                    <a href="{{ route('active-sessions.index') }}" class="btn btn-sm btn-outline-primary">
                        View all
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body pt-0">
            @if ($items->isEmpty())
                <div class="dashboard-recent-empty text-center py-4">
                    <i class="fa-regular fa-user-slash text-muted mb-2"></i>
                    <p class="text-muted mb-0 small">No users are online right now.</p>
                </div>
            @else
                <div class="dashboard-active-users-grid">
                    @foreach ($items as $item)
                        <div class="dashboard-active-user">
                            <div class="dashboard-active-user-avatar-wrap">
                                <img
                                    src="https://ui-avatars.com/api/?background={{ $item['avatar'] }}&color=fff&bold=true&size=64&name={{ urlencode($item['name']) }}"
                                    alt="{{ $item['name'] }}"
                                    class="dashboard-active-user-avatar"
                                >
                                <span class="dashboard-active-user-dot" title="Online"></span>
                            </div>
                            <div class="dashboard-active-user-body min-w-0">
                                <div class="dashboard-active-user-name text-truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
                                <div class="dashboard-active-user-meta text-truncate">
                                    &#64;{{ $item['username'] }} · {{ $item['department'] }}
                                </div>
                                <div class="dashboard-active-user-meta text-truncate">
                                    {{ $item['device'] }} · {{ $item['last_seen'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
