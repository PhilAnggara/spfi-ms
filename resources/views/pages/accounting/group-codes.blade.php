@extends('layouts.app')
@section('title', ' | Group Codes')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Group Codes</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Group Code
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
                            <th class="text-center">Code</th>
                            <th class="text-center">Description</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupCodes as $groupCode)
                            <tr>
                                <td>
                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $groupCode->group_code }}')">{{ $groupCode->group_code }}</span>
                                </td>
                                <td>{{ $groupCode->group_desc }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $groupCode->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                            <i class="fa-light fa-edit text-primary"></i>
                                        </button>
                                        <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $groupCode->id }}, 'Delete Group Code', 'Are you sure want to delete {{ $groupCode->group_code }}?')">
                                            <i class="fa-light fa-trash text-secondary"></i>
                                        </button>
                                        <form action="{{ route('accounting.group-codes.destroy', $groupCode->id) }}" id="hapus-{{ $groupCode->id }}" method="POST">
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

<!-- Create Group Code Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createGroupCodeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createGroupCodeLabel">Create Group Code</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.group-codes.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="group_code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="group_code" name="group_code" class="form-control {{ ($errors->any() && !session('editing_group_code_id')) ? ($errors->has('group_code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_group_code_id')) ? old('group_code') : '' }}" required>
                                @if ($errors->any() && !session('editing_group_code_id'))
                                    @error('group_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="group_desc">Description</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="group_desc" name="group_desc" class="form-control {{ ($errors->any() && !session('editing_group_code_id')) ? ($errors->has('group_desc') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_group_code_id')) ? old('group_desc') : '' }}" required>
                                @if ($errors->any() && !session('editing_group_code_id'))
                                    @error('group_desc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
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
@foreach ($groupCodes as $groupCode)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $groupCode->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $groupCode->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $groupCode->id }}">Edit Group Code - {{ $groupCode->group_code }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.group-codes.update', $groupCode->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="group_code-{{ $groupCode->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="group_code-{{ $groupCode->id }}" name="group_code" class="form-control {{ ($errors->any() && session('editing_group_code_id') == $groupCode->id) ? ($errors->has('group_code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_group_code_id') == $groupCode->id) ? old('group_code') : $groupCode->group_code }}" required>
                                @if ($errors->any() && session('editing_group_code_id') == $groupCode->id)
                                    @error('group_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="group_desc-{{ $groupCode->id }}">Description</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="group_desc-{{ $groupCode->id }}" name="group_desc" class="form-control {{ ($errors->any() && session('editing_group_code_id') == $groupCode->id) ? ($errors->has('group_desc') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_group_code_id') == $groupCode->id) ? old('group_desc') : $groupCode->group_desc }}" required>
                                @if ($errors->any() && session('editing_group_code_id') == $groupCode->id)
                                    @error('group_desc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
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

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush

@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_group_code_id'))
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_group_code_id") }}'));
                editModal.show();
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
