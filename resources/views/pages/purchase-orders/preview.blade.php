@extends('layouts.app')
@section('title', ' | PO Preview')

@section('content')
@php
    $selectedCurrency = $currencies->firstWhere('id', $currencyId) ?? $currencies->first();
    $currencySymbol = $selectedCurrency?->symbol ?: ($selectedCurrency?->code ?: 'Rp');
    $feeItemsForForm = old('fee_items', $feeItems ?? []);
    $hasCapexItems = collect($lineItems)->contains(fn ($item) => (bool) ($item['is_capex'] ?? false));
    $selectedTermType = old('term_of_payment_type', $termOfPaymentType ?? '');
    $selectedTermPayment = old('term_of_payment', $termOfPayment ?? '');
    $selectedTermDelivery = old('term_of_delivery', $termOfDelivery ?? '');
    $selectedPoNumber = old('po_number', $nextPoNumber ?? '');

    if (empty($feeItemsForForm)) {
        $feeItemsForForm = [
            ['type' => '', 'amount' => ''],
        ];
    }
@endphp

<div id="po-page-container">
<div class="page-heading po-page po-preview-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <div class="po-hero">
                    <h3 class="mb-1">PO Preview</h3>
                    <p class="text-muted mb-0">Review pricing and terms before creating this purchase order.</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="po-top-actions">
                    <a href="{{ route('purchase-orders.draft') }}" class="btn btn-outline-secondary icon icon-left">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to Draft
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <form method="post" action="{{ route('purchase-orders.store') }}" class="card shadow-sm border-0 po-preview-card">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">

            <div class="card-body p-3 p-lg-4">
                <div class="po-preview-supplier">
                    <span class="po-preview-supplier-icon"><i class="fa-duotone fa-solid fa-truck-field"></i></span>
                    <div class="min-w-0">
                        <div class="po-preview-kicker">Supplier</div>
                        <div class="po-preview-supplier-name">{{ $supplier->name }}</div>
                    </div>
                    @if ($hasCapexItems)
                        <span class="badge bg-light-primary ms-auto">CAPEX</span>
                    @endif
                </div>

                <div class="po-preview-section">
                    <div class="po-preview-section-title">Header</div>
                    <div class="po-preview-meta-grid">
                        <div class="po-preview-meta-field">
                            <label class="form-label" for="po-number">PO Number</label>
                            <input type="text" id="po-number" name="po_number" class="form-control" value="{{ $selectedPoNumber }}" placeholder="Auto number">
                            <input type="hidden" name="po_number_suggested" value="{{ $nextPoNumber ?? '' }}">
                        </div>
                        <div class="po-preview-meta-field">
                            <label class="form-label" for="currency-id">Currency</label>
                            <select name="currency_id" id="currency-id" class="form-select" @disabled($currencies->isEmpty())>
                                @forelse ($currencies as $currency)
                                    <option value="{{ $currency->id }}" data-symbol="{{ $currency->symbol }}" data-code="{{ $currency->code }}" @selected($currency->id === $currencyId)>
                                        {{ $currency->code }} - {{ $currency->name }}
                                    </option>
                                @empty
                                    <option value="">No currency available</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="po-preview-meta-field po-preview-meta-field--remark">
                            <label class="form-label" for="remark-type">Remark</label>
                            <div class="po-preview-split-control">
                                <select name="remark_type" id="remark-type" class="form-select">
                                    <option value="Normal" @selected($remarkType === 'Normal')>Normal</option>
                                    <option value="Confirmatory" @selected($remarkType === 'Confirmatory')>Confirmatory</option>
                                </select>
                                <input type="text" name="remark_text" class="form-control" value="{{ $remarkText }}" placeholder="Remark text">
                            </div>
                        </div>
                        <div class="po-preview-meta-field po-preview-meta-field--payment">
                            <label class="form-label" for="term-of-payment-type">Term of Payment <span class="text-danger">*</span></label>
                            <div class="po-preview-split-control">
                                <select name="term_of_payment_type" id="term-of-payment-type" class="form-select" required>
                                    <option value="">Type</option>
                                    <option value="cash" @selected($selectedTermType === 'cash')>Cash</option>
                                    <option value="credit" @selected($selectedTermType === 'credit')>Credit</option>
                                </select>
                                <input type="text" name="term_of_payment" class="form-control" value="{{ $selectedTermPayment }}" placeholder="Optional description">
                            </div>
                        </div>
                        <div class="po-preview-meta-field po-preview-meta-field--delivery">
                            <label class="form-label" for="term-of-delivery">Term of Delivery</label>
                            <input type="text" id="term-of-delivery" name="term_of_delivery" class="form-control" value="{{ $selectedTermDelivery }}" placeholder="e.g. FOB, CIF">
                        </div>
                    </div>
                </div>

                <div class="po-preview-section">
                    <div class="po-preview-section-head">
                        <div class="po-preview-section-title mb-0">Line Items</div>
                        <span class="badge bg-light-primary">{{ itemOrItems(count($lineItems)) }}</span>
                    </div>

                    <div id="po-preview-table" class="po-preview-lines">
                        @foreach ($lineItems as $index => $item)
                            <div class="po-preview-line" data-row="{{ $index }}">
                                <div class="po-preview-line-top">
                                    <div class="po-preview-line-identity">
                                        <span class="po-preview-line-index">{{ $index + 1 }}</span>
                                        <div class="min-w-0">
                                            <div class="po-preview-line-name">
                                                {{ $item['item_name'] }}
                                                @if ($item['is_capex'] ?? false)
                                                    <span class="badge bg-light-primary">CAPEX</span>
                                                @endif
                                            </div>
                                            <div class="po-preview-line-meta">
                                                <span>{{ $item['prs_number'] ?? '-' }}</span>
                                                <span>{{ $item['item_code'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="po-preview-line-total">
                                        <span class="po-preview-kicker">Line Total</span>
                                        <div class="line-total" data-row="{{ $index }}">
                                            <span class="currency-symbol">{{ $currencySymbol }}</span> {{ format_po_decimal($item['line_total']) }}
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $item['prs_item_id'] }}">

                                <div class="po-preview-line-primary">
                                    <div class="po-preview-field">
                                        <label class="form-label">Quantity</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][quantity]"
                                                class="form-control qty-input text-end"
                                                min="1"
                                                step="1"
                                                value="{{ $item['quantity'] }}"
                                                data-row="{{ $index }}"
                                                required
                                            >
                                            <span class="input-group-text">{{ $item['unit_name'] }}</span>
                                        </div>
                                    </div>
                                    <div class="po-preview-field po-preview-field--price">
                                        <label class="form-label">Unit Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text currency-symbol">{{ $currencySymbol }}</span>
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][unit_price]"
                                                class="form-control price-input text-end"
                                                min="0"
                                                step="0.00001"
                                                value="{{ format_po_decimal($item['unit_price'], true) }}"
                                                data-row="{{ $index }}"
                                                required
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="po-preview-line-secondary">
                                    <div class="po-preview-field">
                                        <label class="form-label">Discount %</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][discount_rate]"
                                                class="form-control discount-input text-end"
                                                min="0"
                                                step="0.00001"
                                                value="{{ format_po_decimal($item['discount_rate'] ?? 0, true) }}"
                                                data-row="{{ $index }}"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="po-preview-field">
                                        <label class="form-label">VAT (PPN) %</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][ppn_rate]"
                                                class="form-control ppn-input text-end"
                                                min="0"
                                                step="0.00001"
                                                value="{{ format_po_decimal($item['ppn_rate'] ?? 0, true) }}"
                                                data-row="{{ $index }}"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="po-preview-field">
                                        <label class="form-label">Withholding (PPh) %</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][pph_rate]"
                                                class="form-control pph-input text-end"
                                                min="0"
                                                step="0.00001"
                                                value="{{ format_po_decimal($item['pph_rate'] ?? 0, true) }}"
                                                data-row="{{ $index }}"
                                            >
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="po-preview-field po-preview-field--notes">
                                        <label class="form-label">Notes</label>
                                        <input type="text" name="items[{{ $index }}][notes]" class="form-control" placeholder="Optional notes" value="{{ $item['notes'] }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="po-preview-bottom">
                    <div class="po-preview-fees">
                        <div class="po-preview-section-head">
                            <div>
                                <div class="po-preview-section-title mb-0">Additional Charges</div>
                                <small class="text-muted">Optional charges added to grand total</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-fee-btn">
                                <i class="fa-duotone fa-solid fa-plus"></i>
                                Add
                            </button>
                        </div>

                        <div id="fee-items-container" class="po-preview-fee-list">
                            @foreach ($feeItemsForForm as $index => $feeItem)
                                <div class="fee-item-row po-preview-fee-row" data-fee-index="{{ $index }}">
                                    <div class="po-preview-fee-grid">
                                        <div class="po-preview-field">
                                            <label class="form-label">Charge Type</label>
                                            <input
                                                type="text"
                                                name="fee_items[{{ $index }}][type]"
                                                class="form-control"
                                                value="{{ $feeItem['type'] ?? '' }}"
                                                placeholder="Freight, insurance, handling"
                                            >
                                        </div>
                                        <div class="po-preview-field">
                                            <label class="form-label">Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text currency-symbol">{{ $currencySymbol }}</span>
                                                <input
                                                    type="number"
                                                    name="fee_items[{{ $index }}][amount]"
                                                    class="form-control text-end fee-amount-input"
                                                    min="0"
                                                    step="0.00001"
                                                    value="{{ isset($feeItem['amount']) && $feeItem['amount'] !== '' ? format_po_decimal($feeItem['amount'], true) : '' }}"
                                                    placeholder="0.00000"
                                                >
                                            </div>
                                        </div>
                                        <div class="po-preview-fee-action">
                                            <button type="button" class="btn btn-outline-danger remove-fee-btn" aria-label="Remove charge">
                                                <i class="fa-duotone fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="po-preview-summary">
                        <div class="po-preview-section-title">Summary</div>
                        <div class="po-preview-summary-row">
                            <span>Subtotal</span>
                            <span class="fw-semibold" id="subtotal">{{ $currencySymbol }} 0.00000</span>
                        </div>
                        <div class="po-preview-summary-row">
                            <span>Discount</span>
                            <span class="fw-semibold" id="discount-amount">{{ $currencySymbol }} 0.00000</span>
                        </div>
                        <div class="po-preview-summary-row">
                            <span>VAT (PPN)</span>
                            <span class="fw-semibold" id="ppn-amount">{{ $currencySymbol }} 0.00000</span>
                        </div>
                        <div class="po-preview-summary-row">
                            <span>Withholding Tax (PPh)</span>
                            <span class="fw-semibold" id="pph-amount">{{ $currencySymbol }} 0.00000</span>
                        </div>
                        <div class="po-preview-summary-row">
                            <span>Additional Charges</span>
                            <span class="fw-semibold" id="fees-amount">{{ $currencySymbol }} 0.00000</span>
                        </div>
                        <div class="po-preview-summary-total">
                            <span>Grand Total</span>
                            <span class="po-preview-grand-total" id="total">{{ $currencySymbol }} 0.00000</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer po-preview-footer">
                <a href="{{ route('purchase-orders.draft') }}" class="btn btn-outline-secondary">
                    <i class="fa-duotone fa-solid fa-arrow-left"></i>
                    Back
                </a>
                <button type="submit" class="btn btn-success" name="action" value="submit">
                    <i class="fa-duotone fa-solid fa-check-circle"></i>
                    Submit for Approval
                </button>
            </div>
        </form>
    </section>
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/po-preview-index.js') }}"></script>
@endpush
