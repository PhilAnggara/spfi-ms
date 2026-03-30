@extends('layouts.app')
@section('title', ' | Employee List')

@section('content')
<div id="employees-page-container"
    data-filtered-total="{{ $employees->total() }}"
    data-create-modal-id="{{ session('employee_create_modal') && $errors->any() ? 'employee-create-modal' : '' }}"
    data-edit-modal-id="{{ session('employee_edit_id') && $errors->any() ? 'employee-edit-modal-' . session('employee_edit_id') : '' }}">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Employee Master List</h3>
                    <p class="text-muted mb-0">Manage employee records with instant search, live filters, and modal-based CRUD from one page.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <button type="button" class="btn btn-success icon icon-left" data-bs-toggle="modal" data-bs-target="#employee-create-modal">
                        <i class="fa-duotone fa-solid fa-user-plus"></i>
                        Add Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="employee-filter-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="filter-employee-keyword" class="form-label mb-1">Search Employee</label>
                        <input type="text" id="filter-employee-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="Employee ID / code / name / position / phone">
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label for="filter-employee-department" class="form-label mb-1">Department</label>
                        <select id="filter-employee-department" class="form-select">
                            <option value="">All Department</option>
                            @foreach ($departmentOptions as $department)
                                <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') === (string) $department->id)>
                                    {{ $department->code }} - {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-1">
                        <label for="filter-employee-gender" class="form-label mb-1">Gender</label>
                        <select id="filter-employee-gender" class="form-select">
                            <option value="">All Gender</option>
                            <option value="M" @selected(($filters['gender'] ?? '') === 'M')>Male</option>
                            <option value="F" @selected(($filters['gender'] ?? '') === 'F')>Female</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-1">
                        <label for="filter-employee-status" class="form-label mb-1">Status</label>
                        <select id="filter-employee-status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="terminated" @selected(($filters['status'] ?? '') === 'terminated')>Terminated</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label for="filter-employee-sort-by" class="form-label mb-1">Sort By</label>
                        <select id="filter-employee-sort-by" class="form-select">
                            <option value="created_at" @selected(($filters['sort_by'] ?? 'created_at') === 'created_at')>Created Date</option>
                            <option value="employee_name" @selected(($filters['sort_by'] ?? 'created_at') === 'employee_name')>Name</option>
                            <option value="employee_id" @selected(($filters['sort_by'] ?? 'created_at') === 'employee_id')>Employee ID</option>
                            <option value="position_name" @selected(($filters['sort_by'] ?? 'created_at') === 'position_name')>Position</option>
                            <option value="date_hired" @selected(($filters['sort_by'] ?? 'created_at') === 'date_hired')>Date Hired</option>
                            <option value="date_of_birth" @selected(($filters['sort_by'] ?? 'created_at') === 'date_of_birth')>Date of Birth</option>
                            <option value="date_terminated" @selected(($filters['sort_by'] ?? 'created_at') === 'date_terminated')>Date Terminated</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-1">
                        <label for="filter-employee-sort-direction" class="form-label mb-1">Order</label>
                        <select id="filter-employee-sort-direction" class="form-select">
                            <option value="asc" @selected(($filters['sort_direction'] ?? 'desc') === 'asc')>Ascending</option>
                            <option value="desc" @selected(($filters['sort_direction'] ?? 'desc') === 'desc')>Descending</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <button type="button" id="reset-employee-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="employees-page-results">
            <div class="card shadow-sm border-0">
                <div class="card-body position-relative">
                    <div id="employees-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">Employee Data</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light-info text-info-emphasis" id="employee-selected-count">0 selected</span>
                            <button type="button" class="btn btn-light-secondary btn-sm" id="employee-select-all-btn">Select All Results</button>
                            <button type="button" class="btn btn-light-secondary btn-sm" id="employee-clear-selection-btn">Clear Selection</button>
                            <button type="button" class="btn btn-primary btn-sm" id="employee-print-selected-btn" disabled>
                                <i class="fa-light fa-id-card me-1"></i>
                                Print Selected ID Cards
                            </button>
                            <span class="badge bg-light-primary" id="employee-filter-result" data-total="{{ $employees->total() }}">{{ $employees->total() }} records</span>
                        </div>
                    </div>

                    @if ($employees->isEmpty())
                        <div class="po-empty-state text-center text-muted py-5">
                            <i class="fa-duotone fa-solid fa-user-slash po-empty-icon"></i>
                            <p class="mb-0 mt-2 fw-semibold">No employee found.</p>
                            <small>Try changing your keyword or filters to see more results.</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle po-table text-nowrap" id="employees-table">
                                <thead>
                                    <tr>
                                        <th style="width: 44px;">
                                            <input type="checkbox" class="form-check-input employee-select-all-checkbox" id="employee-select-all-checkbox">
                                        </th>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Gender</th>
                                        <th>Position</th>
                                        <th>Hired Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employees as $employee)
                                        @php
                                            $department = $employee->department;
                                            $statusLabel = $employee->employment_status;
                                            $statusBadgeClass = $statusLabel === 'Terminated' ? 'bg-light-danger text-danger' : 'bg-light-success text-success';
                                        @endphp
                                        <tr>
                                            <td class="employee-select-cell">
                                                <div class="employee-select-cell-inner">
                                                    <input id="employee-select-{{ $employee->id }}" type="checkbox" class="form-check-input employee-select-checkbox" value="{{ $employee->id }}" data-employee-name="{{ $employee->employee_name }}" data-employee-id="{{ $employee->employee_id }}">
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $employee->employee_id }}')">
                                                    <i class="fa-solid fa-regular fa-clipboard"></i>
                                                    {{ $employee->employee_id }}
                                                </button>
                                            </td>
                                            <td>
                                                <button type="button" class="employee-list-person employee-list-person-button" data-bs-toggle="modal" data-bs-target="#employee-detail-modal-{{ $employee->id }}">
                                                    <img src="{{ $employee->photo_url }}" alt="{{ $employee->employee_name }} photo" class="employee-list-avatar">
                                                    <div>
                                                        <div class="fw-semibold text-dark">{{ $employee->employee_name }}</div>
                                                        <small class="text-muted">{{ $employee->display_code }}</small>
                                                    </div>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="badge bg-light-primary" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $department?->name ?? '-' }}">
                                                    {{ $department?->code ?? ($employee->legacy_department_code ?? '-') }}
                                                </span>
                                            </td>
                                            <td>{{ $employee->gender === 'F' ? 'Female' : ($employee->gender === 'M' ? 'Male' : '-') }}</td>
                                            <td>{{ $employee->position_name ?? '-' }}</td>
                                            <td>{{ optional($employee->date_hired)->format('d M Y') ?? '-' }}</td>
                                            <td>
                                                <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#employee-detail-modal-{{ $employee->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                                        <i class="fa-light fa-eye text-primary"></i>
                                                    </button>
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#employee-edit-modal-{{ $employee->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                        <i class="fa-light fa-edit text-primary"></i>
                                                    </button>
                                                    <button type="button" class="btn icon" data-print-single-id="{{ $employee->id }}" data-print-single-name="{{ $employee->employee_name }}" data-print-single-code="{{ $employee->employee_id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Print ID Card">
                                                        <i class="fa-light fa-id-card text-primary"></i>
                                                    </button>
                                                    <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $employee->id }}, 'Delete Employee', 'Are you sure want to delete employee {{ $employee->employee_name }}?')">
                                                        <i class="fa-light fa-trash text-secondary"></i>
                                                    </button>
                                                    <form action="{{ route('employees.destroy', $employee) }}" id="hapus-{{ $employee->id }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 employee-pagination-wrap">
                            {{ $employees->onEachSide(1)->links('pagination::bootstrap-5') }}
                        </div>

                    @endif
                </div>
            </div>
        </div>
    </section>

    <div id="employees-page-modals">
    @foreach ($employees as $employee)
        @php
            $department = $employee->department;
            $isEditingTarget = (int) session('employee_edit_id') === (int) $employee->id;
            $photoModalId = 'employee-photo-preview-modal-' . $employee->id;
        @endphp

        <div class="modal fade employee-detail-modal" id="employee-detail-modal-{{ $employee->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header employee-modal-header">
                        <div>
                            <h5 class="modal-title mb-1">Employee Detail</h5>
                            <small class="text-muted">{{ $employee->employee_name }}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-print-single-id="{{ $employee->id }}" data-print-single-name="{{ $employee->employee_name }}" data-print-single-code="{{ $employee->employee_id }}">
                                <i class="fa-light fa-id-card me-1"></i>
                                Print ID Card
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="employee-pill-row mb-3">
                            <span class="badge bg-light-primary">{{ $employee->display_code }}</span>
                            <span class="badge {{ $employee->employment_status === 'Terminated' ? 'bg-light-danger text-danger' : 'bg-light-success text-success' }}">{{ $employee->employment_status }}</span>
                            <span class="badge bg-light-secondary">{{ $department?->code ?? ($employee->legacy_department_code ?? 'NO-DEPT') }}</span>
                        </div>

                        <div class="row g-4 mb-3 align-items-start">
                            <div class="col-12 col-lg-4">
                                <div class="employee-detail-profile-card">
                                    <button type="button" class="employee-photo-card employee-photo-card-button" data-bs-toggle="modal" data-bs-target="#{{ $photoModalId }}">
                                        <img src="{{ $employee->photo_url }}" alt="{{ $employee->employee_name }} photo" class="employee-photo-card-image">
                                    </button>
                                    <div class="employee-detail-profile-copy">
                                        <div class="employee-detail-profile-name">{{ $employee->employee_name }}</div>
                                        <div class="employee-detail-profile-role">{{ $employee->position_name ?? 'No position assigned' }}</div>
                                        <div class="employee-detail-chip-row">
                                            <span class="badge bg-light-primary">{{ $employee->employee_id }}</span>
                                            <span class="badge bg-light-secondary">{{ $employee->display_code }}</span>
                                            <span class="badge {{ $employee->employment_status === 'Terminated' ? 'bg-light-danger text-danger' : 'bg-light-success text-success' }}">{{ $employee->employment_status }}</span>
                                        </div>
                                        <div class="employee-detail-profile-meta">
                                            <span>{{ $department?->name ?? 'No department' }}</span>
                                            <span>{{ $employee->id_biometrik ?: 'No biometric ID' }}</span>
                                            <span>{{ optional($employee->created_at)->format('d M Y H:i') ?: '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-8">
                                <div class="employee-detail-panel">
                                    <div class="employee-detail-section-title">Employee Information</div>
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Department</small>
                                                <div>{{ $department?->name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Position</small>
                                                <div>{{ $employee->position_name ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Gender</small>
                                                <div>{{ $employee->gender === 'F' ? 'Female' : ($employee->gender === 'M' ? 'Male' : '-') }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Date Hired</small>
                                                <div>{{ optional($employee->date_hired)->format('d M Y') ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Date Terminated</small>
                                                <div>{{ optional($employee->date_terminated)->format('d M Y') ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Date of Birth</small>
                                                <div>{{ optional($employee->date_of_birth)->format('d M Y') ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="employee-info-card">
                                                <small>Pay Type</small>
                                                <div>{{ $employee->pay_type ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="employee-info-card">
                                                <small>Contract</small>
                                                <div>{{ $employee->contract ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="employee-info-card">
                                                <small>Civil Status</small>
                                                <div>{{ $employee->civil_status ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Phone / Insurance / Astek</small>
                                                <div>{{ $employee->cell_phone ?? '-' }} | {{ $employee->insurance_no ?? '-' }} | {{ $employee->no_astek ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Account / ID Card</small>
                                                <div>{{ $employee->account_no ?? '-' }} | {{ $employee->identity_card_no ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Religion / Education</small>
                                                <div>{{ $employee->religion ?? '-' }} | {{ $employee->education ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="employee-info-card">
                                                <small>Legacy Department Code</small>
                                                <div>{{ $employee->legacy_department_code ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="employee-info-card employee-remarks-card">
                                                <small>Remarks</small>
                                                <div>{{ $employee->remarks ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="{{ $photoModalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header employee-modal-header">
                        <div>
                            <h5 class="modal-title mb-1">Employee Photo</h5>
                            <small class="text-muted">{{ $employee->employee_name }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="{{ $employee->photo_url }}" alt="{{ $employee->employee_name }} photo" class="employee-photo-zoom-image">
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade employee-form-modal" id="employee-edit-modal-{{ $employee->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="modal-header employee-modal-header">
                            <div>
                                <h5 class="modal-title mb-1">Edit Employee</h5>
                                <small class="text-muted">{{ $employee->employee_name }}</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info border-0 employee-edit-alert mb-3">
                                <div class="fw-semibold mb-1">Quick Edit</div>
                                <div class="small">Update core employee identity, department relation, status dates, and contact details directly from this modal.</div>
                            </div>

                            @include('pages.employees._form-fields', [
                                'employee' => $employee,
                                'departmentOptions' => $departmentOptions,
                                'prefix' => 'employee-edit-' . $employee->id,
                                'useOld' => $isEditingTarget,
                            ])

                            <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-3">
                                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
    </div>
</div>

<div class="modal fade employee-form-modal" id="employee-create-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header employee-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Add Employee</h5>
                        <small class="text-muted">Create a new employee record.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('pages.employees._form-fields', [
                        'employee' => null,
                        'departmentOptions' => $departmentOptions,
                        'prefix' => 'employee-create',
                        'useOld' => true,
                    ])

                    <div class="d-flex justify-content-end gap-2 border-top mt-3 pt-3">
                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Employee</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="employee-id-card-print-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('employees.id-cards.print') }}" target="_blank" id="employee-id-card-print-form">
                <div class="modal-header employee-modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Print Employee ID Card</h5>
                        <small class="text-muted" id="employee-id-card-print-summary">Selected employees: 0</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee-id-card-valid-until" class="form-label">Valid Until</label>
                            <input type="date" class="form-control" id="employee-id-card-valid-until" name="valid_until" min="{{ now()->toDateString() }}" required>
                            <div class="form-text">Choose a validity date for the ID cards before printing.</div>
                    </div>
                    <div id="employee-id-card-hidden-inputs"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-light fa-print me-1"></i>
                        Print ID Card
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/employees-index.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/employees-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/employees-index.js') }}"></script>
@endpush
