@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Category: {{ $category }}
@endsection

@section('content')
    @foreach ($groups as $group)
        <table class="data-table" style="margin-bottom: 4mm;">
            <tbody>
                <tr class="group-title">
                    <td colspan="3">{{ $group['type'] }} - {{ $group['title'] }}</td>
                </tr>
                <tr>
                    <th class="section-col" style="width: 35%;">Number</th>
                    <th class="section-col center" style="width: 25%;">Date</th>
                    <th class="section-col right" style="width: 40%;">Amount</th>
                </tr>
                @forelse ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td class="center nowrap-date">{{ $fmtDate($row['date']) }}</td>
                        <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="center muted">No data available.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="2" class="right bold">Total {{ $group['type'] }}</td>
                    <td class="right bold">{{ $fmtMoney($group['total']) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table class="data-table">
        <tbody>
            <tr class="total-row">
                <td colspan="2" class="right bold" style="width: 60%;">Grand Total</td>
                <td class="right bold">{{ $fmtMoney($grand_total) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
