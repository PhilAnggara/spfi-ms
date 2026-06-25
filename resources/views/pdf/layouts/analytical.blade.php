<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf.partials.analytical-styles')
</head>
<body>
    <div class="report-header">
        <table class="report-header-table">
            <tr>
                <td class="logo-cell">
                    <img src="{{ $logo_path }}" alt="Company Logo" class="logo">
                </td>
                <td>
                    <div class="company-name">{{ $company }}</div>
                    <div class="doc-title">{{ $title }}</div>
                    @hasSection('header-meta')
                        <div class="header-meta">
                            @yield('header-meta')
                        </div>
                    @endif
                </td>
                <td class="header-right"></td>
            </tr>
        </table>
    </div>

    @yield('content')

    @include('pdf.partials.analytical-page-script')
</body>
</html>
