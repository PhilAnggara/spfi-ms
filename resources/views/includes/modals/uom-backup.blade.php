<!-- Create Modal -->
<div class="modal fade" id="create-modal" tabindex="-1" aria-labelledby="create-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="create-modal-label">Create Unit of Measurement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('unit-of-measurement.store') }}" method="POST" id="form-create-uom">
                    @csrf
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Code</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="e.g. PCS" required>
                            @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Name</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Pieces" required>
                            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Remarks</label>
                        </div>
                        <div class="col-md-8">
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Optional">{{ old('remarks') }}</textarea>
                            @error('remarks') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                </form>
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
        </div>
    </div>
</div>

<!-- Edit Modals -->
@foreach ($units as $uom)
<div class="modal fade" id="edit-modal-{{ $uom->id }}" tabindex="-1" aria-labelledby="edit-modal-label-{{ $uom->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $uom->id }}">Edit Unit of Measurement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('unit-of-measurement.update', $uom->id) }}" method="POST" id="form-edit-uom-{{ $uom->id }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Code</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="code" class="form-control" value="{{ old('code', $uom->code) }}" required>
                            @if (session('editing_uom_id') == $uom->id)
                                @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                            @endif
                        </div>
                    </div>
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Name</label>
                        </div>
                        <div class="col-md-8">
                            <input type="text" name="name" class="form-control" value="{{ old('name', $uom->name) }}" required>
                            @if (session('editing_uom_id') == $uom->id)
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            @endif
                        </div>
                    </div>
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-4">
                            <label class="col-form-label">Remarks</label>
                        </div>
                        <div class="col-md-8">
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $uom->remarks) }}</textarea>
                            @if (session('editing_uom_id') == $uom->id)
                                @error('remarks') <small class="text-danger">{{ $message }}</small> @enderror
                            @endif
                        </div>
                    </div>
                </form>
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
        </div>
    </div>
</div>
@endforeach
