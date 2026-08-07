@php
    $useRootRelative = $rootRelative ?? false;
    $assetUrl = function (string $path) use ($useRootRelative): string {
        $url = url($path);

        return $useRootRelative ? (parse_url($url, PHP_URL_PATH) ?: $url) : $url;
    };
@endphp
<link rel="stylesheet" href="{{ $assetUrl('assets/styles/spfi-tokens.css') }}">
<link rel="stylesheet" href="{{ $assetUrl('assets/styles/spfi-scale.css') }}">
<link rel="stylesheet" href="{{ $assetUrl('assets/compiled/css/app.css') }}">
<link rel="stylesheet" href="{{ $assetUrl('assets/compiled/css/error.css') }}">
<link rel="stylesheet" href="{{ $assetUrl('assets/styles/spfi-error.css') }}">
