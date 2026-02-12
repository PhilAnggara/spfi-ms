<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; }
        .sheet { width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 4px 6px; }
        .no-border td { border: none; padding: 2px 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');
    @endphp

    <table class="sheet">
        <tr class="no-border">
            <td colspan="10" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="10" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="10">As Of {{ $fmtDate($as_of) }}</td>
        </tr>
        <tr class="no-border"><td colspan="10">&nbsp;</td></tr>
        <tr class="no-border">
            <td colspan="10">Canvasser Name: {{ $canvaser }}</td>
        </tr>
        <tr class="no-border"><td colspan="10">&nbsp;</td></tr>
        <tr>
            <th colspan="3" class="center">Purchase Requisition Slip</th>
            <th colspan="5" class="center">Item</th>
            <th colspan="2" class="center">Department</th>
        </tr>
        <tr>
            <th>ID</th>
            <th>Number</th>
            <th>Date</th>
            <th>Code</th>
            <th>Description</th>
            <th>Stock on Hand</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Code</th>
            <th>Name</th>
        </tr>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['prs_id'] }}</td>
                <td>{{ $row['prs_number'] }}</td>
                <td>{{ $fmtDate($row['prs_date']) }}</td>
                <td>{{ $row['item_code'] }}</td>
                <td>{{ $row['item_name'] }}</td>
                <td class="right">{{ $fmtQty($row['stock_on_hand']) }}</td>
                <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                <td>{{ $row['unit'] }}</td>
                <td>{{ $row['department_code'] }}</td>
                <td>{{ $row['department_name'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="center">No data available.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>
