@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Category: {{ $category }}
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th class="section-col col-sep">Supplier</th>
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
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-wrap-max">{{ $row['supplier_name'] }}</td>
                    <td>{{ $row['po_number'] }}</td>
                    <td>{{ $row['rr_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['date']) }}</td>
                    <td class="center">{{ $row['currency'] }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                    <td class="right">{{ $fmtMoney($row['unit_price']) }}</td>
                    <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="center muted">No data available.</td>
                </tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="total-row">
                    <td colspan="8" class="right bold">Grand Total</td>
                    <td class="right bold">{{ $fmtQty($total_quantity) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($total_amount) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
