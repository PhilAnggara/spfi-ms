@extends('pdf.layouts.analytical')

@section('header-meta')
    As of: <span class="nowrap-date">{{ $fmtDate($as_of) }}</span>
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2" class="center section-head section-sep">Supplier</th>
                <th colspan="6" class="center section-head section-sep">Current Month</th>
                <th colspan="6" class="center section-head">Year to Date</th>
            </tr>
            <tr>
                <th class="section-col col-sep">Code</th>
                <th class="section-col section-sep">Name</th>
                <th class="section-col section-start right">IDR</th>
                <th class="section-col right">PHP</th>
                <th class="section-col right">EUR</th>
                <th class="section-col right">GBP</th>
                <th class="section-col right">USD</th>
                <th class="section-col section-sep right">YEN</th>
                <th class="section-col section-start right">IDR</th>
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
                    <td>{{ $row['supplier_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['supplier_name'] }}</td>
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
                    <td colspan="14" class="center muted">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
