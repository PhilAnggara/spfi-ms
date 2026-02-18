@extends('layouts.app')
@section('title', ' | Supplier Canvasing')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Supplier Canvasing</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
                    <a href="{{ route('canvasing.report', $prsItem->id) }}" target="_blank" rel="noopener" class="btn btn-sm icon icon-left btn-outline-danger me-2">
                        <i class="fa-duotone fa-solid fa-file-pdf"></i>
                        Export PDF
                    </a>
                    <a href="{{ route('canvasing.index') }}" class="btn btn-sm icon icon-left btn-outline-secondary">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to list
                    </a>
                </div>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">PRS Number</div>
                            <div class="fw-bold">{{ $prsItem->prs->prs_number }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Submitted by</div>
                            <div class="fw-bold">{{ $prsItem->prs->user->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Department</div>
                            <div class="fw-bold">{{ $prsItem->prs->department->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Date Needed</div>
                            <div class="fw-bold">{{ tgl($prsItem->prs->date_needed) }}</div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-4">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="badge bg-light-info">{{ $prsItem->item->code }}</span>
                        <div class="fw-semibold">{{ $prsItem->item->name }}</div>
                        <div class="text-muted">{{ $prsItem->quantity }} {{ $prsItem->item->unit?->name ?? 'PCS' }}</div>
                    </div>
                </div>

                @php
                    $canvasingRows = $prsItem->canvasingItems->values();
                    if ($canvasingRows->isEmpty()) {
                        $canvasingRows = collect([null]);
                    }

                    $supplierTermMap = $suppliers
                        ->mapWithKeys(fn ($supplier) => [
                            $supplier->id => [
                                'term_of_payment_type' => $supplier->term_of_payment_type,
                                'term_of_payment' => $supplier->term_of_payment,
                                'term_of_delivery' => $supplier->term_of_delivery,
                            ],
                        ])
                        ->all();
                @endphp

                <form action="{{ route('canvasing.store', $prsItem->id) }}" method="post" class="form" id="canvasing-form">
                    @csrf
                    <div id="supplier-rows">
                        @foreach ($canvasingRows as $index => $canvasing)
                            <div class="card shadow-sm mb-3 supplier-row" data-index="{{ $index }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="fw-semibold">Supplier #{{ $index + 1 }}</div>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-supplier" @disabled($canvasingRows->count() === 1)>
                                            Remove
                                        </button>
                                    </div>
                                    <input type="hidden" name="suppliers[{{ $index }}][id]" value="{{ $canvasing?->id }}">
                                    <div class="row g-3">
                                        <div class="col-12 col-lg-6">
                                            <label class="form-label" for="supplier-{{ $prsItem->id }}-{{ $index }}">Supplier</label>
                                            <select id="supplier-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][supplier_id]" class="choices choices-supplier form-select" required>
                                                <option value="" disabled {{ $canvasing ? '' : 'selected' }}>-- Select Supplier --</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" data-custom-properties='@json(['searchText' => $supplier->name])' @selected($canvasing && $canvasing->supplier_id == $supplier->id)>{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label" for="unit-price-{{ $prsItem->id }}-{{ $index }}">Unit Price</label>
                                            <input type="number" id="unit-price-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][unit_price]" class="form-control" min="0" step="0.01" value="{{ $canvasing->unit_price ?? '' }}" required>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <label class="form-label" for="lead-time-{{ $prsItem->id }}-{{ $index }}">Lead Time (days)</label>
                                            <input type="number" id="lead-time-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][lead_time_days]" class="form-control" min="0" value="{{ $canvasing->lead_time_days ?? '' }}">
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label" for="term-payment-type-{{ $prsItem->id }}-{{ $index }}">Term of Payment</label>
                                            <div class="input-group">
                                                <select id="term-payment-type-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment_type]" class="form-select" style="max-width: 80px;">
                                                    <option value="" @selected(! ($canvasing?->term_of_payment_type))>Select</option>
                                                    <option value="cash" @selected($canvasing?->term_of_payment_type === 'cash')>Cash</option>
                                                    <option value="credit" @selected($canvasing?->term_of_payment_type === 'credit')>Credit</option>
                                                </select>
                                                <input type="text" id="term-payment-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment]" class="form-control" placeholder="e.g., 40% DP : 60% before delivery" value="{{ $canvasing->term_of_payment ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label" for="term-delivery-{{ $prsItem->id }}-{{ $index }}">Term of Delivery</label>
                                            <input type="text" id="term-delivery-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_delivery]" class="form-control" placeholder="e.g., FOB, CIF" value="{{ $canvasing->term_of_delivery ?? '' }}">
                                        </div>
                                        <div class="col-12 col-lg-4">
                                            <label class="form-label" for="notes-{{ $prsItem->id }}-{{ $index }}">Notes</label>
                                            <input type="text" id="notes-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][notes]" class="form-control" value="{{ $canvasing->notes ?? '' }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                        <button type="button" class="btn btn-outline-secondary" id="add-supplier">
                            <i class="fa-duotone fa-solid fa-layer-plus"></i>
                            Add Supplier
                        </button>
                        <button type="submit" class="btn icon icon-left btn-primary">
                            <i class="fa-duotone fa-solid fa-floppy-disk"></i>
                            Save Canvasing
                        </button>
                    </div>
                </form>

                <template id="supplier-row-template">
                    <div class="card shadow-sm mb-3 supplier-row" data-index="__INDEX__">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="fw-semibold">Supplier #__NUMBER__</div>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-supplier">Remove</button>
                            </div>
                            <input type="hidden" name="suppliers[__INDEX__][id]" value="">
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="supplier-{{ $prsItem->id }}-__INDEX__">Supplier</label>
                                    <select id="supplier-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][supplier_id]" class="choices choices-supplier form-select" required>
                                        <option value="" selected disabled>-- Select Supplier --</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" data-custom-properties='@json(['searchText' => $supplier->name])'>{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <label class="form-label" for="unit-price-{{ $prsItem->id }}-__INDEX__">Unit Price</label>
                                    <input type="number" id="unit-price-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][unit_price]" class="form-control" min="0" step="0.01" required>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <label class="form-label" for="lead-time-{{ $prsItem->id }}-__INDEX__">Lead Time (days)</label>
                                    <input type="number" id="lead-time-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][lead_time_days]" class="form-control" min="0">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="term-payment-type-{{ $prsItem->id }}-__INDEX__">Term of Payment</label>
                                    <div class="input-group">
                                        <select id="term-payment-type-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_payment_type]" class="form-select" style="max-width: 80px;">
                                            <option value="" selected>Select</option>
                                            <option value="cash">Cash</option>
                                            <option value="credit">Credit</option>
                                        </select>
                                        <input type="text" id="term-payment-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_payment]" class="form-control" placeholder="e.g., 40% DP : 60% before delivery">
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="term-delivery-{{ $prsItem->id }}-__INDEX__">Term of Delivery</label>
                                    <input type="text" id="term-delivery-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_delivery]" class="form-control" placeholder="e.g., FOB, CIF">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label" for="notes-{{ $prsItem->id }}-__INDEX__">Notes</label>
                                    <input type="text" id="notes-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][notes]" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
</div>
@endsection

@push('prepend-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/choices.js/public/assets/styles/choices.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/choices.js/public/assets/scripts/choices.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const supplierTermMap = @json($supplierTermMap);

            const applySupplierTerms = (row, force = false) => {
                if (!row) {
                    return;
                }

                const supplierSelect = row.querySelector('select[name$="[supplier_id]"]');
                const paymentTypeInput = row.querySelector('select[name$="[term_of_payment_type]"]');
                const paymentInput = row.querySelector('input[name$="[term_of_payment]"]');
                const deliveryInput = row.querySelector('input[name$="[term_of_delivery]"]');

                if (!supplierSelect || !paymentTypeInput || !paymentInput || !deliveryInput) {
                    return;
                }

                const supplierId = supplierSelect.value;
                if (!supplierId || !Object.prototype.hasOwnProperty.call(supplierTermMap, supplierId)) {
                    return;
                }

                const terms = supplierTermMap[supplierId] || {};

                if (force || !paymentTypeInput.value) {
                    paymentTypeInput.value = terms.term_of_payment_type ?? '';
                }

                if (force || !paymentInput.value.trim()) {
                    paymentInput.value = terms.term_of_payment ?? '';
                }

                if (force || !deliveryInput.value.trim()) {
                    deliveryInput.value = terms.term_of_delivery ?? '';
                }
            };

            const initChoices = (container) => {
                const supplierSelects = (container || document).querySelectorAll('.choices-supplier');
                supplierSelects.forEach((selectEl) => {
                    if (selectEl.choicesInstance) {
                        selectEl.choicesInstance.destroy();
                    }

                    const instance = new Choices(selectEl, {
                        searchFields: ['label', 'value', 'customProperties.searchText'],
                        fuseOptions: {
                            includeScore: true,
                            ignoreLocation: true,
                            threshold: 0.3,
                        },
                    });

                    selectEl.choicesInstance = instance;
                });
            };

            const updateRemoveButtons = () => {
                const rows = document.querySelectorAll('.supplier-row');
                rows.forEach((row) => {
                    const removeBtn = row.querySelector('.remove-supplier');
                    if (removeBtn) {
                        removeBtn.disabled = rows.length === 1;
                    }
                });
            };

            const addSupplierButton = document.getElementById('add-supplier');
            const rowsContainer = document.getElementById('supplier-rows');
            const template = document.getElementById('supplier-row-template');

            if (addSupplierButton && rowsContainer && template) {
                addSupplierButton.addEventListener('click', () => {
                    const index = rowsContainer.querySelectorAll('.supplier-row').length;
                    const html = template.innerHTML
                        .replaceAll('__INDEX__', index)
                        .replaceAll('__NUMBER__', index + 1);

                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html.trim();
                    const row = wrapper.firstElementChild;
                    rowsContainer.appendChild(row);
                    initChoices(row);
                    applySupplierTerms(row, false);
                    updateRemoveButtons();
                });

                rowsContainer.addEventListener('change', (event) => {
                    const supplierSelect = event.target.closest('select[name$="[supplier_id]"]');
                    if (!supplierSelect) {
                        return;
                    }

                    const row = supplierSelect.closest('.supplier-row');
                    applySupplierTerms(row, true);
                });

                rowsContainer.addEventListener('click', (event) => {
                    const removeBtn = event.target.closest('.remove-supplier');
                    if (!removeBtn) {
                        return;
                    }

                    const row = removeBtn.closest('.supplier-row');
                    if (!row) {
                        return;
                    }

                    row.remove();
                    updateRemoveButtons();
                });
            }

            initChoices(document);
            document.querySelectorAll('.supplier-row').forEach((row) => applySupplierTerms(row, true));
            updateRemoveButtons();
        });
    </script>
@endpush
