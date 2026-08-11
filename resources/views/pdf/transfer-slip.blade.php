<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Slip</title>
    @php
        $pageWidthMm = $pageWidthMm ?? 215;
        $pageHeightMm = $pageHeightMm ?? 105;
        $offsetXMm = (float) ($offsetXMm ?? config('transfer-slip.offset_x_mm', 0));
        $offsetYMm = (float) ($offsetYMm ?? config('transfer-slip.offset_y_mm', 0));
        $lx = static fn (float $mm): string => round($mm + $offsetXMm, 2).'mm';
        $ty = static fn (float $mm): string => round($mm + $offsetYMm, 2).'mm';
        $fieldFontSize = 9;
        $numberFontSize = 10;
        $cellFontSize = 10;
        $checkFontSize = 8;
        $remarksFontSize = 9;
        $itemLineHeight = 1.1;
        $lineHeightMm = round(($cellFontSize * $itemLineHeight) * (25.4 / 72), 2);
        $rowGapMm = 0.25;
        $minRowHeightMm = max(3.2, $lineHeightMm + $rowGapMm);
        $itemNameWidthMm = 73;
        // Courier monospace (~0.6em); estimate chars for the item name column.
        $itemNameCharsPerLine = max(18, (int) floor(($itemNameWidthMm * (72 / 25.4)) / max(1.0, $cellFontSize * 0.6)));
        $wrapItemName = static function (string $text, int $charsPerLine, ?int $maxLines = null): array {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '') {
                return ['text' => '(item unavailable)', 'line_count' => 1];
            }

            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $lines = [];
            $current = '';

            $pushLine = static function (string $line) use (&$lines, $maxLines): bool {
                if ($line === '') {
                    return $maxLines !== null && count($lines) >= $maxLines;
                }
                if ($maxLines !== null && count($lines) >= $maxLines) {
                    return true;
                }
                $lines[] = $line;

                return $maxLines !== null && count($lines) >= $maxLines;
            };

            foreach ($words as $word) {
                while (mb_strlen($word) > $charsPerLine) {
                    if ($current !== '' && $pushLine($current)) {
                        return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                    }
                    $current = '';
                    if ($pushLine(mb_substr($word, 0, $charsPerLine))) {
                        return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                    }
                    $word = mb_substr($word, $charsPerLine);
                }

                if ($word === '') {
                    continue;
                }

                $candidate = $current === '' ? $word : $current.' '.$word;
                if (mb_strlen($candidate) <= $charsPerLine) {
                    $current = $candidate;
                    continue;
                }

                if ($pushLine($current)) {
                    return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                }
                $current = $word;
            }

            if ($current !== '') {
                $pushLine($current);
            }

            if ($lines === []) {
                return ['text' => '(item unavailable)', 'line_count' => 1];
            }

            return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
        };
    @endphp
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: Courier, monospace;
            color: #111827;
            background: transparent;
        }

        .ts-form-page {
            position: relative;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            page-break-after: auto;
            overflow: hidden;
            background: transparent;
        }

        .ts-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            z-index: 0;
        }

        .field,
        .cell,
        .check,
        .remarks {
            z-index: 1;
        }

        .field {
            position: absolute;
            font-size: {{ $fieldFontSize }}pt;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: normal;
        }

        .ts-number {
            font-size: {{ $numberFontSize+2 }}pt;
            font-weight: normal;
            letter-spacing: 0.2px;
        }

        .cell {
            position: absolute;
            font-size: {{ $cellFontSize }}pt;
            line-height: {{ $itemLineHeight }};
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-weight: normal;
        }

        .item-cell {
            white-space: pre-line;
            text-overflow: clip;
            line-height: {{ $itemLineHeight }};
            overflow: visible;
            overflow-wrap: normal;
            word-break: normal;
            font-weight: normal;
        }

        .check {
            position: absolute;
            font-size: {{ $checkFontSize }}pt;
            font-weight: normal;
            line-height: 1;
            text-align: center;
        }

        .remarks {
            position: absolute;
            left: {{ $lx(90) }};
            top: {{ $ty(86) }};
            width: 34mm;
            height: 10mm;
            font-size: {{ $remarksFontSize }}pt;
            font-weight: normal;
            line-height: 1.15;
            white-space: normal;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $meta = is_string($transferSlip->meta ?? null)
            ? json_decode($transferSlip->meta, true)
            : (array) ($transferSlip->meta ?? []);
        $legacyType = strtolower(trim((string) ($meta['legacy_ts_type'] ?? '')));

        $itemTypes = collect($items)
            ->map(fn ($item) => strtolower(trim((string) ($item->item_type ?? ''))))
            ->filter()
            ->values();
        $itemCategories = collect($items)
            ->map(fn ($item) => strtolower(trim((string) ($item->category_name ?? ''))))
            ->filter()
            ->values();
        $typeHaystack = trim($legacyType.' '.$itemTypes->implode(' ').' '.$itemCategories->implode(' '));

        $checkedTypes = [
            'finished_goods' => str_contains($typeHaystack, 'finished'),
            'raw_materials' => str_contains($typeHaystack, 'raw'),
            'spare_parts' => str_contains($typeHaystack, 'spare'),
            'supplies' => str_contains($typeHaystack, 'supplies')
                || str_contains($typeHaystack, 'consumable')
                || str_contains($typeHaystack, 'supply'),
        ];
        $checkedTypes['others'] = ! (
            $checkedTypes['finished_goods']
            || $checkedTypes['raw_materials']
            || $checkedTypes['spare_parts']
            || $checkedTypes['supplies']
        ) && $typeHaystack !== '';

        $fromText = 'Inventory Management';
        $toText = trim((string) ($transferSlip->transfer_to ?: ''));
        if ($toText === '') {
            $departmentCode = trim((string) ($transferSlip->department_code ?? ''));
            $departmentName = trim((string) ($transferSlip->department_name ?? ''));
            $toText = trim($departmentCode.($departmentCode !== '' && $departmentName !== '' ? ' - ' : '').$departmentName);
        }

        // Page-mm coordinates calibrated against Blank TS.jpg (215 x 105).
        $rowStartTopMm = 48.4;
        $itemsBottomLimitMm = 76.5;
        $layoutRows = [];
        $currentTop = $rowStartTopMm;

        foreach (collect($items) as $detail) {
            $remainingMm = $itemsBottomLimitMm - $currentTop;
            if ($remainingMm < $minRowHeightMm) {
                break;
            }

            $rawName = (string) ($detail->item_name ?? '(item unavailable)');
            $wrapped = $wrapItemName($rawName, $itemNameCharsPerLine);
            $lineCount = max(1, (int) $wrapped['line_count']);
            $rowHeight = max($minRowHeightMm, ($lineCount * $lineHeightMm) + $rowGapMm);

            if ($rowHeight > $remainingMm) {
                $maxLinesThatFit = max(1, (int) floor(($remainingMm - $rowGapMm) / max(0.01, $lineHeightMm)));
                $wrapped = $wrapItemName($rawName, $itemNameCharsPerLine, $maxLinesThatFit);
                $lineCount = max(1, (int) $wrapped['line_count']);
                $rowHeight = max($minRowHeightMm, ($lineCount * $lineHeightMm) + $rowGapMm);
                if ($rowHeight > $remainingMm) {
                    break;
                }
            }

            $layoutRows[] = [
                'top' => $currentTop,
                'name' => $wrapped['text'],
                'code' => $detail->item_code ?? $detail->product_code ?? '-',
                'qty' => (float) ($detail->quantity ?? 0),
                'uom' => $detail->sws_uom ?? $detail->item_uom_name ?? 'PCS',
            ];
            $currentTop = round($currentTop + $rowHeight, 2);
        }

        $tsDateText = $transferSlip->ts_date
            ? \Carbon\Carbon::parse($transferSlip->ts_date)->locale('id')->translatedFormat('d M Y')
            : '';
        $remarksText = trim((string) ($transferSlip->remarks ?? ''));
        $swsNumber = trim((string) ($transferSlip->sws_number ?? ''));
    @endphp

    <div class="ts-form-page">
        @if($isPreview && ! empty($backgroundImageSrc))
            <img
                src="{!! $backgroundImageSrc !!}"
                alt=""
                class="ts-bg"
                width="{{ round($backgroundWidthPt ?? ($pageWidthMm * 2.834645669), 2) }}"
                height="{{ round($backgroundHeightPt ?? ($pageHeightMm * 2.834645669), 2) }}"
            >
        @endif

        @if ($isPreview)
            @php $tsNumberText = trim((string) ($transferSlip->ts_number ?? '')); @endphp
            @if ($tsNumberText !== '')
                <div class="field ts-number" style="left: {{ $lx(179) }}; top: {{ $ty(12) }}; width: 20mm;">{{ $tsNumberText }}</div>
            @endif
        @endif

        <div class="field" style="left: {{ $lx(25) }}; top: {{ $ty(25.2) }}; width: 55mm;">{{ $fromText }}</div>
        <div class="field" style="left: {{ $lx(25) }}; top: {{ $ty(32.6) }}; width: 55mm;">{{ $toText !== '' ? $toText : '-' }}</div>

        {{-- @if ($checkedTypes['finished_goods'])
            <div class="check" style="left: {{ $lx(92.5) }}; top: {{ $ty(25) }}; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['supplies'])
            <div class="check" style="left: {{ $lx(162.5) }}; top: {{ $ty(25) }}; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['raw_materials'])
            <div class="check" style="left: {{ $lx(92.5) }}; top: {{ $ty(32.8) }}; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['others'])
            <div class="check" style="left: {{ $lx(162.5) }}; top: {{ $ty(32.8) }}; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['spare_parts'])
            <div class="check" style="left: {{ $lx(92.5) }}; top: {{ $ty(39.8) }}; width: 5mm;">X</div>
        @endif --}}

        @foreach ($layoutRows as $row)
            <div class="cell item-cell" style="left: {{ $lx(12.5) }}; top: {{ $ty($row['top']) }}; width: 73mm;">{{ $row['name'] }}</div>
            <div class="cell center" style="left: {{ $lx(89) }}; top: {{ $ty($row['top']) }}; width: 34mm;">{{ $row['code'] }}</div>
            <div class="cell right" style="left: {{ $lx(126) }}; top: {{ $ty($row['top']) }}; width: 35mm;">{{ \App\Support\PdfFormatters::qty($row['qty']) }}</div>
            <div class="cell center" style="left: {{ $lx(165) }}; top: {{ $ty($row['top']) }}; width: 32mm;">{{ $row['uom'] }}</div>
        @endforeach

        <div class="field center" style="left: {{ $lx(8) }}; top: {{ $ty(78) }}; width: 34mm;">{{ $transferSlip->created_by_name ?? '' }}</div>
        <div class="field center" style="left: {{ $lx(48) }}; top: {{ $ty(78) }}; width: 38mm;">{{ $transferSlip->noted_by_name ?? '' }}</div>
        <div class="field center" style="left: {{ $lx(90) }}; top: {{ $ty(78) }}; width: 33mm;">{{ $transferSlip->approved_by_name ?? '' }}</div>
        <div class="field center" style="left: {{ $lx(126) }}; top: {{ $ty(78) }}; width: 35mm;">{{ $transferSlip->received_by_name ?? '' }}</div>
        <div class="field center" style="left: {{ $lx(165) }}; top: {{ $ty(78) }}; width: 32mm;">{{ $swsNumber }}</div>

        <div class="remarks">{{ $remarksText }}</div>
        <div class="field center" style="left: {{ $lx(165) }}; top: {{ $ty(92) }}; width: 32mm;">{{ $tsDateText }}</div>
    </div>
</body>
</html>
