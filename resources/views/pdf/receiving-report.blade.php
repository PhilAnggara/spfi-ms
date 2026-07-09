<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receiving Report</title>
    @php
        $baseWidthMm = 297;
        $baseHeightMm = 210;
        $pageWidthMm = $pageWidthMm ?? 215;
        $pageHeightMm = $pageHeightMm ?? 160;
        $scaleX = $pageWidthMm / $baseWidthMm;
        $scaleY = $pageHeightMm / $baseHeightMm;
        $sx = static fn (float $mm): float => round($mm * $scaleX, 2);
        $sy = static fn (float $mm): float => round($mm * $scaleY, 2);
        $mmX = static fn (float $mm): string => $sx($mm).'mm';
        $mmY = static fn (float $mm): string => $sy($mm).'mm';
        $fieldFontSize = round(13 * $scaleY, 1);
        $poNumberFontSize = round(22 * $scaleY, 1);
        $capexFontSize = round(20 * $scaleY, 1);
        $cellFontSize = round(12 * $scaleY, 1);
        $acctCellFontSize = round(11 * $scaleY, 1);
        $itemCellMaxHeight = round(8.8 * $scaleY, 2);
    @endphp
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
        }

        .rr-form-page {
            position: relative;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
            page-break-after: auto;
            overflow: hidden;
        }

        .rr-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $pageWidthMm }}mm;
            height: {{ $pageHeightMm }}mm;
        }

        .field {
            position: absolute;
            font-size: {{ $fieldFontSize }}px;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: bold;
        }

        .po-number {
            font-size: {{ $poNumberFontSize }}px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        .capex-label {
            font-size: {{ $capexFontSize }}px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .cell {
            position: absolute;
            font-size: {{ $cellFontSize }}px;
            line-height: 1.2;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .item-cell {
            white-space: normal;
            text-overflow: clip;
            line-height: 1;
            max-height: {{ $itemCellMaxHeight }}mm;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .acct-cell {
            position: absolute;
            font-size: {{ $acctCellFontSize }}px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

    </style>
</head>
<body>
    @php
        $po = $receivingReport->purchaseOrder;
        $rowStartTopMm = $sy(73);
        $rowHeightMm = $sy(8);
        $maxRows = 11;
        $rows = $receivingReport->items->take($maxRows)->values();
        $rowCount = $rows->count();
        $allItems = $receivingReport->items;
        $supplierName = trim((string) ($po?->supplier?->name ?? ''));
        $supplierCode = trim((string) ($po?->supplier?->code ?? ''));
        $supplierDisplay = $supplierName !== '' ? $supplierName : '-';
        if ($supplierCode !== '') {
            $supplierDisplay .= ' | '.$supplierCode;
        }

        $currencyConversion = $currencyConversion ?? [
            'po_currency_code' => strtoupper(trim((string) ($po?->currency?->code ?? 'IDR'))),
            'should_convert' => false,
            'rate_found' => false,
            'rate_to_idr' => null,
            'effective_date' => null,
            'multiplier' => 1.0,
            'rate_note' => null,
        ];
        $convertAmount = static function (float $amount) use ($currencyConversion): float {
            if (! ($currencyConversion['should_convert'] ?? false)) {
                return $amount;
            }

            return round($amount * (float) ($currencyConversion['multiplier'] ?? 1), 2);
        };

        $categoryAccountMap = [
            'OFFICE SUPPLIES' => '153',
            'PARTS' => '146',
            'SL/C' => '3047',
            'FACTORY SUPPLIES' => '145',
            'CHEM' => '4000',
            'FUEL' => '743',
            'LABEL' => '143',
            'CARTON' => '142',
            'INGREDIENTS' => '138',
            'CAN' => '140',
            'SPICES' => '138',
            'OTHERS' => '998',
            'RAW MATERIALS' => '137',
            'ZEBRA STOCK POT 80X80 CM' => '',
            'SPICES AND INGREDIENTS' => '138',
            'FISH' => '2003',
            'BC' => '',
            'FISHMEAL' => '257',
            'COAL' => '744',
            'SLUDGE OIL' => '745',
            'LABELING SUPPLIES' => '143',
            'CAPITAL GOODS' => '',
            'SCRAPS' => '',
            'FINISHED GOODS' => '129',
        ];

        $debitRows = [];
        $totalAmountAllItems = 0.0;
        $totalPpnAmountAllItems = 0.0;
        $totalPphAmountAllItems = 0.0;
        $creditAmountAllItems = 0.0;

        $resolveReceivedLineAmounts = function ($poItem, float $qtyTotal): array {
            $orderedQty = (float) ($poItem?->quantity ?? 0);
            $receivedRatio = $orderedQty > 0 ? min(1, max(0, $qtyTotal / $orderedQty)) : 0;
            $lineSubtotal = (float) ($poItem?->line_subtotal ?? ($orderedQty * (float) ($poItem?->unit_price ?? 0)));
            $discountAmount = (float) ($poItem?->discount_amount ?? 0);
            $netLineAmount = max(0, $lineSubtotal - $discountAmount);
            $baseAmount = $netLineAmount * $receivedRatio;
            $discountedUnitCost = $orderedQty > 0
                ? $netLineAmount / $orderedQty
                : (float) ($poItem?->unit_price ?? 0);
            $ppnAmount = (float) ($poItem?->ppn_amount ?? 0) * $receivedRatio;
            $pphAmount = (float) ($poItem?->pph_amount ?? 0) * $receivedRatio;

            return [
                'ordered_qty' => $orderedQty,
                'discounted_unit_cost' => $discountedUnitCost,
                'base_amount' => $baseAmount,
                'ppn_amount' => $ppnAmount,
                'pph_amount' => $pphAmount,
                'line_total' => $baseAmount + $ppnAmount - $pphAmount,
            ];
        };

        $isCapex = (bool) ($po?->items?->first()?->prsItem?->prs?->is_capex ?? false);

        foreach ($allItems as $rrItem) {
            $poItem = $rrItem->purchaseOrderItem;
            $item = $poItem?->item;

            $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
            $lineAmounts = $resolveReceivedLineAmounts($poItem, $qtyTotal);
            $amount = $convertAmount($lineAmounts['base_amount']);
            $ppnAmount = $convertAmount($lineAmounts['ppn_amount']);
            $pphAmount = $convertAmount($lineAmounts['pph_amount']);
            $lineTotal = $convertAmount($lineAmounts['line_total']);
            $totalAmountAllItems += $amount;
            $totalPpnAmountAllItems += $ppnAmount;
            $totalPphAmountAllItems += $pphAmount;
            $creditAmountAllItems += $lineTotal;

            $categoryName = strtoupper(trim((string) ($item?->category?->name ?? 'OTHERS')));
            $costCenterCategories = ['CHEM', 'SL/C'];
            $costCenter = in_array($categoryName, $costCenterCategories, true)
                ? trim((string) ($poItem?->prsItem?->prs?->department?->code ?? ''))
                : '';
            $debitAccount = $isCapex
                ? '169'
                : ($categoryAccountMap[$categoryName] ?? ($categoryAccountMap['OTHERS'] ?? ''));

            $groupKey = $costCenter.'|'.$debitAccount;
            if (! isset($debitRows[$groupKey])) {
                $debitRows[$groupKey] = [
                    'cost_center' => $costCenter,
                    'account' => $debitAccount,
                    'debit' => 0.0,
                    'credit' => null,
                ];
            }

            $debitRows[$groupKey]['debit'] += $amount;
        }

        $termOfPaymentType = collect($allItems)
            ->map(fn ($rrItem) => strtolower(trim((string) data_get($rrItem, 'purchaseOrderItem.meta.term_of_payment_type', ''))))
            ->first(fn ($value) => $value !== '');

        $creditAccount = match ($termOfPaymentType) {
            'credit' => '201',
            'cash' => '148',
            default => '',
        };

        $accountingEntries = collect($debitRows)
            ->values()
            ->sortBy(function (array $row) {
                return sprintf('%s|%s', (string) ($row['cost_center'] ?? ''), (string) ($row['account'] ?? ''));
            })
            ->values()
            ->all();

        if ($totalPpnAmountAllItems > 0) {
            $accountingEntries[] = [
                'cost_center' => '',
                'account' => '551',
                'debit' => $totalPpnAmountAllItems,
                'credit' => null,
            ];
        }

        if ($totalPphAmountAllItems > 0) {
            $accountingEntries[] = [
                'cost_center' => '',
                'account' => '206',
                'debit' => null,
                'credit' => $totalPphAmountAllItems,
            ];
        }

        $accountingEntries[] = [
            'cost_center' => '',
            'account' => $creditAccount,
            'debit' => null,
            'credit' => $creditAmountAllItems,
        ];

        $accountingCodeTotal = collect($accountingEntries)
            ->sum(function (array $entry) {
                $accountCode = trim((string) ($entry['account'] ?? ''));

                return is_numeric($accountCode) ? (int) $accountCode : 0;
            });

        $displaySubTotal = $totalAmountAllItems;
        $displayPpnTotal = $totalPpnAmountAllItems;
        $displayPphTotal = $totalPphAmountAllItems;
        $displayPpnRate = (float) collect($allItems)
            ->map(fn ($rrItem) => (float) ($rrItem->purchaseOrderItem?->ppn_rate ?? 0))
            ->first(fn ($rate) => $rate > 0);
        $displayPphRate = (float) collect($allItems)
            ->map(fn ($rrItem) => (float) ($rrItem->purchaseOrderItem?->pph_rate ?? 0))
            ->first(fn ($rate) => $rate > 0);
        $formatTaxRate = static function (float $rate): string {
            return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        };
        $hasPph = $displayPphTotal > 0;
        $hasPpn = $displayPpnTotal > 0;
        $showSubTotal = $rowCount > 1;
        $showFinalTotal = $hasPpn || $hasPph;
        $subTotalPlusPpn = $displaySubTotal + $displayPpnTotal;
        $displayGrandTotal = $subTotalPlusPpn - $displayPphTotal;
        $summaryBaseTop = $rowCount > 0 ? $rowStartTopMm + ($rowCount * $rowHeightMm) + $sy(0.8) : 0;
        $subTotalTop = $summaryBaseTop + $sy(1.2);
        $ppnTop = $subTotalTop + $sy(5);
        $intermediateTop = ($displayPpnTotal > 0 ? $ppnTop : $subTotalTop) + $sy(5);
        $pphTop = $intermediateTop + $sy(5);
        $summaryTotalLineTop = ($hasPph ? $pphTop : ($displayPpnTotal > 0 ? $ppnTop : $subTotalTop)) + $sy(4.2);
        $summaryTotalTop = $summaryTotalLineTop + $sy(1.2);
        $poDateText = $po?->created_at ? $po->created_at->locale('id')->translatedFormat('d M Y') : '-';
        $rrDateText = $receivingReport->created_at ? $receivingReport->created_at->locale('id')->translatedFormat('d M Y') : '-';
    @endphp

    <div class="rr-form-page">
        @if($isPreview && !empty($backgroundImageDataUri))
            <img src="{{ $backgroundImageDataUri }}" alt="" class="rr-bg">
        @endif

        @if ($isCapex)
            <div class="cell capex-label" style="left: {{ $mmX(15) }}; top: {{ $mmY(33) }}; width: {{ $mmX(40) }};">CAPEX</div>
        @endif

        @if ($isPreview)
            @php $rrNumberText = trim((string) ($receivingReport->rr_number ?? '')); @endphp
            @if ($rrNumberText !== '')
                <div class="field po-number" style="left: {{ $mmX(207) }}; top: {{ $mmY(28) }}; width: {{ $mmX(55) }}; text-align: right;">{{ $rrNumberText }}</div>
            @endif
        @endif

        <div class="field" style="left: {{ $mmX(37) }}; top: {{ $mmY(41) }}; width: {{ $mmX(100) }};">{{ $supplierDisplay }}</div>
        <div class="field po-number" style="left: {{ $mmX(161) }}; top: {{ $mmY(38) }}; width: {{ $mmX(48) }};">{{ $po?->po_number ?? '-' }}</div>
        <div class="field" style="left: {{ $mmX(160) }}; top: {{ $mmY(49) }}; width: {{ $mmX(48) }};">{{ $poDateText }}</div>

        @foreach($rows as $index => $rrItem)
            @php
                $poItem = $rrItem->purchaseOrderItem;
                $item = $poItem?->item;
                $departmentCode = $poItem?->prsItem?->prs?->department?->code ?? '-';
                $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
                $lineAmounts = $resolveReceivedLineAmounts($poItem, $qtyTotal);
                $unitCost = $convertAmount($lineAmounts['discounted_unit_cost']);
                $amount = $convertAmount($lineAmounts['base_amount']);
                $top = $rowStartTopMm + ($index * $rowHeightMm);
            @endphp

            <div class="cell item-cell" style="left: {{ $mmX(15) }}; top: {{ $top }}mm; width: {{ $mmX(65) }};">{{ $item?->name ?? '-' }}</div>
            <div class="cell center" style="left: {{ $mmX(83) }}; top: {{ $top }}mm; width: {{ $mmX(20) }};">{{ $item?->code ?? '-' }}</div>
            <div class="cell center" style="left: {{ $mmX(108) }}; top: {{ $top }}mm; width: {{ $mmX(25) }};">{{ $departmentCode }}</div>
            <div class="cell right" style="left: {{ $mmX(138) }}; top: {{ $top }}mm; width: {{ $mmX(23) }};">{{ number_format($qtyTotal, 2, '.', ',') }}</div>
            <div class="cell center" style="left: {{ $mmX(163) }}; top: {{ $top }}mm; width: {{ $mmX(23) }};">{{ $item?->unit?->name ?? 'PCS' }}</div>
            <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $top }}mm; width: {{ $mmX(35) }};">{{ number_format($unitCost, 2, '.', ',') }}</div>
            <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $top }}mm; width: {{ $mmX(50) }};">{{ number_format($amount, 2, '.', ',') }}</div>
        @endforeach

        @if ($rowCount > 0)
            @if ($showSubTotal)
                <div style="position: absolute; left: {{ $mmX(189) }}; top: {{ $summaryBaseTop }}mm; width: {{ $mmX(89) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $subTotalTop }}mm; width: {{ $mmX(35) }}; font-weight: bold;">Sub Total</div>
                <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $subTotalTop }}mm; width: {{ $mmX(50) }}; font-weight: bold;">{{ number_format($displaySubTotal, 2, '.', ',') }}</div>
            @endif
            @if ($hasPpn)
                <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $ppnTop }}mm; width: {{ $mmX(35) }}; font-weight: bold;">PPn {{ $formatTaxRate($displayPpnRate) }}%</div>
                <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $ppnTop }}mm; width: {{ $mmX(50) }}; font-weight: bold;">{{ number_format($displayPpnTotal, 2, '.', ',') }}</div>
            @endif
            @if ($hasPph)
                <div style="position: absolute; left: {{ $mmX(228) }}; top: {{ $intermediateTop - $sy(1.2) }}mm; width: {{ $mmX(50) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $intermediateTop }}mm; width: {{ $mmX(50) }}; font-weight: bold;">{{ number_format($subTotalPlusPpn, 2, '.', ',') }}</div>
                <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $pphTop }}mm; width: {{ $mmX(35) }}; font-weight: bold;">PPh {{ $formatTaxRate($displayPphRate) }}%</div>
                <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $pphTop }}mm; width: {{ $mmX(50) }}; font-weight: bold;">{{ number_format($displayPphTotal, 2, '.', ',') }}</div>
            @endif
            @if ($showFinalTotal)
                <div style="position: absolute; left: {{ $mmX(228) }}; top: {{ $summaryTotalTop - $sy(1.2) }}mm; width: {{ $mmX(50) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $summaryTotalTop }}mm; width: {{ $mmX(35) }}; font-weight: bold;">Total</div>
                <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $summaryTotalTop }}mm; width: {{ $mmX(50) }}; font-weight: bold;">{{ number_format($hasPph ? $displayGrandTotal : $subTotalPlusPpn, 2, '.', ',') }}</div>
            @endif
        @endif

        @php
            $entryStartTop = $sy(166);
            $entryRowHeight = $sy(5.2);
            $entryRows = collect($accountingEntries)->take(6)->values();
            $totalLineTop = $entryStartTop + ($entryRows->count() * $entryRowHeight) - $sy(1.2);
            $totalEntryTop = $entryStartTop + ($entryRows->count() * $entryRowHeight) - $sy(0.9);
        @endphp

        @if (! empty($currencyConversion['rate_note']))
            <div class="field" style="left: {{ $mmX(16) }}; top: {{ $mmY(157) }}; width: {{ $mmX(120) }}; font-size: {{ round(10 * $scaleY, 1) }}px; white-space: normal;">
                {{ $currencyConversion['rate_note'] }}
            </div>
        @elseif (($currencyConversion['po_currency_code'] ?? 'IDR') === 'USD' && empty($currencyConversion['rate_found']))
            <div class="field" style="left: {{ $mmX(16) }}; top: {{ $mmY(157) }}; width: {{ $mmX(120) }}; font-size: {{ round(10 * $scaleY, 1) }}px; white-space: normal;">
                No USD exchange rate available.
            </div>
        @endif

        @foreach($entryRows as $entryIndex => $entry)
            @php $entryTop = $entryStartTop + ($entryIndex * $entryRowHeight); @endphp
            <div class="acct-cell" style="left: {{ $mmX(16) }}; top: {{ $entryTop }}mm; width: {{ $mmX(20) }};">{{ $entry['cost_center'] !== '' ? $entry['cost_center'] : '' }}</div>
            <div class="acct-cell" style="left: {{ $mmX(30) }}; top: {{ $entryTop }}mm; width: {{ $mmX(20) }};">{{ $entry['account'] !== '' ? $entry['account'] : '' }}</div>
            <div class="acct-cell right" style="left: {{ $mmX(40) }}; top: {{ $entryTop }}mm; width: {{ $mmX(30) }};">{{ $entry['debit'] !== null ? number_format((float) $entry['debit'], 2, '.', ',') : '' }}</div>
            <div class="acct-cell right" style="left: {{ $mmX(70) }}; top: {{ $entryTop }}mm; width: {{ $mmX(30) }};">{{ $entry['credit'] !== null ? number_format((float) $entry['credit'], 2, '.', ',') : '' }}</div>
        @endforeach

        <div style="position: absolute; left: {{ $mmX(30) }}; top: {{ $totalLineTop }}mm; width: {{ $mmX(12) }}; border-top: 1px solid #111827;"></div>
        <div class="acct-cell" style="left: {{ $mmX(30) }}; top: {{ $totalEntryTop }}mm; width: {{ $mmX(12) }}; font-weight: bold;">{{ $accountingCodeTotal }}</div>

        <div class="field center" style="left: {{ $mmX(175) }}; top: {{ $mmY(168) }}; width: {{ $mmX(47) }};">{{ $receivingReport->createdBy?->name ?? '-' }}</div>
        <div class="field center" style="left: {{ $mmX(233) }}; top: {{ $mmY(168) }}; width: {{ $mmX(45) }};">{{ $approvedByName ?? '-' }}</div>
        <div class="field center" style="left: {{ $mmX(235) }}; top: {{ $mmY(185) }}; width: {{ $mmX(45) }};">{{ $rrDateText }}</div>
    </div>
</body>
</html>
