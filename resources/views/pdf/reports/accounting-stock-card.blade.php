@extends('pdf.layouts.analytical')

@section('header-meta')
    Month: {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}<br>
    Category: {{ $category }}
@endsection

@section('content')
    @if ($rows->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No stock card records found for the selected period.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="section-col col-sep">Code</th>
                    <th class="section-col col-sep">Item Description</th>
                    <th class="section-col col-sep">Unit</th>
                    <th class="section-col col-sep right">Qty</th>
                    <th class="section-col col-sep right">Unit Cost</th>
                    <th class="section-col col-sep right">Amount</th>
                    <th class="section-col col-sep right">Beginning</th>
                    <th class="section-col right">Transaction</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="text-wrap-max">{{ $row['item_description'] ?? '-' }}</td>
                        <td>{{ $row['unit'] ?? '-' }}</td>
                        <td class="right">{{ $fmtQty($row['qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['unit_cost']) }}</td>
                        <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                        <td class="right">{{ $fmtMoney($row['beginning_amount']) }}</td>
                        <td class="right">{{ $fmtMoney($row['transaction']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="bold">GRAND TOTAL</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('qty')) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($rows->sum('amount')) }}</td>
                    <td class="right bold">{{ $fmtMoney($rows->sum('beginning_amount')) }}</td>
                    <td class="right bold">{{ $fmtMoney($rows->sum('transaction')) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
