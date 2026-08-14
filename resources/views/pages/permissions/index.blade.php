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
                                            <button type="button" class="btn icon btn-sm"
                                                    data-bstooltip-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="Detail"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#permission-detail-{{ $permission->id }}">
                                                <i class="fa-light fa-eye text-info"></i>
                                            </button>
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
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush
