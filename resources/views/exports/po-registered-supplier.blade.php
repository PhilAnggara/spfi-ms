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
    @endphp

    <table>
        <tr class="no-border">
            <td colspan="14" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="14" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="14">As of {{ $fmtDate($as_of) }}</td>
        </tr>
        <tr class="no-border"><td colspan="14">&nbsp;</td></tr>
        <tr>
            <th colspan="2" class="center">Item</th>
            <th colspan="6" class="center">Current Month</th>
            <th colspan="6" class="center">Year to Date</th>
        </tr>
        <tr>
            <th>Code</th>
            <th>Description</th>
            <th>IDR</th>
            <th>PHP</th>
            <th>EUR</th>
            <th>GBP</th>
            <th>USD</th>
            <th>YEN</th>
            <th>IDR</th>
            <th>PHP</th>
            <th>EUR</th>
            <th>GBP</th>
            <th>USD</th>
            <th>YEN</th>
        </tr>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['supplier_code'] }}</td>
                <td>{{ $row['supplier_name'] }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['IDR']) }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['PHP']) }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['EUR']) }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['GBP']) }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['USD']) }}</td>
                <td class="right">{{ $fmtMoney($row['current_currency']['YEN']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['IDR']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['PHP']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['EUR']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['GBP']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['USD']) }}</td>
                <td class="right">{{ $fmtMoney($row['ytd_currency']['YEN']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="center">No data available.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>
