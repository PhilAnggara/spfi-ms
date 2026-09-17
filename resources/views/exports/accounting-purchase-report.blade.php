<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 8.5px; color: #111; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 3px; }
        th, td { border: none; padding: 2px 5px; vertical-align: top; }
        th { font-size: 7.5px; text-align: left; font-weight: bold; text-transform: uppercase; border-bottom: 1.5px solid #374151; padding-bottom: 4px; }
        .section-col { border-bottom: 1.5px solid #374151; padding-bottom: 4px; }
        .col-sep { border-right: 7px solid #fff; padding-right: 2px; }
        .no-border td { padding: 1px 0; border-bottom: none; }
        .center { text-align: center; }
        .right { text-align: right; }
        th.right { text-align: right; }
        th.center { text-align: center; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
        $fmtQty = fn ($value) => \App\Support\PdfFormatters::qty($value);
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
            <th class="section-col col-sep">Supplier Name</th>
            <th class="section-col col-sep">PO Number</th>
            <th class="section-col col-sep">RR Number</th>
            <th class="section-col col-sep">Date</th>
            <th class="section-col col-sep center">Currency</th>
            <th class="section-col col-sep">Item Code</th>
            <th class="section-col col-sep">Item Name</th>
            <th class="section-col col-sep">UoM</th>
            <th class="section-col col-sep right">Quantity</th>
            <th class="section-col col-sep right">Unit Price</th>
            <th class="section-col right">Amount</th>
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
            <td colspan="8" class="right bold">Grand Total</td>
            <td class="right bold">{{ $fmtQty($total_quantity) }}</td>
            <td></td>
            <td class="right bold">{{ $fmtMoney($total_amount) }}</td>
        </tr>
    </table>
</body>
</html>
