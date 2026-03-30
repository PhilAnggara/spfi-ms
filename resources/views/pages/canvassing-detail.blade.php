@extends('layouts.app')
@section('title', ' | Supplier Canvassing')

@section('content')
    @php
        $canvassingRows = $prsItem->canvassingItems->values();
        if ($canvassingRows->isEmpty()) {
            $canvassingRows = collect([null]);
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
                    <h3 class="mb-1">Supplier Canvassing</h3>
                    <p class="text-muted mb-0">Input penawaran supplier dengan tampilan yang lebih cepat, rapi, dan mudah dipilih.</p>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <a href="{{ route('canvassing.report', $prsItem->id) }}" target="_blank" rel="noopener" class="btn btn-sm icon icon-left btn-outline-danger">
                            <i class="fa-duotone fa-solid fa-file-pdf"></i>
                            Export PDF
                        </a>
                        <a href="{{ route('canvassing.index') }}" class="btn btn-sm icon icon-left btn-outline-secondary">
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
                                <form action="{{ route('canvassing.toggle-direct-purchase', $prsItem->id) }}" method="POST" class="d-inline">
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
                    <form action="{{ route('canvassing.store', $prsItem->id) }}" method="post" class="form" id="canvassing-form" novalidate>
                        @csrf

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Penawaran Supplier</h5>
                                <p class="text-muted mb-0 small">Setiap supplier hanya boleh dipilih satu kali.</p>
                            </div>
                            <span class="badge bg-light-primary" id="supplier-summary">0/0 supplier dipilih</span>
                        </div>

                        <div id="form-notice" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div id="supplier-rows" data-next-index="{{ $canvassingRows->count() }}">
                            @foreach ($canvassingRows as $index => $canvassing)
                                <div class="card border shadow-sm mb-3 supplier-row" data-index="{{ $index }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                            <span class="badge bg-light-secondary supplier-number">Supplier #{{ $index + 1 }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-supplier" @disabled($canvassingRows->count() === 1)>Remove</button>
                                        </div>
                                        <div class="supplier-name px-3 py-2 rounded border bg-light-primary w-100 cursor-pointer d-flex justify-content-between align-items-center mb-3" data-placeholder="Belum dipilih" role="button" tabindex="0">
                                            <span class="supplier-name-text flex-grow-1">{{ $canvassing?->supplier?->name ?? 'Belum dipilih' }}</span>
                                            <button type="button" class="btn btn-sm p-0 ms-2 clear-supplier" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; display: none;" title="Clear supplier">
                                                <i class="fa-duotone fa-solid fa-xmark"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" name="suppliers[{{ $index }}][id]" value="{{ $canvassing?->id }}">
                                        <input type="hidden" name="suppliers[{{ $index }}][supplier_id]" class="supplier-id-input" value="{{ $canvassing?->supplier_id }}">

                                        <div class="row g-3">
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="unit-price-{{ $prsItem->id }}-{{ $index }}">Unit Price</label>
                                                <input type="number" id="unit-price-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][unit_price]" class="form-control" min="0" step="0.01" value="{{ $canvassing->unit_price ?? '' }}" required>
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="lead-time-{{ $prsItem->id }}-{{ $index }}">Lead Time (days)</label>
                                                <input type="number" id="lead-time-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][lead_time_days]" class="form-control" min="0" value="{{ $canvassing->lead_time_days ?? '' }}">
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label" for="notes-{{ $prsItem->id }}-{{ $index }}">Notes</label>
                                                <input type="text" id="notes-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][notes]" class="form-control" value="{{ $canvassing->notes ?? '' }}" placeholder="Tambahan catatan supplier">
                                            </div>

                                            <div class="col-12 col-lg-5">
                                                <label class="form-label" for="term-payment-type-{{ $prsItem->id }}-{{ $index }}">Term of Payment</label>
                                                <div class="input-group">
                                                    <select id="term-payment-type-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment_type]" class="form-select" style="max-width: 100px;">
                                                        <option value="" @selected(! ($canvassing?->term_of_payment_type))>Select</option>
                                                        <option value="cash" @selected($canvassing?->term_of_payment_type === 'cash')>Cash</option>
                                                        <option value="credit" @selected($canvassing?->term_of_payment_type === 'credit')>Credit</option>
                                                    </select>
                                                    <input type="text" id="term-payment-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_payment]" class="form-control" placeholder="e.g., 40% DP : 60% before delivery" value="{{ $canvassing->term_of_payment ?? '' }}">
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-7">
                                                <label class="form-label" for="term-delivery-{{ $prsItem->id }}-{{ $index }}">Term of Delivery</label>
                                                <input type="text" id="term-delivery-{{ $prsItem->id }}-{{ $index }}" name="suppliers[{{ $index }}][term_of_delivery]" class="form-control" placeholder="e.g., FOB, CIF" value="{{ $canvassing->term_of_delivery ?? '' }}">
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
                                Save Canvassing
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

    <script id="canvassing-supplier-term-map" type="application/json">@json($supplierTermMap)</script>
    <script id="canvassing-supplier-list" type="application/json">@json($supplierList)</script>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/modules/canvassing-detail.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/canvassing-detail.js') }}"></script>
@endpush
