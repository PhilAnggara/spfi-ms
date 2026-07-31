@extends('layouts.app')
@section('title', ' | PO Detail')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Purchase Order</h3>
                <p class="text-muted mb-0">Status: {{ $purchaseOrder->status }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 text-md-end">
                @role('administrator|purchasing-manager|general-manager')
                    <a href="{{ route('purchase-orders.approval') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to PO Approval
                    </a>
                @endrole
                @role('purchasing-staff')
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to PO List
                    </a>
                @endrole
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                @php
                    $currencyCode = $purchaseOrder->currency?->code ?? 'IDR';
                    $user = auth()->user();
                    $canEdit = $user
                        && in_array($purchaseOrder->status, ['DRAFT', 'CHANGES_REQUESTED'], true)
                        && ($user->hasRole('administrator') || $purchaseOrder->created_by === $user->id);
                    $canWithdraw = $user
                        && $purchaseOrder->status === 'PENDING_APPROVAL'
                        && ($user->hasRole('administrator') || $purchaseOrder->created_by === $user->id);
                    $canCancel = $user
                        && $purchaseOrder->status === 'APPROVED'
                        && $purchaseOrder->receivingReports->isEmpty()
                        && (
                            $user->hasAnyRole(['administrator', 'purchasing-manager'])
                            || $purchaseOrder->created_by === $user->id
                        );
                    $canRemoveItems = $canEdit && $purchaseOrder->items->count() > 1;
                    $feeItemsForForm = old('fee_items', $purchaseOrder->fees_breakdown ?? []);

                    if (empty($feeItemsForForm)) {
                        $feeItemsForForm = [
                            ['type' => '', 'amount' => ''],
                        ];
                    }

                    $feeItemsReadOnly = collect($purchaseOrder->fees_breakdown ?? [])
                        ->filter(fn ($row) => is_array($row))
                        ->map(function (array $row) {
                            return [
                                'type' => trim((string) ($row['type'] ?? '')),
                                'amount' => (float) ($row['amount'] ?? 0),
                            ];
                        })
                        ->filter(fn (array $row) => $row['type'] !== '' || $row['amount'] > 0)
                        ->values();
                    $firstItemMeta = $purchaseOrder->items->first()?->meta ?? [];
                    $termOfPaymentType = old('term_of_payment_type', $purchaseOrder->term_of_payment_type ?? ($firstItemMeta['term_of_payment_type'] ?? ''));
                    $termOfPayment = old('term_of_payment', $purchaseOrder->term_of_payment ?? ($firstItemMeta['term_of_payment'] ?? ''));
                    $termOfDelivery = old('term_of_delivery', $purchaseOrder->term_of_delivery ?? ($firstItemMeta['term_of_delivery'] ?? ''));
                    $termPaymentDisplay = trim(($termOfPayment ? $termOfPayment . ' ' : '') . ($termOfPaymentType ? ucfirst($termOfPaymentType) : ''));
                    $termPaymentDisplay = $termPaymentDisplay !== '' ? $termPaymentDisplay : '-';
                @endphp
                @if ($canEdit)
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Supplier</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier?->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Created By</div>
                            <div class="fw-semibold">{{ $purchaseOrder->createdBy?->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">PO Number</div>
                            <div class="fw-semibold">{{ $purchaseOrder->po_number ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                    <form method="post" action="{{ route('purchase-orders.update', $purchaseOrder) }}" class="mb-4">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Currency</label>
                                <select name="currency_id" class="form-select" required>
                                    @foreach ($purchaseOrder->currency ? [$purchaseOrder->currency] : [] as $currency)
                                        <option value="{{ $currency->id }}" selected>{{ $currency->code }} - {{ $currency->name }}</option>
                                    @endforeach
                                    @foreach (\App\Models\Currency::query()->orderBy('id')->get() as $currency)
                                        <option value="{{ $currency->id }}" @selected($purchaseOrder->currency_id === $currency->id)>{{ $currency->code }} - {{ $currency->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label">Remark</label>
                                <div class="input-group">
                                    <select name="remark_type" class="form-select" style="max-width: 180px;">
                                        <option value="Normal" @selected($purchaseOrder->remark_type === 'Normal')>Normal</option>
                                        <option value="Confirmatory" @selected($purchaseOrder->remark_type === 'Confirmatory')>Confirmatory</option>
                                    </select>
                                    <input type="text" name="remark_text" class="form-control" value="{{ $purchaseOrder->remark_text }}" placeholder="Remark">
                                </div>
                            </div>
                            <div class="col-12 col-lg-5">
                                <label class="form-label">Term of Payment Type <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="term_of_payment_type" class="form-select" style="max-width: 120px;" required>
                                        <option value="">Select</option>
                                        <option value="cash" @selected($termOfPaymentType === 'cash')>Cash</option>
                                        <option value="credit" @selected($termOfPaymentType === 'credit')>Credit</option>
                                    </select>
                                    <input type="text" name="term_of_payment" class="form-control" value="{{ $termOfPayment }}" placeholder="Description optional">
                                </div>
                            </div>
                            <div class="col-12 col-lg-7">
                                <label class="form-label">Term of Delivery</label>
                                <input type="text" name="term_of_delivery" class="form-control" value="{{ $termOfDelivery }}">
                            </div>
                        </div>

                        @if ($purchaseOrder->approval_notes)
                            <div class="alert alert-warning mt-3">
                                <strong>Changes Requested:</strong> {{ $purchaseOrder->approval_notes }}
                            </div>
                        @endif

                        <div class="table-responsive mt-3">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>PRS ID</th>
                                        <th>Item</th>
                                        <th>Item Code</th>
                                        <th>Dept</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th class="text-end">Unit/Price</th>
                                        <th class="text-end">Disc %</th>
                                        <th class="text-end">VAT (PPN) %</th>
                                        <th class="text-end">Withholding Tax (PPh) %</th>
                                        <th class="text-end">Amount</th>
                                        <th>Notes</th>
                                        @if ($canEdit)
                                            <th class="text-center" style="width: 56px;"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseOrder->items as $index => $item)
                                        @php
                                            $isCapex = (bool) ($item->prsItem?->prs?->is_capex ?? ($item->meta['is_capex'] ?? false));
                                        @endphp
                                        <tr>
                                            <td>{{ $item->meta['prs_number'] ?? $item->prsItem?->prs?->prs_number ?? '-' }}</td>
                                            <td>
                                                {{ $item->item?->name }}
                                                @if ($isCapex)
                                                    <div class="mt-1"><span class="badge bg-light-primary">CAPEX</span></div>
                                                @endif
                                            </td>
                                            <td>{{ $item->item?->code ?? '-' }}</td>
                                            <td>{{ $item->prsItem?->prs?->department?->name ?? '-' }}</td>
                                            <td>
                                                <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control form-control-sm text-end" min="0.00001" step="0.00001" value="{{ $item->quantity }}" required style="min-width: 6.5rem;">
                                            </td>
                                            <td>{{ $item->item?->unit?->name ?? 'PCS' }}</td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $index }}][unit_price]" class="form-control form-control-sm text-end" min="0" step="0.00001" value="{{ format_po_decimal($item->unit_price, true) }}" required style="min-width: 9rem;">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $index }}][discount_rate]" class="form-control form-control-sm text-end" min="0" step="0.00001" value="{{ format_po_decimal($item->discount_rate ?? 0, true) }}" style="min-width: 7rem;">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $index }}][ppn_rate]" class="form-control form-control-sm text-end" min="0" step="0.00001" value="{{ format_po_decimal($item->ppn_rate ?? 0, true) }}" style="min-width: 7rem;">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $index }}][pph_rate]" class="form-control form-control-sm text-end" min="0" step="0.00001" value="{{ format_po_decimal($item->pph_rate ?? 0, true) }}" style="min-width: 7rem;">
                                            </td>
                                            <td class="text-end text-nowrap">{{ $currencyCode }} {{ format_po_decimal($item->total) }}</td>
                                            <td>
                                                <input type="text" name="items[{{ $index }}][notes]" class="form-control form-control-sm" value="{{ $item->notes }}">
                                            </td>
                                            @if ($canEdit)
                                                <td class="text-center">
                                                    @if ($canRemoveItems)
                                                        <button
                                                            type="submit"
                                                            form="remove-po-item-{{ $item->id }}"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Remove item"
                                                            data-bstooltip-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            onclick="return confirm('Remove this item from the PO? It will return to the Draft PO queue.');"
                                                        >
                                                            <i class="fa-duotone fa-solid fa-trash"></i>
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="A purchase order must keep at least one item">
                                                            <i class="fa-duotone fa-solid fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12 col-md-6"></div>
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal</span>
                                        <span class="fw-semibold">{{ $currencyCode }} {{ number_format($purchaseOrder->subtotal, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Discount</span>
                                        <span class="fw-semibold">- {{ $currencyCode }} {{ number_format($purchaseOrder->discount_amount ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>VAT (PPN)</span>
                                        <span class="fw-semibold">{{ $currencyCode }} {{ number_format($purchaseOrder->ppn_amount ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Withholding Tax (PPh)</span>
                                        <span class="fw-semibold">- {{ $currencyCode }} {{ number_format($purchaseOrder->pph_amount ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="border rounded-3 p-3 my-3 bg-light-subtle">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                            <div>
                                                <div class="fw-semibold">Additional Charges</div>
                                                <div class="text-muted small">Add or remove extra charges for this PO.</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-po-fee-btn">
                                                <i class="fa-duotone fa-solid fa-plus"></i>
                                                Add Charge
                                            </button>
                                        </div>

                                        <div id="po-fee-items-container" class="d-flex flex-column gap-2">
                                            @foreach ($feeItemsForForm as $index => $feeItem)
                                                <div class="po-fee-item-row border rounded-2 p-2 bg-white" data-fee-index="{{ $index }}">
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
                                                            <input
                                                                type="number"
                                                                name="fee_items[{{ $index }}][amount]"
                                                                class="form-control form-control-sm text-end"
                                                                min="0"
                                                                step="0.01"
                                                                value="{{ $feeItem['amount'] ?? '' }}"
                                                                placeholder="0"
                                                            >
                                                        </div>
                                                        <div class="col-2 col-md-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-po-fee-btn" aria-label="Remove charge">
                                                                <i class="fa-duotone fa-solid fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">Grand Total</span>
                                        <span class="fw-bold">{{ $currencyCode }} {{ number_format($purchaseOrder->total, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                    @if ($canRemoveItems)
                        @foreach ($purchaseOrder->items as $item)
                            <form
                                id="remove-po-item-{{ $item->id }}"
                                method="post"
                                action="{{ route('purchase-orders.items.destroy', [$purchaseOrder, $item]) }}"
                                class="d-none"
                            >
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                    @endif
                @else
                    @include('pages.purchase-orders.partials.detail-body', [
                        'purchaseOrder' => $purchaseOrder,
                        'showHeaderMeta' => true,
                    ])
                @endif
            </div>

            <div class="card-footer">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <form id="po-number-form" method="post" action="{{ route('purchase-orders.number', $purchaseOrder) }}">
                            @csrf
                            <label class="form-label">PO Number</label>
                            <div class="input-group has-validation">
                                <input
                                    id="po-number-input"
                                    type="text"
                                    name="po_number"
                                    class="form-control @error('po_number') is-invalid @enderror"
                                    value="{{ old('po_number', $purchaseOrder->po_number ?: ($nextPoNumber ?? '')) }}"
                                    aria-invalid="{{ $errors->has('po_number') ? 'true' : 'false' }}"
                                >
                                <input type="hidden" name="po_number_suggested" value="{{ $nextPoNumber ?? '' }}">
                                <button type="submit" class="btn btn-outline-primary">Save Number</button>
                                @error('po_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">Auto-generated, but editable if using existing pre-numbered forms.</div>
                        </form>
                    </div>
                    <div class="col-12 col-md-6 text-md-end">
                        @role('administrator|purchasing-staff|purchasing-manager')
                            @if ($canWithdraw)
                                <form id="withdraw-po-{{ $purchaseOrder->id }}" method="post" action="{{ route('purchase-orders.withdraw', $purchaseOrder) }}" class="d-inline">
                                    @csrf
                                </form>
                                <button type="button" class="btn btn-warning" onclick="confirmWithdrawPo('withdraw-po-{{ $purchaseOrder->id }}')">
                                    <i class="fa-duotone fa-solid fa-rotate-left"></i>
                                    Withdraw to Draft
                                </button>
                            @endif
                            @if ($canCancel)
                                <form id="cancel-po-{{ $purchaseOrder->id }}" method="post" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" class="d-inline">
                                    @csrf
                                </form>
                                <button type="button" class="btn btn-outline-danger" onclick="confirmCancelPo('cancel-po-{{ $purchaseOrder->id }}')">
                                    <i class="fa-duotone fa-solid fa-ban"></i>
                                    Cancel PO
                                </button>
                            @endif
                            @if (in_array($purchaseOrder->status, ['DRAFT', 'CHANGES_REQUESTED']))
                                <form method="post" action="{{ route('purchase-orders.submit', $purchaseOrder) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-duotone fa-solid fa-paper-plane"></i>
                                        Submit for Approval
                                    </button>
                                </form>
                            @endif
                        @endrole
                        @if ($purchaseOrder->status === 'APPROVED')
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poPrintConfirm-{{ $purchaseOrder->id }}">
                                <i class="fa-duotone fa-solid fa-print"></i>
                                Print PO
                            </button>
                        @elseif ($purchaseOrder->status === 'CANCELLED')
                            <button type="button" class="btn btn-primary disabled" disabled>
                                <i class="fa-duotone fa-solid fa-print"></i>
                                Print PO
                            </button>
                            <div class="text-muted small mt-2">This purchase order has been cancelled.</div>
                        @else
                            <button type="button" class="btn btn-primary disabled" disabled>
                                <i class="fa-duotone fa-solid fa-print"></i>
                                Print PO
                            </button>
                            <div class="text-muted small mt-2">PO must be approved before printing.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@if ($purchaseOrder->status === 'APPROVED')
    @include('pages.purchase-orders.partials.print-confirm-modal', [
        'purchaseOrder' => $purchaseOrder,
        'nextPoNumber' => $nextPoNumber ?? '',
        'syncFromInputId' => 'po-number-input',
    ])
@endif
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/document-print-confirm.js') }}"></script>
    @error('po_number')
        @php
            $isPrintConfirmNumberError = session()->hasOldInput('decimal_places')
                || session()->hasOldInput('print_confirm_id');
        @endphp
        @unless ($isPrintConfirmNumberError)
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const poNumberTarget = document.getElementById('po-number-form')
                        || document.getElementById('po-number-input');

                    if (poNumberTarget) {
                        poNumberTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: @json($message),
                            icon: 'error',
                            timer: 5000,
                        });
                    }
                });
            </script>
        @endunless
    @enderror
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('po-fee-items-container');
            const addBtn = document.getElementById('add-po-fee-btn');

            if (!container || !addBtn) {
                return;
            }

            const reindexRows = () => {
                container.querySelectorAll('.po-fee-item-row').forEach((row, index) => {
                    row.setAttribute('data-fee-index', String(index));

                    const typeInput = row.querySelector('input[type="text"]');
                    const amountInput = row.querySelector('input[type="number"]');

                    if (typeInput) {
                        typeInput.setAttribute('name', `fee_items[${index}][type]`);
                    }

                    if (amountInput) {
                        amountInput.setAttribute('name', `fee_items[${index}][amount]`);
                    }
                });
            };

            const createRow = (index) => {
                const row = document.createElement('div');
                row.className = 'po-fee-item-row border rounded-2 p-2 bg-white';
                row.setAttribute('data-fee-index', String(index));

                row.innerHTML = `
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label form-label-sm mb-1">Charge Type</label>
                            <input
                                type="text"
                                name="fee_items[${index}][type]"
                                class="form-control form-control-sm"
                                placeholder="e.g. Freight, Insurance, Handling"
                            >
                        </div>
                        <div class="col-10 col-md-4">
                            <label class="form-label form-label-sm mb-1">Amount</label>
                            <input
                                type="number"
                                name="fee_items[${index}][amount]"
                                class="form-control form-control-sm text-end"
                                min="0"
                                step="0.01"
                                placeholder="0"
                            >
                        </div>
                        <div class="col-2 col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-po-fee-btn" aria-label="Remove charge">
                                <i class="fa-duotone fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;

                return row;
            };

            addBtn.addEventListener('click', function () {
                const nextIndex = container.querySelectorAll('.po-fee-item-row').length;
                container.appendChild(createRow(nextIndex));
                reindexRows();
            });

            container.addEventListener('click', function (event) {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const removeBtn = target.closest('.remove-po-fee-btn');
                if (!removeBtn) {
                    return;
                }

                const row = removeBtn.closest('.po-fee-item-row');
                if (!row) {
                    return;
                }

                const rows = container.querySelectorAll('.po-fee-item-row');
                if (rows.length <= 1) {
                    const textInput = row.querySelector('input[type="text"]');
                    const numberInput = row.querySelector('input[type="number"]');

                    if (textInput) {
                        textInput.value = '';
                    }

                    if (numberInput) {
                        numberInput.value = '';
                    }

                    return;
                }

                row.remove();
                reindexRows();
            });
        });
    </script>
@endpush
