<!-- Create Currency Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createCurrencyLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCurrencyLabel">Create Currency</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('currency.store') }}" method="POST" class="form form-horizontal">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code" name="code" placeholder="e.g. USD" class="form-control {{ ($errors->any() && !session('editing_currency_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_currency_id')) ? old('code') : '' }}" required>
                                @if ($errors->any() && !session('editing_currency_id'))
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name" name="name" placeholder="e.g. US Dollar" class="form-control {{ ($errors->any() && !session('editing_currency_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_currency_id')) ? old('name') : '' }}" required>
                                @if ($errors->any() && !session('editing_currency_id'))
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="symbol">Symbol</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="symbol" name="symbol" placeholder="e.g. $" class="form-control {{ ($errors->any() && !session('editing_currency_id')) ? ($errors->has('symbol') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_currency_id')) ? old('symbol') : '' }}">
                                @if ($errors->any() && !session('editing_currency_id'))
                                    @error('symbol')
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

<!-- Edit Currency Modals -->
@foreach ($currencies as $currency)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $currency->id }}" tabindex="-1" role="dialog" aria-labelledby="editCurrencyLabel-{{ $currency->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCurrencyLabel-{{ $currency->id }}">Edit Currency - {{ $currency->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('currency.update', $currency->id) }}" method="POST" class="form form-horizontal">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="code-{{ $currency->id }}">Code</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="code-{{ $currency->id }}" name="code" placeholder="e.g. USD" class="form-control {{ ($errors->any() && session('editing_currency_id') == $currency->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_currency_id') == $currency->id) ? old('code') : $currency->code }}" required>
                                @if ($errors->any() && session('editing_currency_id') == $currency->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="name-{{ $currency->id }}">Name</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="name-{{ $currency->id }}" name="name" placeholder="e.g. US Dollar" class="form-control {{ ($errors->any() && session('editing_currency_id') == $currency->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_currency_id') == $currency->id) ? old('name') : $currency->name }}" required>
                                @if ($errors->any() && session('editing_currency_id') == $currency->id)
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="symbol-{{ $currency->id }}">Symbol</label>
                            </div>
                            <div class="col-md-8 form-group">
                                <input type="text" id="symbol-{{ $currency->id }}" name="symbol" placeholder="e.g. $" class="form-control {{ ($errors->any() && session('editing_currency_id') == $currency->id) ? ($errors->has('symbol') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_currency_id') == $currency->id) ? old('symbol') : $currency->symbol }}">
                                @if ($errors->any() && session('editing_currency_id') == $currency->id)
                                    @error('symbol')
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
