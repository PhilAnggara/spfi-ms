@extends('pdf.layouts.analytical')

@section('header-meta')
    As Of: <span class="nowrap-date">{{ $fmtDate($as_of) }}</span><br>
    Canvasser: {{ $canvasser }}
@endsection

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="3" class="center section-head section-sep">Purchase Requisition Slip</th>
                <th colspan="5" class="center section-head section-sep">Item</th>
                <th colspan="2" class="center section-head">Department</th>
            </tr>
            <tr>
                <th class="section-col col-sep">ID</th>
                <th class="section-col col-sep">Number</th>
                <th class="section-col section-sep">Date</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col col-sep">Description</th>
                <th class="section-col col-sep right">Stock on Hand</th>
                <th class="section-col col-sep right">Quantity</th>
                <th class="section-col section-sep">Unit</th>
                <th class="section-col section-start col-sep">Code</th>
                <th class="section-col">Name</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['prs_id'] }}</td>
                    <td>{{ $row['prs_number'] }}</td>
                    <td class="nowrap-date">{{ $fmtDate($row['prs_date']) }}</td>
                    <td>{{ $row['item_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['item_name'] }}</td>
                    <td class="right">{{ $fmtQty($row['stock_on_hand']) }}</td>
                    <td class="right">{{ $fmtQty($row['quantity']) }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['department_code'] }}</td>
                    <td class="text-wrap-max">{{ $row['department_name'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
