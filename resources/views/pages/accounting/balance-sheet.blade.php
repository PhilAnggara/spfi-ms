@extends('layouts.app')
@section('title', ' | Balance Sheet Mapping')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Balance Sheet Mapping</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Mapping
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped text-center text-nowrap" id="balance-sheet-table" data-source="{{ route('accounting.balance-sheet.datatables') }}">
                    <thead>
                        <tr>
                            <th class="text-center d-none">ID</th>
                            <th class="text-center">Group Code</th>
                            <th class="text-center">Accounting Code</th>
                            <th class="text-center">Grouping</th>
                            <th class="text-center">Major</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Create Mapping Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createBalanceSheetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBalanceSheetLabel">Create Mapping</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.balance-sheet.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="group_code_id">Group Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="group_code_id" name="group_code_id" class="form-select choices {{ ($errors->any() && !session('editing_balance_sheet_id')) ? ($errors->has('group_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled selected>Select Group Code</option>
                                    @foreach ($groupCodes as $groupCode)
                                        <option value="{{ $groupCode->id }}" {{ old('group_code_id') == $groupCode->id ? 'selected' : '' }}>{{ $groupCode->group_code }} - {{ $groupCode->group_desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_balance_sheet_id'))
                                    @error('group_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="accounting_code_id">Accounting Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="accounting_code_id" name="accounting_code_id" class="form-select choices {{ ($errors->any() && !session('editing_balance_sheet_id')) ? ($errors->has('accounting_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled selected>Select Accounting Code</option>
                                    @foreach ($accountingCodes as $accountingCode)
                                        <option value="{{ $accountingCode->id }}" {{ old('accounting_code_id') == $accountingCode->id ? 'selected' : '' }}>{{ $accountingCode->code }} - {{ $accountingCode->desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_balance_sheet_id'))
                                    @error('accounting_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="grouping_id">Grouping</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="grouping_id" name="grouping_id" class="form-select choices">
                                    <option value="">-</option>
                                    @foreach ($groupings as $grouping)
                                        <option value="{{ $grouping->id }}" {{ old('grouping_id') == $grouping->id ? 'selected' : '' }}>{{ $grouping->code }} - {{ $grouping->desc }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="major">Major</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="major" name="major" class="form-control" value="{{ old('major') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="reset" class="btn icon icon-left btn-light-secondary">
                        <i class="fa-thin fa-rotate-left"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal (Reusable) -->
<div class="modal fade text-left modal-borderless" id="edit-modal" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label">Edit Mapping</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ $editingBalanceSheet ? route('accounting.balance-sheet.update', $editingBalanceSheet->id) : '#' }}" method="POST" class="form form-horizontal" id="edit-form">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="edit-group-code-id">Group Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-group-code-id" name="group_code_id" class="form-select choices {{ ($errors->any() && session('editing_balance_sheet_id')) ? ($errors->has('group_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    @foreach ($groupCodes as $groupCode)
                                        <option value="{{ $groupCode->id }}" {{ (session('editing_balance_sheet_id') ? old('group_code_id', $editingBalanceSheet?->group_code_id) : $editingBalanceSheet?->group_code_id) == $groupCode->id ? 'selected' : '' }}>{{ $groupCode->group_code }} - {{ $groupCode->group_desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_balance_sheet_id'))
                                    @error('group_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-accounting-code-id">Accounting Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-accounting-code-id" name="accounting_code_id" class="form-select choices {{ ($errors->any() && session('editing_balance_sheet_id')) ? ($errors->has('accounting_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    @foreach ($accountingCodes as $accountingCode)
                                        <option value="{{ $accountingCode->id }}" {{ (session('editing_balance_sheet_id') ? old('accounting_code_id', $editingBalanceSheet?->accounting_code_id) : $editingBalanceSheet?->accounting_code_id) == $accountingCode->id ? 'selected' : '' }}>{{ $accountingCode->code }} - {{ $accountingCode->desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_balance_sheet_id'))
                                    @error('accounting_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-grouping-id">Grouping</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-grouping-id" name="grouping_id" class="form-select choices">
                                    <option value="">-</option>
                                    @foreach ($groupings as $grouping)
                                        <option value="{{ $grouping->id }}" {{ (session('editing_balance_sheet_id') ? old('grouping_id', $editingBalanceSheet?->grouping_id) : $editingBalanceSheet?->grouping_id) == $grouping->id ? 'selected' : '' }}>{{ $grouping->code }} - {{ $grouping->desc }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="edit-major">Major</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-major" name="major" class="form-control" value="{{ session('editing_balance_sheet_id') ? old('major', $editingBalanceSheet?->major) : ($editingBalanceSheet?->major ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn icon icon-left btn-light-primary" data-bs-dismiss="modal">
                        <i class="fa-thin fa-xmark"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn icon icon-left btn-primary ms-1">
                        <i class="fa-thin fa-file-pen me-1"></i>
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush
@push('addon-script')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = $('#balance-sheet-table');
        const tableUrl = table.data('source');
        const csrfToken = '{{ csrf_token() }}';
        const updateRouteTemplate = '{{ route('accounting.balance-sheet.update', '__ID__') }}';
        const editModalElement = document.getElementById('edit-modal');
        const editModal = editModalElement ? new bootstrap.Modal(editModalElement) : null;

        const escapeHtml = (value) => {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const dataTable = table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: tableUrl,
                type: 'GET'
            },
            order: [[0, 'desc']],
            columns: [
                {
                    data: 'id',
                    visible: false,
                    searchable: false
                },
                {
                    data: 'group_code',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: 'accounting_code',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: 'grouping_code',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: 'major',
                    render: function(data) {
                        return escapeHtml(data ?? '-');
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(row) {
                        const safeGroupCode = escapeHtml(row.group_code ?? '-');
                        const safeAccountingCode = escapeHtml(row.accounting_code ?? '-');
                        const editAttrs = `
                            data-id="${row.id}"
                            data-group-code-id="${row.group_code_id ?? ''}"
                            data-accounting-code-id="${row.accounting_code_id ?? ''}"
                            data-grouping-id="${row.grouping_id ?? ''}"
                            data-major="${escapeHtml(row.major ?? '')}"
                            data-group-code="${safeGroupCode}"
                            data-accounting-code="${safeAccountingCode}"
                        `;

                        return `
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn icon edit-balance-sheet" ${editAttrs} data-bs-toggle="modal" data-bs-target="#edit-modal" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                    <i class="fa-light fa-edit text-primary"></i>
                                </button>
                                <button type="button" class="btn icon delete-balance-sheet" data-id="${row.id}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                    <i class="fa-light fa-trash text-secondary"></i>
                                </button>
                                <form action="{{ route('accounting.balance-sheet.destroy', '__ID__') }}" id="hapus-${row.id}" method="POST">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="delete">
                                </form>
                            </div>
                        `.replace('__ID__', row.id);
                    }
                }
            ]
        });

        $('#balance-sheet-table tbody').on('click', '.edit-balance-sheet', function() {
            const button = $(this);
            const itemId = button.data('id');
            const updateUrl = updateRouteTemplate.replace('__ID__', itemId);
            const setSelectValue = (elementId, value) => {
                const selectElement = document.getElementById(elementId);
                if (!selectElement) {
                    return;
                }

                const normalizedValue = value === null || value === undefined ? '' : String(value);
                if (selectElement.choicesInstance) {
                    selectElement.choicesInstance.removeActiveItems();
                    if (normalizedValue) {
                        selectElement.choicesInstance.setChoiceByValue(normalizedValue);
                    }
                } else {
                    selectElement.value = normalizedValue;
                }

                selectElement.dispatchEvent(new Event('change'));
            };

            setSelectValue('edit-group-code-id', button.data('group-code-id'));
            setSelectValue('edit-accounting-code-id', button.data('accounting-code-id'));
            setSelectValue('edit-grouping-id', button.data('grouping-id'));
            document.getElementById('edit-major').value = button.data('major') || '';
            document.getElementById('edit-form').setAttribute('action', updateUrl);

            const editLabel = document.getElementById('edit-modal-label');
            if (editLabel) {
                editLabel.textContent = `Edit Mapping - ${button.data('group-code') || ''} / ${button.data('accounting-code') || ''}`;
            }

            if (editModal) {
                editModal.show();
            }
        });

        $('#balance-sheet-table tbody').on('click', '.delete-balance-sheet', function() {
            const itemId = $(this).data('id');
            hapusData(itemId, 'Delete Mapping', 'Are you sure want to delete this mapping?');
        });

        @if ($errors->any())
            @if (session('editing_balance_sheet_id'))
                if (editModal) {
                    editModal.show();
                }
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
