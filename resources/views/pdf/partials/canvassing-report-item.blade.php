@php
    /** @var \App\Models\PrsItem $prsItem */
    /** @var \Illuminate\Support\Collection<int, \App\Models\PrsCanvassingItem> $canvassingItems */
    $item = $prsItem->item;
    $prs = $prsItem->prs;
    $lowestUnitPrice = (float) ($canvassingItems->min('unit_price') ?? 0);
    $highestUnitPrice = (float) ($canvassingItems->max('unit_price') ?? 1);
    $allPricesEqual = $canvassingItems->count() > 1 && $lowestUnitPrice === $highestUnitPrice;
    $lowestCount = $canvassingItems
        ->filter(static fn ($canvassing) => (float) $canvassing->unit_price === $lowestUnitPrice)
        ->count();
    $hasTiedLowest = ! $allPricesEqual && $lowestCount > 1;
    $itemIndex = $itemIndex ?? null;
    $itemTotal = $itemTotal ?? null;
@endphp

<div class="report-item">
    @if ($itemIndex !== null && $itemTotal !== null && $itemTotal > 1)
        <div class="item-heading">Item {{ $itemIndex }} of {{ $itemTotal }} — {{ $item->code ?? '-' }}</div>
    @endif

    <div class="section">
        <div class="section-title">Request Information</div>
        <table class="info-table">
            <tr>
                <td class="label">PRS Number</td>
                <td class="value">{{ $prs->prs_number ?? '-' }}</td>
                <td class="label">Department</td>
                <td class="value">{{ $prs->department->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Item Code</td>
                <td class="value">{{ $item->code ?? '-' }}</td>
                <td class="label">Item Name</td>
                <td class="value text-wrap">{{ $item->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Quantity</td>
                <td class="value">{{ \App\Support\PdfFormatters::qty($prsItem->quantity) }} {{ $item->unit->name ?? 'PCS' }}</td>
                <td class="label">Date Needed</td>
                <td class="value">{{ $prs?->date_needed ? \Illuminate\Support\Carbon::parse($prs->date_needed)->format('d M Y') : '-' }}</td>
            </tr>
            @if ($prsItem->is_direct_purchase)
                <tr>
                    <td class="label">Purchase Type</td>
                    <td class="value direct-purchase" colspan="3">Direct Purchase</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Supplier Quotations</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 28px;" class="text-center">Rank</th>
                    <th style="width: 130px;">Supplier</th>
                    <th style="width: 80px;" class="text-right">Price / Unit</th>
                    <th style="width: 58px;" class="text-center">Payment</th>
                    <th style="width: 110px;">Payment Detail</th>
                    <th style="width: 52px;" class="text-center">Lead Time</th>
                    <th style="width: 95px;">Term of Delivery</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($canvassingItems as $index => $canvassing)
                    @php
                        $unitPrice = (float) $canvassing->unit_price;
                        $isLowest = $unitPrice === $lowestUnitPrice;
                        $rowClass = '';
                        if ($allPricesEqual) {
                            $rowClass = 'equal-row';
                        } elseif ($isLowest && ! $hasTiedLowest) {
                            $rowClass = 'lowest-row';
                        } elseif ($isLowest && $hasTiedLowest) {
                            $rowClass = 'equal-row';
                        }
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            {{ $canvassing->supplier->name ?? '-' }}
                            @if ($allPricesEqual)
                                <div class="equal-label">EQUAL PRICE</div>
                            @elseif ($isLowest && $hasTiedLowest)
                                <div class="equal-label">EQUAL LOWEST</div>
                            @elseif ($isLowest)
                                <div class="lowest-label">LOWEST PRICE</div>
                            @endif
                        </td>
                        <td class="text-right">{{ format_po_decimal($canvassing->unit_price) }}</td>
                        <td class="text-center">{{ $canvassing->term_of_payment_type ? ucfirst($canvassing->term_of_payment_type) : '-' }}</td>
                        <td class="text-wrap">{{ $canvassing->term_of_payment ?? '-' }}</td>
                        <td class="text-center">{{ $canvassing->lead_time_days ?? '-' }}{{ $canvassing->lead_time_days !== null ? ' days' : '' }}</td>
                        <td class="text-wrap">{{ $canvassing->term_of_delivery ?? '-' }}</td>
                    </tr>
                    <tr class="notes-row">
                        <td colspan="7" class="text-wrap">
                            <strong>Notes:</strong> {{ $canvassing->notes ?: '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="visual-section">
        <div class="visual-title">Price Comparison</div>
        @foreach ($canvassingItems as $canvassing)
            @php
                $unitPrice = (float) $canvassing->unit_price;
                $isLowest = $unitPrice === $lowestUnitPrice;
                $isHighest = $unitPrice === $highestUnitPrice;
                $ratio = $highestUnitPrice > 0 ? ($unitPrice / $highestUnitPrice) * 100 : 0;
                $differenceAmount = max($highestUnitPrice - $unitPrice, 0);
                $cheaperPercent = $highestUnitPrice > 0 ? ($differenceAmount / $highestUnitPrice) * 100 : 0;

                if ($allPricesEqual) {
                    $comparisonNote = '(EQUAL PRICE)';
                    $barClass = 'bar-equal';
                } elseif ($isLowest && $hasTiedLowest) {
                    $comparisonNote = '(EQUAL LOWEST)';
                    $barClass = 'bar-equal';
                } elseif ($isLowest) {
                    $comparisonNote = '(LOWEST)';
                    $barClass = 'bar-lowest';
                } elseif ($isHighest) {
                    $comparisonNote = '(HIGHEST)';
                    $barClass = '';
                } else {
                    $comparisonNote = '('.number_format($cheaperPercent, 2, ',', '.').'% cheaper vs highest)';
                    $barClass = '';
                }
            @endphp
            <div class="visual-row">
                <div class="visual-label">
                    {{ $canvassing->supplier->name ?? '-' }} — {{ format_po_decimal($canvassing->unit_price) }}
                    {{ $comparisonNote }}
                </div>
                <div class="bar-wrap">
                    <div class="bar {{ $barClass }}" style="width: {{ number_format($ratio, 2, '.', '') }}%;"></div>
                </div>
            </div>
        @endforeach
        <div class="footnote">
            @if ($allPricesEqual)
                All suppliers quoted the same unit price for this item.
            @elseif ($hasTiedLowest)
                Bar lengths are relative to the highest quoted price; blue indicates suppliers tied for the lowest price.
            @else
                Bar lengths are relative to the highest quoted price for this item; green indicates the lowest price.
            @endif
        </div>
    </div>
</div>
