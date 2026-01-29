<!-- Create Fish Modal -->
<div class="modal fade text-left modal-borderless" id="create-modal" tabindex="-1" role="dialog" aria-labelledby="createFishLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="createFishLabel">Create Fish</h5>
				<button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
					<i data-feather="x"></i>
				</button>
			</div>

			<form action="{{ route('fish.store') }}" method="POST" class="form form-horizontal">
				@csrf
				<div class="modal-body">
					<div class="form-body">
						<div class="row">
							<div class="col-md-4">
								<label for="code">Code</label>
							</div>
							<div class="col-md-8 form-group">
								<input type="text" id="code" name="code" placeholder="e.g. FSH01" class="form-control {{ ($errors->any() && !session('editing_fish_id')) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_fish_id')) ? old('code') : '' }}" required>
								@if ($errors->any() && !session('editing_fish_id'))
									@error('code')
										<div class="invalid-feedback">{{ $message }}</div>
									@enderror
								@endif
							</div>

							<div class="col-md-4">
								<label for="name">Name</label>
							</div>
							<div class="col-md-8 form-group">
								<input type="text" id="name" name="name" placeholder="e.g. Tuna" class="form-control {{ ($errors->any() && !session('editing_fish_id')) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && !session('editing_fish_id')) ? old('name') : '' }}" required>
								@if ($errors->any() && !session('editing_fish_id'))
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
@foreach ($fishes as $fish)
<div class="modal fade text-left modal-borderless" id="edit-modal-{{ $fish->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-modal-label-{{ $fish->id }}" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="edit-modal-label-{{ $fish->id }}">Edit Fish - {{ $fish->name }}</h5>
				<button type="button" class="close rounded-pill" data-bs-dismiss="modal" aria-label="Close">
					<i data-feather="x"></i>
				</button>
			</div>

			<form action="{{ route('fish.update', $fish->id) }}" method="POST" class="form form-horizontal">
				@csrf
				@method('PUT')
				<div class="modal-body">
					<div class="form-body">
						<div class="row">
							<div class="col-md-4">
								<label for="code-{{ $fish->id }}">Code</label>
							</div>
							<div class="col-md-8 form-group">
								<input type="text" id="code-{{ $fish->id }}" name="code" placeholder="e.g. FSH01" class="form-control {{ ($errors->any() && session('editing_fish_id') == $fish->id) ? ($errors->has('code') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_fish_id') == $fish->id) ? old('code') : $fish->code }}" required>
								@if ($errors->any() && session('editing_fish_id') == $fish->id)
									@error('code')
										<div class="invalid-feedback">{{ $message }}</div>
									@enderror
								@endif
							</div>

							<div class="col-md-4">
								<label for="name-{{ $fish->id }}">Name</label>
							</div>
							<div class="col-md-8 form-group">
								<input type="text" id="name-{{ $fish->id }}" name="name" placeholder="e.g. Tuna" class="form-control {{ ($errors->any() && session('editing_fish_id') == $fish->id) ? ($errors->has('name') ? 'is-invalid' : '') : '' }}" value="{{ ($errors->any() && session('editing_fish_id') == $fish->id) ? old('name') : $fish->name }}" required>
								@if ($errors->any() && session('editing_fish_id') == $fish->id)
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
