<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Calibration Sample</title>
    @php
        $pageWidthMm = $pageWidthMm ?? 215;
        $pageHeightMm = $pageHeightMm ?? 160;
        $offsetXMm = (float) ($offsetXMm ?? 0);
        $offsetYMm = (float) ($offsetYMm ?? 0);
        $documentType = strtoupper((string) ($documentType ?? 'RR'));
        $designAnchor = $designAnchor ?? ['x' => 0, 'y' => 0, 'label' => 'Top-left corner of the background table'];

        if ($documentType === 'RR') {
            $baseWidthMm = 297;
            $baseHeightMm = 210;
            $scaleX = $pageWidthMm / $baseWidthMm;
            $scaleY = $pageHeightMm / $baseHeightMm;
            $sx = static fn (float $mm): float => round($mm * $scaleX, 2);
            $sy = static fn (float $mm): float => round($mm * $scaleY, 2);
            $mmX = static fn (float $mm): string => round($sx($mm) + $offsetXMm, 2).'mm';
            $mmY = static fn (float $mm): string => round($sy($mm) + $offsetYMm, 2).'mm';
            $rowTop = round($sy(73) + $offsetYMm, 2);
            $rowLeft = round($sx(15) + $offsetXMm, 2);
        } else {
            $mmX = static fn (float $mm): string => round($mm + $offsetXMm, 2).'mm';
            $mmY = static fn (float $mm): string => round($mm + $offsetYMm, 2).'mm';
            $rowTop = round(48.4 + $offsetYMm, 2);
            $rowLeft = round(12.5 + $offsetXMm, 2);
        }

        $anchorLeft = round((float) $designAnchor['x'] + $offsetXMm, 2);
        $anchorTop = round((float) $designAnchor['y'] + $offsetYMm, 2);
    @endphp
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
        }

        .page {
            position: relative;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            overflow: hidden;
            font-family: Courier, monospace;
        }

        .bg {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
        }

        .field {
            position: absolute;
            font-size: 10px;
            white-space: nowrap;
        }

        .marker {
            position: absolute;
            width: 4mm;
            height: 4mm;
            border: 0.3mm solid #000;
        }

        .hint {
            position: absolute;
            font-size: 8px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="page">
        @if (! empty($backgroundImageDataUri))
            <img src="{{ $backgroundImageDataUri }}" alt="" class="bg">
        @endif

        <div class="marker" style="left: {{ $anchorLeft }}mm; top: {{ $anchorTop }}mm;"></div>

        <div class="field" style="left: {{ $rowLeft }}mm; top: {{ $rowTop }}mm; width: 70mm;">
            SAMPLE ITEM NAME FOR ALIGNMENT
        </div>
        <div class="field" style="left: {{ $documentType === 'RR' ? $mmX(83) : $mmX(89) }}; top: {{ $documentType === 'RR' ? $mmY(73) : $mmY(48.4) }}; width: 30mm; text-align: center;">
            CODE-001
        </div>

        @if ($documentType === 'RR')
            <div class="field" style="left: {{ $mmX(37) }}; top: {{ $mmY(41) }}; width: 80mm;">Sample Supplier Name</div>
            <div class="field" style="left: {{ $mmX(161) }}; top: {{ $mmY(38) }}; width: 40mm;">PO-SAMPLE-001</div>
        @else
            <div class="field" style="left: {{ $mmX(25) }}; top: {{ $mmY(25.2) }}; width: 55mm;">From: Store Sample</div>
            <div class="field" style="left: {{ $mmX(25) }}; top: {{ $mmY(32.6) }}; width: 55mm;">To: Production Sample</div>
        @endif

        <div class="hint" style="left: 2mm; top: 2mm;">{{ $documentType }} calibration sample</div>
        <div class="hint" style="left: 2mm; top: 6mm;">Anchor design: {{ number_format($designAnchor['x'], 2) }} / {{ number_format($designAnchor['y'], 2) }} mm</div>
    </div>
</body>
</html>
