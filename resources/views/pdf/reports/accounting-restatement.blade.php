@extends('pdf.layouts.analytical')

@section('header-meta')
    Month: {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}<br>
    Category: {{ $category }}
@endsection

@push('styles')
<style>
    .data-table th, .data-table td { font-size: 6.5px; padding: 1.5px 2px; }
</style>
@endpush

@section('content')
    @php
        $fmtNullableMoney = fn ($value) => $value === null ? '' : $fmtMoney($value);
        $fmtNullableQty = fn ($value) => $value === null ? '' : $fmtQty($value);
        $totals = $totals ?? [];
    @endphp

    @if ($rows->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No restatement records found for the selected period.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th colspan="2" class="center section-head section-sep">Item</th>
                    <th colspan="3" class="center section-head section-sep">Beg. Inventory</th>
                    <th colspan="3" class="center section-head section-sep">Purchases</th>
                    <th colspan="3" class="center section-head section-sep">Issuances</th>
                    <th colspan="3" class="center section-head section-sep">End. Inv. Theoretical</th>
                    <th colspan="2" class="center section-head section-sep">End. Inv. Percount</th>
                    <th colspan="2" class="center section-head section-sep">Variances O/(U)</th>
                    <th colspan="2" class="center section-head">Total</th>
                </tr>
                <tr>
                    <th class="section-col section-start col-sep">Name</th>
                    <th class="section-col section-sep">Code</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col col-sep right">U/C</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">IM</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col section-sep right">Amt</th>
                    <th class="section-col section-start col-sep right">Qty</th>
                    <th class="section-col right">Amt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-wrap-max">{{ $row['item_name'] ?? '-' }}</td>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="right">{{ $fmtQty($row['beg_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['beg_unit_cost']) }}</td>
                        <td class="right">{{ $fmtMoney($row['beg_amount']) }}</td>
                        <td class="right">{{ $fmtQty($row['purchase_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['purchase_unit_cost']) }}</td>
                        <td class="right">{{ $fmtMoney($row['purchase_amount']) }}</td>
                        <td class="right">{{ $fmtQty($row['issuance_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['issuance_unit_cost']) }}</td>
                        <td class="right">{{ $fmtMoney($row['issuance_amount']) }}</td>
                        <td class="right">{{ $fmtQty($row['end_theoretical_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['end_theoretical_unit_cost']) }}</td>
                        <td class="right">{{ $fmtMoney($row['end_theoretical_amount']) }}</td>
                        <td class="right">{{ $fmtNullableQty($row['percount_qty']) }}</td>
                        <td class="right">{{ $fmtNullableMoney($row['percount_amount']) }}</td>
                        <td class="right">{{ $fmtNullableQty($row['variance_qty']) }}</td>
                        <td class="right">{{ $fmtNullableMoney($row['variance_amount']) }}</td>
                        <td class="right">{{ $fmtQty($row['total_qty']) }}</td>
                        <td class="right">{{ $fmtMoney($row['total_amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2" class="bold">GRAND TOTAL</td>
                    <td class="right bold">{{ $fmtQty($totals['beg_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($totals['beg_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['purchase_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($totals['purchase_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['issuance_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($totals['issuance_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['end_theoretical_qty'] ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($totals['end_theoretical_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['percount_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['percount_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['variance_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['variance_amount'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtQty($totals['total_qty'] ?? 0) }}</td>
                    <td class="right bold">{{ $fmtMoney($totals['total_amount'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
