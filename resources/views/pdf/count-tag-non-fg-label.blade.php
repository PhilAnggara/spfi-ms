<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Count Tag {{ $tag->count_tag_number }}</title>
    <style>
        @page {
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #0f172a;
            background: #ffffff;
        }

        .label {
            border: 0.4mm solid #334155;
            padding: 3mm;
            width: 100%;
        }

        .title {
            font-size: 8pt;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 1.5mm;
        }

        .ct-number {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin-bottom: 2.5mm;
            word-break: break-all;
        }

        .row {
            margin-bottom: 1.4mm;
            line-height: 1.25;
        }

        .label-key {
            display: inline-block;
            min-width: 18mm;
            color: #64748b;
            font-size: 8pt;
        }

        .label-value {
            font-weight: bold;
            font-size: 9.5pt;
        }

        .item-name {
            font-weight: normal;
            color: #334155;
            font-size: 8pt;
        }
    </style>
</head>
<body>
@php
    $location = $tag->location?->name ?? $tag->location_name;
    $section = $tag->section?->code ?? $tag->section_code;
    $positionParts = array_filter([
        $section ? 'Sec '.$section : null,
        ($tag->row || $tag->col || $tag->level)
            ? 'R'.($tag->row ?? '-').'/C'.($tag->col ?? '-').'/L'.($tag->level ?? '-')
            : null,
    ]);
@endphp
<div class="label">
    <div class="title">Count Tag Non-FG</div>
    <div class="ct-number">{{ $tag->count_tag_number }}</div>

    <div class="row">
        <span class="label-key">Item</span>
        <span class="label-value">{{ $tag->item_code }}</span>
        @if ($tag->item?->name)
            <div class="item-name">{{ $tag->item->name }}</div>
        @endif
    </div>

    <div class="row">
        <span class="label-key">Qty</span>
        <span class="label-value">
            {{ number_format((float) $tag->qty, 2) }}
            @if ($tag->uom_code)
                {{ $tag->uom_code }}
            @endif
        </span>
    </div>

    <div class="row">
        <span class="label-key">Location</span>
        <span class="label-value">{{ $location ?: '—' }}</span>
    </div>

    <div class="row">
        <span class="label-key">Position</span>
        <span class="label-value">{{ $positionParts !== [] ? implode(' · ', $positionParts) : '—' }}</span>
    </div>

    <div class="row">
        <span class="label-key">Period</span>
        <span class="label-value">{{ $tag->count_tag_date?->format('Y-m') ?? '—' }}</span>
    </div>
</div>
</body>
</html>
