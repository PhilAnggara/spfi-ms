<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receiving Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
        }

        .rr-form-page {
            position: relative;
            width: 297mm;
            height: 210mm;
            page-break-after: auto;
            overflow: hidden;
        }

        .rr-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
        }

        .field {
            position: absolute;
            font-size: 13px;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .po-number {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        .cell {
            position: absolute;
            font-size: 12px;
            line-height: 1.2;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .item-cell {
            white-space: normal;
            text-overflow: clip;
            line-height: 1;
            max-height: 8.8mm;
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
            font-size: 11px;
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
        $rowStartTopMm = 73;
        $rowHeightMm = 9.2;
        $maxRows = 11;
        $rows = $receivingReport->items->take($maxRows)->values();
        $rowCount = $rows->count();
        $allItems = $receivingReport->items;

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

        foreach ($allItems as $rrItem) {
            $poItem = $rrItem->purchaseOrderItem;
            $item = $poItem?->item;

            $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
            $unitCost = (float) ($poItem?->unit_price ?? 0);
            $amount = $qtyTotal * $unitCost;
            $totalAmountAllItems += $amount;

            $costCenter = trim((string) ($poItem?->prsItem?->prs?->department?->code ?? ''));
            $categoryName = strtoupper(trim((string) ($item?->category?->name ?? 'OTHERS')));
            $debitAccount = $categoryAccountMap[$categoryName] ?? ($categoryAccountMap['OTHERS'] ?? '');

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

        $accountingEntries[] = [
            'cost_center' => '',
            'account' => $creditAccount,
            'debit' => null,
            'credit' => $totalAmountAllItems,
        ];

        $totalAmount = $rows->sum(function ($rrItem) {
            $poItem = $rrItem->purchaseOrderItem;
            $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
            $unitCost = (float) ($poItem?->unit_price ?? 0);

            return $qtyTotal * $unitCost;
        });
        $amountLineTop = $rowStartTopMm + (($rowCount - 1) * $rowHeightMm) + 7.2;
        $amountTotalTop = $amountLineTop + 1.2;
        $poDateText = $po?->created_at ? $po->created_at->locale('id')->translatedFormat('d M Y') : '-';
        $rrDateText = $receivingReport->created_at ? $receivingReport->created_at->locale('id')->translatedFormat('d M Y') : '-';
    @endphp

    <div class="rr-form-page">
        @if($isPreview && !empty($backgroundImageDataUri))
            <img src="{{ $backgroundImageDataUri }}" alt="" class="rr-bg">
        @endif

        <div class="field" style="left: 43mm; top: 41mm; width: 88mm;">{{ $po?->supplier?->name ?? '-' }}</div>
        <div class="field po-number" style="left: 167mm; top: 38mm; width: 48mm;">{{ $po?->po_number ?? '-' }}</div>
        <div class="field" style="left: 169mm; top: 49mm; width: 48mm;">{{ $poDateText }}</div>

        @foreach($rows as $index => $rrItem)
            @php
                $poItem = $rrItem->purchaseOrderItem;
                $item = $poItem?->item;
                $departmentCode = $poItem?->prsItem?->prs?->department?->code ?? '-';
                $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
                $unitCost = (float) ($poItem?->unit_price ?? 0);
                $amount = $qtyTotal * $unitCost;
                $top = $rowStartTopMm + ($index * $rowHeightMm);
            @endphp

            <div class="cell item-cell" style="left: 18mm; top: {{ $top }}mm; width: 65mm;">{{ $item?->name ?? '-' }}</div>
            <div class="cell" style="left: 94mm; top: {{ $top }}mm; width: 19mm;">{{ $item?->code ?? '-' }}</div>
            <div class="cell center" style="left: 114mm; top: {{ $top }}mm; width: 24mm;">{{ $departmentCode }}</div>
            <div class="cell right" style="left: 140mm; top: {{ $top }}mm; width: 20mm;">{{ number_format($qtyTotal, 2, '.', ',') }}</div>
            <div class="cell center" style="left: 167mm; top: {{ $top }}mm; width: 20mm;">{{ $item?->unit?->name ?? 'PCS' }}</div>
            <div class="cell right" style="left: 188mm; top: {{ $top }}mm; width: 30mm;">{{ number_format($unitCost, 2, '.', ',') }}</div>
            <div class="cell right" style="left: 228mm; top: {{ $top }}mm; width: 38mm;">{{ number_format($amount, 2, '.', ',') }}</div>
        @endforeach

        @if($rowCount > 1)
            <div style="position: absolute; left: 235mm; top: {{ $amountLineTop }}mm; width: 38mm; border-top: 1px solid #111827;"></div>
            <div class="cell right" style="left: 228mm; top: {{ $amountTotalTop }}mm; width: 38mm; font-weight: bold;">{{ number_format($totalAmount, 2, '.', ',') }}</div>
        @endif

        @php
            $entryStartTop = 170;
            $entryRowHeight = 5.2;
            $entryRows = collect($accountingEntries)->take(5)->values();
        @endphp

        @foreach($entryRows as $entryIndex => $entry)
            @php $entryTop = $entryStartTop + ($entryIndex * $entryRowHeight); @endphp
            <div class="acct-cell" style="left: 16mm; top: {{ $entryTop }}mm; width: 20mm;">{{ $entry['cost_center'] !== '' ? $entry['cost_center'] : '' }}</div>
            <div class="acct-cell" style="left: 38mm; top: {{ $entryTop }}mm; width: 20mm;">{{ $entry['account'] !== '' ? $entry['account'] : '' }}</div>
            <div class="acct-cell right" style="left: 60mm; top: {{ $entryTop }}mm; width: 28mm;">{{ $entry['debit'] !== null ? number_format((float) $entry['debit'], 2, '.', ',') : '' }}</div>
            <div class="acct-cell right" style="left: 91mm; top: {{ $entryTop }}mm; width: 28mm;">{{ $entry['credit'] !== null ? number_format((float) $entry['credit'], 2, '.', ',') : '' }}</div>
        @endforeach

        <div class="field center" style="left: 175mm; top: 170mm; width: 47mm;">{{ $receivingReport->createdBy?->name ?? '-' }}</div>
        <div class="field center" style="left: 233mm; top: 170mm; width: 45mm;">{{ $approvedByName ?? '-' }}</div>
        <div class="field center" style="left: 235mm; top: 193mm; width: 45mm;">{{ $rrDateText }}</div>
    </div>
</body>
</html>
