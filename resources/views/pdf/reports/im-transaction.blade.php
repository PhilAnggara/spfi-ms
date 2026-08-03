@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
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
    .item-cell {
        font-weight: bold;
        vertical-align: top;
        padding-top: 3px;
    }
    .item-group-start td {
        border-top: 1px solid #d1d5db;
        padding-top: 4px;
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
                    <th class="section-col col-sep">Item Code</th>
                    <th class="section-col section-sep">Item Name</th>
                    <th class="section-col section-start col-sep">Date</th>
                    <th class="section-col col-sep">Type</th>
                    <th class="section-col col-sep">Document No.</th>
                    <th class="section-col col-sep right">Quantity</th>
                    <th class="section-col">Unit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group)
                    @foreach ($group['rows'] as $index => $row)
                        <tr @class(['item-group-start' => $index === 0])>
                            {{-- Always emit item cells; never use rowspan (DomPDF drops it across page breaks and shifts Date left). --}}
                            <td class="item-cell">{{ $index === 0 ? $group['item_code'] : '' }}</td>
                            <td class="item-cell text-wrap-max">{{ $index === 0 ? $group['item_name'] : '' }}</td>
                            <td class="nowrap-date">{{ $fmtTableDate($row['doc_date']) }}</td>
                            <td>{{ $row['doc_type'] }}</td>
                            <td>{{ $row['doc_number'] }}</td>
                            <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                            <td>{{ $group['unit'] ?? '-' }}</td>
                        </tr>
                    @endforeach
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
