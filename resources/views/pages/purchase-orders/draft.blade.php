@extends('layouts.app')
@section('title', ' | Draft PO')

@section('content')
@php
    $totalSuppliers = $itemsBySupplier->count();
    $totalItems = $itemsBySupplier->sum(fn ($items) => $items->count());
    $totalAmount = $itemsBySupplier->sum(function ($items) {
        return $items->sum(function ($item) {
            return $item->quantity * ($item->selectedCanvassingItem?->unit_price ?? 0);
        });
    });
@endphp

<div id="po-page-container">
<div class="page-heading po-page po-draft-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Draft Purchase Order</h3>
                    <p class="text-muted mb-0">Select supplier items, review pricing, and prepare purchase orders from canvassing results.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-primary icon icon-left">
                        <i class="fa-duotone fa-solid fa-list-check"></i>
                        Open PO List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="po-draft-metric">
                    <span class="po-draft-metric-icon text-primary"><i class="fa-duotone fa-solid fa-truck-field"></i></span>
                    <div>
                        <div class="text-muted small">Supplier</div>
                        <div class="po-draft-metric-value">{{ $totalSuppliers }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="po-draft-metric">
                    <span class="po-draft-metric-icon text-success"><i class="fa-duotone fa-solid fa-boxes-stacked"></i></span>
                    <div>
                        <div class="text-muted small">Available Items</div>
                        <div class="po-draft-metric-value">{{ $totalItems }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="po-draft-metric">
                    <span class="po-draft-metric-icon text-warning"><i class="fa-duotone fa-solid fa-money-bill-wave"></i></span>
                    <div>
                        <div class="text-muted small">Estimated Total</div>
                        <div class="po-draft-metric-value">Rp {{ number_format($totalAmount, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end po-filter-grid" id="po-filter-form">
                    <div class="col-12 col-md-8 col-xl-9">
                        <label for="filter-po-keyword" class="form-label mb-1">Search Draft PO</label>
                        <input type="text" id="filter-po-keyword" name="keyword" class="form-control" value="{{ $keyword ?? request('keyword') }}" placeholder="Supplier / item name / item code / PRS number">
                    </div>
                    <div class="col-6 col-md-2 col-xl-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-light fa-magnifying-glass me-1"></i>
                            Search
                        </button>
                    </div>
                    <div class="col-6 col-md-2 col-xl-1">
                        <button type="button" id="reset-po-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="po-page-results">
        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="po-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0">Supplier Draft Queue</h5>
                        @if (($keyword ?? '') !== '')
                            <small class="text-muted">Filtered by "{{ $keyword }}"</small>
                        @endif
                    </div>
                    <span class="badge bg-light-primary">{{ itemOrItems($totalItems) }}</span>
                </div>

                @if ($itemsBySupplier->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-inbox po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No items available for PO creation.</p>
                        <small>Try changing your supplier or item keyword.</small>
                    </div>
                @else
                    <div class="accordion po-draft-accordion" id="poDraftAccordion">
                        @foreach ($itemsBySupplier as $supplierId => $items)
                            @php
                                $supplier = $items->first()?->selectedCanvassingItem?->supplier;
                                $accordionId = 'supplier-' . $supplierId;
                                $supplierTotal = $items->sum(function ($item) {
                                    return $item->quantity * ($item->selectedCanvassingItem?->unit_price ?? 0);
                                });
                                $capexCount = $items->filter(fn ($item) => (bool) ($item->prs?->is_capex ?? false))->count();
                            @endphp
                            <div class="accordion-item po-draft-supplier-item">
                                <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse-{{ $accordionId }}">
                                        <div class="po-draft-supplier-heading">
                                            <span class="po-draft-supplier-icon"><i class="fa-duotone fa-solid fa-truck-field"></i></span>
                                            <div class="po-draft-supplier-main">
                                                <span class="fw-semibold">{{ $supplier?->name ?? 'Unknown Supplier' }}</span>
                                                <small class="text-muted">{{ itemOrItems($items->count()) }}</small>
                                            </div>
                                            <div class="po-draft-supplier-meta">
                                                <span class="badge bg-light-secondary">{{ $items->pluck('prs_id')->filter()->unique()->count() }} PRS</span>
                                                @if ($capexCount > 0)
                                                    <span class="badge bg-light-primary">CAPEX {{ $capexCount }}</span>
                                                @endif
                                                <span class="badge bg-light-success text-success">Rp {{ number_format($supplierTotal, 2) }}</span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#poDraftAccordion" aria-labelledby="heading-{{ $accordionId }}">
                                    <div class="accordion-body">
                                        <form method="post" action="{{ route('purchase-orders.preview') }}" class="po-supplier-form">
                                            @csrf
                                            <input type="hidden" name="supplier_id" value="{{ $supplierId }}">

                                            <div class="po-draft-form-toolbar">
                                                <div class="text-muted small">
                                                    <i class="fa-light fa-circle-check me-1"></i>
                                                    {{ itemOrItems($items->count()) }} ready for preview
                                                </div>
                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary select-all" data-supplier="{{ $supplierId }}">
                                                        <i class="fa-light fa-check-double me-1"></i>
                                                        Select All
                                                    </button>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fa-duotone fa-solid fa-eye me-1"></i>
                                                        Preview PO
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-striped align-middle po-table po-draft-table text-nowrap">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 40px;"></th>
                                                            <th>PRS</th>
                                                            <th>Item</th>
                                                            <th>Qty</th>
                                                            <th>Unit</th>
                                                            <th>Unit Price</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($items as $index => $prsItem)
                                                            @php
                                                                $canvassing = $prsItem->selectedCanvassingItem;
                                                                $item = $prsItem->item;
                                                                $isCapex = (bool) ($prsItem->prs?->is_capex ?? false);
                                                                $accountingCategory = $isCapex ? 'capex' : 'non_capex';
                                                            @endphp
                                                            <tr data-accounting-category="{{ $accountingCategory }}">
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input item-checkbox" data-accounting-category="{{ $accountingCategory }}" checked>
                                                                    <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $prsItem->id }}">
                                                                    <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $prsItem->quantity }}">
                                                                    <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $canvassing?->unit_price ?? 0 }}">
                                                                    <input type="hidden" name="items[{{ $index }}][notes]" value="{{ $canvassing?->notes }}">
                                                                    <input type="hidden" name="items[{{ $index }}][checked]" class="item-checked" value="1">
                                                                </td>
                                                                <td>
                                                                    {{ $prsItem->prs?->prs_number ?? '-' }}
                                                                    @if ($isCapex)
                                                                        <div class="mt-1"><span class="badge bg-light-primary">CAPEX</span></div>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div class="fw-semibold">{{ $item?->name ?? 'Item not found' }}</div>
                                                                    <small class="text-muted">{{ $item?->code ?? '-' }}</small>
                                                                </td>
                                                                <td class="fw-semibold">{{ $prsItem->quantity }}</td>
                                                                <td>{{ $item?->unit?->name ?? 'PCS' }}</td>
                                                                <td class="fw-semibold">{{ number_format($canvassing?->unit_price ?? 0, 2) }}</td>
                                                                <td>{{ $canvassing?->notes ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        </div>
    </section>
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/purchase-orders-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/purchase-orders-index.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/po-draft-index.js') }}"></script>
@endpush
