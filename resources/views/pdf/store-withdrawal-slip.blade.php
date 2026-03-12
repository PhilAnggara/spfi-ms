<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stores Withdrawal Slip</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
        }
        .title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 10px;
        }
        .line {
            border-top: 1px solid #0f172a;
            margin: 8px 0 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }
        .label {
            width: 120px;
            font-weight: 700;
            white-space: nowrap;
        }
        .items th,
        .items td {
            border: 1px solid #0f172a;
            padding: 4px 5px;
            vertical-align: top;
        }
        .items th {
            background: #f1f5f9;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            margin-top: 10px;
            border: 1px solid #0f172a;
            padding: 6px 8px;
        }
        .summary-row {
            width: 100%;
        }
        .summary-row td {
            border: none;
            padding: 1px 0;
        }
        .signatures {
            margin-top: 20px;
        }
        .signatures td {
            border: none;
            padding: 4px 0;
            width: 33%;
            text-align: center;
        }
        .sign-line {
            margin-top: 24px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    @php
        $totalQuantity = (float) $items->sum('quantity');
        $totalRows = $items->count();
    @endphp

    <div class="title">STORES WITHDRAWAL SLIP</div>
    <div class="subtitle">PT Sinar Pure Foods International</div>
    <div class="line"></div>

    <table class="meta">
        <tr>
            <td class="label">SWS Number</td>
            <td>: {{ $sws->sws_number ?? '-' }}</td>
            <td class="label text-right">SWS Date</td>
            <td class="text-right">: {{ $sws->sws_date ? \Carbon\Carbon::parse($sws->sws_date)->format('d M Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Department</td>
            <td>: {{ $sws->department_code ?? '-' }}{{ $sws->department_name ? ' - ' . $sws->department_name : '' }}</td>
            <td class="label text-right">Type</td>
            <td class="text-right">: {{ strtoupper((string) ($sws->type ?? '-')) }}</td>
        </tr>
        <tr>
            <td class="label">Created By</td>
            <td>: {{ $sws->created_by_name ?? '-' }}</td>
            <td class="label text-right">Approved By</td>
            <td class="text-right">: {{ $sws->approved_by_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Info</td>
            <td colspan="3">: {{ $sws->info ?? '-' }}</td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 28px;" class="text-center">No</th>
                <th>Item</th>
                <th style="width: 85px;">Code</th>
                <th style="width: 70px;" class="text-right">Qty</th>
                <th style="width: 60px;" class="text-center">UoM</th>
                <th style="width: 90px;" class="text-right">SOH Snapshot</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $detail)
                @php
                    $qty = (float) ($detail->quantity ?? 0);
                    $soh = (float) ($detail->stock_on_hand_snapshot ?? 0);
                    $uom = $detail->uom ?? $detail->item_uom_name ?? 'PCS';
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $detail->item_name ?? '(item unavailable)' }}</td>
                    <td>{{ $detail->item_code ?? $detail->product_code ?? '-' }}</td>
                    <td class="text-right">{{ number_format($qty, 3, '.', ',') }}</td>
                    <td class="text-center">{{ $uom }}</td>
                    <td class="text-right">{{ number_format($soh, 3, '.', ',') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No item data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <table class="summary-row">
            <tr>
                <td>Total Item Rows</td>
                <td class="text-right">{{ $totalRows }}</td>
            </tr>
            <tr>
                <td><strong>Total Quantity</strong></td>
                <td class="text-right"><strong>{{ number_format($totalQuantity, 3, '.', ',') }}</strong></td>
            </tr>
        </table>
    </div>

    <table class="signatures">
        <tr>
            <td>Requested by</td>
            <td>Checked by</td>
            <td>Approved by</td>
        </tr>
        <tr>
            <td class="sign-line">{{ $sws->created_by_name ?? '____________________' }}</td>
            <td class="sign-line">____________________</td>
            <td class="sign-line">{{ $sws->approved_by_name ?? '____________________' }}</td>
        </tr>
        <tr>
            <td>Date: {{ $sws->created_at ? \Carbon\Carbon::parse($sws->created_at)->format('d M Y') : '__________' }}</td>
            <td>Date: __________</td>
            <td>Date: {{ $sws->approved_at ? \Carbon\Carbon::parse($sws->approved_at)->format('d M Y') : '__________' }}</td>
        </tr>
    </table>
</body>
</html>
