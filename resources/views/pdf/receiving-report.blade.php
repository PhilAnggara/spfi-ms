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
        $offsetXMm = (float) ($offsetXMm ?? config('receiving-report.offset_x_mm', 0));
        $offsetYMm = (float) ($offsetYMm ?? config('receiving-report.offset_y_mm', 0));
        $scaleX = $pageWidthMm / $baseWidthMm;
        $scaleY = $pageHeightMm / $baseHeightMm;
        $sx = static fn (float $mm): float => round($mm * $scaleX, 2);
        $sy = static fn (float $mm): float => round($mm * $scaleY, 2);
        // Widths use scale only; left/top include global form offset.
        $mmW = static fn (float $mm): string => $sx($mm).'mm';
        $mmX = static fn (float $mm): string => round($sx($mm) + $offsetXMm, 2).'mm';
        $mmY = static fn (float $mm): string => round($sy($mm) + $offsetYMm, 2).'mm';
        $oy = static fn (float $mm): string => round($mm + $offsetYMm, 2).'mm';
        $fieldFontSize = round(15.5 * $scaleY, 1);
        $poNumberFontSize = round(24 * $scaleY, 1);
        $capexFontSize = round(22 * $scaleY, 1);
        $cellFontSize = round(16 * $scaleY, 1);
        $acctCellFontSize = round(15.5 * $scaleY, 1);

        // --- Item name column width (base coords on 297mm design width) ---
        // Widen here, then keep code column from overlapping (nameLeft + nameWidth + gap).
        $itemNameLeftBaseMm = 15;
        $itemNameWidthBaseMm = 65; // <-- ubah ini untuk melebarkan kolom nama item
        $itemCodeLeftBaseMm = 83; // geser ke kanan jika nameWidth diperbesar
        $itemNameWidthMm = $sx($itemNameWidthBaseMm);

        // --- Accounting entry column layout (base coords on 297mm design width) ---
        $acctCostCenterLeftBaseMm = 16;
        $acctCostCenterWidthBaseMm = 20;
        $acctAccountLeftBaseMm = 30;
        $acctAccountWidthBaseMm = 18;
        $acctDebitLeftBaseMm = 50;
        $acctDebitWidthBaseMm = 30;
        $acctCreditLeftBaseMm = 105;
        $acctCreditWidthBaseMm = 30;

        // --- Row spacing ---
        // 1) Dalam 1 item (nama wrap): line-height CSS.
        $itemLineHeight = 1.12;
        $wrapStrideMm = round(($cellFontSize * $itemLineHeight) * (25.4 / 72), 2);
        // 2) Antar item 1-baris: tinggi glyph + gap (sudah pas).
        $glyphHeightMm = round(($cellFontSize * 0.72) * (25.4 / 72), 2);
        $interItemGapMm = 0.35;
        // 3) Antar item multi-baris: tarik item berikutnya (sisa line-box terakhir membuat jarak terlalu jauh).
        $multiLineTailPackMm = round(($cellFontSize * 0.38) * (25.4 / 72), 2);
        $minRowHeightMm = $glyphHeightMm + $interItemGapMm;
        $rowHeightForLines = static function (int $lineCount) use ($glyphHeightMm, $wrapStrideMm, $interItemGapMm, $multiLineTailPackMm, $minRowHeightMm): float {
            $lineCount = max(1, $lineCount);
            $height = $glyphHeightMm + (($lineCount - 1) * $wrapStrideMm) + $interItemGapMm;
            if ($lineCount > 1) {
                $height -= $multiLineTailPackMm;
            }

            return round(max($minRowHeightMm, $height), 2);
        };
        // Courier is monospace (~0.6em wide); estimate chars that fit the item name column.
        $itemNameCharsPerLine = max(12, (int) floor(($itemNameWidthMm * (72 / 25.4)) / max(1.0, $cellFontSize * 0.6)));
        $wrapItemName = static function (string $text, int $charsPerLine, ?int $maxLines = null): array {
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '') {
                return ['text' => '-', 'line_count' => 1];
            }

            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $lines = [];
            $current = '';

            $pushLine = static function (string $line) use (&$lines, $maxLines): bool {
                if ($line === '') {
                    return $maxLines !== null && count($lines) >= $maxLines;
                }
                if ($maxLines !== null && count($lines) >= $maxLines) {
                    return true;
                }
                $lines[] = $line;

                return $maxLines !== null && count($lines) >= $maxLines;
            };

            foreach ($words as $word) {
                while (mb_strlen($word) > $charsPerLine) {
                    if ($current !== '' && $pushLine($current)) {
                        return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                    }
                    $current = '';
                    if ($pushLine(mb_substr($word, 0, $charsPerLine))) {
                        return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                    }
                    $word = mb_substr($word, $charsPerLine);
                }

                if ($word === '') {
                    continue;
                }

                $candidate = $current === '' ? $word : $current.' '.$word;
                if (mb_strlen($candidate) <= $charsPerLine) {
                    $current = $candidate;
                    continue;
                }

                if ($pushLine($current)) {
                    return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
                }
                $current = $word;
            }

            if ($current !== '') {
                $pushLine($current);
            }

            if ($lines === []) {
                return ['text' => '-', 'line_count' => 1];
            }

            return ['text' => implode("\n", $lines), 'line_count' => count($lines)];
        };
    @endphp
    <style>
        @page {
            size: {{ $pageWidthMm }}mm {{ $pageHeightMm }}mm;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: Courier, monospace;
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
            font-weight: normal;
        }

        .po-number {
            font-size: {{ $poNumberFontSize }}px;
            font-weight: normal;
            letter-spacing: 0.2px;
        }

        .po-number-bold {
            font-size: {{ $poNumberFontSize }}px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        .capex-label {
            font-size: {{ $capexFontSize }}px;
            font-weight: normal;
            letter-spacing: 0.3px;
        }

        .cell {
            position: absolute;
            font-size: {{ $cellFontSize }}px;
            line-height: {{ $itemLineHeight }};
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-weight: normal;
        }

        .item-cell {
            white-space: pre-line;
            text-overflow: clip;
            line-height: {{ $itemLineHeight }};
            overflow: visible;
            overflow-wrap: normal;
            word-break: normal;
            font-weight: normal;
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
            font-weight: normal;
        }

        .acct-amount-cell {
            position: absolute;
            font-size: {{ $acctCellFontSize }}px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: visible;
            font-weight: normal;
        }

        .summary-label {
            font-weight: normal;
        }

    </style>
</head>
<body>
    @php
        $po = $receivingReport->purchaseOrder;
        $rowStartTopMm = $sy(73);
        // Leave space before the accounting block (starts around base-y 166).
        $itemsBottomLimitMm = $sy(152);
        $allItems = $receivingReport->items
            ->sortBy(fn ($rrItem) => $rrItem->purchase_order_item_id)
            ->values();
        $supplierName = trim((string) ($po?->supplier?->name ?? ''));
        $supplierCode = trim((string) ($po?->supplier?->code ?? ''));
        $supplierDisplay = $supplierName !== '' ? $supplierName : '-';
        if ($supplierCode !== '') {
            $supplierDisplay .= ' | '.$supplierCode;
        }

        $currencyConversion = $currencyConversion ?? ($rrAccountingPayload['currency_conversion'] ?? [
            'po_currency_code' => strtoupper(trim((string) ($po?->currency?->code ?? 'IDR'))),
            'should_convert' => false,
            'rate_found' => false,
            'rate_to_idr' => null,
            'effective_date' => null,
            'multiplier' => 1.0,
            'rate_note' => null,
        ]);
        $convertAmount = static function (float $amount) use ($currencyConversion): float {
            if (! ($currencyConversion['should_convert'] ?? false)) {
                return $amount;
            }

            return round($amount * (float) ($currencyConversion['multiplier'] ?? 1), 2);
        };

        $entryGenerator = app(\App\Services\Accounting\ReceivingReportEntryGenerator::class);
        $rrAccountingPayload = $rrAccountingPayload ?? $entryGenerator->generate($receivingReport, $currencyConversion);
        $resolveReceivedLineAmounts = static function ($poItem, float $qtyTotal) use ($entryGenerator): array {
            return $entryGenerator->resolveReceivedLineAmounts($poItem, $qtyTotal);
        };

        $accountingEntries = $entryGenerator->formatEntriesForPdf($rrAccountingPayload['lines'] ?? []);
        $accountingCodeTotal = (int) ($rrAccountingPayload['totals']['acct_code_total'] ?? 0);
        $displaySubTotal = (float) ($rrAccountingPayload['display']['sub_total'] ?? 0);
        $displayPpnTotal = (float) ($rrAccountingPayload['display']['ppn_total'] ?? 0);
        $displayPphTotal = (float) ($rrAccountingPayload['display']['pph_total'] ?? 0);

        $displayPpnRate = (float) collect($allItems)
            ->map(fn ($rrItem) => (float) ($rrItem->purchaseOrderItem?->ppn_rate ?? 0))
            ->first(fn ($rate) => $rate > 0);
        $displayPphRate = (float) collect($allItems)
            ->map(fn ($rrItem) => (float) ($rrItem->purchaseOrderItem?->pph_rate ?? 0))
            ->first(fn ($rate) => $rate > 0);
        $formatTaxRate = static function (float $rate): string {
            return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        };

        $layoutRows = [];
        $currentTop = $rowStartTopMm;
        foreach ($allItems as $rrItem) {
            $poItem = $rrItem->purchaseOrderItem;
            $item = $poItem?->item;
            $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
            $lineAmounts = $resolveReceivedLineAmounts($poItem, $qtyTotal);
            $remainingMm = $itemsBottomLimitMm - $currentTop;
            if ($remainingMm < $minRowHeightMm) {
                break;
            }

            $wrapped = $wrapItemName((string) ($item?->name ?? '-'), $itemNameCharsPerLine);
            $lineCount = max(1, (int) $wrapped['line_count']);
            $rowHeight = $rowHeightForLines($lineCount);

            if ($rowHeight > $remainingMm) {
                $maxLinesThatFit = max(1, (int) floor(($remainingMm - $interItemGapMm - $glyphHeightMm + $multiLineTailPackMm) / max(0.01, $wrapStrideMm)) + 1);
                $wrapped = $wrapItemName((string) ($item?->name ?? '-'), $itemNameCharsPerLine, $maxLinesThatFit);
                $lineCount = max(1, (int) $wrapped['line_count']);
                $rowHeight = $rowHeightForLines($lineCount);
                if ($rowHeight > $remainingMm) {
                    break;
                }
            }

            $layoutRows[] = [
                'top' => $currentTop,
                'name' => $wrapped['text'],
                'code' => $item?->code ?? '-',
                'department_code' => $poItem?->prsItem?->prs?->department?->code ?? '-',
                'qty_total' => $qtyTotal,
                'unit' => $item?->unit?->name ?? 'PCS',
                'unit_cost' => $convertAmount($lineAmounts['discounted_unit_cost']),
                'amount' => $convertAmount($lineAmounts['base_amount']),
            ];
            $currentTop = round($currentTop + $rowHeight, 2);
        }

        $rowCount = count($layoutRows);
        $hasPph = $displayPphTotal > 0;
        $hasPpn = $displayPpnTotal > 0;
        $showSubTotal = $rowCount > 1;
        $showFinalTotal = $hasPpn || $hasPph;
        $subTotalPlusPpn = $displaySubTotal + $displayPpnTotal;
        $displayGrandTotal = (float) ($rrAccountingPayload['display']['grand_total'] ?? ($subTotalPlusPpn - $displayPphTotal));
        $summaryBaseTop = $rowCount > 0 ? $currentTop + $sy(0.8) : 0;
        $subTotalTop = $summaryBaseTop + $sy(1.2);
        $ppnTop = $subTotalTop + $sy(5);
        $intermediateTop = ($displayPpnTotal > 0 ? $ppnTop : $subTotalTop) + $sy(5);
        $pphTop = $intermediateTop + $sy(5);
        $summaryTotalLineTop = ($hasPph ? $pphTop : ($displayPpnTotal > 0 ? $ppnTop : $subTotalTop)) + $sy(4.2);
        $summaryTotalTop = $summaryTotalLineTop + $sy(1.2);
        $poDateText = $po?->created_at ? $po->created_at->locale('id')->translatedFormat('d M Y') : '-';
        $rrDateText = $receivingReport->created_at ? $receivingReport->created_at->locale('id')->translatedFormat('d M Y') : '-';
        $isCapex = (bool) ($po?->items?->first()?->prsItem?->prs?->is_capex ?? false);
    @endphp

    <div class="rr-form-page">
        @if($isPreview && !empty($backgroundImageDataUri))
            <img src="{{ $backgroundImageDataUri }}" alt="" class="rr-bg">
        @endif

        @if ($isCapex)
            <div class="cell capex-label" style="left: {{ $mmX(15) }}; top: {{ $mmY(33) }}; width: {{ $mmW(40) }};">CAPEX</div>
        @endif

        @if ($isPreview)
            @php $rrNumberText = trim((string) ($receivingReport->rr_number ?? '')); @endphp
            @if ($rrNumberText !== '')
                <div class="field po-number" style="left: {{ $mmX(207) }}; top: {{ $mmY(28) }}; width: {{ $mmW(55) }}; text-align: right;">{{ $rrNumberText }}</div>
            @endif
        @endif

        <div class="field" style="left: {{ $mmX(37) }}; top: {{ $mmY(41) }}; width: {{ $mmW(100) }};">{{ $supplierDisplay }}</div>
        <div class="field po-number-bold" style="left: {{ $mmX(161) }}; top: {{ $mmY(38) }}; width: {{ $mmW(48) }};">{{ $po?->po_number ?? '-' }}</div>
        <div class="field" style="left: {{ $mmX(160) }}; top: {{ $mmY(49) }}; width: {{ $mmW(48) }};">{{ $poDateText }}</div>

        @foreach($layoutRows as $row)
            <div class="cell item-cell" style="left: {{ $mmX($itemNameLeftBaseMm) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW($itemNameWidthBaseMm) }};">{{ $row['name'] }}</div>
            <div class="cell center" style="left: {{ $mmX($itemCodeLeftBaseMm) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(20) }};">{{ $row['code'] }}</div>
            <div class="cell center" style="left: {{ $mmX(108) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(25) }};">{{ $row['department_code'] }}</div>
            <div class="cell right" style="left: {{ $mmX(138) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(23) }};">{{ \App\Support\PdfFormatters::qty($row['qty_total']) }}</div>
            <div class="cell center" style="left: {{ $mmX(163) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(23) }};">{{ $row['unit'] }}</div>
            <div class="cell right" style="left: {{ $mmX(189) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(35) }};">{{ number_format($row['unit_cost'], 2, '.', ',') }}</div>
            <div class="cell right" style="left: {{ $mmX(228) }}; top: {{ $oy($row['top']) }}; width: {{ $mmW(50) }};">{{ number_format($row['amount'], 2, '.', ',') }}</div>
        @endforeach

        @if ($rowCount > 0)
            @if ($showSubTotal)
                <div style="position: absolute; left: {{ $mmX(189) }}; top: {{ $oy($summaryBaseTop) }}; width: {{ $mmW(89) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right summary-label" style="left: {{ $mmX(189) }}; top: {{ $oy($subTotalTop) }}; width: {{ $mmW(35) }};">Sub Total</div>
                <div class="cell right summary-label" style="left: {{ $mmX(228) }}; top: {{ $oy($subTotalTop) }}; width: {{ $mmW(50) }};">{{ number_format($displaySubTotal, 2, '.', ',') }}</div>
            @endif
            @if ($hasPpn)
                <div class="cell right summary-label" style="left: {{ $mmX(189) }}; top: {{ $oy($ppnTop) }}; width: {{ $mmW(35) }};">PPn {{ $formatTaxRate($displayPpnRate) }}%</div>
                <div class="cell right summary-label" style="left: {{ $mmX(228) }}; top: {{ $oy($ppnTop) }}; width: {{ $mmW(50) }};">{{ number_format($displayPpnTotal, 2, '.', ',') }}</div>
            @endif
            @if ($hasPph)
                <div style="position: absolute; left: {{ $mmX(228) }}; top: {{ $oy($intermediateTop - $sy(1.2)) }}; width: {{ $mmW(50) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right summary-label" style="left: {{ $mmX(228) }}; top: {{ $oy($intermediateTop) }}; width: {{ $mmW(50) }};">{{ number_format($subTotalPlusPpn, 2, '.', ',') }}</div>
                <div class="cell right summary-label" style="left: {{ $mmX(189) }}; top: {{ $oy($pphTop) }}; width: {{ $mmW(35) }};">PPh {{ $formatTaxRate($displayPphRate) }}%</div>
                <div class="cell right summary-label" style="left: {{ $mmX(228) }}; top: {{ $oy($pphTop) }}; width: {{ $mmW(50) }};">{{ number_format($displayPphTotal, 2, '.', ',') }}</div>
            @endif
            @if ($showFinalTotal)
                <div style="position: absolute; left: {{ $mmX(228) }}; top: {{ $oy($summaryTotalTop - $sy(1.2)) }}; width: {{ $mmW(50) }}; border-top: 1px solid #111827;"></div>
                <div class="cell right summary-label" style="left: {{ $mmX(189) }}; top: {{ $oy($summaryTotalTop) }}; width: {{ $mmW(35) }};">Total</div>
                <div class="cell right summary-label" style="left: {{ $mmX(228) }}; top: {{ $oy($summaryTotalTop) }}; width: {{ $mmW(50) }};">{{ number_format($hasPph ? $displayGrandTotal : $subTotalPlusPpn, 2, '.', ',') }}</div>
            @endif
        @endif

        @php
            $entryStartTop = $sy(166);
            $entryRowHeight = $sy(5.2);
            $entryRows = collect($accountingEntries)->take(6)->values();
            $totalLineTop = $entryStartTop + ($entryRows->count() * $entryRowHeight) - $sy(0.4);
            $totalEntryTop = $totalLineTop + $sy(2.2);
        @endphp

        @if (! empty($currencyConversion['rate_note']))
            <div class="field" style="left: {{ $mmX(16) }}; top: {{ $mmY(157) }}; width: {{ $mmW(120) }}; font-size: {{ round(11 * $scaleY, 1) }}px; white-space: normal;">
                {{ $currencyConversion['rate_note'] }}
            </div>
        @elseif (($currencyConversion['po_currency_code'] ?? 'IDR') === 'USD' && empty($currencyConversion['rate_found']))
            <div class="field" style="left: {{ $mmX(16) }}; top: {{ $mmY(157) }}; width: {{ $mmW(120) }}; font-size: {{ round(11 * $scaleY, 1) }}px; white-space: normal;">
                No USD exchange rate available.
            </div>
        @endif

        @foreach($entryRows as $entryIndex => $entry)
            @php $entryTop = $entryStartTop + ($entryIndex * $entryRowHeight); @endphp
            <div class="acct-cell" style="left: {{ $mmX($acctCostCenterLeftBaseMm) }}; top: {{ $oy($entryTop) }}; width: {{ $mmW($acctCostCenterWidthBaseMm) }};">{{ $entry['cost_center'] !== '' ? $entry['cost_center'] : '' }}</div>
            <div class="acct-cell" style="left: {{ $mmX($acctAccountLeftBaseMm) }}; top: {{ $oy($entryTop) }}; width: {{ $mmW($acctAccountWidthBaseMm) }};">{{ $entry['account'] !== '' ? $entry['account'] : '' }}</div>
            <div class="acct-amount-cell right" style="left: {{ $mmX($acctDebitLeftBaseMm) }}; top: {{ $oy($entryTop) }}; width: {{ $mmW($acctDebitWidthBaseMm) }};">{{ $entry['debit'] !== null ? number_format((float) $entry['debit'], 2, '.', ',') : '' }}</div>
            <div class="acct-amount-cell right" style="left: {{ $mmX($acctCreditLeftBaseMm) }}; top: {{ $oy($entryTop) }}; width: {{ $mmW($acctCreditWidthBaseMm) }};">{{ $entry['credit'] !== null ? number_format((float) $entry['credit'], 2, '.', ',') : '' }}</div>
        @endforeach

        <div style="position: absolute; left: {{ $mmX(30) }}; top: {{ $oy($totalLineTop) }}; width: {{ $mmW(12) }}; border-top: 1px solid #111827;"></div>
        <div class="acct-cell" style="left: {{ $mmX(30) }}; top: {{ $oy($totalEntryTop) }}; width: {{ $mmW(12) }};">{{ $accountingCodeTotal }}</div>

        <div class="field center" style="left: {{ $mmX(175) }}; top: {{ $mmY(168) }}; width: {{ $mmW(47) }};">{{ $receivingReport->createdBy?->name ?? '-' }}</div>
        <div class="field center" style="left: {{ $mmX(233) }}; top: {{ $mmY(168) }}; width: {{ $mmW(45) }};">{{ $approvedByName ?? '-' }}</div>
        <div class="field center" style="left: {{ $mmX(235) }}; top: {{ $mmY(185) }}; width: {{ $mmW(45) }};">{{ $rrDateText }}</div>
    </div>
</body>
</html>
