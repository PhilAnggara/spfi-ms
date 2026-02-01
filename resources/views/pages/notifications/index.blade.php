@extends('layouts.app')
@section('title', ' | Notifications')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Notifications</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="float-end">
                    @if(Auth::user()->unreadNotifications->count() > 0)
                        <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-check-all"></i>
                                Mark All as Read
                            </button>
                        </form>
                    @endif

                    @if(Auth::user()->notifications()->whereNotNull('read_at')->count() > 0)
                        <form action="{{ route('notifications.clear-read') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                onclick="return confirm('Clear all read notifications?')">
                                <i class="bi bi-trash"></i>
                                Clear Read
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                @if($notifications->count() > 0)
                    <div class="list-group">
                        @foreach($notifications as $notification)
                            <div class="list-group-item {{ $notification->read_at ? '' : 'list-group-item-primary' }}
                                 mb-2 border rounded"
                                 style="cursor: pointer;"
                                 onclick="handleNotificationClick('{{ $notification->id }}', '{{ $notification->data['action_url'] ?? '#' }}')">

                                <div class="d-flex justify-content-between align-items-start">
                                    <!-- Icon & Content -->
                                    <div class="d-flex align-items-start flex-grow-1">
                                        <div class="notification-icon {{ $notification->data['icon_color'] ?? 'bg-primary' }} me-3
                                             d-flex align-items-center justify-content-center rounded-circle"
                                             style="width: 48px; height: 48px;">
                                            <i class="{{ $notification->data['icon'] ?? 'bi-bell' }} text-white"></i>
                                        </div>

                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                {{ $notification->data['title'] ?? 'Notification' }}
                                                @if(!$notification->read_at)
                                                    <span class="badge bg-primary">NEW</span>
                                                @endif
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                {{ $notification->data['message'] }}
                                            </p>

                                            <!-- Additional Info -->
                                            @if(isset($notification->data['department']))
                                                <small class="text-muted">
                                                    <i class="bi bi-building"></i> {{ $notification->data['department'] }}
                                                    @if(isset($notification->data['requester']))
                                                        | <i class="bi bi-person"></i> {{ $notification->data['requester'] }}
                                                    @endif
                                                </small>
                                            @endif

                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> {{ $notification->created_at->diffForHumans() }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            onclick="event.stopPropagation()">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @if(!$notification->read_at)
                                                <li>
                                                    <a class="dropdown-item" href="#"
                                                       onclick="event.stopPropagation(); markAsRead('{{ $notification->id }}')">
                                                        <i class="bi bi-check"></i> Mark as Read
                                                    </a>
                                                </li>
                                            @endif
                                            <li>
                                                <a class="dropdown-item text-danger" href="#"
                                                   onclick="event.stopPropagation(); deleteNotification('{{ $notification->id }}')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $notifications->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fa-3x text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No notifications</h5>
                        <p class="text-muted">You're all caught up!</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-script')
<script>
// Handle notification click
function handleNotificationClick(notificationId, actionUrl) {
    // Mark as read
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => {
        // Redirect to action URL
        if (actionUrl && actionUrl !== '#') {
            window.location.href = actionUrl;
        } else {
            location.reload();
        }
    });
}

// Mark single notification as read
function markAsRead(notificationId) {
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => location.reload());
}

// Delete notification
function deleteNotification(notificationId) {
    if (confirm('Delete this notification?')) {
        fetch(`/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        }).then(() => location.reload());
    }
}
</script>
@endpush
