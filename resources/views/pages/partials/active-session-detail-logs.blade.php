@forelse ($logs as $log)
    <div class="as-timeline-item as-action-{{ $log->action }}" data-log-id="{{ $log->id }}">
        <div class="as-timeline-dot"></div>
        <div class="as-timeline-content">
            <div class="as-timeline-head">
                <strong>{{ $log->summary() }}</strong>
                <span class="text-muted small">{{ $log->created_at->diffForHumans() }}</span>
            </div>
            <div class="small text-muted">{{ $log->created_at->format('d M Y H:i:s') }}</div>
            <div class="small">
                <span class="as-mono">{{ $log->ip_address ?: '-' }}</span>
            </div>
            @if ($log->detailLabel())
                <div class="small text-muted as-timeline-detail">
                    <i class="fa-regular fa-file-lines me-1"></i>{{ $log->detailLabel() }}
                </div>
            @endif
        </div>
    </div>
@empty
    @if (! isset($appendOnly) || ! $appendOnly)
        <div class="text-muted small py-3">No activity recorded yet.</div>
    @endif
@endforelse

@if ($logs->isNotEmpty())
    <div id="as-timeline-sentinel"
         class="as-timeline-sentinel"
         data-has-more="{{ ! empty($hasMore) ? '1' : '0' }}"
         data-oldest-id="{{ $oldestId ?? ($logs->last()?->id ?? '') }}"
         aria-hidden="true"></div>
@endif
