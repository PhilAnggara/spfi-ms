<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 12px;
            font-weight: bold;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 5px;
        }
        .report-info {
            font-size: 10px;
            margin-top: 5px;
            display: flex;
            justify-content: center;
            gap: 30px;
        }
        .number-right {
            text-align: right;
        }
        .footer {
            margin-top: 20px;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="report-info">
            <div><strong>Month:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</div>
            <div><strong>Category:</strong> {{ $category }}</div>
        </div>
    </div>

    @if($rows->isEmpty())
        <p style="text-align: center; margin-top: 20px;">No stock card records found for the selected period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Code</th>
                    <th style="width: 35%;">Item Description</th>
                    <th style="width: 10%;">Unit</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 10%;">Unit Cost</th>
                    <th style="width: 10%;">Amount</th>
                    <th style="width: 10%;">Beginning</th>
                    <th style="width: 10%;">Transaction</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['item_code'] }}</td>
                        <td>{{ $row['item_description'] ?? '-' }}</td>
                        <td>{{ $row['unit'] ?? '-' }}</td>
                        <td class="number-right">{{ number_format($row['qty'], 2) }}</td>
                        <td class="number-right">{{ number_format($row['unit_cost'], 2) }}</td>
                        <td class="number-right">{{ number_format($row['amount'], 2) }}</td>
                        <td class="number-right">{{ number_format($row['beginning_amount'], 2) }}</td>
                        <td class="number-right">{{ number_format($row['transaction'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
