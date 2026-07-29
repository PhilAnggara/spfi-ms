<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
    <style>
        @page {
            size: {{ $pageWidthMm ?? 215 }}mm {{ $pageHeightMm ?? 160 }}mm;
            margin: 8px;
        }
        body {
            margin: 4px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .po-main {
            /* Reserve space so item rows do not collide with fixed footer */
            padding-bottom: 52mm;
        }
        .po-footer {
            position: fixed;
            left: 4px;
            right: 4px;
            bottom: 4px;
            width: auto;
            page-break-inside: avoid;
        }
        .header {
            display: block;
            margin-bottom: 8px;
            margin-top: 90px;
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
            background: none;
            font-weight: bold;
            font-size: 10px;
        }
        .po-supplier {
            font-size: 11px;
            font-weight: normal;
            line-height: 1.15;
        }
        .po-supplier td {
            padding: 0;
            line-height: 1.15;
        }
        .po-supplier .label {
            font-weight: bold;
        }
        .po-items {
            font-size: 11px;
            font-weight: normal;
            margin-top: 6px;
            table-layout: auto;
        }
        .po-items th,
        .po-items td {
            padding: 3px 4px;
            vertical-align: middle;
        }
        .po-items th {
            font-size: 10px;
            font-weight: bold;
        }
        .po-items .item-name {
            font-weight: normal;
            font-size: 11px;
            line-height: 1.2;
        }
        .po-items .col-item-code,
        .po-items .col-unit {
            width: 1%;
            text-align: center;
            white-space: nowrap;
        }
        .po-items .col-qty {
            /* Size to content; stay on one line by default */
            width: 1%;
            text-align: center;
            white-space: nowrap;
        }
        .po-items .col-qty-wrap {
            /* Only very long qty values may wrap */
            white-space: normal;
            max-width: 28mm;
            word-break: break-word;
        }
        .po-delivery {
            margin-top: 4px;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.35;
        }
        .po-delivery .po-number {
            font-size: 12px;
            letter-spacing: 0.3px;
        }
        .po-items .col-price,
        .po-items .col-amount {
            white-space: nowrap;
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
            padding: 4px 6px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: none;
            padding: 2px 0;
            vertical-align: middle;
        }
        .summary-table .summary-label {
            width: 42%;
            text-align: left;
            white-space: nowrap;
        }
        .summary-table .summary-middle {
            width: 18%;
            text-align: center;
            white-space: nowrap;
        }
        .summary-table .summary-amount {
            width: 40%;
            text-align: right;
            white-space: nowrap;
        }
        .summary-total td {
            border-top: 1px solid #111827;
            margin-top: 4px;
            padding-top: 4px;
            font-weight: bold;
        }
        .signatures {
            margin-top: 6px;
        }
        .signature-table {
            width: 100%;
            table-layout: fixed;
        }
        .signature-table td {
            border: none;
            padding: 0 8px 0 0;
            vertical-align: top;
            width: 33.33%;
            text-align: center;
        }
        .signature-table td:last-child {
            padding-right: 0;
        }
        .signature-label {
            font-size: 9px;
        }
        .signature-pad {
            height: 16px;
        }
        .signature-name {
            font-weight: bold;
            min-height: 12px;
            line-height: 1.2;
            font-size: 10px;
        }
        .signature-blank {
            border-bottom: 1px solid #111827;
            height: 12px;
            width: 85%;
            margin: 0 auto;
        }
        .note {
            font-size: 8px;
            line-height: 1.15;
        }
    </style>
</head>
<body>
    @php
        $currency = $purchaseOrder->currency;
        $currencyCode = $currency?->code ?? 'IDR';
        $supplier = $purchaseOrder->supplier;
        $firstItem = $purchaseOrder->items->first();
        $firstMeta = $firstItem?->meta ?? [];
        $termType = $purchaseOrder->term_of_payment_type ?? ($firstMeta['term_of_payment_type'] ?? null);
        $termValue = $purchaseOrder->term_of_payment ?? ($firstMeta['term_of_payment'] ?? null);
        $termTypeDisplay = $termType ? strtoupper((string) $termType) : null;
        $firstPoItem = $purchaseOrder->items->first();
        $firstPoMeta = $firstPoItem?->meta ?? [];
        $isCapex = (bool) ($firstPoItem?->prsItem?->prs?->is_capex ?? ($firstPoMeta['is_capex'] ?? false));
        $decimalPlaces = (int) ($decimalPlaces ?? config('purchase-order.print.decimal_places.default', 2));
        $formatMoney = fn ($amount) => number_format((float) $amount, $decimalPlaces, ',', '.');

        $certifiedName = $purchaseOrder->printCertifiedByName();
        $approvedName = $purchaseOrder->printApprovedByName();

        $uniqueRate = function (string $field) use ($purchaseOrder): ?float {
            $rates = $purchaseOrder->items
                ->map(fn ($item) => round((float) ($item->{$field} ?? 0), 2))
                ->unique()
                ->values();

            return $rates->count() === 1 ? (float) $rates->first() : null;
        };

        $formatRate = function (?float $rate): string {
            if ($rate === null) {
                return '-';
            }

            return rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',') . ' %';
        };

        $discRateDisplay = $formatRate($uniqueRate('discount_rate'));
        $ppnRateDisplay = $formatRate($uniqueRate('ppn_rate'));
        $pphRateDisplay = $formatRate($uniqueRate('pph_rate'));

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

    <div class="po-main">
        <div class="header">
            <div class="title">PURCHASE ORDER</div>
        </div>

        <table class="table-clean po-supplier">
            <tr>
                <td class="label">Supplier Name</td>
                <td>: <strong>{{ $supplier?->name ?? '-' }}</strong>{{ $supplier?->code ? ' | ' . $supplier->code : '' }}</td>
                <td class="label text-right"></td>
                <td class="text-right">&nbsp;</td>
            </tr>
            <tr>
                <td class="label">Address</td>
                <td colspan="3">: {{ $supplier?->address ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Term Payment</td>
                <td colspan="3">: @if($termTypeDisplay || $termValue){{ trim(implode(' • ', array_filter([$termTypeDisplay, $termValue]))) }}@else-@endif</td>
            </tr>
            <tr>
                <td class="label">Transaction Type</td>
                <td>: <strong>{{ $isCapex ? 'CAPEX' : 'Non-CAPEX' }}</strong></td>
                <td class="label text-right">PO Date</td>
                <td class="text-right">: {{ format_date($purchaseOrder->created_at) }}</td>
            </tr>
        </table>

        <table class="po-items">
            <thead>
                <tr>
                    <th style="width: 58px;">PRS</th>
                    <th>Item Name</th>
                    <th class="col-item-code">Item Code</th>
                    <th style="width: 36px;" class="text-center">Dept</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-unit">Unit</th>
                    <th style="width: 78px;" class="text-right col-price">Price</th>
                    <th style="width: 86px;" class="text-right col-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->items as $item)
                    @php
                        $meta = $item->meta ?? [];
                        $prsNumber = $meta['prs_number'] ?? $item->prsItem?->prs?->prs_number ?? '-';
                        $dept = $item->prsItem?->prs?->department?->code ?? '-';
                        $itemCode = $item->item?->code ?? '-';
                        $unitName = $item->item?->unit?->code ?? $item->item?->unit?->name ?? 'PCS';
                        $qtyDisplay = \App\Support\PdfFormatters::qty($item->quantity);
                        $qtyIsVeryLong = mb_strlen($qtyDisplay) > 12;
                    @endphp
                    <tr>
                        <td>{{ $prsNumber }}</td>
                        <td>
                            <div class="item-name">{{ $item->item?->name ?? '-' }}</div>
                        </td>
                        <td class="col-item-code">{{ $itemCode }}</td>
                        <td class="text-center">{{ $dept }}</td>
                        <td class="col-qty{{ $qtyIsVeryLong ? ' col-qty-wrap' : '' }}">{{ $qtyDisplay }}</td>
                        <td class="col-unit">{{ $unitName }}</td>
                        <td class="text-right col-price">{{ $formatMoney($item->unit_price) }}</td>
                        <td class="text-right col-amount">{{ $formatMoney($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="po-footer">
        <table class="table-clean">
            <tr>
                <td style="width: 60%;">
                    <div class="note">
                        Untuk menciptakan kode etik bisnis yang adil, jujur dan produktif, PT. Sinar Pure Foods International menerapkan kebijakan antikorupsi dan anti-siap dalam setiap transaksi bisnis.
                    </div>
                    <ol style="margin:4px 0 0 16px; padding:0; font-size:8px; line-height:1.15;">
                        <li>PT. Sinar Pure Foods International, manajemen dan seluruh karyawan tidak menerima, gratifikasi, pungutan liar dan sejenisnya untuk memperlancar transaksi</li>
                        <li>PT. Sinar Pure Foods International, manajemen dan seluruh karyawan tidak melakukan mark-up harga dan sejenisnya.</li>
                    </ol>
                    <div class="note" style="margin-top:4px;">
                        Hal ini berlaku untuk seluruh supplier, pembeli, kontraktor, karyawan, maupun pemerintah dan pihak luar yang berhubungan dengan PT. Sinar Pure Foods International. Terima kasih untuk usaha anda membantu kami.
                    </div>
                    <div class="po-delivery">
                        Delivery to PT Sinar Pure Foods International | <span style="font-weight: normal;">PO Number</span>: <span class="po-number">{{ $purchaseOrder->po_number }}</span>
                    </div>
                    @if(trim((string)($purchaseOrder->remark_text ?? '')) !== '')
                        <div style="margin-top:4px; font-size:8px;">
                            <strong>Remark:</strong> {{ $purchaseOrder->remark_text }}
                        </div>
                    @endif
                </td>
                <td style="width: 40%;">
                    <div class="summary-box">
                        <table class="summary-table">
                            <tr>
                                <td class="summary-label">Amount</td>
                                <td class="summary-middle">{{ $currencyCode }}</td>
                                <td class="summary-amount">{{ $formatMoney($purchaseOrder->subtotal) }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">Disc</td>
                                <td class="summary-middle">{{ $discRateDisplay }}</td>
                                <td class="summary-amount">{{ $formatMoney($purchaseOrder->discount_amount ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">Withholding Tax (PPh)</td>
                                <td class="summary-middle">{{ $pphRateDisplay }}</td>
                                <td class="summary-amount">{{ $formatMoney($purchaseOrder->pph_amount ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">VAT (PPN)</td>
                                <td class="summary-middle">{{ $ppnRateDisplay }}</td>
                                <td class="summary-amount">{{ $formatMoney($purchaseOrder->ppn_amount ?? 0) }}</td>
                            </tr>
                            @if ($feeItems->isNotEmpty())
                                @foreach ($feeItems as $feeItem)
                                    <tr>
                                        <td class="summary-label">{{ $feeItem['type'] !== '' ? $feeItem['type'] : 'Additional charge' }}</td>
                                        <td class="summary-middle">{{ $currencyCode }}</td>
                                        <td class="summary-amount">{{ $formatMoney($feeItem['amount']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="summary-label">Total Additional Charges</td>
                                    <td class="summary-middle">{{ $currencyCode }}</td>
                                    <td class="summary-amount">{{ $formatMoney($purchaseOrder->fees ?? 0) }}</td>
                                </tr>
                            @elseif ((float) $purchaseOrder->fees > 0)
                                <tr>
                                    <td class="summary-label">Additional Charges</td>
                                    <td class="summary-middle">{{ $currencyCode }}</td>
                                    <td class="summary-amount">{{ $formatMoney($purchaseOrder->fees ?? 0) }}</td>
                                </tr>
                            @endif
                            <tr class="summary-total">
                                <td class="summary-label">TOTAL</td>
                                <td class="summary-middle">{{ $currencyCode }}</td>
                                <td class="summary-amount">{{ $formatMoney($purchaseOrder->total) }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="signatures">
            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-label">Certified by</div>
                        <div class="signature-pad"></div>
                        <div class="signature-name">{{ $certifiedName }}</div>
                    </td>
                    <td>
                        <div class="signature-label">Approved by</div>
                        <div class="signature-pad"></div>
                        <div class="signature-name">{{ $approvedName }}</div>
                    </td>
                    <td>
                        <div class="signature-label">Supplier's Signature</div>
                        <div class="signature-pad"></div>
                        <div class="signature-blank"></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
