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
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="po-preview-table">
                        <thead>
                            <tr>
                                <th>PRS</th>
                                <th>Item</th>
                                <th style="width: 150px;">Quantity</th>
                                <th style="width: 150px;">Unit Price</th>
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

                <div class="row g-3 mt-4">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="discount-rate">Discount (%)</label>
                        <div class="input-group">
                            <input type="number" name="discount_rate" id="discount-rate" class="form-control" min="0" step="0.01" value="{{ $discountRate }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="ppn-rate">PPN (%)</label>
                        <div class="input-group">
                            <input type="number" name="ppn_rate" id="ppn-rate" class="form-control" min="0" step="0.01" value="{{ $ppnRate }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="pph-rate">PPh (%)</label>
                        <div class="input-group">
                            <input type="number" name="pph_rate" id="pph-rate" class="form-control" min="0" step="0.01" value="{{ $pphRate }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="fees">Additional Fees</label>
                        <input type="number" name="fees" id="fees" class="form-control" min="0" step="0.01" value="{{ $fees }}">
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
                                <span class="text-muted">PPN</span>
                                <span class="fw-semibold" id="ppn-amount">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">PPh</span>
                                <span class="fw-semibold" id="pph-amount">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Additional Fees</span>
                                <span class="fw-semibold" id="fees-amount">Rp 0</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total Amount</span>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const currencySelect = document.getElementById('currency-id');

            const getCurrencySymbol = () => {
                if (!currencySelect) {
                    return 'Rp';
                }

                const option = currencySelect.options[currencySelect.selectedIndex];
                if (!option) {
                    return 'Rp';
                }

                return option.dataset.symbol || option.dataset.code || 'Rp';
            };

            const updateCurrencySymbols = () => {
                const symbol = getCurrencySymbol();
                document.querySelectorAll('.currency-symbol').forEach((el) => {
                    el.textContent = symbol;
                });

                return symbol;
            };

            const formatCurrency = (value) => {
                const symbol = getCurrencySymbol();
                const number = Number(value || 0);
                return symbol + ' ' + number.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                });
            };

            const formatSignedCurrency = (value) => {
                const symbol = getCurrencySymbol();
                const number = Math.abs(Number(value || 0));
                const formatted = number.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                });

                return (value < 0 ? '- ' : '') + symbol + ' ' + formatted;
            };

            const updateTotals = () => {
                let subtotal = 0;

                updateCurrencySymbols();

                document.querySelectorAll('#po-preview-table tbody tr').forEach((row) => {
                    const rowId = row.getAttribute('data-row');
                    const qtyInput = row.querySelector(`.qty-input[data-row="${rowId}"]`);
                    const priceInput = row.querySelector(`.price-input[data-row="${rowId}"]`);
                    const lineTotalEl = row.querySelector(`.line-total[data-row="${rowId}"]`);

                    const qty = parseFloat(qtyInput.value || 0);
                    const price = parseFloat(priceInput.value || 0);
                    const lineTotal = qty * price;

                    subtotal += lineTotal;
                    lineTotalEl.textContent = formatCurrency(lineTotal);
                });

                const discountRate = parseFloat(document.getElementById('discount-rate').value || 0);
                const ppnRate = parseFloat(document.getElementById('ppn-rate').value || 0);
                const pphRate = parseFloat(document.getElementById('pph-rate').value || 0);
                const fees = parseFloat(document.getElementById('fees').value || 0);

                const discountAmount = subtotal * (discountRate / 100);
                const baseAmount = subtotal - discountAmount;
                const ppnAmount = baseAmount * (ppnRate / 100);
                const pphAmount = baseAmount * (pphRate / 100);
                const total = baseAmount + ppnAmount - pphAmount + fees;

                document.getElementById('subtotal').textContent = formatCurrency(subtotal);
                document.getElementById('discount-amount').textContent = formatSignedCurrency(-discountAmount);
                document.getElementById('ppn-amount').textContent = formatCurrency(ppnAmount);
                document.getElementById('pph-amount').textContent = formatSignedCurrency(-pphAmount);
                document.getElementById('fees-amount').textContent = formatCurrency(fees);
                document.getElementById('total').textContent = formatCurrency(total);
            };

            document.querySelectorAll('.qty-input, .price-input, #discount-rate, #ppn-rate, #pph-rate, #fees').forEach((input) => {
                input.addEventListener('input', updateTotals);
            });

            if (currencySelect) {
                currencySelect.addEventListener('change', updateTotals);
            }

            updateTotals();
        });
    </script>
@endpush
