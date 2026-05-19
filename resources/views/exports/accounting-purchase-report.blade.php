<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; }
        th { background: #f0f0f0; text-align: center; font-weight: bold; }
        .no-border td { border: none; padding: 2px 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');
    @endphp

    <table>
        <tr class="no-border">
            <td colspan="11" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="11" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="11">Period {{ $fmtDate($date_from) }} - {{ $fmtDate($date_to) }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="11">Category: {{ $category }}</td>
        </tr>
        <tr class="no-border"><td colspan="11">&nbsp;</td></tr>
        <tr>
            <th>Supplier Name</th>
            <th>PO Number</th>
            <th>RR Number</th>
            <th>Date</th>
            <th>Currency</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>UoM</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Amount</th>
        </tr>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['supplier_name'] }}</td>
                <td>{{ $row['po_number'] }}</td>
                <td>{{ $row['rr_number'] }}</td>
                <td>{{ $fmtDate($row['date']) }}</td>
                <td class="center">{{ $row['currency'] }}</td>
                <td>{{ $row['item_code'] }}</td>
                <td>{{ $row['item_name'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                <td class="right">{{ $fmtMoney($row['unit_price']) }}</td>
                <td class="right">{{ $fmtMoney($row['amount']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="center">No data available.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="10" class="right bold">Grand Total</td>
            <td class="right bold">{{ $fmtMoney($total_amount) }}</td>
        </tr>
    </table>
</body>
</html>
