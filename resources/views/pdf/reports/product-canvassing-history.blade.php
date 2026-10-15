@extends('pdf.layouts.analytical')

@section('header-meta')
    Period: <span class="nowrap-date">{{ $fmtDate($date_from) }}</span> - <span class="nowrap-date">{{ $fmtDate($date_to) }}</span><br>
    Selected products: {{ $selected_count ?? 0 }}
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th class="section-col col-sep">Canvass Date</th>
                <th class="section-col col-sep">PRS Number</th>
                <th class="section-col col-sep">Supplier Code</th>
                <th class="section-col col-sep">Supplier Name</th>
                <th class="section-col col-sep right">Unit Price</th>
                <th class="section-col col-sep">Curr</th>
                <th class="section-col col-sep">Status</th>
                <th class="section-col col-sep">TOP</th>
                <th class="section-col col-sep">TOD</th>
                <th class="section-col col-sep">Canvasser</th>
                <th class="section-col">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr class="group-title">
                    <td colspan="11">[{{ $group['item_code'] ?? '' }}] {{ $group['item_name'] ?? '' }} ({{ count($group['rows'] ?? []) }} quote(s))</td>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td class="nowrap-date">{{ $fmtDate($row['canvass_date'] ?? null) }}</td>
                        <td>{{ $row['prs_number'] ?? '-' }}</td>
                        <td>{{ $row['supplier_code'] ?? '-' }}</td>
                        <td class="text-wrap-max" style="text-align: left;">{{ $row['supplier_name'] ?? '-' }}</td>
                        <td class="right">{{ isset($row['unit_price']) ? number_format((float) $row['unit_price'], 2) : '-' }}</td>
                        <td>{{ $row['currency'] ?? '-' }}</td>
                        <td>{{ ! empty($row['is_selected']) ? 'Selected' : 'Not selected' }}</td>
                        <td>{{ $row['term_of_payment'] ?? '-' }}</td>
                        <td>{{ $row['term_of_delivery'] ?? '-' }}</td>
                        <td>{{ $row['canvasser'] ?? '-' }}</td>
                        <td class="text-wrap-max">{{ $row['notes'] ?? '-' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="11" class="center muted">No canvassing history found for the selected products and period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
