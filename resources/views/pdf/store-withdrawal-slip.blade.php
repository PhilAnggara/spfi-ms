<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stores Withdrawal Slip - {{ $sws->sws_number }}</title>
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

        .totals {
            margin-top: 10px;
            width: 40%;
            margin-left: auto;
            border: 1px solid #111827;
        }

        .totals td {
            border: none;
            padding: 2px 6px;
        }

        .totals tr + tr td {
            border-top: 1px solid #d1d5db;
        }

        .totals .totals-label {
            font-weight: bold;
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
            width: 55%;
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
        $totalQuantity = (float) $items->sum('quantity');
        $totalRows = $items->count();
        $isCapex = strtolower((string) ($sws->type ?? '')) === 'capex';
        $departmentLabel = trim(
            ($sws->department_code ? '['.$sws->department_code.'] ' : '').
            ($sws->department_name ?? '')
        );
        $capexLabel = $isCapex ? 'CAPEX' : null;
        $columnWidth = '50%';
        $fmtQty = fn (float|int|string $n): string => \App\Support\PdfFormatters::qty($n);
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <div class="doc-title">
                    Stores Withdrawal Slip
                    @if ($capexLabel)
                        <span class="capex-tag">({{ $capexLabel }})</span>
                    @endif
                </div>
                <div class="doc-company">PT. SINAR PURE FOODS INTERNATIONAL</div>
            </td>
            <td class="doc-number">
                <div class="muted">SWS Number</div>
                <strong>{{ $sws->sws_number ?? '-' }}</strong>
            </td>
        </tr>
    </table>
    <div class="header-rule"></div>

    <table class="meta">
        <tr>
            <td class="label">Requested By</td>
            <td class="sep">:</td>
            <td>{{ $sws->created_by_name ?? '-' }}</td>
            <td class="right-label">SWS Date</td>
            <td class="sep">:</td>
            <td>{{ $sws->sws_date ? \Carbon\Carbon::parse($sws->sws_date)->format('d M Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Department</td>
            <td class="sep">:</td>
            <td>{{ $departmentLabel !== '' ? $departmentLabel : '-' }}</td>
            <td class="right-label">Type</td>
            <td class="sep">:</td>
            <td>{{ strtoupper((string) ($sws->type ?? '-')) }}</td>
        </tr>
    </table>

    <div class="section-gap">
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;" class="text-center">No</th>
                    <th style="width: {{ $isCapex ? '16%' : '18%' }};">Item Code</th>
                    <th style="width: {{ $isCapex ? '28%' : '46%' }};">Item Description</th>
                    @if ($isCapex)
                        <th style="width: 10%;">PRS</th>
                        <th style="width: 10%;">PO</th>
                        <th style="width: 10%;">RR</th>
                    @endif
                    <th style="width: 12%;" class="text-right">Qty</th>
                    <th style="width: 10%;" class="text-center">UOM</th>
                    <th style="width: {{ $isCapex ? '8%' : '14%' }};" class="text-right">{{ $isCapex ? 'RR Qty' : 'SOH' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $index => $detail)
                    @php
                        $qty = (float) ($detail->quantity ?? 0);
                        $soh = (float) ($detail->stock_on_hand_snapshot ?? 0);
                        $uom = $detail->uom ?? $detail->item_uom_name ?? 'PCS';
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $detail->item_code ?? $detail->product_code ?? '-' }}</td>
                        <td>{{ $detail->item_name ?? '(item unavailable)' }}</td>
                        @if ($isCapex)
                            <td>{{ $detail->prs_number ?? '-' }}</td>
                            <td>{{ $detail->po_number ?? '-' }}</td>
                            <td>{{ $detail->rr_number ?? '-' }}</td>
                        @endif
                        <td class="text-right">{{ $fmtQty($qty) }}</td>
                        <td class="text-center">{{ $uom }}</td>
                        <td class="text-right">{{ $fmtQty($soh) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isCapex ? 9 : 6 }}" class="text-center muted">No items found in this SWS</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr>
            <td class="totals-label">Total Item Rows</td>
            <td class="text-right">{{ $totalRows }}</td>
        </tr>
        <tr>
            <td class="totals-label">Total Quantity</td>
            <td class="text-right">{{ $fmtQty($totalQuantity) }}</td>
        </tr>
    </table>

    @if (! empty($sws->info))
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-body">{{ $sws->info }}</div>
        </div>
    @endif

    <div class="signature-section">
        <div class="signature-note">
            This document requires approval before stock withdrawal from the store.
        </div>

        <table class="signatures">
            <tr class="sig-roles">
                <td style="width: {{ $columnWidth }};">Requested By</td>
                <td style="width: {{ $columnWidth }};">Approved By</td>
            </tr>

            <tr class="sig-space">
                <td style="width: {{ $columnWidth }};">&nbsp;</td>
                <td style="width: {{ $columnWidth }};">&nbsp;</td>
            </tr>

            <tr class="sig-names">
                <td style="width: {{ $columnWidth }};">
                    <div class="sig-line"></div>
                    {{ $sws->created_by_name ?? '____________________' }}
                </td>
                <td style="width: {{ $columnWidth }};">
                    <div class="sig-line"></div>
                    {{ $sws->approved_by_name ?? '____________________' }}
                </td>
            </tr>

            <tr class="sig-titles">
                <td style="width: {{ $columnWidth }};">Requester</td>
                <td style="width: {{ $columnWidth }};">Approver</td>
            </tr>

            <tr class="sig-dates">
                <td style="width: {{ $columnWidth }};">
                    {{ $sws->created_at ? \Carbon\Carbon::parse($sws->created_at)->format('d M Y') : 'Date: __________' }}
                </td>
                <td style="width: {{ $columnWidth }};">
                    {{ $sws->approved_at ? \Carbon\Carbon::parse($sws->approved_at)->format('d M Y') : 'Date: __________' }}
                </td>
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
