@extends('pdf.layouts.analytical')

@section('header-meta')
    Period (RR date): <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Canvasser: {{ $canvasser }}<br>
    <span class="muted">Lead Time (days) = RR Date - Assigned Canvasser Date. Each receiving report is listed separately.</span>
@endsection

@section('content')
    @php
        $fmtTableDate = fn (mixed $value) => \App\Support\PdfFormatters::tableDate($value);
    @endphp
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="3" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th colspan="3" class="center section-head section-sep">Item</th>
                <th colspan="2" class="center section-head section-sep">Canvasser</th>
                <th colspan="2" class="center section-head section-sep">Purchase Order</th>
                <th class="center section-head section-sep">Supplier</th>
                <th colspan="2" class="center section-head section-sep">Receiving Report</th>
                <th class="right section-head nowrap">Lead Time</th>
            </tr>
            <tr>
                <th class="section-col col-sep">ID</th>
                <th class="section-col col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col section-sep right">Qty</th>
                <th class="section-col section-start col-sep">Name</th>
                <th class="section-col section-sep">Assigned</th>
                <th class="section-col section-start col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start section-sep">Name</th>
                <th class="section-col section-start col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start right nowrap">Days</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['prs_id'] }}</td>
                    <td>{{ $row['prs_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtTableDate($row['prs_date']) }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                    <td class="right">{{ $fmtQty($row['quantity']) }} {{ $row['unit'] }}</td>
                    <td>{{ $row['canvasser'] ?? '-' }}</td>
                    <td class="nowrap-date">{{ $fmtTableDate($row['assigned_canvasser_at']) }}</td>
                    <td>{{ $row['po_number'] ?? '-' }}</td>
                    <td class="nowrap-date">{{ $fmtTableDate($row['po_date']) }}</td>
                    <td class="text-wrap-max">{{ $row['supplier_name'] ?? '-' }}</td>
                    <td>{{ $row['rr_number'] ?? '-' }}</td>
                    <td class="nowrap-date">{{ $fmtTableDate($row['rr_date']) }}</td>
                    <td class="right nowrap">{{ $row['lead_time_days'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="muted">No lead time records found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
