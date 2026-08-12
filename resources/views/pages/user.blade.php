@extends('layouts.app')
@section('title', ' | Master - User')

@section('content')
<div
    class="page-heading"
    id="user-page"
    data-open-create-modal="{{ $errors->any() ? '1' : '0' }}"
    data-editing-user-id="{{ (string) session('editing_user_id', '') }}"
>
    <div class="page-title">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h3 class="mb-0">Manage Users</h3>
                <small class="text-muted">{{ $users->count() }} user{{ $users->count() != 1 ? 's' : '' }} registered</small>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary icon icon-left" data-bs-toggle="modal" data-bs-target="#create-modal">
                    <i class="fa-duotone fa-solid fa-plus"></i>
                    Add User
                </button>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="uc-toolbar mb-4" data-aos="fade-down">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-6">
                    <div class="uc-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="user-search-input" class="form-control" placeholder="Search name or username (fuzzy)..." autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <select id="user-filter-department" class="form-select">
                        <option value="">All Departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->code }} - {{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <select id="user-filter-role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="General Manager">General Manager</option>
                        <option value="Manager">Manager</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Staff">Staff</option>
                        <option value="Programmer">Programmer</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1 d-grid">
                    <button type="button" id="user-filter-reset" class="btn btn-light border">Reset</button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted" id="user-search-hint">Type to search by similar name/username, not only exact text.</small>
                <small class="text-muted"><span id="user-visible-count">{{ $users->count() }}</span> visible</small>
            </div>
        </div>

        <div id="user-grid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4">
            @foreach ($users as $user)
                @php
                    $isMe = auth()->check() && auth()->user()->id === $user->id;

                    // Role-based gradient: warna mencerminkan jabatan
                    $roleStyle = match(true) {
                        $user->role === 'General Manager' => ['grad' => '7c2d12,c2410c',  'avatar' => 'c2410c', 'badgeCls' => 'uc-badge-gm'],
                        $user->role === 'Manager'         => ['grad' => '1e1b4b,4338ca',  'avatar' => '4338ca', 'badgeCls' => 'uc-badge-mgr'],
                        $user->role === 'Supervisor'      => ['grad' => '164e63,0e7490',  'avatar' => '0e7490', 'badgeCls' => 'uc-badge-sup'],
                        $user->role === 'Programmer'      => ['grad' => '0c4a6e,0284c7',  'avatar' => '0284c7', 'badgeCls' => 'uc-badge-prog'],
                        default                           => ['grad' => '0f172a,334155',  'avatar' => '475569', 'badgeCls' => 'uc-badge-staff'],
                    };
                @endphp
                 <div class="col mb-4"
                     data-aos="fade-up"
                     data-aos-delay="{{ $loop->iteration <= 8 ? $loop->iteration * 60 : 0 }}"
                     data-user-card="true"
                     data-user-name="{{ $user->name }}"
                     data-user-username="{{ $user->username }}"
                     data-user-department-id="{{ $user->department_id }}"
                     data-user-role="{{ $user->role }}"
                     data-order="{{ $loop->index }}">
                    <div class="card h-100 border-0 uc-card position-relative overflow-hidden">

                        {{-- Gradient banner --}}
                        <div class="uc-banner" style="background: linear-gradient(135deg, #{{ $roleStyle['grad'] }});"></div>

                        {{-- "You" badge --}}
                        @if ($isMe)
                            <span class="uc-you-badge position-absolute top-0 start-0 mt-3 ms-3">You</span>
                        @endif

                        {{-- Actions dropdown --}}
                        @if (!$isMe && $user->role !== 'General Manager')
                            <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index:10;">
                                <div class="dropdown">
                                    <button class="uc-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end uc-dropdown">
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="hapusData({{ $user->id }}, 'Delete User', 'Are you sure you want to delete {{ $user->name }}?')">
                                                <i class="fa-solid fa-trash-can me-2"></i>Delete User
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <div class="card-body text-center uc-body">
                            {{-- Avatar --}}
                            <div class="uc-avatar mx-auto">
                                <img src="https://ui-avatars.com/api/?background={{ $roleStyle['avatar'] }}&color=fff&bold=true&size=128&name={{ urlencode($user->name) }}"
                                     alt="{{ $user->name }}">
                            </div>

                            {{-- Name --}}
                            <h6 class="uc-name mt-3 mb-0">
                                {{ $user->name }}
                                @if ($user->hasRole('administrator'))
                                    <span data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Administrator">
                                        <i class="fa-solid fa-shield-check fa-sm ms-1 uc-admin-icon"></i>
                                    </span>
                                @endif
                            </h6>
                            <div class="uc-username">&#64;{{ $user->username }}</div>

                            {{-- Role badge --}}
                            <span class="uc-badge {{ $roleStyle['badgeCls'] }} mt-2">{{ $user->role }}</span>
                            <div class="uc-access-roles mt-2">
                                @if ($user->roles->isEmpty())
                                    <span class="uc-access-chip uc-access-chip-empty">No access role</span>
                                @else
                                    @foreach ($user->roles as $accessRole)
                                        <span class="uc-access-chip"
                                              data-bstooltip-toggle="tooltip"
                                              data-bs-placement="top"
                                              title="Access role">{{ $accessRole->name }}</span>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="uc-info mt-3">
                                <div class="uc-info-row">
                                    <i class="fal fa-envelope fa-fw"></i>
                                    <span class="text-truncate">{{ $user->email }}</span>
                                </div>
                                <div class="uc-info-row mt-1">
                                    <i class="fal fa-building-user fa-fw"></i>
                                    <span class="text-truncate">{{ $user->department->name }}</span>
                                    <span class="uc-dept-code ms-auto flex-shrink-0">{{ $user->department->code }}</span>
                                </div>
                            </div>

                            <div class="mt-3 d-grid gap-2">
                                <button class="btn btn-sm uc-edit-btn w-100" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $user->id }}">
                                    <i class="fa-solid fa-user-pen me-2"></i>
                                    Edit {{ $isMe ? 'Profile' : 'User' }}
                                </button>
                                @can('manage-user-access')
                                    <a href="{{ route('users.access.edit', $user) }}" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fa-solid fa-key me-2"></i>
                                        Manage Access
                                    </a>
                                @endcan
                            </div>
                        </div>

                        @if (!$isMe && $user->role !== 'General Manager')
                            <form action="{{ route('user.destroy', $user->id) }}" id="hapus-{{ $user->id }}" method="POST" class="d-none">
                                @method('delete')
                                @csrf
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Add user card --}}
            <div id="user-add-card" class="col mb-4" data-aos="fade-up">
                <div class="uc-add-card position-relative">
                    <div class="uc-add-inner">
                        <div class="uc-add-icon">
                            <i class="fal fa-user-plus"></i>
                        </div>
                        <span class="uc-add-label">Add New User</span>
                    </div>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#create-modal" class="stretched-link"></a>
                </div>
            </div>

            <div class="col-12 d-none" id="user-empty-state">
                <div class="uc-empty-state text-center py-5">
                    <i class="fa-light fa-users-slash fa-2x mb-2"></i>
                    <h6 class="mb-1">No users found</h6>
                    <p class="mb-0 text-muted small">Try changing keyword, department, or role filter.</p>
                </div>
            </div>
        </div>
    </section>
</div>
@include('includes.modals.user-modal')
@endsection

@push('prepend-style')
@endpush
@push('addon-style')
<link rel="stylesheet" href="{{ url('assets/css/modules/user-index.css') }}">
@endpush
@push('addon-script')
<script src="{{ url('assets/scripts/modules/user-index.js') }}"></script>
@endpush
