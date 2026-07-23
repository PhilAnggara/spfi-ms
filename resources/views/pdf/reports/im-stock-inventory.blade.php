@extends('pdf.layouts.analytical')

@section('header-meta')
    As of: <span class="nowrap-date">{{ $fmtDate($as_of) }}</span><br>
    Category: {{ $category }}
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
    .signatures .signature-label {
        font-size: 8px;
        font-weight: bold;
        margin-bottom: 2mm;
    }
    .signatures .signature-pad {
        height: 12mm;
    }
    .signatures .signature-name {
        font-size: 8px;
        margin-bottom: 2px;
    }
    .signatures .signature-line {
        border-top: 1px solid #111;
        margin: 0 auto 3px;
        width: 70%;
    }
    .signatures .signature-title {
        font-size: 7.5px;
        margin-top: 2px;
    }
</style>
@endpush

@section('content')
    @if ($rows->isEmpty())
        <p class="center muted" style="margin-top: 8mm;">No stock inventory records found for the selected filters.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th colspan="3" class="center section-head section-sep">Item</th>
                    <th colspan="1" class="center section-head section-sep">Beginning</th>
                    <th colspan="1" class="center section-head section-sep">Receipt</th>
                    <th colspan="2" class="center section-head section-sep">Issuances</th>
                    <th colspan="1" class="center section-head">Ending</th>
                </tr>
                <tr>
                    <th class="section-col col-sep">Name</th>
                    <th class="section-col col-sep">Code</th>
                    <th class="section-col section-sep">Unit</th>
                    <th class="section-col section-start section-sep right">Balance</th>
                    <th class="section-col section-start section-sep right">RR</th>
                    <th class="section-col section-start col-sep right">TS</th>
                    <th class="section-col section-sep right">DR</th>
                    <th class="section-col section-start right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-wrap-max">{{ $row['name'] }}</td>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['unit'] ?? '-' }}</td>
                        <td class="right">{{ $fmtQty($row['beginning']) }}</td>
                        <td class="right">{{ $fmtQty($row['rr']) }}</td>
                        <td class="right">{{ $fmtQty($row['ts']) }}</td>
                        <td class="right">{{ $fmtQty($row['dr']) }}</td>
                        <td class="right">{{ $fmtQty($row['ending']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="bold">GRAND TOTAL</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('beginning')) }}</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('rr')) }}</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('ts')) }}</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('dr')) }}</td>
                    <td class="right bold">{{ $fmtQty($rows->sum('ending')) }}</td>
                </tr>
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
