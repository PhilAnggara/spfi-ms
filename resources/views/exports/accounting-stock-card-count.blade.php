<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9px; line-height: 1.4; margin: 18px 24px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
        th, td { border: none; padding: 3px 6px; text-align: left; }
        th { font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 12px; font-weight: bold; }
        .report-title { font-size: 13px; font-weight: bold; margin-top: 5px; }
        .report-info { font-size: 8px; margin-top: 5px; }
        .number-right, .right { text-align: right; }
        .center { text-align: center; }
        .section-head { border-bottom: 1.5px solid #374151; padding-bottom: 4px; text-align: center; }
        .section-col { border-bottom: 1.5px solid #374151; padding-bottom: 4px; }
        .col-sep { border-right: 7px solid #fff; padding-right: 2px; }
        .section-sep { border-right: 18px solid #fff; padding-right: 4px; }
        .section-start { padding-left: 4px; }
        .total-row td { font-weight: bold; border-top: 2px solid #111; padding-top: 5px; }
        .footer { margin-top: 20px; font-size: 9px; text-align: center; color: #666; }
    </style>
</head>
<body>
    @php
        $fmtMoney = fn ($value) => $value === null ? '' : number_format((float) $value, 2, ',', '.');
        $fmtQty = fn ($value) => $value === null ? '' : number_format((float) $value, 2, ',', '.');
        $totals = $totals ?? [];
    @endphp

    <div class="header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="report-info">
            <div><strong>Month:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</div>
            <div><strong>Category:</strong> {{ $category }}</div>
        </div>
    </div>

    @if($rows->isEmpty())
        <p style="text-align: center; margin-top: 20px;">No stock card per count records found for the selected period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="center section-head section-sep">Item</th>
                    <th colspan="2" class="center section-head section-sep">Per Stock Card</th>
                    <th colspan="2" class="center section-head section-sep">Percount</th>
                    <th colspan="2" class="center section-head">Variances O/(U)</th>
                </tr>
                <tr>
                    <th class="section-col section-start col-sep">Name</th>
                    <th class="section-col section-sep">Code</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['item_name'] ?? '-' }}</td>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="number-right">{{ $fmtQty($row['stock_card_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['stock_card_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['percount_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['percount_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['variance_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['variance_amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">GRAND TOTAL</td>
                    <td class="number-right">{{ $fmtQty($totals['stock_card_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['stock_card_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['percount_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['percount_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['variance_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['variance_amount'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
