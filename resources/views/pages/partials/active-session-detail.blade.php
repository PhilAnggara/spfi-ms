@php
    $avatar = match ($user->role) {
        'General Manager' => 'c2410c',
        'Manager' => '4338ca',
        'Supervisor' => '0e7490',
        'Programmer' => '0284c7',
        default => '475569',
    };
@endphp

<div class="as-detail">
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

    <h6 class="as-timeline-title">Activity History</h6>

    @forelse ($logs as $log)
        <div class="as-timeline-item as-action-{{ $log->action }}">
            <div class="as-timeline-dot"></div>
            <div class="as-timeline-content">
                <div class="as-timeline-head">
                    <strong>{{ $log->label() }}</strong>
                    <span class="text-muted small">{{ $log->created_at->diffForHumans() }}</span>
                </div>
                <div class="small text-muted">{{ $log->created_at->format('d M Y H:i:s') }}</div>
                @if ($log->ip_address)
                    <div class="small"><span class="as-mono">{{ $log->ip_address }}</span></div>
                @endif
                @if ($log->action === 'force_logout' && $log->actor)
                    <div class="small">by {{ $log->actor->name }}</div>
                @endif
                @if (! empty($log->meta['route']) || ! empty($log->meta['path']))
                    <div class="small text-muted">{{ $log->meta['path'] ?? $log->meta['route'] }}</div>
                @endif
                @if (! empty($log->meta['message']))
                    <div class="small">{{ $log->meta['message'] }}</div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-muted small py-3">No activity recorded yet.</div>
    @endforelse
</div>
