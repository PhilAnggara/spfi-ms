<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PRS Report per Department</title>
    @include('pdf.partials.header-style')
    <style>
        @page { size: A4 landscape; margin: 16px 20px; }
        body { font-size: 7.5px; line-height: 1.25; margin: 0 24px; }
        .header .company-name { font-size: 14px; }
        .header .company-address,
        .header .company-contact,
        .header .company-web { font-size: 9px; }
        .document-title { font-size: 11px; margin-top: 6px; }
        .header-divider { width: 100%; margin: 6px auto 10px; }
        .meta { text-align: center; margin-bottom: 8px; font-size: 7.5px; color: #4b5563; }
        .scope-note { text-align: center; font-size: 7.5px; color: #6b7280; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 2px 4px; vertical-align: top; word-wrap: break-word; }
        th { background: #f3f4f6; font-weight: 700; text-align: left; font-size: 7px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }
        .dept-header { margin: 10px 0 4px; font-size: 8.5px; font-weight: 700; color: #111827; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 4px; background: #e5e7eb; font-size: 6.5px; }
        .badge-capex { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    @include('pdf.partials.header', ['documentTitle' => $title ?? 'Purchase Requisition Slip Report per Department'])
    <div class="meta">
        Period: {{ $start->format('M Y') }} - {{ $end->format('M Y') }} | Generated: {{ $generated_at->format('d M Y H:i') }}
    </div>
    @if ($scoped_department)
        <div class="scope-note">Department: [{{ $scoped_department->code }}] {{ $scoped_department->name }}</div>
    @endif

    @forelse ($groups as $group)
        <div class="dept-header">Department: [{{ $group['department_code'] }}] {{ $group['department_name'] }} ({{ $group['prs_list']->count() }} PRS)</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 18px;">#</th>
                    <th style="width: 72px;">PRS No.</th>
                    <th style="width: 52px;">PRS Date</th>
                    <th style="width: 52px;">Needed</th>
                    <th style="width: 68px;">Requestor</th>
                    <th style="width: 58px;">Status</th>
                    <th class="text-center" style="width: 34px;">CAPEX</th>
                    <th style="width: 52px;">Item Code</th>
                    <th>Item Name</th>
                    <th class="text-right" style="width: 38px;">Qty</th>
                    <th style="width: 34px;">Unit</th>
                    <th style="width: 62px;">Canvasser</th>
                    <th style="width: 62px;">PO No.</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNum = 0; @endphp
                @forelse ($group['prs_list'] as $prs)
                    @foreach ($prs->items as $itemIdx => $prsItem)
                        @php $rowNum++; @endphp
                        <tr>
                            <td class="text-center">{{ $rowNum }}</td>
                            @if ($itemIdx === 0)
                                <td rowspan="{{ $prs->items->count() }}">{{ $prs->prs_number }}</td>
                                <td rowspan="{{ $prs->items->count() }}">{{ \Carbon\Carbon::parse($prs->prs_date)->format('d/m/y') }}</td>
                                <td rowspan="{{ $prs->items->count() }}">{{ \Carbon\Carbon::parse($prs->date_needed)->format('d/m/y') }}</td>
                                <td rowspan="{{ $prs->items->count() }}">{{ $prs->user?->name ?? '-' }}</td>
                                <td rowspan="{{ $prs->items->count() }}">{{ $prs->status }}</td>
                                <td rowspan="{{ $prs->items->count() }}" class="text-center">
                                    @if ($prs->is_capex)
                                        <span class="badge badge-capex">Yes</span>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $prsItem->item?->code ?? '-' }}</td>
                            <td>{{ $prsItem->item?->name ?? '-' }}</td>
                            <td class="text-right">{{ \App\Support\PdfFormatters::qty($prsItem->quantity) }}</td>
                            <td>{{ $prsItem->item?->unit?->name ?? '-' }}</td>
                            <td>{{ $prsItem->canvasser?->name ?? '-' }}</td>
                            <td>{{ $prsItem->purchaseOrder?->po_number ?? '-' }}</td>
                            @if ($itemIdx === 0)
                                <td rowspan="{{ $prs->items->count() }}">{{ $prs->remarks ?? '-' }}</td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="14" class="text-center muted">No PRS found for this department.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p class="text-center muted">No PRS found in this range.</p>
    @endforelse
</body>
</html>
