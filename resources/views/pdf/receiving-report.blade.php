<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receiving Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }
        .header {
            display: block;
            margin-bottom: 8px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .line {
            border-top: 1px solid #111827;
            margin: 6px 0 8px;
        }
        .muted {
            color: #6b7280;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #111827;
            padding: 3px 4px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
            font-size: 9px;
        }
        .rr-items {
            font-size: 9px;
        }
        .table-clean th,
        .table-clean td {
            border: none;
            padding: 2px 0;
        }
        .label {
            width: 120px;
            white-space: nowrap;
            font-weight: bold;
        }
        .signatures {
            margin-top: 24px;
        }
        .signature-table td {
            border: none;
            padding: 4px 0;
        }
        .signature-line {
            margin-top: 24px;
            font-weight: bold;
        }
        .note {
            font-size: 9px;
            line-height: 1.4;
        }
        .summary-box {
            border: 1px solid #111827;
            padding: 6px;
            margin-top: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    @php
        $po = $receivingReport->purchaseOrder;
        $supplier = $po?->supplier;
        $totalGood = (float) $receivingReport->items->sum('qty_good');
        $totalBad = (float) $receivingReport->items->sum('qty_bad');
        $totalReceived = $totalGood + $totalBad;
    @endphp

    <div class="header">
        <div class="title">RECEIVING REPORT</div>
    </div>

    <table class="table-clean">
        <tr>
            <td class="label">RR Number</td>
            <td>: {{ $receivingReport->rr_number ?? '-' }}</td>
            <td class="label text-right">Received Date</td>
            <td class="text-right">: {{ format_date($receivingReport->received_date) }}</td>
        </tr>
        <tr>
            <td class="label">PO Number</td>
            <td colspan="3">: {{ $po?->po_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Supplier Name</td>
            <td colspan="3">: {{ $supplier?->name ?? '-' }}{{ $supplier?->code ? ' | ' . $supplier->code : '' }}</td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td colspan="3">: {{ $supplier?->address ?? '-' }}</td>
        </tr>
        @if($receivingReport->notes)
        <tr>
            <td class="label">Notes</td>
            <td colspan="3">: {{ $receivingReport->notes }}</td>
        </tr>
        @endif
    </table>

    <div class="line"></div>

    <table class="rr-items">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Item Name</th>
                <th style="width: 70px;">Item Code</th>
                <th style="width: 50px;">PRS Number</th>
                <th style="width: 50px;">Department</th>
                <th style="width: 40px;" class="text-center">Unit</th>
                <th style="width: 60px;" class="text-right">Qty Ordered</th>
                <th style="width: 60px;" class="text-right">Qty Good</th>
                <th style="width: 60px;" class="text-right">Qty Bad</th>
                <th style="width: 60px;" class="text-right">Total Received</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receivingReport->items as $idx => $rrItem)
                @php
                    $poItem = $rrItem->purchaseOrderItem;
                    $item = $poItem?->item;
                    $prsItem = $poItem?->prsItem;
                    $prs = $prsItem?->prs;
                    $qtyGood = (float) $rrItem->qty_good;
                    $qtyBad = (float) $rrItem->qty_bad;
                    $qtyTotal = $qtyGood + $qtyBad;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item?->name ?? '-' }}</td>
                    <td>{{ $item?->code ?? '-' }}</td>
                    <td>{{ $prs?->prs_number ?? '-' }}</td>
                    <td>{{ $prs?->department?->name ?? '-' }}</td>
                    <td class="text-center">{{ $item?->unit?->name ?? 'PCS' }}</td>
                    <td class="text-right">{{ number_format($poItem?->quantity ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($qtyGood, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($qtyBad, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($qtyTotal, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="table-clean" style="margin-top: 10px;">
        <tr>
            <td style="width: 60%;">
                <div class="note">
                    This receiving report confirms the receipt of goods as per the purchase order.
                </div>
                <div class="note" style="margin-top: 4px;">
                    PT Sinar Pure Foods International
                </div>
            </td>
            <td style="width: 40%;">
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Total Items</span>
                        <span>{{ $receivingReport->items->count() }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Qty Good</span>
                        <span>{{ number_format($totalGood, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Qty Bad</span>
                        <span>{{ number_format($totalBad, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row" style="border-top: 1px solid #111827; margin-top: 4px; padding-top: 4px; font-weight: bold;">
                        <span>Total Received</span>
                        <span>{{ number_format($totalReceived, 2, ',', '.') }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="signatures">
        <table class="signature-table" style="width: 100%;">
            <tr>
                <td style="width: 33%;">Received by</td>
                <td style="width: 33%;">Checked by</td>
                <td style="width: 34%;">Approved by</td>
            </tr>
            <tr>
                <td class="signature-line">{{ $receivingReport->createdBy?->name ?? '-' }}</td>
                <td class="signature-line">____________________</td>
                <td class="signature-line">____________________</td>
            </tr>
            <tr>
                <td>Date: {{ format_date($receivingReport->created_at) }}</td>
                <td>Date: ____________________</td>
                <td>Date: ____________________</td>
            </tr>
            <tr>
                <td colspan="3" style="padding-top: 18px;">Supplier's Signature : ____________________________</td>
            </tr>
        </table>
    </div>
</body>
</html>
