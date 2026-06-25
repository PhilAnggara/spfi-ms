@extends('pdf.layouts.analytical')

@section('header-meta')
    As of: <span class="nowrap-date">{{ $fmtDate($as_of) }}</span>
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="3" class="center section-head section-sep">Item</th>
                <th colspan="7" class="center section-head section-sep">Current Month</th>
                <th colspan="7" class="center section-head">Year to Date</th>
            </tr>
            <tr>
                <th class="section-col col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col section-sep">Unit</th>
                <th class="section-col section-start right">Qty</th>
                <th class="section-col right">IDR</th>
                <th class="section-col right">PHP</th>
                <th class="section-col right">EUR</th>
                <th class="section-col right">GBP</th>
                <th class="section-col right">USD</th>
                <th class="section-col section-sep right">YEN</th>
                <th class="section-col section-start right">Qty</th>
                <th class="section-col right">IDR</th>
                <th class="section-col right">PHP</th>
                <th class="section-col right">EUR</th>
                <th class="section-col right">GBP</th>
                <th class="section-col right">USD</th>
                <th class="section-col right">YEN</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['item_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="right">{{ $fmtQty($row['current_qty']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['IDR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['PHP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['EUR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['GBP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['USD']) }}</td>
                    <td class="right">{{ $fmtMoney($row['current_currency']['YEN']) }}</td>
                    <td class="right">{{ $fmtQty($row['ytd_qty']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['IDR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['PHP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['EUR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['GBP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['USD']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ytd_currency']['YEN']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="17" class="center muted">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
