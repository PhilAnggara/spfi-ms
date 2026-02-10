@extends('layouts.app')
@section('title', ' | Draft PO')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Draft PO</h3>
                <p class="text-muted mb-0">Select items per supplier to create a Purchase Order.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                @if ($itemsBySupplier->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fa-duotone fa-solid fa-inbox"></i>
                        <p class="mb-0 mt-2">No items available for PO creation.</p>
                    </div>
                @else
                    <div class="accordion" id="poDraftAccordion">
                        @foreach ($itemsBySupplier as $supplierId => $items)
                            @php
                                $supplier = $items->first()?->canvasingItem?->supplier;
                                $accordionId = 'supplier-' . $supplierId;
                            @endphp
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-{{ $accordionId }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $accordionId }}">
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">{{ $supplier?->name ?? 'Unknown Supplier' }}</span>
                                            <small class="text-muted">{{ itemOrItems($items->count()) }}</small>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-{{ $accordionId }}" class="accordion-collapse collapse" data-bs-parent="#poDraftAccordion">
                                    <div class="accordion-body">
                                        <form method="post" action="{{ route('purchase-orders.preview') }}" class="po-supplier-form">
                                            @csrf
                                            <input type="hidden" name="supplier_id" value="{{ $supplierId }}">

                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                                <button type="button" class="btn btn-sm btn-outline-secondary select-all" data-supplier="{{ $supplierId }}">
                                                    Select All
                                                </button>
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fa-duotone fa-solid fa-eye"></i>
                                                    Preview PO
                                                </button>
                                                <button type="submit" class="btn btn-sm btn-success" name="action" value="submit" formaction="{{ route('purchase-orders.store') }}">
                                                    <i class="fa-duotone fa-solid fa-paper-plane"></i>
                                                    Submit for Approval
                                                </button>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-striped align-middle">
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
                                                                $canvasing = $prsItem->canvasingItem;
                                                            @endphp
                                                            <tr>
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input item-checkbox" data-item="{{ $prsItem->id }}" checked>
                                                                    <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $prsItem->id }}" data-item-input="{{ $prsItem->id }}">
                                                                    <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $prsItem->quantity }}" data-item-input="{{ $prsItem->id }}">
                                                                    <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $canvasing?->unit_price ?? 0 }}" data-item-input="{{ $prsItem->id }}">
                                                                    <input type="hidden" name="items[{{ $index }}][notes]" value="{{ $canvasing?->notes }}" data-item-input="{{ $prsItem->id }}">
                                                                </td>
                                                                <td>{{ $prsItem->prs?->prs_number ?? '-' }}</td>
                                                                <td>
                                                                    <div class="fw-semibold">{{ $prsItem->item->name }}</div>
                                                                    <small class="text-muted">{{ $prsItem->item->code }}</small>
                                                                </td>
                                                                <td>{{ $prsItem->quantity }}</td>
                                                                <td>{{ $prsItem->item->unit?->name ?? 'PCS' }}</td>
                                                                <td>{{ number_format($canvasing?->unit_price ?? 0, 2) }}</td>
                                                                <td>{{ $canvasing?->notes ?? '-' }}</td>
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
    </section>
</div>
@endsection

@push('addon-script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleItemInputs = (itemId, enabled) => {
                const inputs = document.querySelectorAll(`[data-item-input="${itemId}"]`);
                inputs.forEach((input) => {
                    input.disabled = !enabled;
                });
            };

            document.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                toggleItemInputs(checkbox.dataset.item, checkbox.checked);

                checkbox.addEventListener('change', (event) => {
                    toggleItemInputs(event.target.dataset.item, event.target.checked);
                });
            });

            document.querySelectorAll('.select-all').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = button.closest('form');
                    form.querySelectorAll('.item-checkbox').forEach((checkbox) => {
                        checkbox.checked = true;
                        toggleItemInputs(checkbox.dataset.item, true);
                    });
                });
            });
        });
    </script>
@endpush
