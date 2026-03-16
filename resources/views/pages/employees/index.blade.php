@extends('layouts.app')
@section('title', ' | Employee List')

@section('content')
<div id="employees-page-container" data-filtered-total="{{ $employees->total() }}">
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
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-employee-keyword" class="form-label mb-1">Search Employee</label>
                        <input type="text" id="filter-employee-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="Employee ID / code / name / position / phone">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3">
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
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-employee-gender" class="form-label mb-1">Gender</label>
                        <select id="filter-employee-gender" class="form-select">
                            <option value="">All Gender</option>
                            <option value="M" @selected(($filters['gender'] ?? '') === 'M')>Male</option>
                            <option value="F" @selected(($filters['gender'] ?? '') === 'F')>Female</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-employee-status" class="form-label mb-1">Status</label>
                        <select id="filter-employee-status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="terminated" @selected(($filters['status'] ?? '') === 'terminated')>Terminated</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-employee-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

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

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $employees->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>

                @endif
            </div>
        </div>
    </section>

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
    <style>
        .employee-modal-header {
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            border-bottom: 1px solid #e2e8f0;
        }

        .employee-pill-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .employee-list-person {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 220px;
        }

        .employee-list-person-button {
            width: 100%;
            padding: 0;
            border: 0;
            background: transparent;
            text-align: left;
        }

        .employee-list-person-button:hover .fw-semibold,
        .employee-list-person-button:focus-visible .fw-semibold {
            color: #2563eb !important;
        }

        .employee-list-person-button:focus-visible {
            outline: 2px solid #93c5fd;
            outline-offset: 4px;
            border-radius: 0.75rem;
        }

        .employee-list-avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            object-fit: cover;
            border: 2px solid #dbeafe;
            background: #e2e8f0;
            flex-shrink: 0;
        }

        .employee-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.65rem 0.8rem;
            min-height: 72px;
        }

        .employee-info-card small {
            color: #64748b;
            display: block;
            margin-bottom: 0.2rem;
        }

        .employee-info-card div {
            color: #0f172a;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
        }

        .employee-edit-alert {
            background: #eff6ff;
            color: #1e3a8a;
        }

        .employee-photo-upload-card {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 1rem;
            padding: 1.1rem;
            border: 1px solid #dbe4f0;
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 26%),
                linear-gradient(135deg, #f8fafc, #ffffff);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .employee-photo-upload-preview-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .employee-photo-upload-preview {
            width: 100%;
            max-width: 160px;
            aspect-ratio: 3 / 4;
            object-fit: cover;
            border-radius: 1.1rem;
            border: 3px solid #fff;
            background: #e2e8f0;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        }

        .employee-photo-upload-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.9rem;
        }

        .employee-photo-upload-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .employee-photo-upload-subtitle {
            color: #64748b;
            font-size: 0.92rem;
        }

        .employee-photo-dropzone {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border: 1.5px dashed #93c5fd;
            border-radius: 1rem;
            background: rgba(239, 246, 255, 0.8);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .employee-photo-dropzone.is-dragover {
            border-color: #2563eb;
            background: rgba(219, 234, 254, 0.95);
            transform: translateY(-1px);
        }

        .employee-photo-dropzone-icon {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            background: rgba(37, 99, 235, 0.12);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .employee-photo-dropzone-copy {
            flex: 1;
        }

        .employee-photo-dropzone-title {
            color: #0f172a;
            font-weight: 700;
        }

        .employee-photo-dropzone-text,
        .employee-photo-dropzone-meta {
            color: #64748b;
            font-size: 0.9rem;
        }

        .employee-photo-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
            z-index: 2;
        }

        .employee-photo-upload-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .employee-photo-upload-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .employee-photo-file-name {
            color: #475569;
            font-size: 0.9rem;
            word-break: break-word;
        }

        .employee-detail-profile-card {
            position: sticky;
            top: 0;
            border: 1px solid #dbe4f0;
            border-radius: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .employee-detail-profile-copy {
            padding: 1rem 1rem 1.1rem;
        }

        .employee-detail-profile-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .employee-detail-profile-role {
            margin-top: 0.2rem;
            color: #2563eb;
            font-weight: 600;
        }

        .employee-detail-profile-meta {
            display: grid;
            gap: 0.45rem;
            margin-top: 0.85rem;
            color: #475569;
            font-size: 0.92rem;
        }

        .employee-detail-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.85rem;
        }

        .employee-detail-panel {
            padding: 1.15rem;
            border: 1px solid #e2e8f0;
            border-radius: 1.15rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .employee-detail-modal .modal-dialog {
            max-width: min(1180px, calc(100vw - 1.5rem));
        }

        .employee-detail-modal .modal-body {
            padding: 1.35rem;
        }

        .employee-detail-section-title {
            margin-bottom: 0.9rem;
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .employee-remarks-card {
            min-height: 110px;
        }

        .employee-photo-card {
            width: 100%;
            border: 1px solid #dbe4f0;
            border-radius: 1rem;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .employee-photo-card-button {
            padding: 0;
            text-align: left;
            cursor: pointer;
        }

        .employee-photo-card-image {
            width: 100%;
            aspect-ratio: 3 / 4;
            object-fit: cover;
            background: #e2e8f0;
        }

        .employee-photo-card-copy {
            padding: 1rem;
        }

        .employee-photo-card-copy small {
            color: #64748b;
            display: block;
            margin-bottom: 0.35rem;
        }

        .employee-photo-card-copy div {
            font-weight: 700;
            color: #0f172a;
        }

        .employee-photo-card-copy span {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #2563eb;
        }

        .employee-photo-zoom-image {
            max-width: 100%;
            max-height: 75vh;
            border-radius: 1rem;
            object-fit: contain;
            background: #e2e8f0;
        }

        .employee-select-checkbox,
        .employee-select-all-checkbox {
            width: 1.05rem;
            height: 1.05rem;
            cursor: pointer;
        }

        .employee-select-cell {
            padding: .5rem .75rem;
            text-align: center;
            cursor: pointer;
            vertical-align: middle;
        }

        .employee-select-cell .employee-select-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        #employee-selected-count {
            min-width: 94px;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .employee-photo-upload-card {
                grid-template-columns: 1fr;
            }

            .employee-photo-upload-preview {
                max-width: 180px;
            }

            .employee-photo-dropzone {
                flex-direction: column;
                align-items: flex-start;
            }

            .employee-photo-upload-toolbar {
                align-items: flex-start;
            }
        }
    </style>
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/employees-modern.js') }}"></script>
    <script>
        (function () {
            let isLoading = false;
            const createModalId = @json(session('employee_create_modal') && $errors->any() ? 'employee-create-modal' : null);
            const editModalId = @json(session('employee_edit_id') && $errors->any() ? 'employee-edit-modal-' . session('employee_edit_id') : null);

            function initPageTooltips(scope = document) {
                const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

                tooltipElements.forEach((el) => {
                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        if (window.bootstrap.Tooltip.getInstance(el)) {
                            return;
                        }

                        new window.bootstrap.Tooltip(el);
                    }
                });
            }

            function getEmployeeSelectionState() {
                if (!window.__employeeSelectionState) {
                    window.__employeeSelectionState = {
                        filterKey: null,
                        selectedIds: new Set(),
                        selectingAll: false,
                    };
                }

                return window.__employeeSelectionState;
            }

            function buildEmployeeFilterKey(url) {
                const parsedUrl = new URL(url, window.location.origin);
                parsedUrl.searchParams.delete('page');
                parsedUrl.searchParams.delete('selection_scope');

                const sortedParams = new URLSearchParams(
                    Array.from(parsedUrl.searchParams.entries()).sort(([left], [right]) => left.localeCompare(right))
                );

                const queryString = sortedParams.toString();

                return queryString === ''
                    ? parsedUrl.pathname
                    : `${parsedUrl.pathname}?${queryString}`;
            }

            function syncEmployeeSelectionScope(url) {
                const selectionState = getEmployeeSelectionState();
                const nextFilterKey = buildEmployeeFilterKey(url);

                if (selectionState.filterKey !== null && selectionState.filterKey !== nextFilterKey) {
                    selectionState.selectedIds.clear();
                }

                selectionState.filterKey = nextFilterKey;

                return selectionState;
            }

            async function fetchAllFilteredEmployeeIds() {
                const url = new URL(window.location.href);
                url.searchParams.delete('page');
                url.searchParams.set('selection_scope', 'all_ids');

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to load employee selections.');
                }

                const payload = await response.json();

                return {
                    ids: Array.isArray(payload.ids)
                        ? payload.ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0)
                        : [],
                    total: Number(payload.total || 0),
                };
            }

            function updatePhotoUi(input, previewSrc = null) {
                const preview = input.dataset.previewTarget
                    ? document.getElementById(input.dataset.previewTarget)
                    : null;
                const fileNameTarget = input.dataset.fileNameTarget
                    ? document.getElementById(input.dataset.fileNameTarget)
                    : null;
                const clearButton = input.dataset.clearButtonId
                    ? document.getElementById(input.dataset.clearButtonId)
                    : null;
                const removeExistingButton = input.dataset.removeExistingButtonId
                    ? document.getElementById(input.dataset.removeExistingButtonId)
                    : null;
                const removeInput = input.dataset.removeInputId
                    ? document.getElementById(input.dataset.removeInputId)
                    : null;
                const hasExistingPhoto = input.dataset.hasExistingPhoto === 'true';
                const existingSrc = input.dataset.existingSrc || input.dataset.defaultSrc;
                const defaultSrc = input.dataset.defaultSrc || existingSrc;
                const hasFile = Boolean(input.files && input.files.length);
                const removingExisting = removeInput ? removeInput.value === '1' : false;

                if (preview) {
                    preview.src = previewSrc || (hasFile ? preview.src : (removingExisting ? defaultSrc : existingSrc));
                }

                if (fileNameTarget) {
                    if (hasFile) {
                        fileNameTarget.textContent = input.files[0].name;
                    } else if (removingExisting && hasExistingPhoto) {
                        fileNameTarget.textContent = 'Current photo will be removed when you save';
                    } else {
                        fileNameTarget.textContent = input.dataset.existingFileName || 'No file selected';
                    }
                }

                if (clearButton) {
                    clearButton.classList.toggle('d-none', !hasFile);
                }

                if (removeExistingButton) {
                    if (!hasExistingPhoto || hasFile) {
                        removeExistingButton.classList.add('d-none');
                    } else {
                        removeExistingButton.classList.remove('d-none');
                        removeExistingButton.innerHTML = removingExisting
                            ? '<i class="fa-light fa-arrow-rotate-left me-1"></i>Undo Remove'
                            : '<i class="fa-light fa-trash-can me-1"></i>Delete Current Photo';
                    }
                }
            }

            function bindPhotoEventsOnce() {
                if (window.__employeePhotoEventsBound) {
                    return;
                }

                window.__employeePhotoEventsBound = true;

                document.addEventListener('change', function (event) {
                    const input = event.target.closest('.employee-photo-input');
                    if (!input) {
                        return;
                    }

                    const removeInput = input.dataset.removeInputId
                        ? document.getElementById(input.dataset.removeInputId)
                        : null;
                    if (removeInput) {
                        removeInput.value = '0';
                    }

                    const file = input.files && input.files[0];
                    if (!file) {
                        updatePhotoUi(input);
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (loadEvent) {
                        updatePhotoUi(input, loadEvent.target?.result || null);
                    };
                    reader.readAsDataURL(file);
                });

                document.addEventListener('click', function (event) {
                    const clearButton = event.target.closest('[data-photo-clear]');
                    if (clearButton) {
                        const input = document.getElementById(clearButton.dataset.photoClear);
                        if (!input) {
                            return;
                        }

                        input.value = '';
                        updatePhotoUi(input);
                        return;
                    }

                    const removeButton = event.target.closest('[data-photo-remove-existing]');
                    if (!removeButton) {
                        return;
                    }

                    const input = document.getElementById(removeButton.dataset.photoRemoveExisting);
                    if (!input) {
                        return;
                    }

                    const removeInput = input.dataset.removeInputId
                        ? document.getElementById(input.dataset.removeInputId)
                        : null;
                    if (!removeInput) {
                        return;
                    }

                    removeInput.value = removeInput.value === '1' ? '0' : '1';
                    updatePhotoUi(input);
                });
            }

            function initPhotoInputs(scope = document) {
                bindPhotoEventsOnce();

                scope.querySelectorAll('.employee-photo-input').forEach((input) => {
                    updatePhotoUi(input);
                });

                scope.querySelectorAll('[data-photo-dropzone]').forEach((dropzone) => {
                    if (dropzone.dataset.bound === 'true') {
                        return;
                    }

                    dropzone.dataset.bound = 'true';
                    const input = document.getElementById(dropzone.dataset.inputId);
                    if (!input) {
                        return;
                    }

                    ['dragenter', 'dragover'].forEach((eventName) => {
                        dropzone.addEventListener(eventName, function (event) {
                            event.preventDefault();
                            dropzone.classList.add('is-dragover');
                        });
                    });

                    ['dragleave', 'drop'].forEach((eventName) => {
                        dropzone.addEventListener(eventName, function (event) {
                            event.preventDefault();
                            dropzone.classList.remove('is-dragover');
                        });
                    });

                    dropzone.addEventListener('drop', function (event) {
                        const files = event.dataTransfer?.files;
                        if (!files || !files.length) {
                            return;
                        }

                        try {
                            const dataTransfer = new DataTransfer();
                            Array.from(files).forEach((file) => dataTransfer.items.add(file));
                            input.files = dataTransfer.files;
                        } catch (_) {
                            return;
                        }

                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            }

            function initIdCardPrint(scope = document) {
                const selectionState = getEmployeeSelectionState();
                const pageContainer = document.getElementById('employees-page-container');
                const rowCheckboxes = Array.from(scope.querySelectorAll('.employee-select-checkbox'));
                const headerCheckbox = scope.querySelector('#employee-select-all-checkbox');
                const selectAllButton = scope.querySelector('#employee-select-all-btn');
                const clearButton = scope.querySelector('#employee-clear-selection-btn');
                const printSelectedButton = scope.querySelector('#employee-print-selected-btn');
                const selectedBadge = scope.querySelector('#employee-selected-count');
                const printForm = document.getElementById('employee-id-card-print-form');
                const hiddenInputs = document.getElementById('employee-id-card-hidden-inputs');
                const summary = document.getElementById('employee-id-card-print-summary');
                const validUntilInput = document.getElementById('employee-id-card-valid-until');
                const printModalEl = document.getElementById('employee-id-card-print-modal');

                if (!printForm || !hiddenInputs || !summary || !validUntilInput || !printModalEl) {
                    return;
                }

                const printModal = window.bootstrap?.Modal
                    ? window.bootstrap.Modal.getOrCreateInstance(printModalEl)
                    : null;

                const selectedIds = () => Array.from(selectionState.selectedIds.values()).sort((left, right) => left - right);

                const getFilteredTotal = () => {
                    const total = Number(pageContainer?.dataset.filteredTotal || 0);

                    return Number.isFinite(total) ? total : 0;
                };

                const syncCurrentPageCheckboxes = () => {
                    rowCheckboxes.forEach((input) => {
                        input.checked = selectionState.selectedIds.has(Number(input.value));
                    });
                };

                const setSelectionBusy = (active) => {
                    selectionState.selectingAll = active;

                    headerCheckbox?.toggleAttribute('disabled', active);
                    selectAllButton?.toggleAttribute('disabled', active);
                    clearButton?.toggleAttribute('disabled', active && selectionState.selectedIds.size === 0);
                };

                const updateSelectionUi = () => {
                    syncCurrentPageCheckboxes();

                    const selectedCount = selectionState.selectedIds.size;
                    const total = getFilteredTotal();

                    if (selectedBadge) {
                        selectedBadge.textContent = `${selectedCount} selected`;
                    }

                    if (printSelectedButton) {
                        printSelectedButton.disabled = selectedCount === 0;
                    }

                    if (headerCheckbox) {
                        headerCheckbox.checked = total > 0 && selectedCount === total;
                        headerCheckbox.indeterminate = selectedCount > 0 && selectedCount < total;
                    }
                };

                const clearSelection = () => {
                    selectionState.selectedIds.clear();
                    updateSelectionUi();
                };

                const selectAllResults = async () => {
                    if (selectionState.selectingAll) {
                        return;
                    }

                    setSelectionBusy(true);

                    try {
                        const payload = await fetchAllFilteredEmployeeIds();
                        selectionState.selectedIds = new Set(payload.ids);

                        if (pageContainer) {
                            pageContainer.dataset.filteredTotal = String(payload.total);
                        }

                        updateSelectionUi();
                    } catch (_) {
                        window.location.href = window.location.href;
                    } finally {
                        setSelectionBusy(false);
                    }
                };

                const openPrintModal = (employeeIds, singleLabel = null) => {
                    if (!employeeIds.length) {
                        return;
                    }

                    hiddenInputs.innerHTML = '';
                    employeeIds.forEach((employeeId) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'employee_ids[]';
                        input.value = String(employeeId);
                        hiddenInputs.appendChild(input);
                    });

                    summary.textContent = singleLabel
                        ? `Selected employee: ${singleLabel}`
                        : `Selected employees: ${employeeIds.length}`;

                    if (!validUntilInput.value) {
                        const now = new Date();
                        const future = new Date(now);
                        // add 3 months
                        future.setMonth(future.getMonth() + 3);
                        const localIsoDate = new Date(future.getTime() - (future.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
                        validUntilInput.value = localIsoDate;
                    }

                    printModal?.show();
                };

                if (!scope.dataset.idCardSelectionInit) {
                    scope.dataset.idCardSelectionInit = '1';

                    rowCheckboxes.forEach((input) => {
                        input.addEventListener('change', function () {
                            const employeeId = Number(this.value);

                            if (!Number.isInteger(employeeId) || employeeId <= 0) {
                                return;
                            }

                            if (this.checked) {
                                selectionState.selectedIds.add(employeeId);
                            } else {
                                selectionState.selectedIds.delete(employeeId);
                            }

                            updateSelectionUi();
                        });
                    });

                    // Make entire first cell clickable to toggle checkbox (larger hit area)
                    const selectCells = Array.from(scope.querySelectorAll('.employee-select-cell'));
                    selectCells.forEach((cell) => {
                        cell.addEventListener('click', function (e) {
                            // ignore clicks on interactive elements inside the cell
                            if (e.target.closest('input, button, a, label')) {
                                return;
                            }

                            const checkbox = cell.querySelector('.employee-select-checkbox');
                            if (!checkbox) {
                                return;
                            }

                            const id = Number(checkbox.value);
                            if (!Number.isInteger(id) || id <= 0) {
                                return;
                            }

                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });

                    headerCheckbox?.addEventListener('change', function () {
                        if (this.checked) {
                            selectAllResults();
                            return;
                        }

                        clearSelection();
                    });

                    selectAllButton?.addEventListener('click', function () {
                        selectAllResults();
                    });

                    clearButton?.addEventListener('click', function () {
                        clearSelection();
                    });

                    printSelectedButton?.addEventListener('click', function () {
                        openPrintModal(selectedIds());
                    });

                    scope.querySelectorAll('[data-print-single-id]').forEach((button) => {
                        button.addEventListener('click', function () {
                            const employeeId = Number(this.dataset.printSingleId || 0);
                            if (!employeeId) {
                                return;
                            }

                            const employeeCode = this.dataset.printSingleCode || '-';
                            const employeeName = this.dataset.printSingleName || 'Employee';
                            openPrintModal([employeeId], `${employeeName} (${employeeCode})`);
                        });
                    });
                }

                syncCurrentPageCheckboxes();
                updateSelectionUi();
            }

            function openModalById(modalId) {
                if (!modalId || !window.bootstrap || !window.bootstrap.Modal) {
                    return;
                }

                const modalEl = document.getElementById(modalId);
                if (!modalEl) {
                    return;
                }

                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            function setLoading(active) {
                const loadingEl = document.getElementById('employees-page-loading');
                if (!loadingEl) {
                    return;
                }

                loadingEl.classList.toggle('d-none', !active);
                loadingEl.classList.toggle('d-flex', active);
            }

            async function replacePageContent(url, pushState = true) {
                if (isLoading) {
                    return;
                }

                syncEmployeeSelectionScope(url);

                isLoading = true;
                setLoading(true);

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.querySelector('#employees-page-container');
                    const currentContainer = document.querySelector('#employees-page-container');

                    if (!newContainer || !currentContainer) {
                        window.location.href = url;
                        return;
                    }

                    currentContainer.replaceWith(newContainer);

                    if (pushState) {
                        window.history.pushState({}, '', url);
                    }

                    if (typeof initEmployeeFilters === 'function') {
                        initEmployeeFilters();
                    }

                    initPageTooltips(newContainer);
                    initPhotoInputs(document);
                    initIdCardPrint(newContainer);

                    if (window.feather && typeof window.feather.replace === 'function') {
                        window.feather.replace();
                    }
                } catch (_) {
                    window.location.href = url;
                } finally {
                    isLoading = false;
                    setLoading(false);
                }
            }

            window.employeeReplacePageContent = replacePageContent;

            document.addEventListener('click', function (event) {
                const link = event.target.closest('#employees-page-container a[href*="page="]');
                if (!link) {
                    return;
                }

                event.preventDefault();
                replacePageContent(link.href, true);
            });

            window.addEventListener('popstate', function () {
                syncEmployeeSelectionScope(window.location.href);
                replacePageContent(window.location.href, false);
            });

            syncEmployeeSelectionScope(window.location.href);
            initPageTooltips(document);
            initPhotoInputs(document);
            initIdCardPrint(document.getElementById('employees-page-container') || document);
            openModalById(editModalId || createModalId);
        })();
    </script>
@endpush
