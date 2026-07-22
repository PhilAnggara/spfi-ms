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
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
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
            background: #f3f4f6;
            font-weight: bold;
            font-size: 8px;
        }
        .po-items {
            font-size: 8px;
        }
        .po-items th,
        .po-items td {
            padding: 2px 3px;
            vertical-align: middle;
        }
        .po-items th {
            font-size: 7px;
        }
        .po-items .item-name {
            font-weight: bold;
            line-height: 1.15;
        }
        .po-items .item-meta {
            color: #4b5563;
            font-size: 6.5px;
            line-height: 1.1;
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
            font-size: 8px;
        }
        .signature-pad {
            height: 16px;
        }
        .signature-name {
            font-weight: bold;
            min-height: 12px;
            line-height: 1.2;
        }
        .signature-blank {
            border-bottom: 1px solid #111827;
            height: 12px;
            width: 85%;
            margin: 0 auto;
        }
        .note {
            font-size: 7px;
            line-height: 1;
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
        $termPayment = trim(($termValue ? $termValue . ' ' : '') . ($termType ?? ''));
        $termPayment = $termPayment !== '' ? $termPayment : '-';
        $firstPoItem = $purchaseOrder->items->first();
        $firstPoMeta = $firstPoItem?->meta ?? [];
        $isCapex = (bool) ($firstPoItem?->prsItem?->prs?->is_capex ?? ($firstPoMeta['is_capex'] ?? false));

        $approvalThreshold = (float) config('purchase-order.signature.approval_threshold', 4000000);
        $certifiedName = (string) config('purchase-order.signature.certified_by_name', 'Denny Tuhatelu');
        $approvedName = (float) $purchaseOrder->total >= $approvalThreshold
            ? (string) config('purchase-order.signature.approved_by_at_or_above_threshold_name', 'Sam Calamba')
            : (string) config('purchase-order.signature.approved_by_below_threshold_name', 'Denny Tuhatelu');

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
                    <th style="width: 52px;">PRS</th>
                    <th>Item</th>
                    <th style="width: 28px;" class="text-center">Dept</th>
                    <th style="width: 48px;" class="text-center">Qty</th>
                    <th style="width: 58px;" class="text-right">Price</th>
                    <th style="width: 32px;" class="text-right">Disc</th>
                    <th style="width: 32px;" class="text-right">PPN</th>
                    <th style="width: 32px;" class="text-right">PPh</th>
                    <th style="width: 62px;" class="text-right">Amount</th>
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
                    @endphp
                    <tr>
                        <td>{{ $prsNumber }}</td>
                        <td>
                            <div class="item-name">{{ $item->item?->name ?? '-' }}</div>
                            <div class="item-meta">{{ $itemCode }}</div>
                        </td>
                        <td class="text-center">{{ $dept }}</td>
                        <td class="text-center">{{ number_format($item->quantity, 0, ',', '.') }} {{ $unitName }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item->discount_rate ?? 0, 1, ',', '.') }}%</td>
                        <td class="text-right">{{ number_format($item->ppn_rate ?? 0, 1, ',', '.') }}%</td>
                        <td class="text-right">{{ number_format($item->pph_rate ?? 0, 1, ',', '.') }}%</td>
                        <td class="text-right">{{ number_format($item->total, 2, ',', '.') }}</td>
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
                        <table class="summary-table">
                            <tr>
                                <td class="summary-label">Amount</td>
                                <td class="summary-middle">{{ $currencyCode }}</td>
                                <td class="summary-amount">{{ number_format($purchaseOrder->subtotal, 2, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">Disc</td>
                                <td class="summary-middle">{{ $discRateDisplay }}</td>
                                <td class="summary-amount">{{ number_format($purchaseOrder->discount_amount ?? 0, 2, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">Withholding Tax (PPh)</td>
                                <td class="summary-middle">{{ $pphRateDisplay }}</td>
                                <td class="summary-amount">{{ number_format($purchaseOrder->pph_amount ?? 0, 2, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="summary-label">VAT (PPN)</td>
                                <td class="summary-middle">{{ $ppnRateDisplay }}</td>
                                <td class="summary-amount">{{ number_format($purchaseOrder->ppn_amount ?? 0, 2, ',', '.') }}</td>
                            </tr>
                            @if ($feeItems->isNotEmpty())
                                @foreach ($feeItems as $feeItem)
                                    <tr>
                                        <td class="summary-label">{{ $feeItem['type'] !== '' ? $feeItem['type'] : 'Additional charge' }}</td>
                                        <td class="summary-middle">{{ $currencyCode }}</td>
                                        <td class="summary-amount">{{ number_format($feeItem['amount'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="summary-label">Total Additional Charges</td>
                                    <td class="summary-middle">{{ $currencyCode }}</td>
                                    <td class="summary-amount">{{ number_format($purchaseOrder->fees ?? 0, 2, ',', '.') }}</td>
                                </tr>
                            @elseif ((float) $purchaseOrder->fees > 0)
                                <tr>
                                    <td class="summary-label">Additional Charges</td>
                                    <td class="summary-middle">{{ $currencyCode }}</td>
                                    <td class="summary-amount">{{ number_format($purchaseOrder->fees ?? 0, 2, ',', '.') }}</td>
                                </tr>
                            @endif
                            <tr class="summary-total">
                                <td class="summary-label">TOTAL</td>
                                <td class="summary-middle">{{ $currencyCode }}</td>
                                <td class="summary-amount">{{ number_format($purchaseOrder->total, 2, ',', '.') }}</td>
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
