@extends('layouts.app')
@section('title', ' | Roles')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="mb-0">Roles</h3>
                <small class="text-muted">{{ $roles->count() }} access role{{ $roles->count() !== 1 ? 's' : '' }}</small>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary icon icon-left" data-bs-toggle="modal" data-bs-target="#create-role-modal">
                    <i class="fa-duotone fa-solid fa-plus"></i>
                    Add Role
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

    <section class="section">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap" id="table1">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th class="text-center">Permissions</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td>
                                        <span class="badge rounded-pill bg-light-primary text-primary px-3 py-2">{{ $role->name }}</span>
                                        @if ($role->name === 'administrator')
                                            <span class="badge bg-light-warning text-warning ms-1"
                                                  data-bstooltip-toggle="tooltip"
                                                  data-bs-placement="top"
                                                  title="Cannot be renamed or deleted">Protected</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-semibold">{{ $role->permissions_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-semibold">{{ $role->users_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn icon"
                                                    data-bstooltip-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="Detail"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#role-detail-{{ $role->id }}">
                                                <i class="fa-light fa-eye text-info"></i>
                                            </button>
                                            <a href="{{ route('roles.show', $role) }}" class="btn icon"
                                               data-bstooltip-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="Edit">
                                                <i class="fa-light fa-edit text-primary"></i>
                                            </a>
                                            @if ($role->name !== 'administrator')
                                                <button type="button" class="btn icon"
                                                        data-bstooltip-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="Delete"
                                                        onclick="hapusData({{ $role->id }}, 'Delete Role', 'Delete role {{ $role->name }}?')">
                                                    <i class="fa-light fa-trash text-secondary"></i>
                                                </button>
                                                <form action="{{ route('roles.destroy', $role) }}" id="hapus-{{ $role->id }}" method="POST" class="d-none">
                                                    @csrf
                                                    @method('delete')
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

@foreach ($roles as $role)
    <div class="modal fade" id="role-detail-{{ $role->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Role detail:
                        <span class="badge bg-light-primary text-primary">{{ $role->name }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Permissions ({{ $role->permissions_count }})</h6>
                    @forelse (($rolePermissionGroups[$role->id] ?? []) as $group)
                        <div class="mb-3">
                            <div class="small fw-semibold mb-1">{{ $group['label'] }}</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($group['permissions'] as $permission)
                                    <span class="badge bg-light-secondary text-secondary">{{ $permission->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small">No permissions assigned.</p>
                    @endforelse

                    <hr>
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Users ({{ $role->users_count }})</h6>
                    @include('includes.partials.rbac-user-cards', [
                        'users' => $role->users,
                        'emptyMessage' => 'No users have this role.',
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('roles.show', $role) }}" class="btn btn-primary">Edit Role</a>
                </div>
            </div>
        </div>
    </div>
@endforeach

<div class="modal fade" id="create-role-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('roles.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Create Role</h5>
                    <small class="text-muted">Use kebab-case (e.g. purchasing-staff)</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. purchasing-staff" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <div class="alert alert-light border mt-3 mb-0">
                    <div class="fw-semibold mb-1">Developer checklist</div>
                    <ul class="mb-0 ps-3 small">
                        <li>Assign permissions to this role from <strong>Edit Role</strong> after creating it.</li>
                        <li>If routes still use <code>role:...</code> middleware, add the new role name there — prefer migrating to <code>permission:...</code>.</li>
                        <li>Update sidebar <code>@@role</code> / <code>@@canany</code> only if this role should see menus not covered by permissions.</li>
                        <li>Seed or document the role so other environments stay in sync.</li>
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
