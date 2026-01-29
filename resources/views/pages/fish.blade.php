@extends('layouts.app')
@section('title', ' | Fish')

@section('content')
<div class="page-heading">
	<div class="page-title">
		<div class="row mb-4">
			<div class="col-12 col-md-6 order-md-1">
				<h3>Fish</h3>
			</div>
			<div class="col-12 col-md-6 order-md-2">
				<div class="float-md-end">
					<button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
						<i class="fa-duotone fa-solid fa-plus"></i>
						Add Fish
					</button>
				</div>
			</div>
		</div>
	</div>
	<section class="section">
		<div class="card shadow-sm">
			<div class="card-body">
				<table class="table table-striped text-center text-nowrap" id="table1">
					<thead>
						<tr>
							<th class="text-center">Code</th>
							<th class="text-center">Name</th>
							<th class="text-center">Action</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($fishes as $fish)
							<tr>
								<td>
									<span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $fish->code }}')">{{ $fish->code }}</span>
								</td>
								<td>{{ $fish->name }}</td>
								<td>
									<div class="btn-group btn-group-sm">
										<button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $fish->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
											<i class="fa-light fa-edit text-primary"></i>
										</button>
										<button type="button" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="hapusData({{ $fish->id }}, 'Delete Fish', 'Are you sure want to delete {{ $fish->name }}?')">
											<i class="fa-light fa-trash text-secondary"></i>
										</button>
										<form action="{{ route('fish.destroy', $fish->id) }}" id="hapus-{{ $fish->id }}" method="POST">
											@method('delete')
											@csrf
										</form>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</section>
</div>
@include('includes.modals.fish-modal')
@endsection

@push('prepend-style')
	<link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-style')
	<link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
	<link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
	<script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
	<script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
	<script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
	<script src="{{ url('assets/static/js/pages/form-element-select.js') }}"></script>
@endpush

@push('addon-script')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		@if ($errors->any())
			@if (session('editing_fish_id'))
				const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_fish_id") }}'));
				editModal.show();
			@else
				const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
				createModal.show();
			@endif
		@endif
	});
</script>
@endpush
