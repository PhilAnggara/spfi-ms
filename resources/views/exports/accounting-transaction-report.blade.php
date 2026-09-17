<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9px; line-height: 1.4; margin: 18px 24px; color: #111; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
        th, td { border: none; padding: 2px 6px; text-align: left; vertical-align: top; }
        th {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1.5px solid #374151;
            padding-bottom: 4px;
        }
        .header { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 12px; font-weight: bold; }
        .report-title { font-size: 13px; font-weight: bold; margin-top: 5px; }
        .report-info { font-size: 8px; margin-top: 5px; }
        .number-right, th.number-right { text-align: right; }
        .item-title td {
            font-weight: bold;
            font-size: 9px;
            padding-top: 10px;
            padding-bottom: 4px;
            border-bottom: 1px solid #d1d5db;
        }
        .item-code { padding-right: 10px; white-space: nowrap; }
        .muted { color: #6b7280; font-weight: normal; }
        .summary-head td {
            padding-top: 6px;
            font-style: italic;
            color: #6b7280;
            font-size: 8px;
        }
        .summary-row td {
            font-size: 8px;
            color: #374151;
            padding-top: 2px;
            padding-bottom: 2px;
        }
        .summary-label { padding-left: 14px; }
        .net-row td {
            font-weight: bold;
            font-size: 8px;
            padding-top: 4px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .grand-gap td { height: 10px; padding: 0; }
        .grand-row td {
            font-weight: bold;
            border-top: 1.5px solid #111;
            padding-top: 6px;
        }
        .footer { margin-top: 20px; font-size: 9px; text-align: center; color: #666; }
    </style>
</head>
<body>
    @php
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');
    @endphp

    <div class="header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="report-info">
            <div><strong>Period:</strong> {{ $fmtDate($date_from) }} - {{ $fmtDate($date_to) }}</div>
            <div><strong>Category:</strong> {{ $category }}</div>
        </div>
    </div>

    @if($groups->isEmpty())
        <p style="text-align: center; margin-top: 20px;">No transaction records found for the selected period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 8%;">Type</th>
                    <th style="width: 16%;">Document No.</th>
                    <th class="number-right" style="width: 12%;">Qty</th>
                    <th class="number-right" style="width: 14%;">Unit Cost</th>
                    <th class="number-right" style="width: 14%;">Amount</th>
                    <th class="number-right" style="width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groups as $group)
                    <tr class="item-title">
                        <td colspan="7">
                            <span class="item-code">{{ $group['item_code'] }}</span>
                            {{ $group['item_name'] ?? '-' }}
                            @if (! empty($group['unit']))
                                <span class="muted">({{ $group['unit'] }})</span>
                            @endif
                        </td>
                    </tr>

                    @foreach($group['rows'] as $row)
                        <tr>
                            <td>{{ $fmtDate($row['doc_date']) }}</td>
                            <td>{{ $row['doc_type'] }}</td>
                            <td>{{ $row['doc_number'] }}</td>
                            <td class="number-right">{{ $fmtQty($row['qty']) }}</td>
                            <td class="number-right">{{ $fmtMoney($row['unit_cost']) }}</td>
                            <td class="number-right">{{ $fmtMoney($row['amount']) }}</td>
                            <td class="number-right">{{ $fmtQty($row['balance']) }}</td>
                        </tr>
                    @endforeach

                    <tr class="summary-head">
                        <td colspan="7" class="summary-label">Document summary</td>
                    </tr>
                    @foreach($group['document_summary'] as $summary)
                        <tr class="summary-row">
                            <td colspan="2"></td>
                            <td class="summary-label">Total {{ $summary['doc_type'] }}</td>
                            <td class="number-right">{{ $fmtQty($summary['qty']) }}</td>
                            <td></td>
                            <td class="number-right">{{ $fmtMoney($summary['amount']) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    <tr class="net-row">
                        <td colspan="2"></td>
                        <td class="summary-label">Total RR - TS</td>
                        <td class="number-right">{{ $fmtQty($group['rr_minus_ts_qty'] ?? 0) }}</td>
                        <td></td>
                        <td class="number-right">{{ $fmtMoney($group['rr_minus_ts_amount'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                @endforeach

                <tr class="grand-gap"><td colspan="7">&nbsp;</td></tr>
                <tr class="grand-row">
                    <td colspan="3">GRAND TOTAL</td>
                    <td class="number-right">{{ $fmtQty($grand_total_qty ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($grand_total_amount ?? 0) }}</td>
                    <td></td>
                </tr>
                <tr class="grand-row">
                    <td colspan="3">GRAND TOTAL (RR - TS)</td>
                    <td class="number-right">{{ $fmtQty($grand_rr_minus_ts_qty ?? 0) }}</td>
                    <td></td>
                    <td class="number-right">{{ $fmtMoney($grand_rr_minus_ts_amount ?? 0) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
