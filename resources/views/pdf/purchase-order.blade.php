<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2933;
        }
        .header {
            margin-bottom: 16px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
        }
        .muted {
            color: #6b7280;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
        }
        .text-right {
            text-align: right;
        }
        .signatures {
            margin-top: 32px;
        }
        .signature-block {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }
        .signature-name {
            margin-top: 48px;
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $signatureMeta = $purchaseOrder->signature_meta ?? [];
        $certified = $signatureMeta['certified_by'] ?? null;
        $approved = $signatureMeta['approved_by'] ?? null;
    @endphp

    <div class="header">
        <div class="title">PURCHASE ORDER</div>
        <div class="muted">PO Number: {{ $purchaseOrder->po_number }}</div>
        <div class="muted">Date: {{ format_date($purchaseOrder->created_at) }}</div>
    </div>

    <table>
        <tr>
            <td><strong>Supplier</strong></td>
            <td>{{ $purchaseOrder->supplier?->name }}</td>
            <td><strong>Total</strong></td>
            <td class="text-right">{{ number_format($purchaseOrder->total, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Created By</strong></td>
            <td>{{ $purchaseOrder->createdBy?->name }}</td>
            <td><strong>Status</strong></td>
            <td>{{ $purchaseOrder->status }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->item?->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->item?->unit?->name ?? 'PCS' }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td class="text-right"><strong>Subtotal</strong></td>
            <td class="text-right" style="width: 140px;">{{ number_format($purchaseOrder->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"><strong>Tax</strong></td>
            <td class="text-right">{{ number_format($purchaseOrder->tax_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"><strong>Fees</strong></td>
            <td class="text-right">{{ number_format($purchaseOrder->fees, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right"><strong>Total</strong></td>
            <td class="text-right"><strong>{{ number_format($purchaseOrder->total, 2) }}</strong></td>
        </tr>
    </table>

    <div class="signatures">
        <div class="signature-block">
            <div class="muted">Certified by</div>
            <div class="signature-name">{{ $certified['name'] ?? $purchaseOrder->certifiedBy?->name ?? '-' }}</div>
            <div class="muted">{{ $certified['title'] ?? ($purchaseOrder->certifiedBy ? get_job_title($purchaseOrder->certifiedBy) : '-') }}</div>
        </div>
        <div class="signature-block" style="float: right;">
            <div class="muted">Approved by</div>
            <div class="signature-name">{{ $approved['name'] ?? $purchaseOrder->approvedBy?->name ?? '-' }}</div>
            <div class="muted">{{ $approved['title'] ?? ($purchaseOrder->approvedBy ? get_job_title($purchaseOrder->approvedBy) : '-') }}</div>
        </div>
    </div>
</body>
</html>
