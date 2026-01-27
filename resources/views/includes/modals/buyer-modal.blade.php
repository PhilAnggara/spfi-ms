<!-- Create Buyer Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createBuyerLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBuyerLabel">Create Buyer</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('buyer.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" name="name" placeholder="Buyer Name" class="form-control {{ ($errors->any() && !session('editing_buyer_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_buyer_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_buyer_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="address">Address</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="address" name="address" placeholder="Buyer Address" class="form-control {{ ($errors->any() && !session('editing_buyer_id')) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}" rows="3" required>{{ ($errors->any() && !session('editing_buyer_id')) ? old('address') : '' }}</textarea>
                                @if ($errors->any() && !session('editing_buyer_id'))
                                    @error('address')
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

<!-- Edit Buyer Modals -->
@foreach ($buyers as $buyer)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $buyer->id }}" tabindex="-1" role="dialog" aria-labelledby="editBuyerLabel-{{ $buyer->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBuyerLabel-{{ $buyer->id }}">Edit Buyer - {{ $buyer->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('buyer.update', $buyer->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="name-{{ $buyer->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name-{{ $buyer->id }}" name="name" placeholder="Buyer Name" class="form-control {{ ($errors->any() && session('editing_buyer_id') == $buyer->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_buyer_id') == $buyer->id) ? old('name') : $buyer->name }}" required>
                                @if ($errors->any() && session('editing_buyer_id') == $buyer->id)
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="address-{{ $buyer->id }}">Address</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <textarea id="address-{{ $buyer->id }}" name="address" placeholder="Buyer Address" class="form-control {{ ($errors->any() && session('editing_buyer_id') == $buyer->id) ? ($errors->has('address') ? 'is-invalid' : '') : '' }}" rows="3" required>{{ ($errors->any() && session('editing_buyer_id') == $buyer->id) ? old('address') : $buyer->address }}</textarea>
                                @if ($errors->any() && session('editing_buyer_id') == $buyer->id)
                                    @error('address')
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
