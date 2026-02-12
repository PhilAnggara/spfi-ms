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
            <td colspan="26" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="26" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="26">Period {{ $fmtDate($date_from) }} - {{ $fmtDate($date_to) }}</td>
        </tr>
        <tr class="no-border"><td colspan="26">&nbsp;</td></tr>
        <tr>
            <th colspan="3" class="center">Purchase Requisition Slip</th>
            <th colspan="2" class="center">Department</th>
            <th colspan="4" class="center">Purchase Order</th>
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
            <th>ID</th>
            <th>Number</th>
            <th>Date</th>
            <th>Code</th>
            <th>Name</th>
            <th>Number</th>
            <th>Date</th>
            <th>Curr</th>
            <th>Supplier</th>
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
                <td>{{ $row['prs_id'] }}</td>
                <td>{{ $row['prs_number'] }}</td>
                <td>{{ $fmtDate($row['prs_date']) }}</td>
                <td>{{ $row['department_code'] }}</td>
                <td>{{ $row['department_name'] }}</td>
                <td>{{ $row['po_number'] }}</td>
                <td>{{ $fmtDate($row['po_date']) }}</td>
                <td>{{ $row['currency'] }}</td>
                <td>{{ $row['supplier'] }}</td>
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
                <td colspan="26" class="center">No data available.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="17" class="bold">G R A N D   T O T A L</td>
            <td class="right bold">{{ $fmtMoney($totals['IDR'] + $totals['PHP'] + $totals['EUR'] + $totals['GBP'] + $totals['USD'] + $totals['YEN']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['IDR']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['PHP']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['EUR']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['GBP']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['USD']) }}</td>
            <td class="right bold">{{ $fmtMoney($totals['YEN']) }}</td>
            <td colspan="2"></td>
        </tr>
    </table>
</body>
</html>
