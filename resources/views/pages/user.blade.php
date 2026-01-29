@extends('layouts.app')
@section('title', ' | Master - User')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Manage Users</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2 d-lg-none">
                <div class="float-md-end">
                    <button type="button" class="btn btn-sm icon icon-left btn-outline-success" data-bs-toggle="modal" data-bs-target="#create-modal">
                        <i class="fa-duotone fa-solid fa-plus"></i>
                        Add User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3">
            <div class="col mb-3 d-none d-lg-block">
                <div data-aos="zoom-in" class="card h-100 shadow-sm text-center bg-light-primary hover-shadow card-add">
                    <div class="card-body d-flex justify-content-center align-items-center">
                        <i class="fa-duotone fa-regular fa-user-circle-plus fa-5x text-primary"></i>
                    </div>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#create-modal" class="stretched-link"></a>
                </div>
            </div>
            @foreach ($users as $user)
                <div class="col mb-3">
                    <div data-aos="zoom-in" data-aos-delay="{{ $loop->iteration <= 5 ? $loop->iteration * 150 : 750 }}" class="card h-100 shadow-sm text-center hover-shadow">
                        <div class="card-header position-relative">
                            <div class="avatar avatar-xl">
                                <img src="https://ui-avatars.com/api/?background=435EBE&color=fff&bold=true&name={{ $user->name }}" alt="Avatar">
                            </div>
                            @if (auth()->check() && auth()->user()->id === $user->id)
                                <span class="badge bg-secondary position-absolute top-0 end-0 m-2">You</span>
                            @endif
                        </div>
                        <div class="card-body">
                            {{-- @foreach ($user->roles as $role)
                                <span class="badge bg-light-dark mb-2">{{ $role->name }}</span>
                            @endforeach --}}
                            <h5 class="card-title mb-0">
                                {{ $user->name }}
                                @if ($user->hasRole('administrator'))
                                    <span data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Administrator">
                                        <i class="fa-light fa-folder-gear fa-sm" style="color: #001861;"></i>
                                    </span>
                                @endif
                            </h5>
                            <small class="text-muted">{{ $user->username }}</small>
                            <p class="card-text my-2"><i class="fa-light fa-envelope"></i> {{ $user->email }}</p>
                            <p class="card-text my-2">
                                <i class="fa-light fa-building-user"></i>
                                {{ $user->department->name }} ({{ $user->department->code }})
                            </p>
                            <span class="badge
                                @if ($user->role === 'General Manager') bg-primary
                                @elseif ($user->role === 'Manager') bg-light-primary
                                @elseif ($user->role === 'Programmer') bg-light-info
                                @else bg-light-secondary
                                @endif
                            ">
                                {{ $user->role }}
                            </span>
                        </div>
                        <div class="card-footer text-body-secondary py-1">
                            <button class="btn icon icon-left" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $user->id }}">
                                <i class="fal fa-user-pen"></i> Edit {{ auth()->user()->id == $user->id ? 'Profile' : 'User' }}
                            </button>
                            @if (auth()->user()->id !== $user->id && $user->role !== 'General Manager')
                                <button class="btn icon icon-left" onclick="hapusData({{ $user->id }}, 'Delete User', 'Are you sure you want to delete {{ $user->name }}?')">
                                    <i class="fal fa-trash-alt"></i> Delete User
                                </button>
                                <form action="{{ route('user.destroy', $user->id) }}" id="hapus-{{ $user->id }}" method="POST">
                                    @method('delete')
                                    @csrf
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@include('includes.modals.user-modal')
@endsection

@push('prepend-style')
@endpush
@push('addon-style')
@endpush
@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($errors->any())
            @if (session('editing_user_id'))
                // Show edit modal if we were editing a user
                const editModal = new bootstrap.Modal(document.getElementById('edit-modal-{{ session("editing_user_id") }}'));
                editModal.show();
            @else
                // Show create modal if we were creating a user
                const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
                createModal.show();
            @endif
        @endif
    });
</script>
@endpush
