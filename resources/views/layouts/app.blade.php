<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
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
            <div id="main-content">

                @yield('content')

            </div>
            @include('includes.footer')
        </div>
    </div>
    @include('includes.script')
    @livewireScripts

</body>
</html>
