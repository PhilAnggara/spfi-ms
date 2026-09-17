@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Category: {{ $category }}
@endsection

@push('styles')
<style>
    .item-title td {
        font-weight: bold;
        font-size: 8px;
        padding-top: 7px;
        padding-bottom: 3px;
        border-bottom: 1px solid #d1d5db;
    }
    .item-title .item-code {
        white-space: nowrap;
        padding-right: 8px;
    }
    .movement-row td {
        padding-top: 2px;
        padding-bottom: 2px;
    }
    .summary-block td {
        font-size: 7.5px;
        color: #374151;
        padding-top: 2px;
        padding-bottom: 2px;
    }
    .summary-label {
        padding-left: 12px !important;
    }
    .summary-head td {
        padding-top: 5px;
        font-style: italic;
        color: #6b7280;
        border-top: none;
    }
    .net-row td {
        font-weight: bold;
        font-size: 7.5px;
        padding-top: 3px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
    }
    .grand-gap td {
        height: 8px;
        padding: 0;
        border: none !important;
    }
    .grand-row td {
        font-weight: bold;
        border-top: 1.5px solid #111;
        padding-top: 5px;
    }
</style>
@endpush

@section('content')
    @php
        $fmtTableDate = fn (mixed $value) => \App\Support\PdfFormatters::tableDate($value);
    @endphp

    @if ($groups->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No transaction records found for the selected filters.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="section-col col-sep" style="width: 12%;">Date</th>
                    <th class="section-col col-sep" style="width: 8%;">Type</th>
                    <th class="section-col col-sep" style="width: 16%;">Document No.</th>
                    <th class="section-col col-sep right" style="width: 12%;">Qty</th>
                    <th class="section-col col-sep right" style="width: 14%;">Unit Cost</th>
                    <th class="section-col col-sep right" style="width: 14%;">Amount</th>
                    <th class="section-col right" style="width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group)
                    <tr class="item-title">
                        <td colspan="7">
                            <span class="item-code">{{ $group['item_code'] }}</span>
                            {{ $group['item_name'] ?? '-' }}
                            @if (! empty($group['unit']))
                                <span class="muted">({{ $group['unit'] }})</span>
                            @endif
                        </td>
                    </tr>

                    @foreach ($group['rows'] as $row)
                        <tr class="movement-row">
                            <td class="nowrap-date">{{ $fmtTableDate($row['doc_date']) }}</td>
                            <td>{{ $row['doc_type'] }}</td>
                            <td>{{ $row['doc_number'] }}</td>
                            <td class="right">{{ $fmtQty($row['qty']) }}</td>
                            <td class="right">{{ $fmtMoney($row['unit_cost']) }}</td>
                            <td class="right">{{ $fmtMoney($row['amount']) }}</td>
                            <td class="right">{{ $fmtQty($row['balance']) }}</td>
                        </tr>
                    @endforeach

                    <tr class="summary-block summary-head">
                        <td colspan="7" class="summary-label">Document summary</td>
                    </tr>
                    @foreach ($group['document_summary'] as $summary)
                        <tr class="summary-block">
                            <td colspan="2"></td>
                            <td class="summary-label">Total {{ $summary['doc_type'] }}</td>
                            <td class="right">{{ $fmtQty($summary['qty']) }}</td>
                            <td></td>
                            <td class="right">{{ $fmtMoney($summary['amount']) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    <tr class="net-row">
                        <td colspan="2"></td>
                        <td class="summary-label">Total RR - TS</td>
                        <td class="right">{{ $fmtQty($group['rr_minus_ts_qty'] ?? 0) }}</td>
                        <td></td>
                        <td class="right">{{ $fmtMoney($group['rr_minus_ts_amount'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                @endforeach

                <tr class="grand-gap"><td colspan="7">&nbsp;</td></tr>
                <tr class="grand-row">
                    <td colspan="3" class="bold">GRAND TOTAL</td>
                    <td class="right bold">{{ $fmtQty($grand_total_qty ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($grand_total_amount ?? 0) }}</td>
                    <td></td>
                </tr>
                <tr class="grand-row">
                    <td colspan="3" class="bold">GRAND TOTAL (RR - TS)</td>
                    <td class="right bold">{{ $fmtQty($grand_rr_minus_ts_qty ?? 0) }}</td>
                    <td></td>
                    <td class="right bold">{{ $fmtMoney($grand_rr_minus_ts_amount ?? 0) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
