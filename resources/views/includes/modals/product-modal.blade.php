<!-- Create Product Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createProductLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createProductLabel">Add Product</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('product.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="ABCDE" maxlength="8"
                                    class="form-control {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_product_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" name="name" placeholder="Product Name"
                                    class="form-control {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && !session('editing_product_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="unit">Unit</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="unit" name="unit_of_measure_id" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('unit_of_measure_id') ? 'is-invalid' : '') : '' }}" required>
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

                            <div class="col-md-4">
                                <label for="category">Category</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="category" name="category_id" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('category_id') ? 'is-invalid' : '') : '' }}" required>
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

                            <div class="col-md-4">
                                <label for="type">Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="type" name="type" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}">
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

<!-- Modal edit reusable: dipakai untuk semua item agar tidak render ribuan modal -->
<div class="modal fade text-left modal-borderless" id="edit-modal" tabindex="-1" role="dialog" aria-labelledby="editProductLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductLabel">Edit Product{{ $editingItem ? ' - ' . $editingItem->name : '' }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ $editingItem ? route('product.update', $editingItem->id) : '#' }}" method="POST" class="form form-horizontal" id="edit-form">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="edit-code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-code" name="code" placeholder="ABCDE" maxlength="8"
                                    class="form-control {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id')) ? old('code') : ($editingItem?->code ?? '') }}" required>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="edit-name" name="name" placeholder="Product Name"
                                    class="form-control {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id')) ? old('name') : ($editingItem?->name ?? '') }}" required>
                                @if ($errors->any() && session('editing_product_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="edit-unit">Unit</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-unit" name="unit_of_measure_id" class="choices form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('unit_of_measure_id') ? 'is-invalid' : '') : '' }}" required>
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

                            <div class="col-md-4">
                                <label for="edit-category">Category</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-category" name="category_id" class="choices form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('category_id') ? 'is-invalid' : '') : '' }}" required>
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

                            <div class="col-md-4">
                                <label for="edit-type">Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="edit-type" name="type" class="choices form-select {{ ($errors->any() && session('editing_product_id')) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}">
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
