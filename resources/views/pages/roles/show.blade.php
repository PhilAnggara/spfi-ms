@extends('layouts.app')
@section('title', ' | Edit Role — ' . $role->name)

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="mb-0">Edit role</h3>
                <small class="text-muted">
                    <span class="badge bg-light-primary text-primary">{{ $role->name }}</span>
                    · Assign permissions and review users
                </small>
            </div>
            <div class="col-auto">
                <a href="{{ route('roles.index') }}" class="btn btn-light">Back to Roles</a>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <section class="section">
        <form action="{{ route('roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="sync_permissions" value="1">

            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0">Role name</h5></div>
                <div class="card-body">
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $role->name) }}"
                           @if ($role->name === 'administrator') readonly @endif
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Permissions</h5>
                    <small class="text-muted">{{ $role->permissions->count() }} selected</small>
                </div>
                <div class="card-body">
                    @php
                        $selected = old('permissions', $role->permissions->pluck('name')->all());
                        $actorIsAdmin = auth()->user()?->hasRole('administrator');
                        $permissionsLocked = $role->name === 'administrator' && ! $actorIsAdmin;
                    @endphp
                    @if ($permissionsLocked)
                        <div class="alert alert-warning">Only administrators can change the administrator role permissions.</div>
                    @endif
                    @forelse ($permissionGroups as $group)
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2 border-bottom pb-1">{{ $group['label'] }}</h6>
                            <div class="row">
                                @foreach ($group['permissions'] as $permission)
                                    @php
                                        $isProtectedPerm = $permission->name === 'reset-activity-logs';
                                        $permDisabled = $permissionsLocked || ($isProtectedPerm && ! $actorIsAdmin);
                                    @endphp
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check">
                                            @if ($permDisabled && in_array($permission->name, $selected, true))
                                                <input type="hidden" name="permissions[]" value="{{ $permission->name }}">
                                            @endif
                                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                                   value="{{ $permission->name }}" id="perm-{{ $permission->id }}"
                                                   @checked(in_array($permission->name, $selected, true))
                                                   @disabled($permDisabled)>
                                            <label class="form-check-label" for="perm-{{ $permission->id }}">
                                                <code class="small">{{ $permission->name }}</code>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No permissions available.</p>
                    @endforelse
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary" @disabled($permissionsLocked)>Save Role</button>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Users with this role ({{ $role->users->count() }})</h5></div>
            <div class="card-body">
                @include('includes.partials.rbac-user-cards', [
                    'users' => $role->users,
                    'emptyMessage' => 'No users currently have this role.',
                ])
            </div>
        </div>
    </section>
</div>
@endsection
