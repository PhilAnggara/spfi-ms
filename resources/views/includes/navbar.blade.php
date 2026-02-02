<header>
    <nav class="navbar navbar-expand navbar-light navbar-top">
        <div class="container-fluid">
            <a href="#" class="burger-btn d-block">
                <i class="bi bi-justify fs-3"></i>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-lg-0">
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link active dropdown-toggle text-gray-600" href="#"
                            data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                            <i class="fa-light fa-bell fa-shake fa-xl"></i>

                            <!-- Badge untuk unread notifications count -->
                            @if(Auth::user()->unreadNotifications->count() > 0)
                                <span class="badge badge-notification bg-danger">
                                    {{ Auth::user()->unreadNotifications->count() }}
                                </span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow"
                            aria-labelledby="dropdownMenuButton" style="min-width: 350px; max-height: 500px; overflow-y: auto;">

                            <!-- Header -->
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Notifications</h6>
                                @if(Auth::user()->unreadNotifications->count() > 0)
                                    <button class="btn btn-sm btn-link p-0" id="markAllReadBtn" style="font-size: 0.75rem;">
                                        Mark all as read
                                    </button>
                                @endif
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <!-- Notifications List -->
                            @forelse(Auth::user()->notifications->take(5) as $notification)
                                <li class="dropdown-item notification-item {{ $notification->read_at ? '' : 'bg-light' }}"
                                    style="white-space: normal; cursor: pointer;"
                                    data-notification-id="{{ $notification->id }}"
                                    data-action-url="{{ $notification->data['action_url'] ?? '#' }}">

                                    <a class="d-flex align-items-start text-decoration-none" href="#">
                                        <!-- Icon -->
                                        <div class="notification-icon {{ $notification->data['icon_color'] ?? 'bg-primary' }} flex-shrink-0 p-1">
                                            <i class="{{ $notification->data['icon'] ?? 'bi-bell' }}"></i>
                                        </div>

                                        <!-- Content -->
                                        <div class="notification-text ms-3 flex-grow-1">
                                            <p class="notification-title font-bold mb-1">
                                                {{ $notification->data['title'] ?? 'Notification' }}
                                                @if(!$notification->read_at)
                                                    <span class="badge bg-primary" style="font-size: 0.65rem;">NEW</span>
                                                @endif
                                            </p>
                                            <p class="notification-subtitle text-sm mb-1">
                                                {{ $notification->data['message'] }}
                                            </p>
                                            <small class="text-muted" style="font-size: 0.7rem;">
                                                <i class="bi bi-clock"></i> {{ $notification->created_at->diffForHumans() }}
                                            </small>
                                        </div>
                                    </a>
                                </li>
                            @empty
                                <li class="dropdown-item text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fa-2x ms-1 d-block"></i>
                                        <p class="ms-1">No notifications</p>
                                    </div>
                                </li>
                            @endforelse

                            <!-- Footer -->
                            @if(Auth::user()->notifications->count() > 0)
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a href="{{ route('notifications.index') }}" class="dropdown-item text-center text-primary">
                                        <i class="bi bi-arrow-right-circle"></i> View All Notifications
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                </ul>
                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-menu d-flex">
                            <div class="user-name text-end me-3">
                                <h6 class="mb-0 text-gray-600">{{ auth()->user()->name }}</h6>
                                <p class="mb-0 text-sm text-gray-600">{{ get_job_title(auth()->user()) }}</p>
                            </div>
                            <div class="user-img d-flex align-items-center">
                                <div class="avatar avatar-md">
                                    <img src="https://ui-avatars.com/api/?background=435EBE&color=fff&bold=true&name={{ auth()->user()->name }}">
                                </div>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="dropdownMenuButton"
                        style="min-width: 11rem;">
                        <li>
                            <h6 class="dropdown-header">Hello, {{ Str::before(auth()->user()->name, ' ') }}!</h6>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#change-password-modal">
                                <i class="icon-mid bi bi-shield-lock me-2"></i> Change Password
                            </a>
                        </li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="keluar()">
                                <i class="icon-mid bi bi-box-arrow-left me-2"></i> Logout
                            </a>
                            <form action="{{ route('logout') }}" id="logoutForm" method="POST">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>

@include('includes.modals.change-password-modal')

<!-- Script untuk handle notifications -->
<script>
// Mark notification as read ketika di-click
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();

        const notificationId = this.dataset.notificationId;
        const actionUrl = this.dataset.actionUrl;

        // Mark as read via AJAX
        if (notificationId) {
            fetch(`/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            }).then(response => response.json())
              .then(data => {
                  // Redirect ke action URL jika ada
                  if (actionUrl && actionUrl !== '#') {
                      window.location.href = actionUrl;
                  } else {
                      // Refresh page untuk update badge count
                      location.reload();
                  }
              });
        }
    });
});

// Mark all as read button
document.getElementById('markAllReadBtn')?.addEventListener('click', function(e) {
    e.preventDefault();

    if (confirm('Mark all notifications as read?')) {
        fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        }).then(response => response.json())
          .then(data => {
              location.reload();
          });
    }
});
</script>
