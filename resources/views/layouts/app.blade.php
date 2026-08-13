<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-user-id" content="{{ auth()->id() }}">
    <title>{{ config('app.name', 'SPFI') }}@yield('title')</title>
    @include('includes.style')
    @livewireStyles
</head>
<body>
    <script src="{{ url('assets/static/js/initTheme.js') }}"></script>
    <div id="app">
        @include('includes.sidebar')
        <div id="main" class='layout-navbar navbar-fixed'>
            @include('includes.navbar')
            <div id="main-content" style="min-height: 80vh;">
                @if (config('reconcile.freeze_writes'))
                    @include('partials.reconcile-freeze-banner')
                @endif

                @yield('content')

            </div>
            @include('includes.footer')
        </div>
    </div>
    @include('includes.script')
    @livewireScripts

</body>
</html>
