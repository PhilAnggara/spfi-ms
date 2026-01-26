<!-- Create Unit of Measurement Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createUomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUomLabel">Create Unit of Measurement</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('unit-of-measurement.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="e.g. PCS" class="form-control {{ ($errors->any() && !session('editing_uom_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_uom_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_uom_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" name="name" placeholder="e.g. Pieces" class="form-control {{ ($errors->any() && !session('editing_uom_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_uom_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_uom_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="remarks">Remarks</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="remarks" name="remarks" placeholder="Optional" class="form-control {{ ($errors->any() && !session('editing_uom_id')) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}" rows="2">{{ ($errors->any() && !session('editing_uom_id')) ? old('remarks') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_uom_id'))
                                    @error('remarks')
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
@foreach ($units as $unit)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $unit->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $unit->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $unit->id }}">Edit Unit of Measurement - {{ $unit->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('unit-of-measurement.update', $unit->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code-{{ $unit->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code-{{ $unit->id }}" name="code" placeholder="e.g. PCS" class="form-control {{ ($errors->any() && session('editing_uom_id') == $unit->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_uom_id') == $unit->id) ? old('code') : $unit->code }}" required>
                                @if ($errors->any() && session('editing_uom_id') == $unit->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name-{{ $unit->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name-{{ $unit->id }}" name="name" placeholder="e.g. Pieces" class="form-control {{ ($errors->any() && session('editing_uom_id') == $unit->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_uom_id') == $unit->id) ? old('name') : $unit->name }}" required>
                                @if ($errors->any() && session('editing_uom_id') == $unit->id)
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="remarks-{{ $unit->id }}">Remarks</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="remarks-{{ $unit->id }}" name="remarks" class="form-control {{ ($errors->any() && session('editing_uom_id') == $unit->id) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}" rows="2">{{ ($errors->any() && session('editing_uom_id') == $unit->id) ? old('remarks') : $unit->remarks }}</textarea>
                                @if ($errors->any() && session('editing_uom_id') == $unit->id)
                                    @error('remarks')
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
