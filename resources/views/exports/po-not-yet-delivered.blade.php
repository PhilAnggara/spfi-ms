<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; }
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
        $fmtMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtQty = fn ($value) => number_format((float) $value, 2, ',', '.');
    @endphp

    <table>
        <tr class="no-border">
            <td colspan="23" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="23" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="23">As Of {{ $fmtDate($as_of) }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="23">Canvasser Name: {{ $canvaser }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="23">PO Type: {{ strtoupper($po_type) }}</td>
        </tr>
        <tr class="no-border"><td colspan="23">&nbsp;</td></tr>
        <tr>
            <th colspan="4" class="center">Purchase Order</th>
            <th colspan="2" class="center">Supplier</th>
            <th colspan="4" class="center">Item</th>
            <th class="center">Unit Price</th>
            <th class="center">Disc</th>
            <th class="center">PPH</th>
            <th class="center">PPN</th>
            <th class="center">Amount</th>
            <th colspan="6" class="center">Currency</th>
            <th class="center">Canvasser Name</th>
            <th class="center">Remarks</th>
        </tr>
        <tr>
            <th>Number</th>
            <th>Date</th>
            <th>Type</th>
            <th>Curr</th>
            <th>Code</th>
            <th>Name</th>
            <th>Code</th>
            <th>Description</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th>IDR</th>
            <th>PHP</th>
            <th>EUR</th>
            <th>GBP</th>
            <th>USD</th>
            <th>YEN</th>
            <th></th>
            <th></th>
        </tr>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['po_number'] }}</td>
                <td>{{ $fmtDate($row['po_date']) }}</td>
                <td>{{ strtoupper($row['po_type'] ?? '') }}</td>
                <td>{{ $row['currency'] }}</td>
                <td>{{ $row['supplier_code'] }}</td>
                <td>{{ $row['supplier_name'] }}</td>
                <td>{{ $row['item_code'] }}</td>
                <td>{{ $row['item_name'] }}</td>
                <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                <td>{{ $row['unit'] }}</td>
                <td class="right">{{ $fmtMoney($row['unit_price']) }}</td>
                <td class="right">{{ $fmtMoney($row['discount']) }}</td>
                <td class="right">{{ $fmtMoney($row['pph']) }}</td>
                <td class="right">{{ $fmtMoney($row['ppn']) }}</td>
                <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['IDR']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['PHP']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['EUR']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['GBP']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['USD']) }}</td>
                <td class="right">{{ $fmtMoney($row['currency_buckets']['YEN']) }}</td>
                <td>{{ $row['canvaser'] }}</td>
                <td>{{ $row['remarks'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="23" class="center">No data available.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>
