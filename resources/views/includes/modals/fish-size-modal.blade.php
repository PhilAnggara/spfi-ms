@foreach ($fishes as $fish)
<div class="modal fade text-left modal-borderless" id="size-modal-{{ $fish->id }}" tabindex="-1" role="dialog" aria-labelledby="sizeModalLabel-{{ $fish->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sizeModalLabel-{{ $fish->id }}">Fish Size - {{ $fish->name }}</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <ul class="list-group">
                        @forelse ($fish->sizes as $size)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-light-secondary me-2">{{ $fish->code }}-{{ $size->code }}</span>
                                    {{ $size->size_range }}
                                </div>
                                <div class="ms-auto">
                                    <button type="button" class="btn btn-sm icon icon-left" onclick="hapusData('size-{{ $size->id }}', 'Delete Size', 'Are you sure want to delete {{ $size->code }}?')">
                                        <i class="fa-thin fa-trash"></i>
                                        Delete
                                    </button>
                                    <form action="{{ route('fish-size.destroy', $size->id) }}" id="hapus-size-{{ $size->id }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center">
                                <i class="fa-thin fa-empty-set"></i>
                                <small class="text-muted">No size ranges yet.</small>
                            </li>
                        @endforelse
                    </ul>
                </div>

                <div class="divider">
                    <div class="divider-text fw-bold">Add New Size Range</div>
                </div>

                <form action="{{ route('fish-size.store') }}" method="POST" class="form form-horizontal">
                    @csrf
                    <input type="hidden" name="fish_id" value="{{ $fish->id }}">
                    <input type="hidden" name="fish_code" value="{{ $fish->code }}">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="code-{{ $fish->id }}">Code</label>
                        </div>
                        <div class="col-md-8 form-group">
                            <div class="input-group">
                                <span class="input-group-text">{{ $fish->code }}</span>
                                <input typ  e="text" id="code-{{ $fish->id }}" name="code" placeholder="e.g. 200" class="form-control {{ ($errors->any() && session('size_modal_fish_id') == $fish->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('size_modal_fish_id') == $fish->id) ? old('code') : '' }}" required>
                                @if ($errors->any() && session('size_modal_fish_id') == $fish->id)
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="size_range-{{ $fish->id }}">Size Range</label>
                        </div>
                        <div class="col-md-8 form-group">
                            <input type="text" id="size_range-{{ $fish->id }}" name="size_range" placeholder="e.g. 0.200-0.299" class="form-control {{ ($errors->any() && session('size_modal_fish_id') == $fish->id) ? ($errors->has('size_range') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('size_modal_fish_id') == $fish->id) ? old('size_range') : '' }}" required>
                            @if ($errors->any() && session('size_modal_fish_id') == $fish->id)
                                @error('size_range')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn icon icon-left btn-primary">
                            <i class="fa-thin fa-file-plus me-1"></i>
                            Add Size Range
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach
