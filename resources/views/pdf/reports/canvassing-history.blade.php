@extends('pdf.layouts.analytical')

@section('header-meta')
    Product: {{ $item_code }} — {{ $item_name }}<br>
    Unit: {{ $unit ?? '-' }} · Category: {{ $category ?? '-' }}<br>
    @php
        $avg = $summary['avg_unit_price'] ?? null;
        $min = $summary['min_unit_price'] ?? null;
    @endphp
    Summary: Avg {{ $avg !== null ? number_format((float) $avg, 2) : '-' }}
    · Min {{ $min !== null ? number_format((float) $min, 2) : '-' }}
    · {{ $summary['quote_count'] ?? 0 }} quote(s)
    · {{ $summary['supplier_count'] ?? 0 }} supplier(s)
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
                <th class="section-col col-sep">Status</th>
                <th class="section-col col-sep">TOP</th>
                <th class="section-col col-sep">TOD</th>
                <th class="section-col col-sep">Canvasser</th>
                <th class="section-col">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="nowrap-date">{{ $fmtDate($row['canvass_date'] ?? null) }}</td>
                    <td>{{ $row['prs_number'] ?? '-' }}</td>
                    <td>{{ $row['supplier_code'] ?? '-' }}</td>
                    <td class="text-wrap-max" style="text-align: left;">{{ $row['supplier_name'] ?? '-' }}</td>
                    <td class="right">{{ isset($row['unit_price']) ? number_format((float) $row['unit_price'], 2) : '-' }}</td>
                    <td>{{ ! empty($row['is_selected']) ? 'Selected' : 'Not selected' }}</td>
                    <td>{{ $row['term_of_payment'] ?? '-' }}</td>
                    <td>{{ $row['term_of_delivery'] ?? '-' }}</td>
                    <td>{{ $row['canvasser'] ?? '-' }}</td>
                    <td class="text-wrap-max">{{ $row['notes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">No canvassing history found for this item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
