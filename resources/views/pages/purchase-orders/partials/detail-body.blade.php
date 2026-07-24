{{--
    Shared PO detail body (preview theme).
    Expected: $purchaseOrder (with relations), optional $showHeaderMeta (default true).
--}}
@php
    $currencyCode = $purchaseOrder->currency?->code ?? $purchaseOrder->currency?->symbol ?? 'Rp';
    $firstItemMeta = $purchaseOrder->items->first()?->meta ?? [];
    $termOfPaymentType = $purchaseOrder->term_of_payment_type ?? ($firstItemMeta['term_of_payment_type'] ?? null);
    $termOfPayment = $purchaseOrder->term_of_payment ?? ($firstItemMeta['term_of_payment'] ?? null);
    $termOfDelivery = $purchaseOrder->term_of_delivery ?? ($firstItemMeta['term_of_delivery'] ?? null);
    $termPaymentDisplay = trim(($termOfPayment ? $termOfPayment.' ' : '').($termOfPaymentType ? ucfirst((string) $termOfPaymentType) : ''));
    $termPaymentDisplay = $termPaymentDisplay !== '' ? $termPaymentDisplay : '-';
    $feeItems = collect($purchaseOrder->fees_breakdown ?? [])
        ->filter(fn ($row) => is_array($row))
        ->map(fn (array $row) => [
            'type' => trim((string) ($row['type'] ?? '')),
            'amount' => (float) ($row['amount'] ?? 0),
        ])
        ->filter(fn (array $row) => $row['type'] !== '' || $row['amount'] > 0)
        ->values();
    $showHeaderMeta = $showHeaderMeta ?? true;
    $hasCapexItems = $purchaseOrder->items->contains(
        fn ($item) => (bool) ($item->prsItem?->prs?->is_capex ?? ($item->meta['is_capex'] ?? false))
    );
@endphp

