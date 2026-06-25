@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span>
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="3" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th colspan="2" class="center section-head section-sep">Department</th>
                <th colspan="4" class="center section-head section-sep">Purchase Order</th>
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
                <th class="section-col col-sep">ID</th>
                <th class="section-col col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col section-sep">Name</th>
                <th class="section-col section-start col-sep">Number</th>
                <th class="section-col col-sep">Date</th>
                <th class="section-col col-sep">Curr</th>
                <th class="section-col section-sep">Supplier</th>
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
                    <td>{{ $row['prs_id'] }}</td>
                    <td>{{ $row['prs_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['prs_date']) }}</td>
                    <td>{{ $row['department_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['department_name'] }}</td>
                    <td>{{ $row['po_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['po_date']) }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td class="text-wrap-max">{{ $row['supplier'] }}</td>
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
                    <td colspan="26" class="center muted">No data available.</td>
                </tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="total-row">
                    <td colspan="17" class="bold">G R A N D   T O T A L</td>
                    <td class="right bold">{{ $fmtMoney($totals['IDR'] + $totals['PHP'] + $totals['EUR'] + $totals['GBP'] + $totals['USD'] + $totals['YEN']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['IDR']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['PHP']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['EUR']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['GBP']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['USD']) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['YEN']) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
