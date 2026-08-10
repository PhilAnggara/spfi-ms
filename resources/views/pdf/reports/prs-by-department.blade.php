@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span>
    @if (! empty($scoped_department_label))
        <br>Department: {{ $scoped_department_label }}
    @endif
@endsection

@push('styles')
<style>
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
                <th colspan="6" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th colspan="4" class="center section-head section-sep">Item</th>
                <th class="center section-head section-sep"></th>
                <th class="center section-head section-sep"></th>
                <th class="center section-head"></th>
            </tr>
            <tr>
                <th class="section-col col-sep">Number</th>
                <th class="section-col col-sep">PRS Date</th>
                <th class="section-col col-sep">Date Needed</th>
                <th class="section-col col-sep">Requestor</th>
                <th class="section-col col-sep">Status</th>
                <th class="section-col section-sep">CAPEX</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col col-sep right">Qty</th>
                <th class="section-col section-sep">Unit</th>
                <th class="section-col section-sep">Canvasser</th>
                <th class="section-col section-sep">PO No.</th>
                <th class="section-col">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr class="group-title">
                    <td colspan="13">Department: [{{ $group['department_code'] }}] {{ $group['department_name'] }} ({{ count($group['rows']) }} line(s))</td>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr @class(['item-group-start' => ! empty($row['is_prs_start'])])>
                        <td>{{ $row['prs_number'] ?? '' }}</td>
                        <td class="nowrap-date">{{ ! empty($row['prs_date']) ? $fmtDate($row['prs_date']) : '' }}</td>
                        <td class="nowrap-date">{{ ! empty($row['date_needed']) ? $fmtDate($row['date_needed']) : '' }}</td>
                        <td class="text-wrap-max">{{ $row['requestor'] ?? '' }}</td>
                        <td>{{ $row['status'] ?? '' }}</td>
                        <td class="center">
                            @if (array_key_exists('is_capex', $row) && $row['is_capex'] !== null)
                                {{ $row['is_capex'] ? 'Yes' : '-' }}
                            @endif
                        </td>
                        <td>{{ $row['item_code'] }}</td>
                        <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                        <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="text-wrap-max">{{ $row['canvasser'] }}</td>
                        <td>{{ $row['po_number'] }}</td>
                        <td class="text-wrap-max">{{ $row['remarks'] ?? '' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="13" class="center muted">No PRS found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
