@extends('layouts.app')
@section('title', ' | Permissions')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="mb-0">Permissions</h3>
                <small class="text-muted">{{ $permissions->count() }} permission{{ $permissions->count() !== 1 ? 's' : '' }}</small>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary icon icon-left" data-bs-toggle="modal" data-bs-target="#create-permission-modal">
                    <i class="fa-duotone fa-solid fa-plus"></i>
                    Add Permission
                </button>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-warning">
        Renaming a permission does not update hard-coded middleware, <code>@@can</code>, or controller checks. Prefer creating a new permission when unsure.
    </div>

    <section class="section">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap" id="table1">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Module</th>
                                <th class="text-center">Roles</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissionGroups as $group)
                                @foreach ($group['permissions'] as $permission)
                                    @php
                                        $userCount = ($permissionUserSources[$permission->id] ?? collect())->count();
                                    @endphp
                                    <tr>
                                        <td><code>{{ $permission->name }}</code></td>
                                        <td><span class="badge bg-light-secondary text-secondary">{{ $group['label'] }}</span></td>
                                        <td class="text-center">{{ $permission->roles_count }}</td>
                                        <td class="text-center">{{ $userCount }}</td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn icon"
                                                        data-bstooltip-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="Detail"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#permission-detail-{{ $permission->id }}">
                                                    <i class="fa-light fa-eye text-info"></i>
                                                </button>
                                                <button type="button" class="btn icon"
                                                        data-bstooltip-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="Rename"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#edit-permission-{{ $permission->id }}">
                                                    <i class="fa-light fa-edit text-primary"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

@foreach ($permissions as $permission)
    <div class="modal fade" id="permission-detail-{{ $permission->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Permission: <code>{{ $permission->name }}</code></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Roles ({{ $permission->roles->count() }})</h6>
                    @if ($permission->roles->isEmpty())
                        <p class="text-muted small">Not assigned to any role.</p>
                    @else
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @foreach ($permission->roles as $role)
                                <span class="badge bg-light-primary text-primary">{{ $role->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    <hr>
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">
                        Users ({{ ($permissionUserSources[$permission->id] ?? collect())->count() }})
                    </h6>
                    @include('includes.partials.rbac-permission-user-cards', [
                        'rows' => $permissionUserSources[$permission->id] ?? collect(),
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                            data-bs-toggle="modal" data-bs-target="#edit-permission-{{ $permission->id }}">
                        Rename
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit-permission-{{ $permission->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('permissions.update', $permission) }}" method="POST" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Rename Permission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name (kebab-case)</label>
                    <input type="text" name="name" class="form-control" value="{{ $permission->name }}" required>
                    <div class="alert alert-light border mt-3 mb-0">
                        <div class="fw-semibold mb-1">Developer checklist</div>
                        <ul class="mb-0 ps-3 small">
                            <li>Update every hard-coded reference: route <code>permission:...</code>, <code>@@can</code>, Form Requests, and controllers.</li>
                            <li>Clear permission cache after deploy if names changed in production.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<div class="modal fade" id="create-permission-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('permissions.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Create Permission</h5>
                    <small class="text-muted">Use kebab-case (e.g. view-products)</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. view-products" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <div class="alert alert-light border mt-3 mb-0">
                    <div class="fw-semibold mb-1">Developer checklist</div>
                    <ul class="mb-0 ps-3 small">
                        <li>Wire the new name into routes: <code>middleware('permission:your-name')</code>.</li>
                        <li>Gate menus with <code>@@can('your-name')</code> / <code>@@canany([...])</code> in the sidebar.</li>
                        <li>Use <code>$user->can('your-name')</code> in controllers or Form Requests when needed.</li>
                        <li>Assign the permission to roles (or as a direct user permission) before testing access.</li>
                        <li>Add it to <code>RolePermissionSeeder</code> so other environments stay in sync.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush
