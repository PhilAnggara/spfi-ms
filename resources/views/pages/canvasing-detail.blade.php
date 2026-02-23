@extends('layouts.app')
@section('title', ' | Supplier Canvasing')

@section('content')
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

        $supplierList = $suppliers
            ->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ])
            ->values();
    @endphp

    <div class="page-heading">
        <div class="page-title mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-7">
                    <h3 class="mb-1">Supplier Canvasing</h3>
                    <p class="text-muted mb-0">Input penawaran supplier dengan tampilan yang lebih cepat, rapi, dan mudah dipilih.</p>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <a href="{{ route('canvasing.report', $prsItem->id) }}" target="_blank" rel="noopener" class="btn btn-sm icon icon-left btn-outline-danger">
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
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">PRS Number</div>
                                <div class="fw-bold fs-6">{{ $prsItem->prs->prs_number }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Submitted by</div>
                                <div class="fw-bold fs-6">{{ $prsItem->prs->user->name }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Department</div>
                                <div class="fw-bold fs-6">{{ $prsItem->prs->department->name }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Date Needed</div>
                                <div class="fw-bold fs-6">{{ tgl($prsItem->prs->date_needed) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mt-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge bg-light-info text-uppercase">{{ $prsItem->item->code }}</span>
                                <span class="fw-semibold">{{ $prsItem->item->name }}</span>
                                <span class="badge bg-light-secondary">Qty {{ $prsItem->quantity }} {{ $prsItem->item->unit?->name ?? 'PCS' }}</span>
                                @if ($prsItem->is_direct_purchase)
                                    <span class="badge bg-light-info">
                                        <i class="fa-duotone fa-solid fa-basket-shopping"></i>
                                        Direct Purchase
                                    </span>
                                @endif
                            </div>
                            @if (!$prsItem->purchase_order_id)
                                <form action="{{ route('canvasing.toggle-direct-purchase', $prsItem->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="is_direct_purchase" value="{{ $prsItem->is_direct_purchase ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm {{ $prsItem->is_direct_purchase ? 'btn-info' : 'btn-outline-info' }}">
                                        <i class="fa-duotone fa-solid fa-basket-shopping"></i>
                                        {{ $prsItem->is_direct_purchase ? 'Revert to Needs PO' : 'Mark as Direct Purchase' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('canvasing.store', $prsItem->id) }}" method="post" class="form" id="canvasing-form" novalidate>
                        @csrf

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Penawaran Supplier</h5>
                                <p class="text-muted mb-0 small">Setiap supplier hanya boleh dipilih satu kali.</p>
                            </div>
                            <span class="badge bg-light-primary" id="supplier-summary">0/0 supplier dipilih</span>
                        </div>

                        <div id="form-notice" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div id="supplier-rows" data-next-index="{{ $canvasingRows->count() }}">
                            @foreach ($canvasingRows as $index => $canvasing)
                                <div class="card border shadow-sm mb-3 supplier-row" data-index="{{ $index }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                            <span class="badge bg-light-secondary supplier-number">Supplier #{{ $index + 1 }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-supplier" @disabled($canvasingRows->count() === 1)>Remove</button>
                                        </div>
                                        <div class="supplier-name px-3 py-2 rounded border bg-light-primary w-100 cursor-pointer d-flex justify-content-between align-items-center mb-3" data-placeholder="Belum dipilih" role="button" tabindex="0">
                                            <span class="supplier-name-text flex-grow-1">{{ $canvasing?->supplier?->name ?? 'Belum dipilih' }}</span>
                                            <button type="button" class="btn btn-sm p-0 ms-2 clear-supplier" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; display: none;" title="Clear supplier">
                                                <i class="fa-duotone fa-solid fa-xmark"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" name="suppliers[{{ $index }}][id]" value="{{ $canvasing?->id }}">
                                        <input type="hidden" name="suppliers[{{ $index }}][supplier_id]" class="supplier-id-input" value="{{ $canvasing?->supplier_id }}">

                                        <div class="row g-3">
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="unit-price-{{ $prsItem->id }}-{{ $index }}">Unit Price</label>
                                                <input type="number" id="unit-price-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][unit_price]" class="form-control" min="0" step="0.01" value="{{ $canvasing->unit_price ?? '' }}" required>
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="lead-time-{{ $prsItem->id }}-{{ $index }}">Lead Time (days)</label>
                                                <input type="number" id="lead-time-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][lead_time_days]" class="form-control" min="0" value="{{ $canvasing->lead_time_days ?? '' }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label" for="notes-{{ $prsItem->id }}-{{ $index }}">Notes</label>
                                                <input type="text" id="notes-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][notes]" class="form-control" value="{{ $canvasing->notes ?? '' }}" placeholder="Tambahan catatan supplier">
                                            </div>

                                            <div class="col-12 col-lg-5">
                                                <label class="form-label" for="term-payment-type-{{ $prsItem->id }}-{{ $index }}">Term of Payment</label>
                                                <div class="input-group">
                                                    <select id="term-payment-type-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment_type]" class="form-select" style="max-width: 100px;">
                                                        <option value="" @selected(! ($canvasing?->term_of_payment_type))>Select</option>
                                                        <option value="cash" @selected($canvasing?->term_of_payment_type === 'cash')>Cash</option>
                                                        <option value="credit" @selected($canvasing?->term_of_payment_type === 'credit')>Credit</option>
                                                    </select>
                                                    <input type="text" id="term-payment-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment]" class="form-control" placeholder="e.g., 40% DP : 60% before delivery" value="{{ $canvasing->term_of_payment ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-7">
                                                <label class="form-label" for="term-delivery-{{ $prsItem->id }}-{{ $index }}">Term of Delivery</label>
                                                <input type="text" id="term-delivery-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_delivery]" class="form-control" placeholder="e.g., FOB, CIF" value="{{ $canvasing->term_of_delivery ?? '' }}">
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
                        <div class="card border shadow-sm mb-3 supplier-row" data-index="__INDEX__">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <span class="badge bg-light-secondary supplier-number">Supplier #__NUMBER__</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-supplier">Remove</button>
                                </div>
                                <div class="supplier-name px-3 py-2 rounded border bg-light-primary w-100 cursor-pointer d-flex justify-content-between align-items-center mb-3" data-placeholder="Belum dipilih" role="button" tabindex="0">
                                    <span class="supplier-name-text flex-grow-1">Belum dipilih</span>
                                    <button type="button" class="btn btn-sm p-0 ms-2 clear-supplier" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; display: none;" title="Clear supplier">
                                        <i class="fa-duotone fa-solid fa-xmark"></i>
                                    </button>
                                </div>

                                <input type="hidden" name="suppliers[__INDEX__][id]" value="">
                                <input type="hidden" name="suppliers[__INDEX__][supplier_id]" class="supplier-id-input" value="">

                                <div class="row g-3">
                                    <div class="col-12 col-lg-3">
                                        <label class="form-label" for="unit-price-{{ $prsItem->id }}-__INDEX__">Unit Price</label>
                                        <input type="number" id="unit-price-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][unit_price]" class="form-control" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-12 col-lg-3">
                                        <label class="form-label" for="lead-time-{{ $prsItem->id }}-__INDEX__">Lead Time (days)</label>
                                        <input type="number" id="lead-time-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][lead_time_days]" class="form-control" min="0">
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label" for="notes-{{ $prsItem->id }}-__INDEX__">Notes</label>
                                        <input type="text" id="notes-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][notes]" class="form-control" placeholder="Tambahan catatan supplier">
                                    </div>

                                    <div class="col-12 col-lg-5">
                                        <label class="form-label" for="term-payment-type-{{ $prsItem->id }}-__INDEX__">Term of Payment</label>
                                        <div class="input-group">
                                            <select id="term-payment-type-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_payment_type]" class="form-select" style="max-width: 100px;">
                                                <option value="" selected>Select</option>
                                                <option value="cash">Cash</option>
                                                <option value="credit">Credit</option>
                                            </select>
                                            <input type="text" id="term-payment-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_payment]" class="form-control" placeholder="e.g., 40% DP : 60% before delivery">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-7">
                                        <label class="form-label" for="term-delivery-{{ $prsItem->id }}-__INDEX__">Term of Delivery</label>
                                        <input type="text" id="term-delivery-{{ $prsItem->id }}-__INDEX__" name="suppliers[__INDEX__][term_of_delivery]" class="form-control" placeholder="e.g., FOB, CIF">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="supplierPickerModal" tabindex="-1" aria-labelledby="supplierPickerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierPickerModalLabel">Pilih Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="supplier-picker-search" placeholder="Cari supplier...">
                    </div>
                    <div id="supplier-picker-list" class="list-group"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('addon-style')
    <style>
        .supplier-name {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .supplier-name:hover {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
        }

        .supplier-name:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }

        .supplier-name .clear-supplier {
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }

        .supplier-name:hover .clear-supplier {
            opacity: 1;
        }
    </style>
@endpush

@push('addon-script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const supplierTermMap = @json($supplierTermMap);
            const suppliers = @json($supplierList);

            const rowsContainer = document.getElementById('supplier-rows');
            const template = document.getElementById('supplier-row-template');
            const addSupplierButton = document.getElementById('add-supplier');
            const form = document.getElementById('canvasing-form');
            const supplierSummary = document.getElementById('supplier-summary');
            const formNotice = document.getElementById('form-notice');

            const pickerModalEl = document.getElementById('supplierPickerModal');
            const pickerSearchInput = document.getElementById('supplier-picker-search');
            const pickerList = document.getElementById('supplier-picker-list');
            const pickerModal = pickerModalEl ? new bootstrap.Modal(pickerModalEl) : null;

            let activeRow = null;

            const getSupplierNameById = (supplierId) => {
                const found = suppliers.find((supplier) => String(supplier.id) === String(supplierId));
                return found ? found.name : '';
            };

            const getRows = () => Array.from(rowsContainer.querySelectorAll('.supplier-row'));

            const showFormNotice = (message) => {
                if (!formNotice) {
                    return;
                }

                formNotice.textContent = message;
                formNotice.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            const clearFormNotice = () => {
                if (!formNotice) {
                    return;
                }

                formNotice.textContent = '';
                formNotice.classList.add('d-none');
            };

            const setRowInvalidState = (row, invalid = false) => {
                if (!row) {
                    return;
                }

                row.classList.toggle('border-danger', invalid);
                row.classList.toggle('border-2', invalid);
            };

            const getSelectedSupplierIds = (exceptRow = null) => {
                return getRows()
                    .filter((row) => row !== exceptRow)
                    .map((row) => row.querySelector('.supplier-id-input')?.value)
                    .filter(Boolean);
            };

            const applySupplierTerms = (row, force = false) => {
                if (!row) {
                    return;
                }

                const supplierId = row.querySelector('.supplier-id-input')?.value;
                const paymentTypeInput = row.querySelector('select[name$="[term_of_payment_type]"]');
                const paymentInput = row.querySelector('input[name$="[term_of_payment]"]');
                const deliveryInput = row.querySelector('input[name$="[term_of_delivery]"]');

                if (!supplierId || !paymentTypeInput || !paymentInput || !deliveryInput) {
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

            const updateSupplierBadge = (row) => {
                const supplierNameEl = row.querySelector('.supplier-name');
                const supplierNameText = row.querySelector('.supplier-name-text');
                const clearButton = row.querySelector('.clear-supplier');
                const supplierId = row.querySelector('.supplier-id-input')?.value;
                if (!supplierNameEl || !supplierNameText) {
                    return;
                }

                const placeholder = supplierNameEl.dataset.placeholder || 'Belum dipilih';
                const supplierName = supplierId ? getSupplierNameById(supplierId) : '';

                supplierNameEl.classList.remove('bg-light-primary', 'bg-body', 'text-dark', 'text-muted', 'fw-semibold');

                if (supplierName) {
                    supplierNameText.textContent = supplierName;
                    supplierNameEl.classList.add('bg-body', 'text-dark', 'fw-semibold');
                    if (clearButton) {
                        clearButton.style.display = 'flex';
                    }
                } else {
                    supplierNameText.textContent = placeholder;
                    supplierNameEl.classList.add('bg-light-primary', 'text-muted');
                    if (clearButton) {
                        clearButton.style.display = 'none';
                    }
                }
            };

            const updateSupplierSummary = () => {
                if (!supplierSummary) {
                    return;
                }

                const rows = getRows();
                const selectedCount = rows.filter((row) => row.querySelector('.supplier-id-input')?.value).length;
                supplierSummary.textContent = `${selectedCount}/${rows.length} supplier dipilih`;
            };

            const updateRemoveButtons = () => {
                const rows = getRows();
                rows.forEach((row, index) => {
                    const removeBtn = row.querySelector('.remove-supplier');
                    const numberBadge = row.querySelector('.supplier-number');

                    if (removeBtn) {
                        removeBtn.disabled = rows.length === 1;
                    }

                    if (numberBadge) {
                        numberBadge.textContent = `Supplier #${index + 1}`;
                    }
                });
            };

            const showSupplierPicker = (row) => {
                activeRow = row;
                renderSupplierPickerList('');
                if (pickerSearchInput) {
                    pickerSearchInput.value = '';
                }
                if (pickerModal) {
                    pickerModal.show();
                }
            };

            const renderSupplierPickerList = (searchText) => {
                if (!pickerList) {
                    return;
                }

                const keyword = (searchText || '').trim().toLowerCase();
                const selectedByOthers = getSelectedSupplierIds(activeRow);
                const currentSupplierId = activeRow?.querySelector('.supplier-id-input')?.value || null;

                const filteredSuppliers = suppliers.filter((supplier) => {
                    if (!keyword) {
                        return true;
                    }

                    return supplier.name.toLowerCase().includes(keyword);
                })
                .sort((a, b) => {
                    if (!keyword) {
                        return a.name.localeCompare(b.name);
                    }

                    const aName = a.name.toLowerCase();
                    const bName = b.name.toLowerCase();
                    const aStarts = aName.startsWith(keyword) ? 0 : 1;
                    const bStarts = bName.startsWith(keyword) ? 0 : 1;

                    if (aStarts !== bStarts) {
                        return aStarts - bStarts;
                    }

                    const aIndex = aName.indexOf(keyword);
                    const bIndex = bName.indexOf(keyword);
                    if (aIndex !== bIndex) {
                        return aIndex - bIndex;
                    }

                    return a.name.localeCompare(b.name);
                });

                if (!filteredSuppliers.length) {
                    pickerList.innerHTML = '<div class="text-muted small p-2">Supplier tidak ditemukan.</div>';
                    return;
                }

                pickerList.innerHTML = filteredSuppliers
                    .map((supplier) => {
                        const isTaken = selectedByOthers.includes(String(supplier.id));
                        const isSelected = String(supplier.id) === String(currentSupplierId || '');

                        return `
                            <button
                                type="button"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isSelected ? 'active' : ''}"
                                data-supplier-id="${supplier.id}"
                                ${isTaken ? 'disabled' : ''}
                            >
                                <span>${supplier.name}</span>
                                ${isTaken ? '<span class="badge bg-light-secondary">Sudah dipilih</span>' : ''}
                            </button>
                        `;
                    })
                    .join('');
            };

            const selectSupplierFromButton = (supplierButton) => {
                if (!supplierButton || !activeRow) {
                    return false;
                }

                const selectedByOthers = getSelectedSupplierIds(activeRow);
                const supplierId = supplierButton.dataset.supplierId;

                if (!supplierId || selectedByOthers.includes(String(supplierId))) {
                    return false;
                }

                assignSupplierToRow(activeRow, supplierId);

                if (pickerModal) {
                    pickerModal.hide();
                }

                return true;
            };

            const assignSupplierToRow = (row, supplierId) => {
                if (!row) {
                    return;
                }

                const hiddenSupplierInput = row.querySelector('.supplier-id-input');
                if (!hiddenSupplierInput) {
                    return;
                }

                hiddenSupplierInput.value = supplierId ? String(supplierId) : '';
                updateSupplierBadge(row);
                applySupplierTerms(row, true);
                setRowInvalidState(row, false);
                clearFormNotice();
                updateSupplierSummary();
            };

            const addSupplierRow = () => {
                if (!template || !rowsContainer) {
                    return;
                }

                const nextIndex = Number(rowsContainer.dataset.nextIndex || getRows().length);
                const nextNumber = getRows().length + 1;

                const html = template.innerHTML
                    .replaceAll('__INDEX__', String(nextIndex))
                    .replaceAll('__NUMBER__', String(nextNumber));

                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const row = wrapper.firstElementChild;
                rowsContainer.appendChild(row);

                rowsContainer.dataset.nextIndex = String(nextIndex + 1);
                updateSupplierBadge(row);
                clearFormNotice();
                updateRemoveButtons();
                updateSupplierSummary();
            };

            if (addSupplierButton) {
                addSupplierButton.addEventListener('click', addSupplierRow);
            }

            rowsContainer.addEventListener('click', (event) => {
                const clearButton = event.target.closest('.clear-supplier');
                if (clearButton) {
                    const row = clearButton.closest('.supplier-row');
                    setRowInvalidState(row, false);
                    assignSupplierToRow(row, null);
                    return;
                }

                const removeButton = event.target.closest('.remove-supplier');
                if (removeButton) {
                    const row = removeButton.closest('.supplier-row');
                    if (!row) {
                        return;
                    }

                    row.remove();
                    clearFormNotice();
                    updateRemoveButtons();
                    updateSupplierSummary();
                    return;
                }

                const supplierName = event.target.closest('.supplier-name');
                if (supplierName && !event.target.closest('.clear-supplier')) {
                    const row = supplierName.closest('.supplier-row');
                    if (row) {
                        showSupplierPicker(row);
                    }
                    return;
                }
            });

            if (pickerModalEl) {
                pickerModalEl.addEventListener('shown.bs.modal', () => {
                    pickerSearchInput?.focus();
                });
            }

            rowsContainer.addEventListener('keydown', (event) => {
                const supplierName = event.target.closest('.supplier-name');
                if (!supplierName) {
                    return;
                }

                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    const row = supplierName.closest('.supplier-row');
                    if (row) {
                        showSupplierPicker(row);
                    }
                }
            });

            if (pickerSearchInput) {
                pickerSearchInput.addEventListener('input', (event) => {
                    renderSupplierPickerList(event.target.value || '');
                });

                pickerSearchInput.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    event.preventDefault();

                    const firstAvailableButton = pickerList?.querySelector('[data-supplier-id]:not([disabled])');
                    if (!firstAvailableButton) {
                        return;
                    }

                    selectSupplierFromButton(firstAvailableButton);
                });
            }

            if (pickerList) {
                pickerList.addEventListener('click', (event) => {
                    const supplierButton = event.target.closest('[data-supplier-id]');
                    if (!supplierButton) {
                        return;
                    }

                    selectSupplierFromButton(supplierButton);
                });
            }

            if (form) {
                form.addEventListener('submit', (event) => {
                    const rows = getRows();
                    rows.forEach((row) => setRowInvalidState(row, false));

                    const selectedIds = rows
                        .map((row) => row.querySelector('.supplier-id-input')?.value)
                        .filter(Boolean);

                    const unselectedRows = rows.filter((row) => !row.querySelector('.supplier-id-input')?.value);
                    if (unselectedRows.length) {
                        unselectedRows.forEach((row) => setRowInvalidState(row, true));
                        event.preventDefault();
                        showFormNotice('Mohon pilih supplier untuk setiap baris terlebih dahulu.');
                        return;
                    }

                    const uniqueCount = new Set(selectedIds).size;
                    if (uniqueCount !== selectedIds.length) {
                        const countMap = new Map();
                        selectedIds.forEach((supplierId) => {
                            countMap.set(supplierId, (countMap.get(supplierId) || 0) + 1);
                        });

                        rows.forEach((row) => {
                            const supplierId = row.querySelector('.supplier-id-input')?.value;
                            if (supplierId && (countMap.get(supplierId) || 0) > 1) {
                                setRowInvalidState(row, true);
                            }
                        });

                        event.preventDefault();
                        showFormNotice('Supplier yang sama tidak boleh dipilih lebih dari satu kali.');
                        return;
                    }

                    if (!form.checkValidity()) {
                        event.preventDefault();
                        showFormNotice('Mohon lengkapi field wajib seperti Unit Price pada setiap baris.');
                        form.reportValidity();
                        return;
                    }

                    clearFormNotice();
                });
            }

            getRows().forEach((row) => {
                updateSupplierBadge(row);
                applySupplierTerms(row, false);
            });
            updateRemoveButtons();
            updateSupplierSummary();
        });
    </script>
@endpush
