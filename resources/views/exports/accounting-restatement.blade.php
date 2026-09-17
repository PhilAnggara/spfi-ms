<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 7.5px; line-height: 1.3; margin: 12px 16px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 8px; }
        th, td { border: none; padding: 2px 3px; text-align: left; vertical-align: middle; }
        th { font-size: 6.5px; font-weight: bold; text-transform: uppercase; }
        .header { text-align: center; margin-bottom: 12px; }
        .company-name { font-size: 11px; font-weight: bold; }
        .report-title { font-size: 12px; font-weight: bold; margin-top: 4px; }
        .report-info { font-size: 7.5px; margin-top: 4px; }
        .number-right, th.number-right, .right { text-align: right; }
        .center { text-align: center; }
        .section-head { border-bottom: 1.5px solid #374151; padding-bottom: 4px; text-align: center; }
        .section-col { border-bottom: 1.5px solid #374151; padding-bottom: 4px; }
        .col-sep { border-right: 7px solid #fff; padding-right: 2px; }
        .section-sep { border-right: 18px solid #fff; padding-right: 4px; }
        .section-start { padding-left: 4px; }
        .total-row td { font-weight: bold; border-top: 2px solid #111; padding-top: 5px; }
        .footer { margin-top: 16px; font-size: 8px; text-align: center; color: #666; }
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
        <p style="text-align: center; margin-top: 20px;">No restatement records found for the selected period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th colspan="2" class="center section-head section-sep">Item</th>
                    <th colspan="3" class="center section-head section-sep">Beg. Inventory</th>
                    <th colspan="3" class="center section-head section-sep">Purchases</th>
                    <th colspan="3" class="center section-head section-sep">Issuances</th>
                    <th colspan="3" class="center section-head section-sep">End. Inv. Theoretical</th>
                    <th colspan="2" class="center section-head section-sep">End. Inv. Percount</th>
                    <th colspan="2" class="center section-head section-sep">Variances O/(U)</th>
                    <th colspan="2" class="center section-head">Total</th>
                </tr>
                <tr>
                    <th class="section-col section-start col-sep">Name</th>
                    <th class="section-col section-sep">Code</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">IM</th>
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
                        <td class="number-right">{{ $fmtQty($row['beg_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['beg_unit_cost']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['beg_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['purchase_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['purchase_unit_cost']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['purchase_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['issuance_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['issuance_unit_cost']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['issuance_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['end_theoretical_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['end_theoretical_unit_cost']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['end_theoretical_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['percount_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['percount_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['variance_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['variance_amount']) }}</td>
                        <td class="number-right">{{ $fmtQty($row['total_qty']) }}</td>
                        <td class="number-right">{{ $fmtMoney($row['total_amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">GRAND TOTAL</td>
                    <td class="number-right">{{ $fmtQty($totals['beg_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($totals['beg_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['purchase_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($totals['purchase_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['issuance_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($totals['issuance_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['end_theoretical_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($totals['end_theoretical_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['percount_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['percount_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['variance_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['variance_amount'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtQty($totals['total_qty'] ?? 0) }}</td>
                    <td class="number-right">{{ $fmtMoney($totals['total_amount'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
