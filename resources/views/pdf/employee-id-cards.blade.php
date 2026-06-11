<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee ID Cards</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #0f172a;
            background: #ffffff;
        }

        .card-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3mm;
        }

        .card-cell {
            width: 54mm;
            vertical-align: top;
            padding: 0;
        }

        /* ── Card wrapper ────────────────────────────────────── */
        .id-card {
            position: relative;
            width: 54mm;
            height: 86mm;
            border: 0.4mm solid #b6d5f2;
            border-radius: 4mm;
            overflow: hidden;
            background: #ffffff;
            page-break-inside: avoid;
        }

        /* ── Header ──────────────────────────────────────────── */
        .id-card-header {
            padding: 2.8mm 3mm 2.4mm;
            background-color: #0f4c81;
            /* background-color: #ffffff; */
            background-image: linear-gradient(128deg, #0a3b6b 0%, #0f4c81 50%, #0ea5e9 100%);
            border-top-left-radius: 3.5mm;
            border-top-right-radius: 3.5mm;
        }

        .id-card-header-stripe {
            height: 0.8mm;
            margin-top: 1.6mm;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.28);
            /* background: rgba(15, 76, 129, 0.8); */
        }

        .brand-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-logo-cell {
            width: 9mm;
            padding-right: 1.8mm;
            vertical-align: middle;
        }

        .brand-logo {
            width: 8mm;
            height: 8mm;
            border-radius: 1.5mm;
            background: #ffffff;
            padding: 0.5mm;
        }

        .brand-name-cell {
            vertical-align: middle;
        }

        .brand-name {
            font-size: 8pt;
            font-weight: bold;
            color: #ffffff;
            /* color: #0f4c81; */
            line-height: 1.25;
            letter-spacing: 0.02em;
        }

        /* ── Body ────────────────────────────────────────────── */
        .id-card-body {
            padding: 3mm 3.5mm 7mm;
            text-align: center;
        }

        .id-card-photo-wrap {
            width: 24mm;
            height: 30mm;
            margin: 0 auto 2mm;
            overflow: hidden;
            border-radius: 2mm;
            border: 0.6mm solid #ffffff;
            background: #dbeafe;
        }

        .id-card-photo {
            display: block;
        }

        .id-card-photo-placeholder {
            width: 24mm;
            height: 30mm;
            padding-top: 4.8mm;
            text-align: center;
            background: linear-gradient(160deg, #f8fcff 0%, #e2efff 100%);
        }

        .id-card-photo-placeholder-avatar {
            width: 9.5mm;
            height: 9.5mm;
            margin: 0 auto 1.5mm;
            border-radius: 50%;
            border: 0.45mm solid #ffffff;
            background: radial-gradient(circle at 35% 30%, #bfd3e8 0%, #7ea0c1 78%);
        }

        .id-card-photo-placeholder-body {
            width: 12.5mm;
            height: 8mm;
            margin: 0 auto 1.9mm;
            border-top-left-radius: 6.3mm;
            border-top-right-radius: 6.3mm;
            border-bottom-left-radius: 2.4mm;
            border-bottom-right-radius: 2.4mm;
            background: linear-gradient(180deg, #9ab6d2 0%, #6f90b2 100%);
            border: 0.45mm solid #ffffff;
        }

        .id-card-photo-placeholder-label {
            font-size: 4.5pt;
            font-weight: bold;
            color: #496785;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .id-card-name {
            font-size: 8.5pt;
            /* font-weight: bold; */
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 1.8mm;
        }

        .id-card-empid-wrap {
            text-align: center;
            margin-bottom: 1.5mm;
        }

        .id-card-empid {
            display: inline-block;
            font-size: 7pt;
            font-weight: bold;
            color: #ffffff;
            background-color: #1b5fa0;
            border: 0.3mm solid #5fa8d8;
            padding: 0.7mm 2.5mm;
            border-radius: 999px;
            letter-spacing: 0.08em;
            line-height: 1.2;
        }

        .id-card-dept {
            font-size: 7.6pt;
            color: #475569;
            font-weight: bold;
            margin-top: 1mm;
        }

        /* ── Footer (pinned to bottom) ───────────────────────── */
        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 0.3mm solid #dbeafe;
            padding: 1.5mm 3mm 1.2mm;
            text-align: center;
            background: #f8fbff;
        }

        .id-card-footer-valid {
            font-size: 4.8pt;
            color: #64748b;
            font-weight: bold;
            letter-spacing: 0.04em;
        }

        /* ── Decorative accent circle (right-bottom of card) ─── */
        .id-card-accent {
            position: absolute;
            width: 32mm;
            height: 32mm;
            border-radius: 50%;
            background: rgba(14, 165, 233, 0.07);
            bottom: -10mm;
            right: -10mm;
        }
    </style>
</head>
<body>
    <table class="card-grid">
        @foreach ($employees->chunk(3) as $row)
        <tr>
            @foreach ($row as $employee)
            @php
                $photoSrc = null;
                $hasCustomPhoto = false;

                if ($employee->photo_path) {
                    $candidatePhotoPath = public_path($employee->photo_path);
                    if (file_exists($candidatePhotoPath)) {
                        $photoSrc = $candidatePhotoPath;
                        $hasCustomPhoto = true;
                    }
                }

                $photoStyle = 'width: 24mm; height: 30mm; margin: 0;';
                $frameWidth = 24.0;
                $frameHeight = 30.0;
                $frameRatio = $frameWidth / $frameHeight;
                $imageSize = $hasCustomPhoto ? @getimagesize($photoSrc) : false;

                if (is_array($imageSize) && !empty($imageSize[0]) && !empty($imageSize[1])) {
                    $imageRatio = $imageSize[0] / $imageSize[1];

                    if ($imageRatio > $frameRatio) {
                        $renderHeight = $frameHeight;
                        $renderWidth = $frameHeight * $imageRatio;
                        $offsetX = ($renderWidth - $frameWidth) / 2;
                        $photoStyle = sprintf(
                            'width: %.3Fmm; height: %.3Fmm; margin-left: -%.3Fmm; margin-top: 0;',
                            $renderWidth,
                            $renderHeight,
                            $offsetX
                        );
                    } else {
                        $renderWidth = $frameWidth;
                        $renderHeight = $frameWidth / $imageRatio;
                        $offsetY = ($renderHeight - $frameHeight) / 2;
                        $photoStyle = sprintf(
                            'width: %.3Fmm; height: %.3Fmm; margin-top: -%.3Fmm; margin-left: 0;',
                            $renderWidth,
                            $renderHeight,
                            $offsetY
                        );
                    }
                }

                $departmentName = $employee->department?->name
                    ?? $employee->department?->code
                    ?? $employee->legacy_department_code
                    ?? '-';
            @endphp
            <td class="card-cell">
                <div class="id-card">
                    <div class="id-card-accent"></div>

                    {{-- Header --}}
                    <div class="id-card-header">
                        <table class="brand-table">
                            <tr>
                                <td class="brand-logo-cell">
                                    <img src="{{ $logoPath }}" class="brand-logo" alt="">
                                </td>
                                <td class="brand-name-cell">
                                    <div class="brand-name">PT. Sinar Pure Foods International</div>
                                </td>
                            </tr>
                        </table>
                        <div class="id-card-header-stripe"></div>
                    </div>

                    {{-- Body --}}
                    <div class="id-card-body">
                        <div class="id-card-photo-wrap">
                            @if ($hasCustomPhoto)
                            <img src="{{ $photoSrc }}" class="id-card-photo" style="{{ $photoStyle }}" alt="">
                            @else
                            <div class="id-card-photo-placeholder">
                                <div class="id-card-photo-placeholder-avatar"></div>
                                <div class="id-card-photo-placeholder-body"></div>
                                <div class="id-card-photo-placeholder-label">No Photo</div>
                            </div>
                            @endif
                        </div>
                        <div class="id-card-name">{{ $employee->employee_name }}</div>
                        <div class="id-card-empid-wrap">
                            <span class="id-card-empid">{{ $employee->employee_id }}</span>
                        </div>
                        <div class="id-card-dept">{{ $departmentName }}</div>
                    </div>

                    {{-- Footer (absolute bottom) --}}
                    <div class="id-card-footer">
                        <span class="id-card-footer-valid">Valid Until: {{ $validUntil->format('d M Y') }}</span>
                    </div>
                </div>
            </td>
            @endforeach

            {{-- Pad incomplete last row so table stays stable --}}
            @for ($i = $row->count(); $i < 3; $i++)
            <td class="card-cell"></td>
            @endfor
        </tr>
        @endforeach
    </table>
</body>
</html>
