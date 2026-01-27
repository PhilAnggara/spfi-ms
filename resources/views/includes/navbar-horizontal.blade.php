<header class="mb-5">
    <div class="header-top">
        <div class="container">
            <div class="logo">
                <a href="{{ route('dashboard') }}"><img src="{{ url('assets/images/logo.png') }}" alt="Logo" srcset=""></a>
            </div>
            <div class="header-top-right">

                <div class="dropdown">
                    <a href="#" id="topbarUserDropdown"
                        class="user-dropdown d-flex align-items-center dropend dropdown-toggle "
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar avatar-md2">
                            <img src="https://ui-avatars.com/api/?background=435EBE&color=fff&bold=true&name={{ auth()->user()->name }}">
                        </div>
                        <div class="text">
                            <h6 class="user-dropdown-name">{{ auth()->user()->name }}</h6>
                            <p class="user-dropdown-status text-sm text-muted">{{ get_job_title(auth()->user()) }}</p>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg"
                        aria-labelledby="topbarUserDropdown">
                        <li>
                            <h6 class="dropdown-header">Hello, {{ Str::before(auth()->user()->name, ' ') }}!</h6>
                        </li>
                        <li>
                            <div class="dropdown-item">
                                <i class="icon-mid bi bi-moon-stars me-2"></i> Dark Mode
                                <div class="form-check form-switch float-end">
                                    <input class="form-check-input" type="checkbox" id="toggle-dark" style="cursor: pointer">
                                </div>
                            </div>
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

                <!-- Burger button responsive -->
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </div>
        </div>
    </div>
    <nav class="main-navbar">
        <div class="container">
            <ul>

                <li class="menu-item {{ Request::is('/') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class='menu-link'>
                        <span>
                            <i class="fa-duotone fa-solid fa-objects-column {{ Request::is('/') ? 'fa-fade' : '' }}"></i>
                            Dashboard
                        </span>
                    </a>
                </li>

                @role('administrator')
                    <li class="menu-item  has-sub">
                        <a href="#" class='menu-link'>
                            <span>
                                <i class="fa-duotone fa-solid fa-user-tie {{ Request::is('master/*') ? 'fa-fade' : '' }}"></i>
                                Master
                            </span>
                        </a>
                        <div class="submenu ">
                            <!-- Wrap to submenu-group-wrapper if you want 3-level submenu. Otherwise remove it. -->
                            <div class="submenu-group-wrapper">
                                <ul class="submenu-group">

                                    <li class="submenu-item {{ Request::is('master/*') ? 'active' : '' }} has-sub">
                                        <a href="#" class='submenu-link'>Management</a>
                                        <ul class="subsubmenu">
                                            <li class="subsubmenu-item ">
                                                <a href="{{ route('user.index') }}" class="subsubmenu-link">User</a>
                                            </li>
                                        </ul>
                                    </li>

                                    <li class="submenu-item  has-sub">
                                        <a href="#" class='submenu-link'>Inventory</a>
                                        <!-- 3 Level Submenu -->
                                        <ul class="subsubmenu">
                                            <li class="subsubmenu-item ">
                                                <a href="{{ route('product.index') }}" class="subsubmenu-link">Product</a>
                                            </li>
                                            <li class="subsubmenu-item ">
                                                <a href="{{ route('product-category.index') }}" class="subsubmenu-link">Product Category</a>
                                            </li>
                                            <li class="subsubmenu-item ">
                                                <a href="{{ route('unit-of-measurement.index') }}" class="subsubmenu-link">Unit of Measurement</a>
                                            </li>
                                        </ul>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </li>
                @endrole


            </ul>
        </div>
    </nav>

</header>

@include('includes.modals.change-password-modal')
