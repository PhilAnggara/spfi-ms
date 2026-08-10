@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span>
@endsection

@push('styles')
<style>
    .col-row-num {
        width: 24px;
        max-width: 24px;
    }
    .item-group-start td {
        border-top: 1px solid #d1d5db;
        padding-top: 4px;
    }
</style>
@endpush

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="4" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th class="center section-head section-sep"></th>
                <th colspan="4" class="center section-head section-sep">Items</th>
                <th class="center section-head"></th>
            </tr>
            <tr>
                <th class="section-col col-sep col-row-num center">#</th>
                <th class="section-col col-sep">Number</th>
                <th class="section-col col-sep">PRS Date</th>
                <th class="section-col section-sep">Date Needed</th>
                <th class="section-col section-sep">Department</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col col-sep right">Qty</th>
                <th class="section-col section-sep">Unit</th>
                <th class="section-col">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class(['item-group-start' => ! empty($row['is_prs_start'])])>
                    <td class="center col-row-num">{{ $row['row_number'] ?? '' }}</td>
                    <td>{{ $row['prs_number'] ?? '' }}</td>
                    <td class="nowrap-date">{{ ! empty($row['prs_date']) ? $fmtDate($row['prs_date']) : '' }}</td>
                    <td class="nowrap-date">{{ ! empty($row['date_needed']) ? $fmtDate($row['date_needed']) : '' }}</td>
                    <td class="text-wrap-max">{{ $row['department_name'] ?? '' }}</td>
                    <td>{{ $row['item_code'] ?? '' }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] ?? '' }}</td>
                    <td class="right">{{ $fmtQty($row['quantity'] ?? 0) }}</td>
                    <td>{{ $row['unit'] ?? '' }}</td>
                    <td class="text-wrap-max">{{ $row['remarks'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">No PRS found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
