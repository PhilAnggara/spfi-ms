<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9.5px; color: #111; }
        .sheet { width: 100%; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 3px; }
        td, th { border: none; padding: 2px 7px; }
        th { font-size: 8.5px; font-weight: bold; text-align: left; text-transform: uppercase; }
        .no-border td { padding: 1px 0; }
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
            <td colspan="10">Canvasser Name: {{ $canvasser }}</td>
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
            <th class="right">Stock on Hand</th>
            <th class="right">Quantity</th>
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
