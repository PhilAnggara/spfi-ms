@php
    $useRootRelative = ! empty($rootRelative ?? false);
    $assetUrl = function (string $path) use ($useRootRelative): string {
        $url = url($path);

        return $useRootRelative ? (string) parse_url($url, PHP_URL_PATH) : $url;
    };
@endphp
<link rel="shortcut icon" href="{{ $assetUrl('assets/images/favicon.png') }}" type="image/x-icon">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $assetUrl('apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'SPFI-MS') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#00408B">
