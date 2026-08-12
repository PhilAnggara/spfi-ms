{{--
    Expected: $rows = collection of ['user' => User, 'via_role' => bool, 'direct' => bool, 'role_names' => string[]]
--}}
@php
    $emptyMessage = $emptyMessage ?? 'No users currently have this permission.';
@endphp

@include('includes.partials.rbac-user-list-styles')

@if ($rows->isEmpty())
    <div class="rbac-user-empty">
        <i class="fa-light fa-users fa-lg"></i>
        <span class="small">{{ $emptyMessage }}</span>
    </div>
@else
    <div class="rbac-user-list">
        @foreach ($rows as $row)
            @php
                $user = $row['user'];
                $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
                $roleNames = $row['role_names'] ?? [];
            @endphp
            <div class="rbac-user-row">
                <span class="rbac-user-initials" aria-hidden="true">{{ $initials }}</span>
                <div class="min-w-0 flex-grow-1">
                    <div class="rbac-user-name text-truncate">{{ $user->name }}</div>
                    <div class="rbac-user-meta text-truncate">{{ '@'.$user->username }}</div>
                    <div class="rbac-source-badges">
                        @if ($row['via_role'])
                            <span class="badge bg-light-success text-success"
                                  data-bstooltip-toggle="tooltip"
                                  data-bs-placement="top"
                                  title="{{ $roleNames !== [] ? implode(', ', $roleNames) : 'Via role' }}">Via role</span>
                        @endif
                        @if ($row['direct'])
                            <span class="badge bg-light-warning text-warning">Direct</span>
                        @endif
                    </div>
                </div>
                @can('manage-user-access')
                    <a href="{{ route('users.access.edit', $user) }}"
                       class="btn btn-sm btn-outline-primary flex-shrink-0"
                       data-bstooltip-toggle="tooltip"
                       data-bs-placement="top"
                       title="Manage Access">
                        Access
                    </a>
                @endcan
            </div>
        @endforeach
    </div>
@endif
