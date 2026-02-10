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

                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="po-preview-table">
                        <thead>
                            <tr>
                                <th>PRS</th>
                                <th>Item</th>
                                <th style="width: 120px;">Qty</th>
                                <th style="width: 140px;">Unit Price</th>
                                <th>Notes</th>
                                <th style="width: 140px;">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineItems as $index => $item)
                                <tr data-row="{{ $index }}">
                                    <td>{{ $item['prs_number'] ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $item['item_name'] }}</div>
                                        <small class="text-muted">{{ $item['item_code'] }}</small>
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $item['prs_item_id'] }}">
                                        <input type="number" name="items[{{ $index }}][quantity]" class="form-control form-control-sm qty-input" min="1" value="{{ $item['quantity'] }}" data-row="{{ $index }}">
                                        <small class="text-muted">{{ $item['unit_name'] }}</small>
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $index }}][unit_price]" class="form-control form-control-sm price-input" min="0" step="0.01" value="{{ $item['unit_price'] }}" data-row="{{ $index }}">
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][notes]" class="form-control form-control-sm" value="{{ $item['notes'] }}">
                                    </td>
                                    <td>
                                        <div class="fw-semibold line-total" data-row="{{ $index }}">{{ number_format($item['line_total'], 2) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mt-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="tax-rate">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="tax-rate" class="form-control" min="0" step="0.01" value="{{ $taxRate }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="fees">Additional Fees</label>
                        <input type="number" name="fees" id="fees" class="form-control" min="0" step="0.01" value="{{ $fees }}">
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-md-6"></div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span class="fw-semibold" id="subtotal">{{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax</span>
                                <span class="fw-semibold" id="tax-amount">0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Fees</span>
                                <span class="fw-semibold" id="fees-amount">{{ number_format($fees, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold" id="total">{{ number_format($subtotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                <button type="submit" class="btn btn-outline-primary" name="action" value="draft">
                    Save as Draft
                </button>
                <button type="submit" class="btn btn-primary" name="action" value="submit">
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
            const formatNumber = (value) => Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const updateTotals = () => {
                let subtotal = 0;

                document.querySelectorAll('#po-preview-table tbody tr').forEach((row) => {
                    const rowId = row.getAttribute('data-row');
                    const qtyInput = row.querySelector(`.qty-input[data-row="${rowId}"]`);
                    const priceInput = row.querySelector(`.price-input[data-row="${rowId}"]`);
                    const lineTotalEl = row.querySelector(`.line-total[data-row="${rowId}"]`);

                    const qty = parseFloat(qtyInput.value || 0);
                    const price = parseFloat(priceInput.value || 0);
                    const lineTotal = qty * price;

                    subtotal += lineTotal;
                    lineTotalEl.textContent = formatNumber(lineTotal);
                });

                const taxRate = parseFloat(document.getElementById('tax-rate').value || 0);
                const fees = parseFloat(document.getElementById('fees').value || 0);
                const taxAmount = subtotal * (taxRate / 100);
                const total = subtotal + taxAmount + fees;

                document.getElementById('subtotal').textContent = formatNumber(subtotal);
                document.getElementById('tax-amount').textContent = formatNumber(taxAmount);
                document.getElementById('fees-amount').textContent = formatNumber(fees);
                document.getElementById('total').textContent = formatNumber(total);
            };

            document.querySelectorAll('.qty-input, .price-input, #tax-rate, #fees').forEach((input) => {
                input.addEventListener('input', updateTotals);
            });

            updateTotals();
        });
    </script>
@endpush
