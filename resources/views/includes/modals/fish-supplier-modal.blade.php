<!-- Create Fish Supplier Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSupplierLabel">Create Fish Supplier</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('fish-supplier.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="e.g. FS001" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" name="name" placeholder="e.g. PT Mina Jaya" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('name')
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
@foreach ($suppliers as $supplier)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $supplier->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $supplier->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-modal-label-{{ $supplier->id }}">Edit Fish Supplier - {{ $supplier->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('fish-supplier.update', $supplier->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="edit-code-{{ $supplier->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-code-{{ $supplier->id }}" name="code" placeholder="e.g. FS001" class="form-control {{ (session('editing_supplier_id') == $supplier->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ (session('editing_supplier_id') == $supplier->id) ? old('code', $supplier->code) : $supplier->code }}" required>
                                @if (session('editing_supplier_id') == $supplier->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-name-{{ $supplier->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-name-{{ $supplier->id }}" name="name" placeholder="e.g. PT Mina Jaya" class="form-control {{ (session('editing_supplier_id') == $supplier->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ (session('editing_supplier_id') == $supplier->id) ? old('name', $supplier->name) : $supplier->name }}" required>
                                @if (session('editing_supplier_id') == $supplier->id)
                                    @error('name')
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
