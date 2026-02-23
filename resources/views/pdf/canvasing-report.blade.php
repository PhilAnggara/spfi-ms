<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Canvasing Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 10px;
        }
        .section {
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            border: 1px solid #111827;
            padding: 4px 5px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-size: 9px;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary td {
            border: none;
            padding: 2px 0;
        }
        .label {
            width: 140px;
            font-weight: bold;
            white-space: nowrap;
        }
        .text-wrap {
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }
        .notes-row td {
            font-size: 8.5px;
            background: #fafafa;
            border-top: none;
        }
        .lowest-row td {
            background: #eef9ee;
        }
        .lowest-label {
            display: inline-block;
            margin-top: 2px;
            padding: 1px 4px;
            border: 1px solid #2f855a;
            color: #2f855a;
            font-size: 8px;
            font-weight: bold;
        }
        .visual-section {
            margin-top: 10px;
            border: 1px solid #111827;
            padding: 6px;
        }
        .visual-title {
            font-weight: bold;
            margin-bottom: 6px;
        }
        .visual-row {
            margin-bottom: 5px;
        }
        .visual-label {
            font-size: 8.5px;
            margin-bottom: 2px;
        }
        .bar-wrap {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        .bar {
            height: 8px;
            background: #6b7280;
        }
        .bar-lowest {
            background: #2f855a;
        }
        .footnote {
            margin-top: 4px;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    @php
        $item = $prsItem->item;
        $prs = $prsItem->prs;
        $selectedId = $prsItem->selected_canvasing_item_id;
        $lowestUnitPrice = (float) ($canvasingItems->min('unit_price') ?? 0);
        $highestUnitPrice = (float) ($canvasingItems->max('unit_price') ?? 1);
    @endphp

    <div class="title">Supplier Canvasing Report</div>
    <div class="subtitle">Generated at {{ now()->format('d M Y H:i') }} by {{ $generatedBy->name ?? '-' }}</div>

    <div class="section">
        <table class="summary">
            <tr>
                <td class="label">PRS Number</td>
                <td>: {{ $prs->prs_number ?? '-' }}</td>
                <td class="label">Department</td>
                <td>: {{ $prs->department->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Item Code</td>
                <td>: {{ $item->code ?? '-' }}</td>
                <td class="label">Item Name</td>
                <td>: {{ $item->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Quantity</td>
                <td>: {{ number_format($prsItem->quantity, 0, ',', '.') }} {{ $item->unit->name ?? 'PCS' }}</td>
                <td class="label">Date Needed</td>
                <td>: {{ $prs?->date_needed ? \Illuminate\Support\Carbon::parse($prs->date_needed)->format('d M Y') : '-' }}</td>
            </tr>
            @if ($prsItem->is_direct_purchase)
            <tr>
                <td class="label">Purchase Type</td>
                <td colspan="3" style="color: #2563eb; font-weight: bold;">Direct Purchase</td>
            </tr>
            @endif
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 24px;" class="text-center">No</th>
                <th style="width: 130px;">Supplier</th>
                <th style="width: 80px;" class="text-right">Price / Unit</th>
                <th style="width: 60px;" class="text-center">Payment Type</th>
                <th style="width: 120px;">Payment Detail</th>
                <th style="width: 55px;" class="text-center">Lead Time</th>
                <th style="width: 95px;">Term of Delivery</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($canvasingItems as $index => $canvasing)
                @php
                    $isLowest = (float) $canvasing->unit_price === $lowestUnitPrice;
                @endphp
                <tr class="{{ $isLowest ? 'lowest-row' : '' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $canvasing->supplier->name ?? '-' }}
                        @if ($selectedId === $canvasing->id)
                            {{-- <div style="font-size:9px; color:#111827;">(Selected)</div> --}}
                        @endif
                        @if ($isLowest)
                            <div class="lowest-label">LOWEST PRICE</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $canvasing->unit_price, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $canvasing->term_of_payment_type ? ucfirst($canvasing->term_of_payment_type) : '-' }}</td>
                    <td class="text-wrap">{{ $canvasing->term_of_payment ?? '-' }}</td>
                    <td class="text-center">{{ $canvasing->lead_time_days ?? '-' }} {{ $canvasing->lead_time_days ? 'days' : '' }}</td>
                    <td class="text-wrap">{{ $canvasing->term_of_delivery ?? '-' }}</td>
                </tr>
                <tr class="notes-row">
                    <td colspan="7" class="text-wrap">
                        <strong>Notes:</strong> {{ $canvasing->notes ?: '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="visual-section">
        <div class="visual-title">Supplier Price Visualization</div>
        @foreach ($canvasingItems as $canvasing)
            @php
                $isLowest = (float) $canvasing->unit_price === $lowestUnitPrice;
                $ratio = $highestUnitPrice > 0 ? ((float) $canvasing->unit_price / $highestUnitPrice) * 100 : 0;
                $differenceAmount = max($highestUnitPrice - (float) $canvasing->unit_price, 0);
                $cheaperPercent = $highestUnitPrice > 0 ? ($differenceAmount / $highestUnitPrice) * 100 : 0;
            @endphp
            <div class="visual-row">
                <div class="visual-label">
                    {{ $canvasing->supplier->name ?? '-' }} - {{ number_format((float) $canvasing->unit_price, 2, ',', '.') }}
                    @if ($differenceAmount > 0)
                        ({{ number_format($cheaperPercent, 2, ',', '.') }}% cheaper; difference {{ number_format($differenceAmount, 2, ',', '.') }} from highest)
                    @else
                        (HIGHEST PRICE)
                    @endif
                    @if ($isLowest)
                        (LOWEST)
                    @endif
                </div>
                <div class="bar-wrap">
                    <div class="bar {{ $isLowest ? 'bar-lowest' : '' }}" style="width: {{ number_format($ratio, 2, '.', '') }}%;"></div>
                </div>
            </div>
        @endforeach
        <div class="footnote">Bar lengths are relative to the highest price for the same item; green indicates the lowest price.</div>
    </div>
</body>
</html>
