<!-- Create Supplier Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content master-form-modal po-detail-modal">
            <form action="{{ route('supplier.store') }}" method="POST">
                @csrf
                <div class="modal-header master-form-modal-header po-detail-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="master-form-modal-icon" aria-hidden="true">
                            <i class="fa-duotone fa-truck"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="createSupplierLabel">Add Supplier</h5>
                            <small class="text-muted">Register a new supplier to the master list</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body master-form-modal-body">
                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Basic Information</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="code">Code</label>
                                <input type="text" id="code" name="code" placeholder="Supplier Code"
                                    autocomplete="off" spellcheck="false"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('code') : '' }}"
                                    data-check-code-url="{{ route('supplier.check-code') }}" required>
                                <div class="invalid-feedback js-code-invalid-feedback"></div>
                                <div class="valid-feedback js-code-valid-feedback"></div>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" placeholder="Supplier Name"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field master-form-modal-field--full">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" placeholder="Supplier Address"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}"
                                    rows="2" required>{{ ($errors->any() && !session('editing_supplier_id')) ? old('address') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Contact</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" placeholder="Phone Number"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('phone') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('phone') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="fax">Fax</label>
                                <input type="text" id="fax" name="fax" placeholder="Fax Number"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('fax') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('fax') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('fax')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email Address"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('email') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" placeholder="Contact Person Name"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('contact_person') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_supplier_id')) ? old('contact_person') : '' }}">
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Notes</div>
                        <div class="master-form-modal-grid master-form-modal-grid--single">
                            <div class="master-form-modal-field">
                                <label for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" placeholder="Additional Notes"
                                    class="form-control {{ ($errors->any() && !session('editing_supplier_id')) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}"
                                    rows="2">{{ ($errors->any() && !session('editing_supplier_id')) ? old('remarks') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_supplier_id'))
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer master-form-modal-footer po-detail-modal-footer">
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="fa-light fa-rotate-left me-1"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-light fa-file-circle-plus me-1"></i>
                        Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade text-left modal-borderless" id="edit-modal" tabindex="-1" role="dialog" aria-labelledby="editSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content master-form-modal po-detail-modal">
            <form action="{{ $editingSupplier ? route('supplier.update', $editingSupplier->id) : '#' }}" method="POST" id="edit-form">
                @csrf
                @method('PUT')
                <div class="modal-header master-form-modal-header po-detail-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="master-form-modal-icon master-form-modal-icon--edit" aria-hidden="true">
                            <i class="fa-duotone fa-pen-to-square"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="editSupplierLabel">Edit Supplier{{ $editingSupplier ? ' — ' . $editingSupplier->name : '' }}</h5>
                            <small class="text-muted">Update supplier details in the master list</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body master-form-modal-body">
                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Basic Information</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="edit-code">Code</label>
                                <input type="text" id="edit-code" name="code" placeholder="Supplier Code"
                                    autocomplete="off" spellcheck="false"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('code') : ($editingSupplier?->code ?? '') }}"
                                    data-check-code-url="{{ route('supplier.check-code') }}"
                                    data-ignore-id="{{ $editingSupplier?->id ?? '' }}" required>
                                <div class="invalid-feedback js-code-invalid-feedback"></div>
                                <div class="valid-feedback js-code-valid-feedback"></div>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-name">Name</label>
                                <input type="text" id="edit-name" name="name" placeholder="Supplier Name"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('name') : ($editingSupplier?->name ?? '') }}" required>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field master-form-modal-field--full">
                                <label for="edit-address">Address</label>
                                <textarea id="edit-address" name="address" placeholder="Supplier Address"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}"
                                    rows="2" required>{{ ($errors->any() && session('editing_supplier_id')) ? old('address') : ($editingSupplier?->address ?? '') }}</textarea>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Contact</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="edit-phone">Phone</label>
                                <input type="text" id="edit-phone" name="phone" placeholder="Phone Number"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('phone') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('phone') : ($editingSupplier?->phone ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-fax">Fax</label>
                                <input type="text" id="edit-fax" name="fax" placeholder="Fax Number"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('fax') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('fax') : ($editingSupplier?->fax ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('fax')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-email">Email</label>
                                <input type="email" id="edit-email" name="email" placeholder="Email Address"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('email') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('email') : ($editingSupplier?->email ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-contact-person">Contact Person</label>
                                <input type="text" id="edit-contact-person" name="contact_person" placeholder="Contact Person Name"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('contact_person') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_supplier_id')) ? old('contact_person') : ($editingSupplier?->contact_person ?? '') }}">
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Notes</div>
                        <div class="master-form-modal-grid master-form-modal-grid--single">
                            <div class="master-form-modal-field">
                                <label for="edit-remarks">Remarks</label>
                                <textarea id="edit-remarks" name="remarks" placeholder="Additional Notes"
                                    class="form-control {{ ($errors->any() && session('editing_supplier_id')) ? ($errors->has('remarks') ? 'is-invalid' : '') : '' }}"
                                    rows="2">{{ ($errors->any() && session('editing_supplier_id')) ? old('remarks') : ($editingSupplier?->remarks ?? '') }}</textarea>
                                @if ($errors->any() && session('editing_supplier_id'))
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer master-form-modal-footer po-detail-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-light fa-file-pen me-1"></i>
                        Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
