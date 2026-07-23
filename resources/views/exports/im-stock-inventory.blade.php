<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 8.5px; color: #111; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 3px; }
        td, th { border: none; padding: 2px 5px; }
        th { font-size: 7.5px; font-weight: bold; text-align: left; text-transform: uppercase; }
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

    <table>
        <tr class="no-border">
            <td colspan="8" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="8" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="8">As of {{ $fmtDate($as_of) }} | Category: {{ $category }}</td>
        </tr>
        <tr class="no-border"><td colspan="8">&nbsp;</td></tr>
        <tr>
            <th colspan="3" class="center">Item</th>
            <th class="center">Beginning</th>
            <th class="center">Receipt</th>
            <th colspan="2" class="center">Issuances</th>
            <th class="center">Ending</th>
        </tr>
        <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Unit</th>
            <th class="right">Balance</th>
            <th class="right">RR</th>
            <th class="right">TS</th>
            <th class="right">DR</th>
            <th class="right">Balance</th>
        </tr>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['unit'] ?? '-' }}</td>
                <td class="right">{{ $fmtQty($row['beginning']) }}</td>
                <td class="right">{{ $fmtQty($row['rr']) }}</td>
                <td class="right">{{ $fmtQty($row['ts']) }}</td>
                <td class="right">{{ $fmtQty($row['dr']) }}</td>
                <td class="right">{{ $fmtQty($row['ending']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">No stock inventory records found for the selected filters.</td>
            </tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr>
                <td colspan="3" class="bold">GRAND TOTAL</td>
                <td class="right bold">{{ $fmtQty($rows->sum('beginning')) }}</td>
                <td class="right bold">{{ $fmtQty($rows->sum('rr')) }}</td>
                <td class="right bold">{{ $fmtQty($rows->sum('ts')) }}</td>
                <td class="right bold">{{ $fmtQty($rows->sum('dr')) }}</td>
                <td class="right bold">{{ $fmtQty($rows->sum('ending')) }}</td>
            </tr>
        @endif
        <tr class="no-border"><td colspan="8">&nbsp;</td></tr>
        <tr class="no-border"><td colspan="8">&nbsp;</td></tr>
        <tr class="no-border">
            <td colspan="3" class="center bold">Prepared by</td>
            <td colspan="2" class="center bold">Checked by</td>
            <td colspan="3" class="center bold">Approved by</td>
        </tr>
        <tr class="no-border"><td colspan="8">&nbsp;</td></tr>
        <tr class="no-border"><td colspan="8">&nbsp;</td></tr>
        <tr class="no-border">
            <td colspan="3" class="center">{{ $prepared_by_name }}</td>
            <td colspan="2" class="center">{{ $checked_by_name }}</td>
            <td colspan="3" class="center">{{ $approved_by_name }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="3" class="center">_______________________</td>
            <td colspan="2" class="center">_______________________</td>
            <td colspan="3" class="center">_______________________</td>
        </tr>
        <tr class="no-border">
            <td colspan="3" class="center">{{ $prepared_by_title }}</td>
            <td colspan="2" class="center">{{ $checked_by_title }}</td>
            <td colspan="3" class="center">{{ $approved_by_title }}</td>
        </tr>
    </table>
</body>
</html>
