@extends('layouts.app')
@section('title', ' | Access — ' . $user->name)

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="mb-0">Manage Access</h3>
                <small class="text-muted">{{ $user->name }} ({{ '@'.$user->username }})</small>
            </div>
            <div class="col-auto">
                <a href="{{ route('user.index') }}" class="btn btn-light">Back to Users</a>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
        Effective permissions = permissions from selected <strong>roles</strong> plus any <strong>direct</strong> permissions below.
        Job title (Manager / Supervisor / Staff) on the user profile does not grant access roles automatically.
    </div>

    <section class="section">
        <form action="{{ route('users.access.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            @php
                $actorIsAdmin = auth()->user()?->hasRole('administrator');
                $targetIsAdmin = $user->hasRole('administrator');
                $formLocked = $targetIsAdmin && ! $actorIsAdmin;
            @endphp

            @if ($formLocked)
                <div class="alert alert-warning">Only administrators can modify this user's access.</div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0">Access roles</h5></div>
                <div class="card-body">
                    <div class="row">
                        @foreach ($roles as $role)
                            @php
                                $isProtectedRole = $role->name === 'administrator';
                                $roleLocked = $formLocked || ($isProtectedRole && ! $actorIsAdmin);
                            @endphp
                            <div class="col-md-4 col-lg-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]"
                                           value="{{ $role->name }}" id="role-{{ $role->id }}"
                                           @checked(in_array($role->name, old('roles', $user->roles->pluck('name')->all()), true))
                                           @disabled($roleLocked)>
                                    <label class="form-check-label" for="role-{{ $role->id }}">
                                        <span class="badge bg-light-primary text-primary">{{ $role->name }}</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Direct permissions</h5>
                    <small class="text-muted">Extra permissions for this user only. Items already covered by a role are marked <span class="badge bg-light-success text-success">Via role</span>.</small>
                </div>
                <div class="card-body">
                    @forelse ($permissionGroups as $group)
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2 border-bottom pb-1">{{ $group['label'] }}</h6>
                            <div class="row">
                                @foreach ($group['permissions'] as $permission)
                                    @php
                                        $isViaRole = in_array($permission->name, $viaRolePermissionNames, true);
                                        $sourceRoles = $viaRoleSources[$permission->name] ?? [];
                                        $isProtectedPerm = $permission->name === 'reset-activity-logs';
                                        $permLocked = $formLocked || ($isProtectedPerm && ! $actorIsAdmin);
                                    @endphp
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check d-flex align-items-start gap-2 {{ $isViaRole ? 'rbac-perm-via-role' : '' }}">
                                            @if ($permLocked && in_array($permission->name, old('permissions', $user->permissions->pluck('name')->all()), true))
                                                <input type="hidden" name="permissions[]" value="{{ $permission->name }}">
                                            @endif
                                            <input class="form-check-input mt-1" type="checkbox" name="permissions[]"
                                                   value="{{ $permission->name }}" id="dperm-{{ $permission->id }}"
                                                   @checked(in_array($permission->name, old('permissions', $user->permissions->pluck('name')->all()), true))
                                                   @disabled($permLocked)>
                                            <label class="form-check-label" for="dperm-{{ $permission->id }}">
                                                <code class="small {{ $isViaRole ? 'rbac-perm-via-role-text' : '' }}">{{ $permission->name }}</code>
                                                @if ($isViaRole)
                                                    <span class="badge bg-light-success text-success ms-1"
                                                          data-bstooltip-toggle="tooltip"
                                                          data-bs-placement="top"
                                                          title="{{ implode(', ', $sourceRoles) }}">Via role</span>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No permissions found.</p>
                    @endforelse
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary" @disabled($formLocked)>Save Access</button>
                </div>
            </div>
        </form>
    </section>
</div>
@endsection

@push('addon-style')
<style>
    .rbac-perm-via-role-text {
        color: #15803d !important;
        opacity: .85;
    }
    .rbac-perm-via-role .form-check-label {
        color: #166534;
    }
</style>
@endpush
