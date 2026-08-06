@extends('layouts.app')
@section('title', ' | Active Users / Sessions')

@section('content')
<div class="page-heading" id="active-sessions-page">
    <div class="page-title">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h3 class="mb-0">Active Users / Sessions</h3>
                <small class="text-muted">Monitor presence, devices, and recent activity across all accounts</small>
            </div>
            <div class="col-auto">
                <a href="{{ route('active-sessions.index') }}" class="btn btn-outline-primary icon icon-left">
                    <i class="fa-duotone fa-solid fa-arrows-rotate"></i>
                    Refresh
                </a>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="as-stats mb-4" data-aos="fade-down">
            <div class="as-stat as-stat-online">
                <div class="as-stat-icon"><i class="fa-solid fa-circle"></i></div>
                <div>
                    <div class="as-stat-value">{{ $onlineCount }}</div>
                    <div class="as-stat-label">Online</div>
                </div>
            </div>
            <div class="as-stat as-stat-offline">
                <div class="as-stat-icon"><i class="fa-regular fa-circle"></i></div>
                <div>
                    <div class="as-stat-value">{{ $offlineCount }}</div>
                    <div class="as-stat-label">Offline</div>
                </div>
            </div>
            <div class="as-stat as-stat-total">
                <div class="as-stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="as-stat-value">{{ $totalCount }}</div>
                    <div class="as-stat-label">Total Users</div>
                </div>
            </div>
        </div>

        <div class="as-toolbar mb-4" data-aos="fade-down">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label for="as-search" class="form-label mb-1">Search</label>
                    <div class="as-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="as-search" class="form-control" placeholder="Search name or username..." autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="as-status" class="form-label mb-1">Status</label>
                    <select id="as-status" class="form-select" aria-label="Filter by status">
                        <option value="all" selected>All Status</option>
                        <option value="online">Online only</option>
                        <option value="offline">Offline only</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="as-sort" class="form-label mb-1">Sort by</label>
                    <select id="as-sort" class="form-select" aria-label="Sort users">
                        <option value="last_seen" selected>Last Activity</option>
                        <option value="online">Online First</option>
                        <option value="name_asc">Name A–Z</option>
                        <option value="name_desc">Name Z–A</option>
                        <option value="department">Department</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button type="button" id="as-filter-reset" class="btn btn-light border">Reset</button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">Filters update instantly — no page reload.</small>
                <small class="text-muted"><span id="as-visible-count">{{ $totalCount }}</span> visible</small>
            </div>
        </div>

        <div class="as-table-wrap" data-aos="fade-up">
            <div class="as-table-head d-none d-xl-grid">
                <div>User</div>
                <div>Status</div>
                <div>Last Activity</div>
                <div>IP Address</div>
                <div>Device</div>
                <div class="text-end">Actions</div>
            </div>

            <div id="as-list" class="as-list">
                @foreach ($users as $user)
                    @php
                        $isMe = auth()->id() === $user->id;
                        $isOnline = (bool) $user->is_online;
                        $hasSession = (bool) $user->has_active_session;
                        $avatar = match ($user->role) {
                            'General Manager' => 'c2410c',
                            'Manager' => '4338ca',
                            'Supervisor' => '0e7490',
                            'Programmer' => '0284c7',
                            default => '475569',
                        };
                        $device = $user->last_user_agent ? $user->deviceLabel() : '-';
                        $lastSeenTs = $user->last_seen_at?->timestamp ?? 0;
                    @endphp
                    <div class="as-row"
                         data-as-row="true"
                         data-name="{{ $user->name }}"
                         data-username="{{ $user->username }}"
                         data-status="{{ $isOnline ? 'online' : 'offline' }}"
                         data-department="{{ $user->department?->name ?? '' }}"
                         data-last-seen="{{ $lastSeenTs }}"
                         data-order="{{ $loop->index }}">
                        <div class="as-col as-col-user">
                            <div class="as-avatar-wrap">
                                <img src="https://ui-avatars.com/api/?background={{ $avatar }}&color=fff&bold=true&size=80&name={{ urlencode($user->name) }}"
                                     alt="{{ $user->name }}" class="as-avatar">
                                <span class="as-presence {{ $isOnline ? 'is-online' : 'is-offline' }}" title="{{ $isOnline ? 'Online' : 'Offline' }}"></span>
                            </div>
                            <div class="as-identity">
                                <div class="as-name">
                                    <span class="as-name-text" title="{{ $user->name }}">{{ $user->name }}</span>
                                    @if ($isMe)
                                        <span class="as-you">You</span>
                                    @endif
                                </div>
                                <div class="as-meta" title="&#64;{{ $user->username }} · {{ $user->role }}{{ $user->department ? ' · '.$user->department->name : '' }}">
                                    <span>&#64;{{ $user->username }}</span>
                                    <span class="as-dot">·</span>
                                    <span>{{ $user->role }}</span>
                                    @if ($user->department)
                                        <span class="as-dot">·</span>
                                        <span>{{ $user->department->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="as-col as-col-status" data-label="Status">
                            @if ($isOnline)
                                <span class="as-badge as-badge-online">Online</span>
                            @else
                                <span class="as-badge as-badge-offline">Offline</span>
                            @endif
                        </div>

                        <div class="as-col as-col-activity" data-label="Last Activity">
                            @if ($user->last_seen_at)
                                <span class="as-info-value">{{ $user->last_seen_at->diffForHumans() }}</span>
                                <small class="text-muted d-block">{{ $user->last_seen_at->format('d M Y H:i') }}</small>
                            @else
                                <span class="as-info-value text-muted">Never</span>
                            @endif
                        </div>

                        <div class="as-col as-col-ip" data-label="IP Address">
                            <span class="as-mono">{{ $user->last_ip_address ?? '-' }}</span>
                        </div>

                        <div class="as-col as-col-device" data-label="Device" title="{{ $device }}">
                            <span class="as-device-text">{{ $device }}</span>
                        </div>

                        <div class="as-col as-col-actions">
                            <div class="btn-group btn-group-sm">
                                <button type="button"
                                        class="btn icon as-btn-detail"
                                        data-detail-url="{{ route('active-sessions.show', $user) }}"
                                        data-user-name="{{ $user->name }}"
                                        data-bstooltip-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="Activity detail">
                                    <i class="fa-light fa-eye text-primary"></i>
                                </button>
                                @if (! $isMe && $hasSession)
                                    <button type="button"
                                            class="btn icon"
                                            onclick="hapusData({{ $user->id }}, 'Force Logout', 'End all active sessions for {{ addslashes($user->name) }}?')"
                                            data-bstooltip-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Force logout">
                                        <i class="fa-light fa-right-from-bracket text-danger"></i>
                                    </button>
                                    <form action="{{ route('active-sessions.destroy-sessions', $user) }}" id="hapus-{{ $user->id }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="as-empty {{ $users->isEmpty() ? '' : 'd-none' }}" id="as-empty">
                    <i class="fa-light fa-users-slash fa-2x mb-2"></i>
                    <h6 class="mb-1">No users found</h6>
                    <p class="mb-0 text-muted small">Try changing keyword, status, or sort filters.</p>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="offcanvas offcanvas-end as-offcanvas" tabindex="-1" id="as-detail-offcanvas" aria-labelledby="as-detail-title">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="as-detail-title">User Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="as-detail-body">
        <div class="as-detail-loading text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Loading activity...</div>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
<link rel="stylesheet" href="{{ url('assets/css/modules/active-sessions.css') }}">
@endpush
@push('addon-script')
<script src="{{ url('assets/scripts/modules/active-sessions.js') }}"></script>
@endpush
