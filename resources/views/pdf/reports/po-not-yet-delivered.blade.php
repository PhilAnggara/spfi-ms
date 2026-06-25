@extends('pdf.layouts.analytical')

@section('header-meta')
    As Of: <span class="nowrap-date">{{ $fmtDate($as_of) }}</span><br>
    Canvasser: {{ $canvasser }}<br>
    PO Type: {{ strtoupper($po_type) }}
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="4" class="center section-head section-sep">Purchase Order</th>
                <th colspan="2" class="center section-head section-sep">Supplier</th>
                <th colspan="4" class="center section-head section-sep">Item</th>
                <th class="center section-head col-sep">Unit Price</th>
                <th class="center section-head col-sep">Disc</th>
                <th class="center section-head col-sep">PPH</th>
                <th class="center section-head col-sep">PPN</th>
                <th class="center section-head col-sep">Amount</th>
                <th colspan="6" class="center section-head section-sep">Currency</th>
                <th class="center section-head col-sep">Canvasser</th>
                <th class="center section-head">Remarks</th>
            </tr>
            <tr>
                <th class="section-col col-sep">Number</th>
                <th class="section-col col-sep">Date</th>
                <th class="section-col col-sep">Type</th>
                <th class="section-col section-sep">Curr</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col section-sep">Name</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col col-sep right">Qty</th>
                <th class="section-col section-sep">Unit</th>
                <th class="section-col"></th>
                <th class="section-col"></th>
                <th class="section-col"></th>
                <th class="section-col"></th>
                <th class="section-col"></th>
                <th class="section-col right">IDR</th>
                <th class="section-col right">PHP</th>
                <th class="section-col right">EUR</th>
                <th class="section-col right">GBP</th>
                <th class="section-col right">USD</th>
                <th class="section-col section-sep right">YEN</th>
                <th class="section-col"></th>
                <th class="section-col"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['po_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['po_date']) }}</td>
                    <td>{{ strtoupper($row['po_type'] ?? '') }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ $row['supplier_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['supplier_name'] }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                    <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td class="right">{{ $fmtMoney($row['unit_price']) }}</td>
                    <td class="right">{{ $fmtMoney($row['discount']) }}</td>
                    <td class="right">{{ $fmtMoney($row['pph']) }}</td>
                    <td class="right">{{ $fmtMoney($row['ppn']) }}</td>
                    <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['IDR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['PHP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['EUR']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['GBP']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['USD']) }}</td>
                    <td class="right">{{ $fmtMoney($row['currency_buckets']['YEN']) }}</td>
                    <td>{{ $row['canvasser'] }}</td>
                    <td class="text-wrap-max">{{ $row['remarks'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="23" class="center muted">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
