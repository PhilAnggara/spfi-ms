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
                                <input type="text" id="code" name="code" placeholder="ABCDE" minlength="7" maxlength="7" pattern="[A-Za-z0-9]{7}"
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
                                <select id="unit" name="unit" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('unit') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('unit')) ? '' : 'selected' }} disabled>-- Select Unit --</option>
                                    @foreach ($itemUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($errors->any() && !session('editing_product_id') && old('unit') === $unit) ? 'selected' : '' }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="category">Category</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="category" name="category" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('category') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('category')) ? '' : 'selected' }} disabled>-- Select Category --</option>
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category }}" {{ ($errors->any() && !session('editing_product_id') && old('category') === $category) ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && !session('editing_product_id'))
                                    @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="type">Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="type" name="type" class="choices form-select {{ ($errors->any() && !session('editing_product_id')) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && !session('editing_product_id') && old('type')) ? '' : 'selected' }} disabled>-- Select Type --</option>
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

@foreach ($items as $item)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $item->id }}" tabindex="-1" role="dialog" aria-labelledby="editProductLabel-{{ $item->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductLabel-{{ $item->id }}">Edit Product - {{ $item->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('product.update', $item->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code-{{ $item->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code-{{ $item->id }}" name="code" placeholder="ABCDE" minlength="7" maxlength="7" pattern="[A-Za-z0-9]{7}"
                                    class="form-control {{ ($errors->any() && session('editing_product_id') == $item->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id') == $item->id) ? old('code') : $item->code }}" required>
                                @if ($errors->any() && session('editing_product_id') == $item->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name-{{ $item->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name-{{ $item->id }}" name="name" placeholder="Product Name"
                                    class="form-control {{ ($errors->any() && session('editing_product_id') == $item->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}"
                                    value="{{ ($errors->any() && session('editing_product_id') == $item->id) ? old('name') : $item->name }}" required>
                                @if ($errors->any() && session('editing_product_id') == $item->id)
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="unit-{{ $item->id }}">Unit</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="unit-{{ $item->id }}" name="unit" class="choices form-select {{ ($errors->any() && session('editing_product_id') == $item->id) ? ($errors->has('unit') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && session('editing_product_id') == $item->id && old('unit')) ? '' : 'selected' }} disabled>-- Select Unit --</option>
                                    @foreach ($itemUnits as $unit)
                                        <option value="{{ $unit }}" {{ ($errors->any() && session('editing_product_id') == $item->id) ? (old('unit') === $unit ? 'selected' : '') : ($item->unit === $unit ? 'selected' : '') }}>{{ $unit }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id') == $item->id)
                                    @error('unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="category-{{ $item->id }}">Category</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="category-{{ $item->id }}" name="category" class="choices form-select {{ ($errors->any() && session('editing_product_id') == $item->id) ? ($errors->has('category') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && session('editing_product_id') == $item->id && old('category')) ? '' : 'selected' }} disabled>-- Select Category --</option>
                                    @foreach ($itemCategories as $category)
                                        <option value="{{ $category }}" {{ ($errors->any() && session('editing_product_id') == $item->id) ? (old('category') === $category ? 'selected' : '') : ($item->category === $category ? 'selected' : '') }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id') == $item->id)
                                    @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="type-{{ $item->id }}">Type</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <select id="type-{{ $item->id }}" name="type" class="choices form-select {{ ($errors->any() && session('editing_product_id') == $item->id) ? ($errors->has('type') ? 'is-invalid' : '') : '' }}" required>
                                    <option value="" {{ ($errors->any() && session('editing_product_id') == $item->id && old('type')) ? '' : 'selected' }} disabled>-- Select Type --</option>
                                    @foreach ($types as $t)
                                        <option value="{{ $t }}" {{ ($errors->any() && session('editing_product_id') == $item->id) ? (old('type') === $t ? 'selected' : '') : ($item->type === $t ? 'selected' : '') }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->any() && session('editing_product_id') == $item->id)
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

@endforeach
