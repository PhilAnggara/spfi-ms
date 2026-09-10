@php
    $avatar = match ($user->role) {
        'General Manager' => 'c2410c',
        'Manager' => '4338ca',
        'Supervisor' => '0e7490',
        'Programmer' => '0284c7',
        default => '475569',
    };
@endphp

<div class="as-detail" data-detail-url="{{ $detailUrl }}" data-has-more="{{ $hasMore ? '1' : '0' }}" data-oldest-id="{{ $oldestId ?? '' }}">
    <div class="as-detail-profile">
        <img src="https://ui-avatars.com/api/?background={{ $avatar }}&color=fff&bold=true&size=96&name={{ urlencode($user->name) }}"
             alt="{{ $user->name }}" class="as-detail-avatar">
        <div>
            <div class="as-detail-name">{{ $user->name }}</div>
            <div class="text-muted">&#64;{{ $user->username }} · {{ $user->role }}</div>
            <div class="mt-2">
                @if ($isOnline)
                    <span class="as-badge as-badge-online">Online</span>
                @else
                    <span class="as-badge as-badge-offline">Offline</span>
                @endif
            </div>
        </div>
    </div>

    <div class="as-detail-grid">
        <div>
            <div class="as-info-label">Department</div>
            <div>{{ $user->department?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="as-info-label">Last Activity</div>
            <div>
                @if ($user->last_seen_at)
                    {{ $user->last_seen_at->diffForHumans() }}
                    <div class="small text-muted">{{ $user->last_seen_at->format('d M Y H:i:s') }}</div>
                @else
                    Never
                @endif
            </div>
        </div>
        <div>
            <div class="as-info-label">IP Address</div>
            <div class="as-mono">{{ $user->last_ip_address ?? '-' }}</div>
        </div>
        <div>
            <div class="as-info-label">Device</div>
            <div>{{ $user->last_user_agent ? $user->deviceLabel() : '-' }}</div>
        </div>
    </div>

    <div class="as-timeline-toolbar">
        <h6 class="as-timeline-title mb-0">Activity History</h6>
        <div class="as-timeline-filter">
            <label for="as-activity-filter" class="form-label mb-0 small text-muted">Type</label>
            <select id="as-activity-filter"
                    class="form-select form-select-sm"
                    data-base-url="{{ route('active-sessions.show', $user) }}"
                    aria-label="Filter activity type">
                <option value="all" @selected($actionFilter === null)>All types</option>
                @foreach ($availableActions as $action)
                    <option value="{{ $action }}" @selected($actionFilter === $action)>
                        {{ \App\Models\UserActivityLog::actionLabel($action) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div id="as-timeline" class="as-timeline">
        @include('pages.partials.active-session-detail-logs', [
            'logs' => $logs,
            'hasMore' => $hasMore,
            'oldestId' => $oldestId,
        ])
    </div>
</div>
