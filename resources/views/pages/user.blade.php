@extends('layouts.app')
@section('title', ' | Master - User')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Manage Users</h3>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row row-cols-2 row-cols-lg-4">
            <div class="col mb-3">
                <div data-aos="zoom-in" class="card h-100 shadow-sm text-center bg-light-primary hover-shadow card-add">
                    <div class="card-body d-flex justify-content-center align-items-center">
                        <i class="fa-duotone fa-regular fa-user-circle-plus fa-5x text-primary"></i>
                    </div>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#tambah" class="stretched-link"></a>
                </div>
            </div>
            @foreach ($users as $user)
                <div class="col mb-3">
                    <div data-aos="zoom-in" data-aos-delay="{{ $loop->iteration * 150 }}" class="card h-100 shadow-sm text-center hover-shadow">
                        <div class="card-header position-relative">
                            <div class="avatar avatar-xl">
                                <img src="https://ui-avatars.com/api/?background=435EBE&color=fff&bold=true&name={{ $user->name }}" alt="Avatar">
                            </div>
                            @if (auth()->check() && auth()->user()->id === $user->id)
                                <span class="badge bg-secondary position-absolute top-0 end-0 m-2">You</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">{{ $user->name }}</h5>
                            <small class="text-muted">({{ $user->username }})</small>
                            <p class="card-text my-2"><i class="fa-light fa-envelope"></i> {{ $user->email }}</p>
                            <p class="card-text my-2">
                                <i class="fa-light fa-building-user"></i>
                                {{ $user->department->name }} ({{ $user->department->code }})
                            </p>
                            @if ($user->role === 'Administrator')
                                <span class="badge bg-light-primary">{{ $user->role }}</span>
                            @else
                                <span class="badge bg-light-secondary">{{ $user->role }}</span>
                            @endif
                        </div>
                        <div class="card-footer text-body-secondary py-1">
                            @if (auth()->check() && auth()->user()->id !== $user->id)
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
@endsection

@push('prepend-style')
@endpush
@push('addon-style')
@endpush
@push('addon-script')
@endpush
