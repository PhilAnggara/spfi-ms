@extends('layouts.app')
@section('title', ' | PO Preview')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>PO Preview</h3>
                <p class="text-muted mb-0">Review and adjust items before saving.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 text-md-end">
                <a href="{{ route('purchase-orders.draft') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-duotone fa-solid fa-arrow-left"></i>
                    Back to Draft
                </a>
            </div>
        </div>
    </div>

    <section class="section">
        <form method="post" action="{{ route('purchase-orders.store') }}" class="card shadow-sm">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">

            <div class="card-body">
                <div class="border rounded p-3 mb-4">
                    <div class="text-muted small">Supplier</div>
                    <div class="fw-semibold">{{ $supplier->name }}</div>
                </div>

                @php
                    $selectedCurrency = $currencies->firstWhere('id', $currencyId) ?? $currencies->first();
                    $currencySymbol = $selectedCurrency?->symbol ?: ($selectedCurrency?->code ?: 'Rp');
                    $feeItemsForForm = old('fee_items', $feeItems ?? []);
                    $hasCapexItems = collect($lineItems)->contains(fn ($item) => (bool) ($item['is_capex'] ?? false));
                    $selectedTermType = old('term_of_payment_type', $termOfPaymentType ?? '');
                    $selectedTermPayment = old('term_of_payment', $termOfPayment ?? '');
                    $selectedTermDelivery = old('term_of_delivery', $termOfDelivery ?? '');

                    if (empty($feeItemsForForm)) {
                        $feeItemsForForm = [
                            ['type' => '', 'amount' => ''],
                        ];
                    }
                @endphp

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
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
                    <div class="col-12 col-md-8">
                        <label class="form-label">Remark</label>
                        <div class="input-group">
                            <select name="remark_type" class="form-select" style="max-width: 180px;">
                                <option value="Normal" @selected($remarkType === 'Normal')>Normal</option>
                                <option value="Confirmatory" @selected($remarkType === 'Confirmatory')>Confirmatory</option>
                            </select>
                            <input type="text" name="remark_text" class="form-control" value="{{ $remarkText }}" placeholder="Remark">
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Term of Payment <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="term_of_payment_type" class="form-select" style="max-width: 120px;" required>
                                <option value="">Select</option>
                                <option value="cash" @selected($selectedTermType === 'cash')>Cash</option>
                                <option value="credit" @selected($selectedTermType === 'credit')>Credit</option>
                            </select>
                            <input type="text" name="term_of_payment" class="form-control" value="{{ $selectedTermPayment }}" placeholder="e.g., 40% DP : 60% before delivery" required>
                        </div>
                        <small class="text-muted">Default from canvassing can be changed before creating PO.</small>
                    </div>
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Term of Delivery</label>
                        <input type="text" name="term_of_delivery" class="form-control" value="{{ $selectedTermDelivery }}" placeholder="e.g., FOB, CIF">
                    </div>
                    @if ($hasCapexItems)
                        <div class="col-12">
                            <span class="badge bg-light-primary">PO Category: CAPEX</span>
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="po-preview-table">
                        <thead>
                            <tr>
                                <th>PRS</th>
                                <th>Item</th>
                                <th style="width: 150px;">Quantity</th>
                                <th style="width: 150px;">Unit Price</th>
                                <th style="width: 120px;">Discount (%)</th>
                                <th style="width: 120px;">VAT (PPN) (%)</th>
                                <th style="width: 120px;">Withholding Tax (PPh) (%)</th>
                                <th>Notes</th>
                                <th style="width: 150px;" class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineItems as $index => $item)
                                <tr data-row="{{ $index }}">
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $item['prs_number'] ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $item['item_name'] }}</div>
                                        <small class="text-muted">{{ $item['item_code'] }}</small>
                                        @if ($item['is_capex'] ?? false)
                                            <div class="mt-1"><span class="badge bg-light-primary">CAPEX</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $item['prs_item_id'] }}">
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control qty-input" min="1" value="{{ $item['quantity'] }}" data-row="{{ $index }}">
                                            <span class="input-group-text" style="min-width: 60px;">{{ $item['unit_name'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text currency-symbol">{{ $currencySymbol }}</span>
                                            <input type="number" name="items[{{ $index }}][unit_price]" class="form-control price-input text-end" min="0" step="0.01" value="{{ $item['unit_price'] }}" data-row="{{ $index }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="items[{{ $index }}][discount_rate]" class="form-control discount-input text-end" min="0" step="0.01" value="{{ $item['discount_rate'] ?? 0 }}" data-row="{{ $index }}">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="items[{{ $index }}][ppn_rate]" class="form-control ppn-input text-end" min="0" step="0.01" value="{{ $item['ppn_rate'] ?? 0 }}" data-row="{{ $index }}">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="items[{{ $index }}][pph_rate]" class="form-control pph-input text-end" min="0" step="0.01" value="{{ $item['pph_rate'] ?? 0 }}" data-row="{{ $index }}">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][notes]" class="form-control form-control-sm" placeholder="-" value="{{ $item['notes'] }}">
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-secondary line-total" data-row="{{ $index }}" style="font-size: 1.05em;">
                                            <span class="currency-symbol">{{ $currencySymbol }}</span> {{ number_format($item['line_total'], 0, ',', '.') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-xl-8">
                        <div class="border rounded-3 p-3 p-md-4 bg-light-subtle">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1">Additional Charges</h6>
                                    <p class="text-muted small mb-0">Add optional charges that should be included in this purchase order total.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-fee-btn">
                                    <i class="fa-duotone fa-solid fa-plus"></i>
                                    Add Charge
                                </button>
                            </div>

                            <div id="fee-items-container" class="d-flex flex-column gap-2">
                                @foreach ($feeItemsForForm as $index => $feeItem)
                                    <div class="fee-item-row border rounded-2 p-2 bg-white" data-fee-index="{{ $index }}">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label form-label-sm mb-1">Charge Type</label>
                                                <input
                                                    type="text"
                                                    name="fee_items[{{ $index }}][type]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $feeItem['type'] ?? '' }}"
                                                    placeholder="e.g. Freight, Insurance, Handling"
                                                >
                                            </div>
                                            <div class="col-10 col-md-4">
                                                <label class="form-label form-label-sm mb-1">Amount</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text currency-symbol">{{ $currencySymbol }}</span>
                                                    <input
                                                        type="number"
                                                        name="fee_items[{{ $index }}][amount]"
                                                        class="form-control text-end fee-amount-input"
                                                        min="0"
                                                        step="0.01"
                                                        value="{{ $feeItem['amount'] ?? '' }}"
                                                        placeholder="0"
                                                    >
                                                </div>
                                            </div>
                                            <div class="col-2 col-md-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-fee-btn" aria-label="Remove charge">
                                                    <i class="fa-duotone fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-md-6"></div>
                    <div class="col-12 col-md-6">
                        <div class="border-start border-4 border-primary rounded bg-light-primary p-3">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-semibold" id="subtotal">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Discount</span>
                                <span class="fw-semibold" id="discount-amount">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">VAT (PPN)</span>
                                <span class="fw-semibold" id="ppn-amount">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Withholding Tax (PPh)</span>
                                <span class="fw-semibold" id="pph-amount">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Additional Charges</span>
                                <span class="fw-semibold" id="fees-amount">Rp 0</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Grand Total</span>
                                <span class="fw-bold" id="total" style="font-size: 1.2em;">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
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
@endsection

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/po-preview-index.js') }}"></script>
@endpush
