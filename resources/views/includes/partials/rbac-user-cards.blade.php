{{--
    Expected: $users (iterable of User), optional $emptyMessage, optional $showAccessButton (bool)
--}}
@php
    $emptyMessage = $emptyMessage ?? 'No users found.';
    $showAccessButton = $showAccessButton ?? true;
@endphp

@include('includes.partials.rbac-user-list-styles')

@if ($users->isEmpty())
    <div class="rbac-user-empty">
        <i class="fa-light fa-users fa-lg"></i>
        <span class="small">{{ $emptyMessage }}</span>
    </div>
@else
    <div class="rbac-user-list">
        @foreach ($users as $user)
            @php
                $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
            @endphp
            <div class="rbac-user-row">
                <span class="rbac-user-initials" aria-hidden="true">{{ $initials }}</span>
                <div class="min-w-0 flex-grow-1">
                    <div class="rbac-user-name text-truncate">{{ $user->name }}</div>
                    <div class="rbac-user-meta text-truncate">
                        {{ '@'.$user->username }}
                        @if ($user->department)
                            <span class="opacity-50">·</span> {{ $user->department->name }}
                        @endif
                    </div>
                </div>
                @if ($showAccessButton)
                    @can('manage-user-access')
                        <a href="{{ route('users.access.edit', $user) }}"
                           class="btn btn-sm btn-outline-primary flex-shrink-0"
                           data-bstooltip-toggle="tooltip"
                           data-bs-placement="top"
                           title="Manage Access">
                            Access
                        </a>
                    @endcan
                @endif
            </div>
        @endforeach
    </div>
@endif
