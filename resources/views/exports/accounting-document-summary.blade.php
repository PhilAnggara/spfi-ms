<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 5px 6px; vertical-align: top; }
        th { background: #f0f0f0; text-align: center; font-weight: bold; }
        .no-border td { border: none; padding: 2px 0; }
        .section-title td { background: #e8e8e8; font-weight: bold; }
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
            <td colspan="3" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="3" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="3">Period {{ $fmtDate($date_from) }} - {{ $fmtDate($date_to) }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="3">Category: {{ $category }}</td>
        </tr>
    </table>

    @foreach ($groups as $group)
        <table>
            <tr class="section-title">
                <td colspan="3">{{ $group['type'] }} - {{ $group['title'] }}</td>
            </tr>
            <tr>
                <th style="width: 35%;">Number</th>
                <th style="width: 25%;">Date</th>
                <th style="width: 40%;">Amount</th>
            </tr>
            @forelse ($group['rows'] as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td class="center">{{ $fmtDate($row['date']) }}</td>
                    <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center">No data available.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="2" class="right bold">Total {{ $group['type'] }}</td>
                <td class="right bold">{{ $fmtMoney($group['total']) }}</td>
            </tr>
        </table>
    @endforeach

    <table>
        <tr>
            <td colspan="2" class="right bold">Grand Total</td>
            <td class="right bold">{{ $fmtMoney($grand_total) }}</td>
        </tr>
    </table>
</body>
</html>
