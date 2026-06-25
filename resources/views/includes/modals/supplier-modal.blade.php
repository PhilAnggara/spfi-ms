<!-- Create Supplier Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSupplierLabel">Add Supplier</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('supplier.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="Supplier Code" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('code') : '' }}" required>
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
                                <input type="text" id="name" name="name" placeholder="Supplier Name" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="address">Address</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="address" name="address" placeholder="Supplier Address" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}" rows="2" required>{{ ($errors->any() && !session('editing_supplier_id')) ? old('address') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="phone">Phone</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="phone" name="phone" placeholder="Phone Number" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('phone') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('phone') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="fax">Fax</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="fax" name="fax" placeholder="Fax Number" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('fax') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('fax') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('fax')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="email">Email</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="email" id="email" name="email" placeholder="Email Address" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('email') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="contact_person">Contact Person</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="contact_person" name="contact_person" placeholder="Contact Person Name" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('contact_person') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('contact_person') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="remarks">Remarks</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="remarks" name="remarks" placeholder="Additional Notes" class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}" rows="2">{{ ($errors->any() && !session('editing_supplier_id')) ? old('remarks') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_supplier_id'))
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

<!-- Edit Supplier Modal -->
<div class="modal fade text-left modal-borderless" id="edit-modal" tabindex="-1" role="dialog" aria-labelledby="editSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSupplierLabel">Edit Supplier{{ $editingSupplier ? ' - ' . $editingSupplier->name : '' }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ $editingSupplier ? route('supplier.update', $editingSupplier->id) : '#' }}" method="POST" class="form form-horizontal" id="edit-form">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="edit-code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-code" name="code" placeholder="Supplier Code" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('code') : ($editingSupplier?->code ?? '') }}" required>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-name" name="name" placeholder="Supplier Name" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('name') : ($editingSupplier?->name ?? '') }}" required>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-address">Address</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="edit-address" name="address" placeholder="Supplier Address" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}" rows="2" required>{{ ($errors->any() && session('editing_supplier_id')) ? old('address') : ($editingSupplier?->address ?? '') }}</textarea>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-phone">Phone</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-phone" name="phone" placeholder="Phone Number" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('phone') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('phone') : ($editingSupplier?->phone ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-fax">Fax</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-fax" name="fax" placeholder="Fax Number" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('fax') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('fax') : ($editingSupplier?->fax ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('fax')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-email">Email</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="email" id="edit-email" name="email" placeholder="Email Address" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('email') : ($editingSupplier?->email ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-contact-person">Contact Person</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-contact-person" name="contact_person" placeholder="Contact Person Name" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('contact_person') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_supplier_id')) ? old('contact_person') : ($editingSupplier?->contact_person ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-remarks">Remarks</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="edit-remarks" name="remarks" placeholder="Additional Notes" class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}" rows="2">{{ ($errors->any() && session('editing_supplier_id')) ? old('remarks') : ($editingSupplier?->remarks ?? '') }}</textarea>
                                @if ($errors->any() && session('editing_supplier_id'))
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
