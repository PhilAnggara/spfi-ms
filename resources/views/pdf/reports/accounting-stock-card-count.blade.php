@extends('pdf.layouts.analytical')

@section('header-meta')
    Month: {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}<br>
    Category: {{ $category }}
@endsection

@section('content')
    @php
        $fmtNullableMoney = fn ($value) => $value === null ? '' : $fmtMoney($value);
        $fmtNullableQty = fn ($value) => $value === null ? '' : $fmtQty($value);
        $totals = $totals ?? [];
    @endphp

    @if ($rows->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No stock card per count records found for the selected period.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th colspan="2" class="center section-head section-sep">Item</th>
                    <th colspan="2" class="center section-head section-sep">Per Stock Card</th>
                    <th colspan="2" class="center section-head section-sep">Percount</th>
                    <th colspan="2" class="center section-head">Variances O/(U)</th>
                </tr>
                <tr>
                    <th class="section-col section-start col-sep">Name</th>
                    <th class="section-col section-sep">Code</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col section-sep right">Amount</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-wrap-max">{{ $row['item_name'] ?? '-' }}</td>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="right">{{ $fmtQty($row['stock_card_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['stock_card_amount']) }}</td>
                        <td class="right">{{ $fmtNullableQty($row['percount_qty']) }}</td>
                        <td class="right">{{ $fmtNullableMoney($row['percount_amount']) }}</td>
                        <td class="right">{{ $fmtNullableQty($row['variance_qty']) }}</td>
                        <td class="right">{{ $fmtNullableMoney($row['variance_amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2" class="bold">GRAND TOTAL</td>
                    <td class="right bold">{{ $fmtQty($totals['stock_card_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['stock_card_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['percount_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['percount_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['variance_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['variance_amount'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
