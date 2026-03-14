@php
    $employee = $employee ?? null;
    $useOld = $useOld ?? false;
    $fieldValue = function (string $field, mixed $default = null) use ($useOld) {
        return $useOld ? old($field, $default) : $default;
    };
    $selectedDepartmentId = $fieldValue('employee_department_id', $employee?->employee_department_id);
    $selectedGender = $fieldValue('gender', $employee?->gender);
    $defaultPhotoUrl = asset('assets/images/employee-default.svg');
    $hasExistingPhoto = filled($employee?->photo_path);
    $photoPreviewUrl = $employee?->photo_url ?? $defaultPhotoUrl;
    $photoInputId = $prefix . '-photo';
    $fileNameTargetId = $prefix . '-photo-file-name';
    $clearButtonId = $prefix . '-photo-clear';
    $removeExistingButtonId = $prefix . '-photo-remove-existing';
    $removeInputId = $prefix . '-remove-photo';
@endphp

<div class="row g-3">
    <div class="col-12">
        <div class="employee-photo-upload-card">
            <div class="employee-photo-upload-preview-wrap">
                <img
                    src="{{ $photoPreviewUrl }}"
                    alt="Employee photo preview"
                    class="employee-photo-upload-preview"
                    id="{{ $prefix }}-photo-preview"
                >
            </div>

            <div class="employee-photo-upload-content">
                <div class="employee-photo-upload-header">
                    <div>
                        <label for="{{ $photoInputId }}" class="form-label mb-1">Employee Photo</label>
                        <div class="employee-photo-upload-subtitle">Drag and drop an image here, or click the upload area to choose a file.</div>
                    </div>
                    @if ($employee?->photo_path)
                        <span class="badge bg-light-primary">Current photo</span>
                    @endif
                </div>

                <div
                    class="employee-photo-dropzone @error('photo') is-invalid @enderror"
                    data-photo-dropzone
                    data-input-id="{{ $photoInputId }}"
                    aria-label="Upload employee photo"
                >
                    <div class="employee-photo-dropzone-icon">
                        <i class="fa-duotone fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div class="employee-photo-dropzone-copy">
                        <div class="employee-photo-dropzone-title">Drop image here</div>
                        <div class="employee-photo-dropzone-text">or click to choose a file</div>
                    </div>
                    <div class="employee-photo-dropzone-meta">JPG, PNG, WEBP, GIF up to 2 MB</div>
                    <input
                        type="file"
                        id="{{ $photoInputId }}"
                        name="photo"
                        accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                        class="employee-photo-input @error('photo') is-invalid @enderror"
                        data-preview-target="{{ $prefix }}-photo-preview"
                        data-default-src="{{ $defaultPhotoUrl }}"
                        data-existing-src="{{ $photoPreviewUrl }}"
                        data-file-name-target="{{ $fileNameTargetId }}"
                        data-existing-file-name="{{ $hasExistingPhoto ? basename((string) $employee->photo_path) : 'No file selected' }}"
                        data-clear-button-id="{{ $clearButtonId }}"
                        data-remove-existing-button-id="{{ $removeExistingButtonId }}"
                        data-remove-input-id="{{ $removeInputId }}"
                        data-has-existing-photo="{{ $hasExistingPhoto ? 'true' : 'false' }}"
                    >
                </div>

                <input type="hidden" name="remove_photo" id="{{ $removeInputId }}" value="{{ old('remove_photo', '0') }}">

                <div class="employee-photo-upload-toolbar">
                    <div class="employee-photo-file-name" id="{{ $fileNameTargetId }}">
                        {{ $hasExistingPhoto ? basename((string) $employee->photo_path) : 'No file selected' }}
                    </div>
                    <div class="employee-photo-upload-actions">
                        <button type="button" class="btn btn-light-secondary btn-sm d-none" id="{{ $clearButtonId }}" data-photo-clear="{{ $photoInputId }}">
                            <i class="fa-light fa-xmark me-1"></i>
                            Clear Selection
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm {{ $hasExistingPhoto ? '' : 'd-none' }}" id="{{ $removeExistingButtonId }}" data-photo-remove-existing="{{ $photoInputId }}">
                            <i class="fa-light fa-trash-can me-1"></i>
                            Delete Current Photo
                        </button>
                    </div>
                </div>

                @error('photo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-employee-id" class="form-label mb-1">Employee ID</label>
        <input type="text" id="{{ $prefix }}-employee-id" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" value="{{ $fieldValue('employee_id', $employee?->employee_id) }}" maxlength="50" required>
        @error('employee_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-code-employee" class="form-label mb-1">Code Employee</label>
        <input type="text" id="{{ $prefix }}-code-employee" name="code_employee" class="form-control @error('code_employee') is-invalid @enderror" value="{{ $fieldValue('code_employee', $employee?->code_employee) }}" maxlength="100">
        @error('code_employee')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-id-biometrik" class="form-label mb-1">Biometric ID</label>
        <input type="text" id="{{ $prefix }}-id-biometrik" name="id_biometrik" class="form-control @error('id_biometrik') is-invalid @enderror" value="{{ $fieldValue('id_biometrik', $employee?->id_biometrik) }}" maxlength="100">
        @error('id_biometrik')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-6">
        <label for="{{ $prefix }}-employee-name" class="form-label mb-1">Employee Name</label>
        <input type="text" id="{{ $prefix }}-employee-name" name="employee_name" class="form-control @error('employee_name') is-invalid @enderror" value="{{ $fieldValue('employee_name', $employee?->employee_name) }}" maxlength="255" required>
        @error('employee_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-lg-6">
        <label for="{{ $prefix }}-department" class="form-label mb-1">Department</label>
        <select id="{{ $prefix }}-department" name="employee_department_id" class="form-select @error('employee_department_id') is-invalid @enderror" required>
            <option value="">Select department</option>
            @foreach ($departmentOptions as $department)
                <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>
                    {{ $department->code }} - {{ $department->name }}
                </option>
            @endforeach
        </select>
        @error('employee_department_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-gender" class="form-label mb-1">Gender</label>
        <select id="{{ $prefix }}-gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
            <option value="">Select gender</option>
            <option value="M" @selected((string) $selectedGender === 'M')>Male</option>
            <option value="F" @selected((string) $selectedGender === 'F')>Female</option>
        </select>
        @error('gender')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-position-name" class="form-label mb-1">Position</label>
        <input type="text" id="{{ $prefix }}-position-name" name="position_name" class="form-control @error('position_name') is-invalid @enderror" value="{{ $fieldValue('position_name', $employee?->position_name) }}" maxlength="255">
        @error('position_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-pay-type" class="form-label mb-1">Pay Type</label>
        <input type="text" id="{{ $prefix }}-pay-type" name="pay_type" class="form-control @error('pay_type') is-invalid @enderror" value="{{ $fieldValue('pay_type', $employee?->pay_type) }}" maxlength="50">
        @error('pay_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-contract" class="form-label mb-1">Contract</label>
        <input type="text" id="{{ $prefix }}-contract" name="contract" class="form-control @error('contract') is-invalid @enderror" value="{{ $fieldValue('contract', $employee?->contract) }}" maxlength="100">
        @error('contract')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-civil-status" class="form-label mb-1">Civil Status</label>
        <input type="text" id="{{ $prefix }}-civil-status" name="civil_status" class="form-control @error('civil_status') is-invalid @enderror" value="{{ $fieldValue('civil_status', $employee?->civil_status) }}" maxlength="50">
        @error('civil_status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-date-hired" class="form-label mb-1">Date Hired</label>
        <input type="date" id="{{ $prefix }}-date-hired" name="date_hired" class="form-control @error('date_hired') is-invalid @enderror" value="{{ $fieldValue('date_hired', optional($employee?->date_hired)->format('Y-m-d')) }}">
        @error('date_hired')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-date-terminated" class="form-label mb-1">Date Terminated</label>
        <input type="date" id="{{ $prefix }}-date-terminated" name="date_terminated" class="form-control @error('date_terminated') is-invalid @enderror" value="{{ $fieldValue('date_terminated', optional($employee?->date_terminated)->format('Y-m-d')) }}">
        @error('date_terminated')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-date-of-birth" class="form-label mb-1">Date of Birth</label>
        <input type="date" id="{{ $prefix }}-date-of-birth" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ $fieldValue('date_of_birth', optional($employee?->date_of_birth)->format('Y-m-d')) }}">
        @error('date_of_birth')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-cell-phone" class="form-label mb-1">Cell Phone</label>
        <input type="text" id="{{ $prefix }}-cell-phone" name="cell_phone" class="form-control @error('cell_phone') is-invalid @enderror" value="{{ $fieldValue('cell_phone', $employee?->cell_phone) }}" maxlength="50">
        @error('cell_phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-account-no" class="form-label mb-1">Account No</label>
        <input type="text" id="{{ $prefix }}-account-no" name="account_no" class="form-control @error('account_no') is-invalid @enderror" value="{{ $fieldValue('account_no', $employee?->account_no) }}" maxlength="255">
        @error('account_no')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-identity-card" class="form-label mb-1">Identity Card No</label>
        <input type="text" id="{{ $prefix }}-identity-card" name="identity_card_no" class="form-control @error('identity_card_no') is-invalid @enderror" value="{{ $fieldValue('identity_card_no', $employee?->identity_card_no) }}" maxlength="255">
        @error('identity_card_no')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-insurance-no" class="form-label mb-1">Insurance No</label>
        <input type="text" id="{{ $prefix }}-insurance-no" name="insurance_no" class="form-control @error('insurance_no') is-invalid @enderror" value="{{ $fieldValue('insurance_no', $employee?->insurance_no) }}" maxlength="255">
        @error('insurance_no')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-no-astek" class="form-label mb-1">No Astek</label>
        <input type="text" id="{{ $prefix }}-no-astek" name="no_astek" class="form-control @error('no_astek') is-invalid @enderror" value="{{ $fieldValue('no_astek', $employee?->no_astek) }}" maxlength="255">
        @error('no_astek')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="{{ $prefix }}-religion" class="form-label mb-1">Religion</label>
        <input type="text" id="{{ $prefix }}-religion" name="religion" class="form-control @error('religion') is-invalid @enderror" value="{{ $fieldValue('religion', $employee?->religion) }}" maxlength="100">
        @error('religion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="{{ $prefix }}-education" class="form-label mb-1">Education</label>
        <input type="text" id="{{ $prefix }}-education" name="education" class="form-control @error('education') is-invalid @enderror" value="{{ $fieldValue('education', $employee?->education) }}" maxlength="100">
        @error('education')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="{{ $prefix }}-remarks" class="form-label mb-1">Remarks</label>
        <textarea id="{{ $prefix }}-remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ $fieldValue('remarks', $employee?->remarks) }}</textarea>
        @error('remarks')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
