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
                $selected = old('permissions', $user->permissions->pluck('name')->all());
                $selectedRoles = old('roles', $user->roles->pluck('name')->all());
            @endphp

            @if ($formLocked)
                <div class="alert alert-warning">Only administrators can modify this user's access.</div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0">Access roles</h5></div>
                <div class="card-body">
                    @include('includes.partials.rbac-permission-matrix-styles')
                    <div class="rbac-role-grid">
                        @foreach ($roles as $role)
                            @php
                                $isProtectedRole = $role->name === 'administrator';
                                $roleLocked = $formLocked || ($isProtectedRole && ! $actorIsAdmin);
                            @endphp
                            <label class="rbac-role-chip" for="role-{{ $role->id }}">
                                <input class="form-check-input" type="checkbox" name="roles[]"
                                       value="{{ $role->name }}" id="role-{{ $role->id }}"
                                       @checked(in_array($role->name, $selectedRoles, true))
                                       @disabled($roleLocked)>
                                <span class="rbac-role-chip-name">{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Direct permissions</h5>
                    <small class="text-muted">Extra permissions for this user only. Items already covered by a role show a green indicator (<span class="rbac-perm-via-dot is-legend"></span> Via role).</small>
                </div>
                <div class="card-body">
                    @include('includes.partials.rbac-permission-matrix', [
                        'permissionMatrix' => $permissionMatrix,
                        'selected' => $selected,
                        'idPrefix' => 'dperm',
                        'permissionsLocked' => $formLocked,
                        'actorIsAdmin' => $actorIsAdmin,
                        'viaRolePermissionNames' => $viaRolePermissionNames,
                        'viaRoleSources' => $viaRoleSources,
                    ])
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary" @disabled($formLocked)>Save Access</button>
                </div>
            </div>
        </form>
    </section>
</div>
@endsection
