@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Selected products: {{ $selected_count ?? 0 }}
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th class="section-col col-sep">PO Number</th>
                <th class="section-col col-sep">PO Date</th>
                <th class="section-col col-sep">Supplier Code</th>
                <th class="section-col col-sep">Supplier Name</th>
                <th class="section-col col-sep right">Qty</th>
                <th class="section-col col-sep right">Unit Price</th>
                <th class="section-col col-sep">Curr</th>
                <th class="section-col">Canvasser</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr class="group-title">
                    <td colspan="8">[{{ $group['item_code'] ?? '' }}] {{ $group['item_name'] ?? '' }} ({{ count($group['rows'] ?? []) }} line(s))</td>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['po_number'] ?? '-' }}</td>
                        <td class="nowrap-date">{{ $fmtDate($row['po_date'] ?? null) }}</td>
                        <td>{{ $row['supplier_code'] ?? '-' }}</td>
                        <td class="text-wrap-max" style="text-align: left;">{{ $row['supplier_name'] ?? '-' }}</td>
                        <td class="right">{{ isset($row['quantity']) ? number_format((float) $row['quantity'], 2) : '-' }}</td>
                        <td class="right">{{ isset($row['unit_price']) ? number_format((float) $row['unit_price'], 2) : '-' }}</td>
                        <td>{{ $row['currency'] ?? '-' }}</td>
                        <td>{{ $row['canvasser'] ?? '-' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="8" class="center muted">No purchase history found for the selected products and period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
