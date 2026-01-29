<!-- Create Batch Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createBatchLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBatchLabel">Add Batch</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('batch.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Batch Number</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="Batch Number" class="form-control {{ ($errors->any() && !session('editing_batch_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_batch_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_batch_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fish_supplier_id">Fish Supplier</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fish_supplier_id" name="fish_supplier_id" class="form-select choices {{ ($errors->any() && !session('editing_batch_id')) ? ($errors->has('fish_supplier_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="">Select Fish Supplier</option>
                                    @foreach ($fishSuppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (($errors->any() && !session('editing_batch_id')) ? old('fish_supplier_id') : '') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_batch_id'))
                                    @error('fish_supplier_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="vessel_id">Vessel</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="vessel_id" name="vessel_id" class="form-select choices {{ ($errors->any() && !session('editing_batch_id')) ? ($errors->has('vessel_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="">Select Vessel</option>
                                    @foreach ($vessels as $vessel)
                                        <option value="{{ $vessel->id }}" {{ (($errors->any() && !session('editing_batch_id')) ? old('vessel_id') : '') == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_batch_id'))
                                    @error('vessel_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fishing_method">Fishing Method</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fishing_method" name="fishing_method" class="form-select {{ ($errors->any() && !session('editing_batch_id')) ? ($errors->has('fishing_method') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled selected>-- Select Fishing Method --</option>
                                    <option value="Purse - Seine" {{ (($errors->any() && !session('editing_batch_id')) ? old('fishing_method') : '') == 'Purse - Seine' ? 'selected' : '' }}>Purse - Seine</option>
                                    <option value="Pole & Line" {{ (($errors->any() && !session('editing_batch_id')) ? old('fishing_method') : '') == 'Pole & Line' ? 'selected' : '' }}>Pole & Line</option>
                                </select>
                                @if ($errors->any() && !session('editing_batch_id'))
                                    @error('fishing_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fish_type">Fish Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fish_type" name="fish_type" class="form-select {{ ($errors->any() && !session('editing_batch_id')) ? ($errors->has('fish_type') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled selected>-- Select Fish Type --</option>
                                    <option value="Fresh" {{ (($errors->any() && !session('editing_batch_id')) ? old('fish_type') : '') == 'Fresh' ? 'selected' : '' }}>Fresh</option>
                                    <option value="Frozen" {{ (($errors->any() && !session('editing_batch_id')) ? old('fish_type') : '') == 'Frozen' ? 'selected' : '' }}>Frozen</option>
                                </select>
                                @if ($errors->any() && !session('editing_batch_id'))
                                    @error('fish_type')
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
                        <i class="fa-thin fa-file-plus me-1"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Batch Modals -->
@foreach ($batches as $batch)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $batch->id }}" tabindex="-1" role="dialog" aria-labelledby="editBatchLabel-{{ $batch->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBatchLabel-{{ $batch->id }}">Edit Batch - {{ $batch->code }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('batch.update', $batch->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code-{{ $batch->id }}">Batch Number</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code-{{ $batch->id }}" name="code" placeholder="Batch Number" class="form-control {{ ($errors->any() && session('editing_batch_id') == $batch->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_batch_id') == $batch->id) ? old('code') : $batch->code }}" required>
                                @if ($errors->any() && session('editing_batch_id') == $batch->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fish_supplier_id-{{ $batch->id }}">Fish Supplier</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fish_supplier_id-{{ $batch->id }}" name="fish_supplier_id" class="form-select choices {{ ($errors->any() && session('editing_batch_id') == $batch->id) ? ($errors->has('fish_supplier_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="">Select Fish Supplier</option>
                                    @foreach ($fishSuppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('fish_supplier_id') : $batch->fish_supplier_id) == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_batch_id') == $batch->id)
                                    @error('fish_supplier_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="vessel_id-{{ $batch->id }}">Vessel</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="vessel_id-{{ $batch->id }}" name="vessel_id" class="form-select choices {{ ($errors->any() && session('editing_batch_id') == $batch->id) ? ($errors->has('vessel_id') ? 'is-invalid' : '') : '' }}" required>
                                    @foreach ($vessels as $vessel)
                                        <option value="{{ $vessel->id }}" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('vessel_id') : $batch->vessel_id) == $vessel->id ? 'selected' : '' }}>
                                            {{ $vessel->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_batch_id') == $batch->id)
                                    @error('vessel_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fishing_method-{{ $batch->id }}">Fishing Method</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fishing_method-{{ $batch->id }}" name="fishing_method" class="form-select {{ ($errors->any() && session('editing_batch_id') == $batch->id) ? ($errors->has('fishing_method') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled>-- Select Fishing Method --</option>
                                    <option value="Purse - Seine" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('fishing_method') : $batch->fishing_method) == 'Purse - Seine' ? 'selected' : '' }}>Purse - Seine</option>
                                    <option value="Pole & Line" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('fishing_method') : $batch->fishing_method) == 'Pole & Line' ? 'selected' : '' }}>Pole & Line</option>
                                </select>
                                @if ($errors->any() && session('editing_batch_id') == $batch->id)
                                    @error('fishing_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fish_type-{{ $batch->id }}">Fish Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="fish_type-{{ $batch->id }}" name="fish_type" class="form-select {{ ($errors->any() && session('editing_batch_id') == $batch->id) ? ($errors->has('fish_type') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" disabled>-- Select Fish Type --</option>
                                    <option value="Fresh" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('fish_type') : $batch->fish_type) == 'Fresh' ? 'selected' : '' }}>Fresh</option>
                                    <option value="Frozen" {{ (($errors->any() && session('editing_batch_id') == $batch->id) ? old('fish_type') : $batch->fish_type) == 'Frozen' ? 'selected' : '' }}>Frozen</option>
                                </select>
                                @if ($errors->any() && session('editing_batch_id') == $batch->id)
                                    @error('fish_type')
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
