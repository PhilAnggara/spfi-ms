<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRS Report</title>
    <style>
        @page { margin: 24px; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { display: table; width: 100%; margin-bottom: 20px; border-bottom: 2px solid #1f2937; padding-bottom: 12px; }
        .header-content { display: table-row; }
        .logo-cell { display: table-cell; width: 80px; vertical-align: middle; }
        .logo { width: 70px; height: auto; }
        .company-cell { display: table-cell; vertical-align: middle; text-align: center; }
        .company-name { margin: 0; font-size: 18px; font-weight: 700; letter-spacing: 0.5px; color: #1f2937; }
        .report-title { margin: 4px 0 0 0; font-size: 14px; font-weight: 600; color: #4b5563; }
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
    <div class="header">
        <div class="header-content">
            <div class="logo-cell">
                <img src="{{ public_path('assets/images/sinar.png') }}" alt="Logo" class="logo">
            </div>
            <div class="company-cell">
                <h1 class="company-name">PT. Sinar Pure Foods International</h1>
                <p class="report-title">Purchase Requisition Slip Report</p>
            </div>
        </div>
    </div>
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
                                <li>{{ $item->item->code ?? '-' }} — {{ $item->item->name ?? '-' }} ({{ $item->quantity }} {{ $item->item->unit ?? '' }})</li>
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
