<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
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
        .po-items {
            font-size: 9px;
        }
        .table-clean th,
        .table-clean td {
            border: none;
            padding: 2px 0;
        }
        .table-summary td {
            border: none;
            padding: 2px 0;
        }
        .label {
            width: 120px;
            white-space: nowrap;
            font-weight: bold;
        }
        .summary-box {
            border: 1px solid #111827;
            padding: 6px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .summary-row span {
            display: inline-block;
        }
        .summary-total {
            border-top: 1px solid #111827;
            margin-top: 4px;
            padding-top: 4px;
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
    </style>
</head>
<body>
    @php
        $signatureMeta = $purchaseOrder->signature_meta ?? [];
        $certified = $signatureMeta['certified_by'] ?? null;
        $approved = $signatureMeta['approved_by'] ?? null;
        $currency = $purchaseOrder->currency;
        $currencyCode = $currency?->code ?? 'IDR';
        $supplier = $purchaseOrder->supplier;
        $firstItem = $purchaseOrder->items->first();
        $firstMeta = $firstItem?->meta ?? [];
        $termType = $firstMeta['term_of_payment_type'] ?? null;
        $termValue = $firstMeta['term_of_payment'] ?? null;
        $termPayment = trim(($termValue ? $termValue . ' ' : '') . ($termType ?? ''));
        $termPayment = $termPayment !== '' ? $termPayment : '-';
    @endphp

    <div class="header">
        <div class="title">PURCHASE ORDER</div>
    </div>

    <table class="table-clean">
        <tr>
            <td class="label">Supplier Name</td>
            <td>: {{ $supplier?->name ?? '-' }}{{ $supplier?->code ? ' | ' . $supplier->code : '' }}</td>
            <td class="label text-right">PO Date</td>
            <td class="text-right">: {{ format_date($purchaseOrder->created_at) }}</td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td colspan="3">: {{ $supplier?->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Term Payment</td>
            <td colspan="3">: {{ $termPayment }}</td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="po-items">
        <thead>
            <tr>
                <th style="width: 60px;">PRS ID</th>
                <th>Item Name</th>
                <th style="width: 55px;">Item Code</th>
                <th style="width: 55px;">Dept</th>
                <th style="width: 40px;" class="text-center">Qty</th>
                <th style="width: 40px;" class="text-center">Unit</th>
                <th style="width: 70px;" class="text-right">Unit/price</th>
                <th style="width: 40px;" class="text-right">Disc %</th>
                <th style="width: 40px;" class="text-right">PPN %</th>
                <th style="width: 40px;" class="text-right">PPh %</th>
                <th style="width: 70px;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                @php
                    $meta = $item->meta ?? [];
                    $prsNumber = $meta['prs_number'] ?? $item->prsItem?->prs?->prs_number ?? '-';
                    $dept = $item->prsItem?->prs?->department?->name ?? '-';
                    $itemCode = $item->item?->code ?? '-';
                    $unitName = $item->item?->unit?->name ?? 'PCS';
                @endphp
                <tr>
                    <td>{{ $prsNumber }}</td>
                    <td>{{ $item->item?->name ?? '-' }}</td>
                    <td>{{ $itemCode }}</td>
                    <td>{{ $dept }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $unitName }}</td>
                    <td class="text-right">{{ $currencyCode }} {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->discount_rate ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->ppn_rate ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->pph_rate ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ $currencyCode }} {{ number_format($item->total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="table-clean" style="margin-top: 10px;">
        <tr>
            <td style="width: 60%;">
                <div class="note">
                    Untuk menciptakan kode etik bisnis yang adil, jujur dan produktif, PT. Sinar Pure Foods International menerapkan kebijakan antikorupsi dan anti-siap dalam setiap transaksi bisnis.
                </div>
                <div class="note" style="margin-top: 4px;">
                    Delivery to PT Sinar Pure Foods International
                </div>
                <div style="margin-top: 6px;">
                    <strong>PO Number</strong> : {{ $purchaseOrder->po_number }}
                </div>
            </td>
            <td style="width: 40%;">
                <div class="summary-box">
                    <div class="summary-row">
                        <span>Amount</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Disc</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->discount_amount ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>PPH</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->pph_amount ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>PPN/VAT</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->ppn_amount ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>Fees</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->fees ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>TOTAL</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="signatures">
        <table class="signature-table" style="width: 100%;">
            <tr>
                <td style="width: 22%;">Certified by</td>
                <td style="width: 28%;">Date Certified</td>
                <td style="width: 22%;">Approved by</td>
                <td style="width: 28%;">Date Approved</td>
            </tr>
            <tr>
                <td class="signature-line">{{ $certified['name'] ?? $purchaseOrder->certifiedBy?->name ?? '-' }}</td>
                <td>{{ $purchaseOrder->submitted_at ? format_date($purchaseOrder->submitted_at) : '-' }}</td>
                <td class="signature-line">{{ $approved['name'] ?? $purchaseOrder->approvedBy?->name ?? '-' }}</td>
                <td>{{ $purchaseOrder->approved_at ? format_date($purchaseOrder->approved_at) : '-' }}</td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top: 18px;">Supplier's Signature : ____________________________</td>
            </tr>
        </table>
    </div>
</body>
</html>
