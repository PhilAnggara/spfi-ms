@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Department: {{ $department }}
@endsection

@push('styles')
<style>
    .signatures {
        width: 100%;
        margin-top: 12mm;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    .signatures td {
        width: 33.33%;
        text-align: center;
        vertical-align: top;
        padding: 0 8px;
        border: none;
    }
    .signatures .signature-label { font-size: 8px; font-weight: bold; margin-bottom: 2mm; }
    .signatures .signature-pad { height: 12mm; }
    .signatures .signature-name { font-size: 8px; margin-bottom: 2px; }
    .signatures .signature-line { border-top: 1px solid #111; margin: 0 auto 3px; width: 70%; }
    .signatures .signature-title { font-size: 7.5px; margin-top: 2px; }
</style>
@endpush

@section('content')
    @php
        $fmtTableDate = fn (mixed $value) => \App\Support\PdfFormatters::tableDate($value);
    @endphp

    @if ($rows->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No stores withdrawal records found for the selected filters.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th colspan="2" class="center section-head section-sep">Stores Withdrawal Slip</th>
                    <th class="center section-head section-sep"></th>
                    <th colspan="3" class="center section-head section-sep">Item</th>
                    <th colspan="2" class="center section-head section-sep">Quantity</th>
                    <th class="center section-head section-sep"></th>
                    <th class="center section-head"></th>
                </tr>
                <tr>
                    <th class="section-col col-sep">Number</th>
                    <th class="section-col section-sep">Date</th>
                    <th class="section-col section-start section-sep">Department</th>
                    <th class="section-col section-start col-sep">Code</th>
                    <th class="section-col col-sep">Description</th>
                    <th class="section-col section-sep">Unit</th>
                    <th class="section-col section-start col-sep right">Stock On Hand</th>
                    <th class="section-col section-sep right">Request</th>
                    <th class="section-col section-start section-sep">Creator</th>
                    <th class="section-col section-start">Info</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['sws_number'] }}</td>
                        <td class="nowrap-date">{{ $fmtTableDate($row['sws_date']) }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                        <td>{{ $row['unit'] ?? '-' }}</td>
                        <td class="right">{{ $fmtQty($row['stock_on_hand']) }}</td>
                        <td class="right">{{ $fmtQty($row['request_qty']) }}</td>
                        <td>{{ $row['creator'] }}</td>
                        <td class="text-wrap-max">{{ $row['info'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-label">Prepared by</div>
                <div class="signature-pad"></div>
                <div class="signature-name">{{ $prepared_by_name }}</div>
                <div class="signature-line"></div>
                <div class="signature-title">{{ $prepared_by_title }}</div>
            </td>
            <td>
                <div class="signature-label">Checked by</div>
                <div class="signature-pad"></div>
                <div class="signature-name">{{ $checked_by_name }}</div>
                <div class="signature-line"></div>
                <div class="signature-title">{{ $checked_by_title }}</div>
            </td>
            <td>
                <div class="signature-label">Approved by</div>
                <div class="signature-pad"></div>
                <div class="signature-name">{{ $approved_by_name }}</div>
                <div class="signature-line"></div>
                <div class="signature-title">{{ $approved_by_title }}</div>
            </td>
        </tr>
    </table>
@endsection
