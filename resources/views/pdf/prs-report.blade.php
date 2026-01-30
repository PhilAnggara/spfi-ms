<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRS Report</title>
    @include('pdf.partials.header-style')
    <style>
        .meta { text-align: center; margin-bottom: 12px; font-size: 11px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; text-align: left; }
        .text-center { text-align: center; }
        .muted { color: #6b7280; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 6px; background: #e5e7eb; font-size: 10px; }
        .items { margin: 0; padding-left: 14px; }
        .items li { margin-bottom: 2px; }
    </style>
</head>
<body>
    @include('pdf.partials.header', ['documentTitle' => 'Purchase Requisition Slip Report'])
    <div class="meta">
        Range: {{ $start->format('M Y') }} - {{ $end->format('M Y') }} | Generated: {{ $generated_at->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 36px;">#</th>
                <th>PRS Number</th>
                <th>Department</th>
                <th>PRS Date</th>
                <th>Date Needed</th>
                <th class="text-center">Items</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prsList as $idx => $prs)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $prs->prs_number }}</td>
                    <td>{{ $prs->department->name ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($prs->prs_date)->format('d M Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($prs->date_needed)->format('d M Y') }}</td>
                    <td>
                        <div class="badge">{{ $prs->items->count() }} item(s)</div>
                        <ul class="items">
                            @foreach ($prs->items as $item)
                                <li>{{ $item->item->code ?? '-' }} — {{ $item->item->name ?? '-' }} ({{ $item->quantity }} {{ $item->item->unit?->name ?? '' }})</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>{{ $prs->remarks ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center muted">No PRS found in this range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
