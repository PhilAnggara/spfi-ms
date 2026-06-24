<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 8px; color: #111; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 2px; }
        td, th { border: none; padding: 1px 4px; }
        th { font-size: 7px; font-weight: bold; text-align: left; text-transform: uppercase; }
        .no-border td { padding: 1px 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    @php
        $fmtDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : '';
        $fmtQty = fn ($value) => number_format((float) $value, 0, ',', '.');
    @endphp

    <table>
        <tr class="no-border">
            <td colspan="14" class="bold">{{ $company }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="14" class="bold">{{ $title }}</td>
        </tr>
        <tr class="no-border">
            <td colspan="14">Period {{ $start->format('M Y') }} - {{ $end->format('M Y') }}</td>
        </tr>
        @if ($scoped_department)
            <tr class="no-border">
                <td colspan="14">Department: [{{ $scoped_department->code }}] {{ $scoped_department->name }}</td>
            </tr>
        @endif
        <tr class="no-border"><td colspan="14">&nbsp;</td></tr>

        @forelse ($groups as $group)
            <tr class="no-border">
                <td colspan="14" class="bold">Department: [{{ $group['department_code'] }}] {{ $group['department_name'] }} ({{ $group['prs_list']->count() }} PRS)</td>
            </tr>
            <tr>
                <th class="center">#</th>
                <th>PRS No.</th>
                <th>PRS Date</th>
                <th>Date Needed</th>
                <th>Requestor</th>
                <th>Status</th>
                <th class="center">CAPEX</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th class="right">Qty</th>
                <th>Unit</th>
                <th>Canvasser</th>
                <th>PO No.</th>
                <th>Remarks</th>
            </tr>
            @php $rowNum = 0; @endphp
            @forelse ($group['prs_list'] as $prs)
                @foreach ($prs->items as $prsItem)
                    @php $rowNum++; @endphp
                    <tr>
                        <td class="center">{{ $rowNum }}</td>
                        <td>{{ $prs->prs_number }}</td>
                        <td>{{ $fmtDate($prs->prs_date) }}</td>
                        <td>{{ $fmtDate($prs->date_needed) }}</td>
                        <td>{{ $prs->user?->name ?? '-' }}</td>
                        <td>{{ $prs->status }}</td>
                        <td class="center">{{ $prs->is_capex ? 'Yes' : '-' }}</td>
                        <td>{{ $prsItem->item?->code ?? '-' }}</td>
                        <td>{{ $prsItem->item?->name ?? '-' }}</td>
                        <td class="right">{{ $fmtQty($prsItem->quantity) }}</td>
                        <td>{{ $prsItem->item?->unit?->name ?? '-' }}</td>
                        <td>{{ $prsItem->canvasser?->name ?? '-' }}</td>
                        <td>{{ $prsItem->purchaseOrder?->po_number ?? '-' }}</td>
                        <td>{{ $prs->remarks ?? '-' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="14" class="muted">No PRS found for this department.</td>
                </tr>
            @endforelse
            <tr class="no-border"><td colspan="14">&nbsp;</td></tr>
        @empty
            <tr>
                <td colspan="14" class="muted">No PRS found in this range.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>
