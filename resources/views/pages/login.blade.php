<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>SPFI-MS | Login</title>
    <link rel="shortcut icon" href="{{ url('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/auth.css') }}">
    <link rel="stylesheet" href="{{ url('assets/styles/main.css') }}">
</head>
<body>

    <script src="{{ url('assets/static/js/initTheme.js') }}"></script>
    <div id="auth">

        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <div class="auth-logo">
                        <a href="{{ route('dashboard') }}"><img src="{{ url('assets/images/logo.png') }}" alt="Logo"></a>
                    </div>
                    <h1 class="auth-title">Log in.</h1>
                    <p class="auth-subtitle mb-5">Log in with your data that you entered during registration.</p>

                    <form action="{{ route('login') }}" method="post">
                        @csrf
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="text" name="username" value="{{ old('username') }}" class="form-control form-control-xl @error('username') is-invalid @enderror" placeholder="Username" required autocomplete="off" autofocus>
                            <div class="form-control-icon">
                                <i class="bi bi-person"></i>
                            </div>
                            @error('username')
                            <div class="invalid-feedback">
                                <i class="bx bx-radio-circle"></i>
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" name="password" class="form-control form-control-xl @error('password') is-invalid @enderror" placeholder="Password" required>
                            <div class="form-control-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                        </div>
                        <div class="form-check form-check-lg d-flex align-items-end">
                            <input class="form-check-input me-2" type="checkbox" value="" id="remember_me" name="remember">
                            <label class="form-check-label text-gray-600" for="remember_me" name="remember">
                                Keep me logged in
                            </label>
                        </div>
                        <button class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Log in</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right">

                </div>
            </div>
        </div>

    </div>

    <script src="{{ url('assets/scripts/set-font-size.js') }}"></script>
</body>
</html>
