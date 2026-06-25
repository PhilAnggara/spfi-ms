<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', $title ?? 'Report')</title>
    @include('pdf.partials.header-style')
    @if (! empty($landscape))
        <style>
            @page { size: A4 landscape; margin: 16px 20px; }
            body { font-size: 7.5px; line-height: 1.25; margin: 0 24px; }
            .header .company-name { font-size: 14px; }
            .header .company-address,
            .header .company-contact,
            .header .company-web { font-size: 9px; }
            .document-title { font-size: 11px; margin-top: 6px; }
            .header-divider { width: 100%; margin: 6px auto 10px; }
        </style>
    @endif
    @stack('styles')
</head>
<body>
    @include('pdf.partials.header', ['documentTitle' => $documentTitle ?? $title ?? 'Generated Document'])
    @yield('content')
</body>
</html>
