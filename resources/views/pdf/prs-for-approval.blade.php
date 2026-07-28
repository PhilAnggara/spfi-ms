<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PRS for Approval - {{ $prs->prs_number }}</title>
    <style>
        @page {
            margin: 22px 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.35;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            vertical-align: bottom;
            padding: 0;
        }

        .doc-title {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .doc-company {
            font-size: 9px;
            color: #4b5563;
            margin-top: 2px;
        }

        .doc-number {
            text-align: right;
            font-size: 10px;
        }

        .doc-number strong {
            font-size: 11px;
        }

        .header-rule {
            border-top: 1px solid #111827;
            border-bottom: 2px solid #111827;
            margin: 8px 0 12px;
            height: 0;
        }

        .meta td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
        }

        .meta .label {
            width: 95px;
            font-weight: bold;
            white-space: nowrap;
        }

        .meta .sep {
            width: 8px;
        }

        .meta .right-label {
            width: 95px;
            font-weight: bold;
            white-space: nowrap;
            padding-left: 20px;
        }

        .capex-tag {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .section-gap {
            margin-top: 12px;
        }

        .items th,
        .items td {
            border: 1px solid #111827;
            padding: 4px 5px;
            vertical-align: top;
        }

        .items th {
            background: #f3f4f6;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }

        .remarks {
            margin-top: 10px;
        }

        .remarks-label {
            font-weight: bold;
            margin-bottom: 2px;
        }

        .remarks-body {
            border-top: 1px solid #111827;
            padding-top: 4px;
            min-height: 18px;
        }

        .signature-section {
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .signature-note {
            font-size: 9px;
            color: #4b5563;
            margin-bottom: 14px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signatures td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }

        .sig-roles td {
            font-size: 9px;
            font-weight: bold;
            padding-bottom: 4px;
        }

        .sig-space td {
            height: 48px;
            line-height: 48px;
            font-size: 1px;
        }

        .sig-names td {
            font-size: 10px;
            font-weight: bold;
            padding-top: 5px;
            vertical-align: top;
        }

        .sig-line {
            width: 78%;
            margin: 0 auto 5px;
            border-top: 1px solid #111827;
            height: 0;
        }

        .sig-titles td {
            font-size: 8.5px;
            color: #4b5563;
            padding-top: 2px;
        }

        .sig-dates td {
            font-size: 8.5px;
            color: #6b7280;
            padding-top: 5px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $manager = get_manager($prs->user);
        $approverCount = count($approvers);
        $hasSharedApprovers = $approverCount > 1;
        $columnCount = $hasSharedApprovers ? (2 + $approverCount) : 3;
        $columnWidth = number_format(100 / $columnCount, 2, '.', '').'%';
        $departmentLabel = trim(
            ($prs->department->code ? '['.$prs->department->code.'] ' : '').
            ($prs->department->name ?? '')
        );
        $capexLabel = $prs->is_capex ? 'CAPEX' : null;
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <div class="doc-title">
                    Purchase Requisition Slip
                    @if ($capexLabel)
                        <span class="capex-tag">({{ $capexLabel }})</span>
                    @endif
                </div>
                <div class="doc-company">PT. SINAR PURE FOODS INTERNATIONAL</div>
            </td>
            <td class="doc-number">
                <div class="muted">PRS Number</div>
                <strong>{{ $prs->prs_number }}</strong>
            </td>
        </tr>
    </table>
    <div class="header-rule"></div>

    <table class="meta">
        <tr>
            <td class="label">Requested By</td>
            <td class="sep">:</td>
            <td>{{ $prs->user->name }}</td>
            <td class="right-label">PRS Date</td>
            <td class="sep">:</td>
            <td>{{ \Carbon\Carbon::parse($prs->prs_date)->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Department</td>
            <td class="sep">:</td>
            <td>{{ $departmentLabel !== '' ? $departmentLabel : '-' }}</td>
            <td class="right-label">Date Needed</td>
            <td class="sep">:</td>
            <td>{{ \Carbon\Carbon::parse($prs->date_needed)->format('d M Y') }}</td>
        </tr>
    </table>

    <div class="section-gap">
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;" class="text-center">No</th>
                    <th style="width: 16%;">Item Code</th>
                    <th style="width: 48%;">Item Description</th>
                    <th style="width: 15%;" class="text-right">Qty Needed</th>
                    <th style="width: 15%;" class="text-center">UOM</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prs->items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $item->item->code ?? '-' }}</td>
                        <td>{{ $item->item->name ?? '-' }}</td>
                        <td class="text-right">{{ \App\Support\PdfFormatters::qty($item->quantity) }}</td>
                        <td class="text-center">{{ $item->item->unit?->name ?? 'PCS' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center muted">No items found in this PRS</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($prs->remarks)
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-body">{{ $prs->remarks }}</div>
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-note">
            This document requires approval before processing by the Purchasing Department.
        </div>

        <table class="signatures">
            {{-- Role labels --}}
            <tr class="sig-roles">
                <td style="width: {{ $columnWidth }};">Requested By</td>
                <td style="width: {{ $columnWidth }};">Reviewed By</td>
                @if ($hasSharedApprovers)
                    <td colspan="{{ $approverCount }}">Approved By</td>
                @else
                    <td style="width: {{ $columnWidth }};">Approved By</td>
                @endif
            </tr>

            {{-- Empty signing space (same height = aligned lines) --}}
            <tr class="sig-space">
                @for ($i = 0; $i < $columnCount; $i++)
                    <td style="width: {{ $columnWidth }};">&nbsp;</td>
                @endfor
            </tr>

            {{-- Names sit on the shared baseline --}}
            <tr class="sig-names">
                <td style="width: {{ $columnWidth }};">
                    <div class="sig-line"></div>
                    {{ $prs->user->name }}
                </td>
                <td style="width: {{ $columnWidth }};">
                    <div class="sig-line"></div>
                    {{ $manager?->name ?? '____________________' }}
                </td>
                @foreach ($approvers as $approver)
                    <td style="width: {{ $columnWidth }};">
                        <div class="sig-line"></div>
                        {{ $approver['name'] }}
                    </td>
                @endforeach
            </tr>

            <tr class="sig-titles">
                <td style="width: {{ $columnWidth }};">Requester</td>
                <td style="width: {{ $columnWidth }};">{{ $manager ? get_job_title($manager) : 'Manager' }}</td>
                @foreach ($approvers as $approver)
                    <td style="width: {{ $columnWidth }};">{{ $approver['title'] }}</td>
                @endforeach
            </tr>

            <tr class="sig-dates">
                <td style="width: {{ $columnWidth }};">{{ \Carbon\Carbon::parse($prs->prs_date)->format('d M Y') }}</td>
                <td style="width: {{ $columnWidth }};">Date: __________</td>
                @foreach ($approvers as $approver)
                    <td style="width: {{ $columnWidth }};">Date: __________</td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="footer">
        Generated on {{ \Carbon\Carbon::now()->format('d M Y H:i') }}
        &nbsp;|&nbsp;
        System-generated document — official signature required for approval
    </div>
</body>
</html>
