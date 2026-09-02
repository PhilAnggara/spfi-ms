<!-- Create Product Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createProductLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content master-form-modal po-detail-modal product-form-modal">
            <form action="{{ route('product.store') }}" method="POST">
                @csrf
                <div class="modal-header master-form-modal-header po-detail-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="master-form-modal-icon" aria-hidden="true">
                            <i class="fa-duotone fa-box"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="createProductLabel">Add Product</h5>
                            <small class="text-muted">Register a new product to the master list</small>
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
                                <input type="text" id="code" name="code" placeholder="ABCDE" maxlength="8"
                                    autocomplete="off" spellcheck="false"
                                    class="form-control {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_product_id')) ? old('code') : '' }}"
                                    data-check-code-url="{{ route('product.check-code') }}" required>
                                <div class="invalid-feedback js-code-invalid-feedback"></div>
                                <div class="valid-feedback js-code-valid-feedback"></div>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" placeholder="Product Name"
                                    class="form-control {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_product_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Classification</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="unit">Unit</label>
                                <select id="unit" name="unit_of_measure_id" class="form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('unit_of_measure_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('unit_of_measure_id')) ? '' : 'selected' }} disabled>-- Select Unit --</option>
                                    @foreach ($itemUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ ($errors->any() && !session('editing_product_id') && (string) old('unit_of_measure_id') === (string) $unit->id) ? 'selected' : '' }}>{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('unit_of_measure_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="category">Category</label>
                                <select id="category" name="category_id" class="form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('category_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('category_id')) ? '' : 'selected' }} disabled>-- Select Category --</option>
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category->id }}" {{ ($errors->any() && !session('editing_product_id') && (string) old('category_id') === (string) $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field master-form-modal-field--full">
                                <label for="type">Type</label>
                                <select id="type" name="type" class="form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}">
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('type')) ? '' : 'selected' }}>-- Select Type --</option>
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}" {{ ($errors->any() && !session('editing_product_id') && old('type') === $t) ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('type')
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
                        <i class="fa-light fa-file-circle-plus me-1"></i>
                        Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($canManageProducts ?? false)
<!-- Edit Product Modal -->
<div class="modal fade text-left modal-borderless" id="edit-modal" tabindex="-1" role="dialog" aria-labelledby="editProductLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content master-form-modal po-detail-modal product-form-modal">
            <form action="{{ $editingItem ? route('product.update', $editingItem->id) : '#' }}" method="POST" id="edit-form">
                @csrf
                @method('PUT')
                <div class="modal-header master-form-modal-header po-detail-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="master-form-modal-icon master-form-modal-icon--edit" aria-hidden="true">
                            <i class="fa-duotone fa-pen-to-square"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="editProductLabel">Edit Product{{ $editingItem ? ' — ' . $editingItem->name : '' }}</h5>
                            <small class="text-muted">Update product details in the master list</small>
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
                                <input type="text" id="edit-code" name="code" placeholder="ABCDE" maxlength="8"
                                    autocomplete="off" spellcheck="false"
                                    class="form-control {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id')) ? old('code') : ($editingItem?->code ?? '') }}"
                                    data-check-code-url="{{ route('product.check-code') }}"
                                    data-ignore-id="{{ $editingItem?->id ?? '' }}" required>
                                <div class="invalid-feedback js-code-invalid-feedback"></div>
                                <div class="valid-feedback js-code-valid-feedback"></div>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-name">Name</label>
                                <input type="text" id="edit-name" name="name" placeholder="Product Name"
                                    class="form-control {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id')) ? old('name') : ($editingItem?->name ?? '') }}" required>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="master-form-modal-section">
                        <div class="master-form-modal-section-title">Classification</div>
                        <div class="master-form-modal-grid">
                            <div class="master-form-modal-field">
                                <label for="edit-unit">Unit</label>
                                <select id="edit-unit" name="unit_of_measure_id" class="form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('unit_of_measure_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && session('editing_product_id') && old('unit_of_measure_id')) ? '' : 'selected' }} disabled>-- Select Unit --</option>
                                    @foreach ($itemUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ ($errors->any() && session('editing_product_id')) ? ((string) old('unit_of_measure_id') === (string) $unit->id ? 'selected' : '') : (($editingItem && $editingItem->unit_of_measure_id == $unit->id) ? 'selected' : '') }}>{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('unit_of_measure_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field">
                                <label for="edit-category">Category</label>
                                <select id="edit-category" name="category_id" class="form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('category_id') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && session('editing_product_id') && old('category_id')) ? '' : 'selected' }} disabled>-- Select Category --</option>
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category->id }}" {{ ($errors->any() && session('editing_product_id')) ? ((string) old('category_id') === (string) $category->id ? 'selected' : '') : (($editingItem && $editingItem->category_id == $category->id) ? 'selected' : '') }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="master-form-modal-field master-form-modal-field--full">
                                <label for="edit-type">Type</label>
                                <select id="edit-type" name="type" class="form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}">
                                    <option value="" {{ ($errors->any() && session('editing_product_id') && old('type')) ? '' : 'selected' }}>-- Select Type --</option>
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}" {{ ($errors->any() && session('editing_product_id')) ? (old('type') === $t ? 'selected' : '') : (($editingItem && $editingItem->type === $t) ? 'selected' : '') }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('type')
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
                        Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
