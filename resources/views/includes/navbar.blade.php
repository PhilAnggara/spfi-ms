<header>
    <nav class="navbar navbar-expand navbar-light navbar-top">
        <div class="container-fluid">
            <a href="#" class="burger-btn d-block">
                <i class="bi bi-justify fs-3"></i>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-lg-0">
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link active dropdown-toggle text-gray-600" href="#"
                            data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                            {{-- <i class='bi bi-bell bi-sub fs-4'></i> --}}
                            {{-- <i class="fa-light fa-bell"></i> --}}
                            <i class="fa-light fa-bell fa-shake fa-xl"></i>
                            {{-- <i class="fa-duotone fa-light fa-bell fa-beat fa-xl"></i> --}}
                            {{-- <i class="fa-duotone fa-regular fa-bell fa-shake fa-xl"></i> --}}
                            <span class="badge badge-notification bg-danger">7</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow"
                            aria-labelledby="dropdownMenuButton">
                            <li class="dropdown-header">
                                <h6>Notifications</h6>
                            </li>
                            <li class="dropdown-item notification-item">
                                <a class="d-flex align-items-center" href="#">
                                    <div class="notification-icon bg-success">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </div>
                                    <div class="notification-text ms-4">
                                        <p class="notification-title font-bold">Report submitted</p>
                                        <p class="notification-subtitle font-thin text-sm">Receiving Report</p>
                                    </div>
                                </a>
                            </li>
                            <li class="dropdown-item notification-item">
                                <a class="d-flex align-items-center" href="#">
                                    <div class="notification-icon bg-success">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </div>
                                    <div class="notification-text ms-4">
                                        <p class="notification-title font-bold">Report submitted</p>
                                        <p class="notification-subtitle font-thin text-sm">Receiving Report</p>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <p class="text-center py-2 mb-0"><a href="#">See all notification</a></p>
                            </li>
                        </ul>
                    </li>
                </ul>
                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-menu d-flex">
                            <div class="user-name text-end me-3">
                                <h6 class="mb-0 text-gray-600">{{ auth()->user()->name }}</h6>
                                <p class="mb-0 text-sm text-gray-600">{{ auth()->user()->role }} - {{ auth()->user()->department?->code ?? '-' }}</p>
                            </div>
                            <div class="user-img d-flex align-items-center">
                                <div class="avatar avatar-md">
                                    <img src="https://ui-avatars.com/api/?background=435EBE&color=fff&bold=true&name={{ auth()->user()->name }}">
                                </div>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuButton"
                        style="min-width: 11rem;">
                        <li>
                            <h6 class="dropdown-header">Hello, {{ Str::before(auth()->user()->name, ' ') }}!</h6>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#change-password-modal">
                                <i class="icon-mid bi bi-shield-lock me-2"></i> Change Password
                            </a>
                        </li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="keluar()">
                                <i class="icon-mid bi bi-box-arrow-left me-2"></i> Logout
                            </a>
                            <form action="{{ route('logout') }}" id="logoutForm" method="POST">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>

<div class="modal fade text-left" id="change-password-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="myModalLabel1">Change Password</h5>
                <button type="button" class="close rounded-pill" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i data-feather="x"></i>
                </button>
            </div>

            <form action="{{ route('password.change') }}" method="POST" class="form form-vertical">
                @csrf
                <div class="modal-body">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('current_password')) ? 'is-invalid' : '' }}" name="current_password" placeholder="Current Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('current_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="new_password">Password</label>
                                    <input type="password" id="new_password" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('password')) ? 'is-invalid' : '' }}" name="password" placeholder="Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="new_password_confirmation">Password</label>
                                    <input type="password" id="new_password_confirmation" class="form-control {{ ($errors->any() && session('change_password') && $errors->has('password_confirmation')) ? 'is-invalid' : '' }}" name="password_confirmation" placeholder="Confirm Password" required>
                                    @if ($errors->any() && session('change_password'))
                                        @error('password_confirmation')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
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
                        <i class="fa-thin fa-shield-keyhole me-1"></i>
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('addon-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if ($errors->any() && session('change_password'))
            const changePwdModal = new bootstrap.Modal(document.getElementById('change-password-modal'));
            changePwdModal.show();
        @endif
    });
</script>
@endpush
