<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Slip</title>
    @php
        $pageWidthMm = $pageWidthMm ?? 215;
        $pageHeightMm = $pageHeightMm ?? 105;
        $fieldFontSize = 8;
        $numberFontSize = 9;
        $cellFontSize = 8;
        $checkFontSize = 7;
        $remarksFontSize = 8;
        $itemCellMaxHeight = 2.6;
    @endphp
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
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
            font-weight: bold;
        }

        .ts-number {
            font-size: {{ $numberFontSize+2 }}pt;
            font-weight: normal;
            letter-spacing: 0.2px;
        }

        .cell {
            position: absolute;
            font-size: {{ $cellFontSize }}pt;
            line-height: 1.05;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-weight: bold;
        }

        .item-cell {
            white-space: normal;
            text-overflow: clip;
            line-height: 1;
            max-height: {{ $itemCellMaxHeight }}mm;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .check {
            position: absolute;
            font-size: {{ $checkFontSize }}pt;
            font-weight: bold;
            line-height: 1;
            text-align: center;
        }

        .remarks {
            position: absolute;
            left: 90mm;
            top: 86mm;
            width: 34mm;
            height: 10mm;
            font-size: {{ $remarksFontSize }}pt;
            font-weight: bold;
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
        $rowHeightMm = 3.1;
        $maxRows = 8;
        $rows = collect($items)->take($maxRows)->values();

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
                <div class="field ts-number" style="left: 179mm; top: 12mm; width: 20mm;">{{ $tsNumberText }}</div>
            @endif
        @endif

        <div class="field" style="left: 25mm; top: 25.2mm; width: 55mm;">{{ $fromText }}</div>
        <div class="field" style="left: 25mm; top: 32.6mm; width: 55mm;">{{ $toText !== '' ? $toText : '-' }}</div>

        {{-- @if ($checkedTypes['finished_goods'])
            <div class="check" style="left: 92.5mm; top: 25mm; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['supplies'])
            <div class="check" style="left: 162.5mm; top: 25mm; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['raw_materials'])
            <div class="check" style="left: 92.5mm; top: 32.8mm; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['others'])
            <div class="check" style="left: 162.5mm; top: 32.8mm; width: 5mm;">X</div>
        @endif
        @if ($checkedTypes['spare_parts'])
            <div class="check" style="left: 92.5mm; top: 39.8mm; width: 5mm;">X</div>
        @endif --}}

        @foreach ($rows as $index => $detail)
            @php
                $top = $rowStartTopMm + ($index * $rowHeightMm);
                $qty = (float) ($detail->quantity ?? 0);
                $uom = $detail->sws_uom ?? $detail->item_uom_name ?? 'PCS';
            @endphp
            <div class="cell item-cell" style="left: 12.5mm; top: {{ $top }}mm; width: 73mm;">{{ $detail->item_name ?? '(item unavailable)' }}</div>
            <div class="cell center" style="left: 89mm; top: {{ $top }}mm; width: 34mm;">{{ $detail->item_code ?? $detail->product_code ?? '-' }}</div>
            <div class="cell right" style="left: 126mm; top: {{ $top }}mm; width: 35mm;">{{ number_format($qty, 3, '.', ',') }}</div>
            <div class="cell center" style="left: 165mm; top: {{ $top }}mm; width: 32mm;">{{ $uom }}</div>
        @endforeach

        <div class="field center" style="left: 8mm; top: 78mm; width: 34mm;">{{ $transferSlip->created_by_name ?? '' }}</div>
        <div class="field center" style="left: 48mm; top: 78mm; width: 38mm;">{{ $transferSlip->noted_by_name ?? '' }}</div>
        <div class="field center" style="left: 90mm; top: 78mm; width: 33mm;">{{ $transferSlip->approved_by_name ?? '' }}</div>
        <div class="field center" style="left: 126mm; top: 78mm; width: 35mm;">{{ $transferSlip->received_by_name ?? '' }}</div>
        <div class="field center" style="left: 165mm; top: 78mm; width: 32mm;">{{ $swsNumber }}</div>

        <div class="remarks">{{ $remarksText }}</div>
        <div class="field center" style="left: 165mm; top: 92mm; width: 32mm;">{{ $tsDateText }}</div>
    </div>
</body>
</html>
