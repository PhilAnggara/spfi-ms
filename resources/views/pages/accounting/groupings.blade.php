@extends('layouts.app')
@section('title', ' | Groupings')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Groupings</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add Grouping
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
                            <th class="text-center">Major</th>
                            <th class="text-center">Grp</th>
                            <th class="text-center">Tab</th>
                            <th class="text-center">Other</th>
                            <th class="text-center">Selection</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupings as $grouping)
                            <tr>
                                <td>
                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $grouping->code }}')">{{ $grouping->code }}</span>
                                </td>
                                <td>{{ $grouping->desc }}</td>
                                <td>{{ $grouping->major ?? '-' }}</td>
                                <td>{{ $grouping->grp }}</td>
                                <td>{{ $grouping->tab }}</td>
                                <td>{{ $grouping->other ? 'Yes' : 'No' }}</td>
                                <td>{{ $grouping->selection ? 'Yes' : 'No' }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $grouping->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                            <i class="fa-light fa-edit text-primary"></i>
                                        </button>
                                        <button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $grouping->id }}, 'Delete Grouping', 'Are you sure want to delete {{ $grouping->code }}?')">
                                            <i class="fa-light fa-trash text-secondary"></i>
                                        </button>
                                        <form action="{{ route('accounting.groupings.destroy', $grouping->id) }}" id="hapus-{{ $grouping->id }}" method="POST">
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

<!-- Create Grouping Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createGroupingLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createGroupingLabel">Create Grouping</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.groupings.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" class="form-control {{ ($errors->any() && !session('editing_grouping_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_grouping_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_grouping_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="desc">Description</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="desc" name="desc" class="form-control {{ ($errors->any() && !session('editing_grouping_id')) ? ($errors->has('desc') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_grouping_id')) ? old('desc') : '' }}" required>
                                @if ($errors->any() && !session('editing_grouping_id'))
                                    @error('desc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="major">Major</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="major" name="major" class="form-control" value="{{ ($errors->any() && !session('editing_grouping_id')) ? old('major') : '' }}">
                            </div>

                            <div class="col-md-4">
                                <label for="grp">Grp</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="number" id="grp" name="grp" class="form-control" value="{{ ($errors->any() && !session('editing_grouping_id')) ? old('grp', 0) : 0 }}">
                            </div>

                            <div class="col-md-4">
                                <label for="tab">Tab</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="number" id="tab" name="tab" class="form-control" value="{{ ($errors->any() && !session('editing_grouping_id')) ? old('tab', 0) : 0 }}">
                            </div>

                            <div class="col-md-4">
                                <label>Flags</label>
                            </div>
                            <div class="col-md-8 form-group d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="other" name="other" value="1" {{ old('other') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="other">Other</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selection" name="selection" value="1" {{ old('selection') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="selection">Selection</label>
                                </div>
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
@foreach ($groupings as $grouping)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $grouping->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $grouping->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $grouping->id }}">Edit Grouping - {{ $grouping->code }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('accounting.groupings.update', $grouping->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code-{{ $grouping->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code-{{ $grouping->id }}" name="code" class="form-control {{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('code') : $grouping->code }}" required>
                                @if ($errors->any() && session('editing_grouping_id') == $grouping->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="desc-{{ $grouping->id }}">Description</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="desc-{{ $grouping->id }}" name="desc" class="form-control {{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? ($errors->has('desc') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('desc') : $grouping->desc }}" required>
                                @if ($errors->any() && session('editing_grouping_id') == $grouping->id)
                                    @error('desc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="major-{{ $grouping->id }}">Major</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="major-{{ $grouping->id }}" name="major" class="form-control" value="{{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('major') : $grouping->major }}">
                            </div>

                            <div class="col-md-4">
                                <label for="grp-{{ $grouping->id }}">Grp</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="number" id="grp-{{ $grouping->id }}" name="grp" class="form-control" value="{{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('grp') : $grouping->grp }}">
                            </div>

                            <div class="col-md-4">
                                <label for="tab-{{ $grouping->id }}">Tab</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="number" id="tab-{{ $grouping->id }}" name="tab" class="form-control" value="{{ ($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('tab') : $grouping->tab }}">
                            </div>

                            <div class="col-md-4">
                                <label>Flags</label>
                            </div>
                            <div class="col-md-8 form-group d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="other-{{ $grouping->id }}" name="other" value="1" {{ (($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('other') : $grouping->other) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="other-{{ $grouping->id }}">Other</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selection-{{ $grouping->id }}" name="selection" value="1" {{ (($errors->any() && session('editing_grouping_id') == $grouping->id) ? old('selection') : $grouping->selection) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="selection-{{ $grouping->id }}">Selection</label>
                                </div>
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
            @if (session('editing_grouping_id'))
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_grouping_id") }}'));
                editModal.show();
            @else
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
