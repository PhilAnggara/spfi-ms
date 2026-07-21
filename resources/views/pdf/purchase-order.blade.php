<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
    <style>
        @page { margin: 8px; }
        body {
            margin: 4px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }
        .header {
            display: block;
            margin-bottom: 8px;
            margin-top: 84px;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            display: none;
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
            font-size: 8px;
        }
        .po-items {
            font-size: 8px;
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
            font-size: 7px;
            line-height: 1;
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
        $termType = $purchaseOrder->term_of_payment_type ?? ($firstMeta['term_of_payment_type'] ?? null);
        $termValue = $purchaseOrder->term_of_payment ?? ($firstMeta['term_of_payment'] ?? null);
        $termPayment = trim(($termValue ? $termValue . ' ' : '') . ($termType ?? ''));
        $termPayment = $termPayment !== '' ? $termPayment : '-';
        $firstPoItem = $purchaseOrder->items->first();
        $firstPoMeta = $firstPoItem?->meta ?? [];
        $isCapex = (bool) ($firstPoItem?->prsItem?->prs?->is_capex ?? ($firstPoMeta['is_capex'] ?? false));
    @endphp

    <div class="header">
        <div class="title">PURCHASE ORDER</div>
    </div>

    <table class="table-clean">
        <tr>
            <td class="label">Supplier Name</td>
            <td>: {{ $supplier?->name ?? '-' }}{{ $supplier?->code ? ' | ' . $supplier->code : '' }}</td>
            <td class="label text-right"></td>
            <td class="text-right">&nbsp;</td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td colspan="3">: {{ $supplier?->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Term Payment</td>
            <td colspan="3">: @if($termValue || $termType)
                    @if($termValue){{ $termValue }}@endif
                    @if($termValue && $termType) &bull; @endif
                    @if($termType){{ $termType }}@endif
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Transaction Type</td>
            <td>: <strong>{{ $isCapex ? 'CAPEX' : 'Non-CAPEX' }}</strong></td>
            <td class="label text-right">PO Date</td>
            <td class="text-right">: {{ format_date($purchaseOrder->created_at) }}</td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="po-items">
        <thead>
            <tr>
                <th style="width: 60px;">PRS ID</th>
                <th>Item Name</th>
                <th style="width: 50px;">Item Code</th>
                <th style="width: 20px;" class="text-center">Dept</th>
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
                    $dept = $item->prsItem?->prs?->department?->code ?? '-';
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
                <ol style="margin:4px 0 0 16px; padding:0; font-size:7px; line-height:1;">
                    <li>PT. Sinar Pure Foods International, manajemen dan seluruh karyawan tidak menerima, gratifikasi, pungutan liar dan sejenisnya untuk memperlancar transaksi</li>
                    <li>PT. Sinar Pure Foods International, manajemen dan seluruh karyawan tidak melakukan mark-up harga dan sejenisnya.</li>
                </ol>
                <div class="note" style="margin-top:4px;">
                    Hal ini berlaku untuk seluruh supplier, pembeli, kontraktor, karyawan, maupun pemerintah dan pihak luar yang berhubungan dengan PT. Sinar Pure Foods International. Terima kasih untuk usaha anda membantu kami.
                </div>
                <div class="note" style="margin-top: 6px; font-size:8px; font-weight:bold;">
                    Delivery to PT Sinar Pure Foods International | (<strong>PO Number</strong> : {{ $purchaseOrder->po_number }})
                </div>
                @if(trim((string)($purchaseOrder->remark_text ?? '')) !== '')
                    <div style="margin-top:4px; font-size:7px;">
                        <strong>Remark:</strong> {{ $purchaseOrder->remark_text }}
                    </div>
                @endif
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
                        <span>Withholding Tax (PPh)</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->pph_amount ?? 0, 2, ',', '.') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>VAT (PPN)</span>
                        <span>{{ $currencyCode }} {{ number_format($purchaseOrder->ppn_amount ?? 0, 2, ',', '.') }}</span>
                    </div>
                    @php
                        $feeItems = collect($purchaseOrder->fees_breakdown ?? [])
                            ->filter(fn ($row) => is_array($row))
                            ->map(function (array $row) {
                                return [
                                    'type' => trim((string) ($row['type'] ?? '')),
                                    'amount' => (float) ($row['amount'] ?? 0),
                                ];
                            })
                            ->filter(fn (array $row) => $row['type'] !== '' || $row['amount'] > 0)
                            ->values();
                    @endphp
                    @if ($feeItems->isNotEmpty())
                        @foreach ($feeItems as $feeItem)
                            <div class="summary-row">
                                <span>{{ $feeItem['type'] !== '' ? $feeItem['type'] : 'Additional charge' }}</span>
                                <span>{{ $currencyCode }} {{ number_format($feeItem['amount'], 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                        <div class="summary-row">
                            <span>Total Additional Charges</span>
                            <span>{{ $currencyCode }} {{ number_format($purchaseOrder->fees ?? 0, 2, ',', '.') }}</span>
                        </div>
                    @elseif ((float) $purchaseOrder->fees > 0)
                        <div class="summary-row">
                            <span>Additional Charges</span>
                            <span>{{ $currencyCode }} {{ number_format($purchaseOrder->fees ?? 0, 2, ',', '.') }}</span>
                        </div>
                    @endif
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
                {{-- <td>{{ $purchaseOrder->submitted_at ? format_date($purchaseOrder->submitted_at) : '-' }}</td> --}}
                <td></td>
                <td class="signature-line">{{ $approved['name'] ?? $purchaseOrder->approvedBy?->name ?? '-' }}</td>
                {{-- <td>{{ $purchaseOrder->approved_at ? format_date($purchaseOrder->approved_at) : '-' }}</td> --}}
                <td></td>
            </tr>
            <tr>
                <td colspan="4" style="padding-top: 18px;">Supplier's Signature : ____________________________</td>
            </tr>
        </table>
    </div>
</body>
</html>
