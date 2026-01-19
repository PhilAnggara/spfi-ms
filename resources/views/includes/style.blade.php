    <link rel="shortcut icon" href="{{ url('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.1/css/all.css">

    @stack('prepend-style')

    <link rel="stylesheet" href="{{ url('assets/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ url('assets/vendors/aos/aos.css') }}">
    <link rel="stylesheet" href="{{ url('assets/styles/main.css') }}">

    @stack('addon-style')
