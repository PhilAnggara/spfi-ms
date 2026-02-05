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
                <table class="table table-striped text-center text-nowrap" id="table1">
                    <thead>
                        <tr>
                            <th class="text-center">Group Code</th>
                            <th class="text-center">Accounting Code</th>
                            <th class="text-center">Grouping</th>
                            <th class="text-center">Major</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balanceSheets as $balanceSheet)
                            <tr>
                                <td>{{ $balanceSheet->groupCode?->group_code ?? '-' }}</td>
                                <td>{{ $balanceSheet->accountingCode?->code ?? '-' }}</td>
                                <td>{{ $balanceSheet->grouping?->code ?? '-' }}</td>
                                <td>{{ $balanceSheet->major ?? '-' }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $balanceSheet->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                            <i class="fa-light fa-edit text-primary"></i>
                                        </button>
                                        <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $balanceSheet->id }}, 'Delete Mapping', 'Are you sure want to delete this mapping?')">
                                            <i class="fa-light fa-trash text-secondary"></i>
                                        </button>
                                        <form action="{{ route('accounting.balance-sheet.destroy', $balanceSheet->id) }}" id="hapus-{{ $balanceSheet->id }}" method="POST">
                                            @method('delete')
                                            @csrf
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
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

<!-- Edit Modals -->
@foreach ($balanceSheets as $balanceSheet)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $balanceSheet->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $balanceSheet->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $balanceSheet->id }}">Edit Mapping</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.balance-sheet.update', $balanceSheet->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="group_code_id-{{ $balanceSheet->id }}">Group Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="group_code_id-{{ $balanceSheet->id }}" name="group_code_id" class="form-select choices {{ ($errors->any() && session('editing_balance_sheet_id') == $balanceSheet->id) ? ($errors->has('group_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    @foreach ($groupCodes as $groupCode)
                                        <option value="{{ $groupCode->id }}" {{ (session('editing_balance_sheet_id') == $balanceSheet->id ? old('group_code_id', $balanceSheet->group_code_id) : $balanceSheet->group_code_id) == $groupCode->id ? 'selected' : '' }}>{{ $groupCode->group_code }} - {{ $groupCode->group_desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_balance_sheet_id') == $balanceSheet->id)
                                    @error('group_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="accounting_code_id-{{ $balanceSheet->id }}">Accounting Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="accounting_code_id-{{ $balanceSheet->id }}" name="accounting_code_id" class="form-select choices {{ ($errors->any() && session('editing_balance_sheet_id') == $balanceSheet->id) ? ($errors->has('accounting_code_id') ? 'is-invalid' : '') : '' }}" required>
                                    @foreach ($accountingCodes as $accountingCode)
                                        <option value="{{ $accountingCode->id }}" {{ (session('editing_balance_sheet_id') == $balanceSheet->id ? old('accounting_code_id', $balanceSheet->accounting_code_id) : $balanceSheet->accounting_code_id) == $accountingCode->id ? 'selected' : '' }}>{{ $accountingCode->code }} - {{ $accountingCode->desc }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_balance_sheet_id') == $balanceSheet->id)
                                    @error('accounting_code_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="grouping_id-{{ $balanceSheet->id }}">Grouping</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="grouping_id-{{ $balanceSheet->id }}" name="grouping_id" class="form-select choices">
                                    <option value="">-</option>
                                    @foreach ($groupings as $grouping)
                                        <option value="{{ $grouping->id }}" {{ (session('editing_balance_sheet_id') == $balanceSheet->id ? old('grouping_id', $balanceSheet->grouping_id) : $balanceSheet->grouping_id) == $grouping->id ? 'selected' : '' }}>{{ $grouping->code }} - {{ $grouping->desc }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="major-{{ $balanceSheet->id }}">Major</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="major-{{ $balanceSheet->id }}" name="major" class="form-control" value="{{ (session('editing_balance_sheet_id') == $balanceSheet->id) ? old('major', $balanceSheet->major) : $balanceSheet->major }}">
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
@endforeach
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_balance_sheet_id'))
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_balance_sheet_id") }}'));
                editModal.show();
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