<div class="po-detail-body">
    @if ($showHeaderMeta)
        <div class="po-preview-supplier">
            <span class="po-preview-supplier-icon"><i class="fa-duotone fa-solid fa-truck-field"></i></span>
            <div class="min-w-0">
                <div class="po-preview-kicker">Supplier</div>
                <div class="po-preview-supplier-name">{{ $purchaseOrder->supplier?->name ?? '-' }}</div>
            </div>
            @if ($hasCapexItems)
                <span class="badge bg-light-primary ms-auto">CAPEX</span>
            @endif
        </div>

        <div class="po-preview-section">
            <div class="po-preview-section-title">Header</div>
            <div class="po-detail-meta-grid">
                <div class="po-detail-meta-card">
                    <div class="po-preview-kicker">Created By</div>
                    <div class="po-detail-meta-value">{{ $purchaseOrder->createdBy?->name ?? '-' }}</div>
                </div>
                <div class="po-detail-meta-card">
                    <div class="po-preview-kicker">Status</div>
                    <div class="po-detail-meta-value">{{ $purchaseOrder->status }}</div>
                </div>
                <div class="po-detail-meta-card">
                    <div class="po-preview-kicker">Currency</div>
                    <div class="po-detail-meta-value">{{ $currencyCode }}</div>
                </div>
                <div class="po-detail-meta-card po-detail-meta-card--wide">
                    <div class="po-preview-kicker">Remark</div>
                    <div class="po-detail-meta-value">{{ $purchaseOrder->remark_type ?? '-' }}</div>
                    <div class="po-detail-meta-sub">{{ $purchaseOrder->remark_text ?: '-' }}</div>
                </div>
                <div class="po-detail-meta-card po-detail-meta-card--full">
                    <div class="po-preview-kicker">Term of Payment</div>
                    <div class="po-detail-meta-value">{{ $termPaymentDisplay }}</div>
                    @if ($termOfDelivery)
                        <div class="po-detail-meta-sub">Term of Delivery: {{ $termOfDelivery }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($purchaseOrder->approval_notes)
        <div class="alert alert-warning mt-3 mb-0">
            <strong>Changes Requested:</strong> {{ $purchaseOrder->approval_notes }}
        </div>
    @endif

    <div class="po-preview-section">
        <div class="po-preview-section-head">
            <div class="po-preview-section-title mb-0">Line Items</div>
            <span class="badge bg-light-primary">{{ itemOrItems($purchaseOrder->items->count()) }}</span>
        </div>

        <div class="po-preview-lines">
            @forelse ($purchaseOrder->items as $index => $item)
                @php
                    $isCapex = (bool) ($item->prsItem?->prs?->is_capex ?? ($item->meta['is_capex'] ?? false));
                @endphp
                <div class="po-preview-line">
                    <div class="po-preview-line-top">
                        <div class="po-preview-line-identity">
                            <span class="po-preview-line-index">{{ $index + 1 }}</span>
                            <div class="min-w-0">
                                <div class="po-preview-line-name">
                                    {{ $item->item?->name ?? '-' }}
                                    @if ($isCapex)
                                        <span class="badge bg-light-primary">CAPEX</span>
                                    @endif
                                </div>
                                <div class="po-preview-line-meta">
                                    <span>{{ $item->meta['prs_number'] ?? $item->prsItem?->prs?->prs_number ?? '-' }}</span>
                                    <span>{{ $item->item?->code ?? '-' }}</span>
                                    @if ($item->prsItem?->prs?->department?->name)
                                        <span>{{ $item->prsItem->prs->department->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="po-preview-line-total">
                            <span class="po-preview-kicker">Line Total</span>
                            <div class="line-total">{{ $currencyCode }} {{ format_po_decimal($item->total) }}</div>
                        </div>
                    </div>

                    <div class="po-detail-line-stats">
                        <div class="po-detail-stat">
                            <div class="po-preview-kicker">Quantity</div>
                            <div class="po-detail-stat-value">{{ rtrim(rtrim(number_format((float) $item->quantity, 5, '.', ''), '0'), '.') ?: '0' }} {{ $item->item?->unit?->name ?? 'PCS' }}</div>
                        </div>
                        <div class="po-detail-stat">
                            <div class="po-preview-kicker">Unit Price</div>
                            <div class="po-detail-stat-value">{{ $currencyCode }} {{ format_po_decimal($item->unit_price) }}</div>
                        </div>
                        <div class="po-detail-stat">
                            <div class="po-preview-kicker">Discount %</div>
                            <div class="po-detail-stat-value">{{ format_po_decimal($item->discount_rate ?? 0) }}</div>
                        </div>
                        <div class="po-detail-stat">
                            <div class="po-preview-kicker">VAT (PPN) %</div>
                            <div class="po-detail-stat-value">{{ format_po_decimal($item->ppn_rate ?? 0) }}</div>
                        </div>
                        <div class="po-detail-stat">
                            <div class="po-preview-kicker">Withholding (PPh) %</div>
                            <div class="po-detail-stat-value">{{ format_po_decimal($item->pph_rate ?? 0) }}</div>
                        </div>
                        <div class="po-detail-stat po-detail-stat--notes">
                            <div class="po-preview-kicker">Notes</div>
                            <div class="po-detail-stat-value">{{ $item->notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="po-empty-state text-center text-muted py-4">
                    <p class="mb-0">No items on this purchase order.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="po-preview-bottom">
        <div class="po-preview-fees">
            <div class="po-preview-section-title">Additional Charges</div>
            @forelse ($feeItems as $feeItem)
                <div class="po-detail-fee-row">
                    <span class="text-muted">{{ $feeItem['type'] ?: 'Charge' }}</span>
                    <span class="fw-semibold">{{ $currencyCode }} {{ format_po_decimal($feeItem['amount']) }}</span>
                </div>
            @empty
                <div class="text-muted small">No additional charges.</div>
            @endforelse
        </div>

        <div class="po-preview-summary">
            <div class="po-preview-section-title">Summary</div>
            <div class="po-preview-summary-row">
                <span>Subtotal</span>
                <span class="fw-semibold">{{ $currencyCode }} {{ format_po_decimal($purchaseOrder->subtotal) }}</span>
            </div>
            <div class="po-preview-summary-row">
                <span>Discount</span>
                <span class="fw-semibold">- {{ $currencyCode }} {{ format_po_decimal($purchaseOrder->discount_amount ?? 0) }}</span>
            </div>
            <div class="po-preview-summary-row">
                <span>VAT (PPN)</span>
                <span class="fw-semibold">{{ $currencyCode }} {{ format_po_decimal($purchaseOrder->ppn_amount ?? 0) }}</span>
            </div>
            <div class="po-preview-summary-row">
                <span>Withholding Tax (PPh)</span>
                <span class="fw-semibold">- {{ $currencyCode }} {{ format_po_decimal($purchaseOrder->pph_amount ?? 0) }}</span>
            </div>
            <div class="po-preview-summary-row">
                <span>Additional Charges</span>
                <span class="fw-semibold">{{ $currencyCode }} {{ format_po_decimal($purchaseOrder->fees ?? 0) }}</span>
            </div>
            <div class="po-preview-summary-total">
                <span>Grand Total</span>
                <span class="po-preview-grand-total">{{ $currencyCode }} {{ format_po_decimal($purchaseOrder->total) }}</span>
            </div>
        </div>
    </div>
</div>
