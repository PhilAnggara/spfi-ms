@extends('layouts.app')
@section('title', ' | Canvasing Detail')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Canvasing Detail</h3>
            </div>
            <div class="col-12 col-md-6 order-md-2">
                <div class="float-md-end">
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
                            <div class="fw-bold">{{ $prs->prs_number }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Submitted by</div>
                            <div class="fw-bold">{{ $prs->user->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Department</div>
                            <div class="fw-bold">{{ $prs->department->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Date Needed</div>
                            <div class="fw-bold">{{ tgl($prs->date_needed) }}</div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('canvasing.store', $prs->id) }}" method="post" class="form">
                    @csrf
                    <div class="row g-3">
                        @foreach ($prs->items as $index => $item)
                            @php
                                $canvasing = $item->canvasingItem;
                            @endphp
                            <div class="col-12">
                                <div class="card border shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                            <span class="badge bg-light-secondary">#{{ $index + 1 }}</span>
                                            <div class="fw-semibold">{{ $item->item->code }} - {{ $item->item->name }}</div>
                                            <div class="text-muted">{{ $item->quantity }} {{ $item->item->unit?->name ?? 'PCS' }}</div>
                                        </div>

                                        <input type="hidden" name="items[{{ $index }}][prs_item_id]" value="{{ $item->id }}">

                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <label class="form-label" for="supplier-{{ $item->id }}">Supplier</label>
                                                <select id="supplier-{{ $item->id }}" name="items[{{ $index }}][supplier_id]" class="choices choices-supplier form-select" required>
                                                    <option value="" disabled {{ $canvasing ? '' : 'selected' }}>-- Select Supplier --</option>
                                                    @foreach ($suppliers as $supplier)
                                                        <option value="{{ $supplier->id }}" data-custom-properties='@json(['searchText' => $supplier->name])' @selected($canvasing && $canvasing->supplier_id == $supplier->id)>{{ $supplier->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="unit-price-{{ $item->id }}">Unit Price</label>
                                                <input type="number" id="unit-price-{{ $item->id }}" name="items[{{ $index }}][unit_price]" class="form-control" min="0" step="0.01" value="{{ $canvasing->unit_price ?? '' }}" required>
                                            </div>
                                            <div class="col-12 col-lg-3">
                                                <label class="form-label" for="lead-time-{{ $item->id }}">Lead Time (days)</label>
                                                <input type="number" id="lead-time-{{ $item->id }}" name="items[{{ $index }}][lead_time_days]" class="form-control" min="0" value="{{ $canvasing->lead_time_days ?? '' }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="notes-{{ $item->id }}">Notes</label>
                                                <input type="text" id="notes-{{ $item->id }}" name="items[{{ $index }}][notes]" class="form-control" value="{{ $canvasing->notes ?? '' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn icon icon-left btn-primary">
                            <i class="fa-duotone fa-solid fa-floppy-disk"></i>
                            Save Canvasing
                        </button>
                    </div>
                </form>
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
            const supplierSelects = document.querySelectorAll('.choices-supplier');
            supplierSelects.forEach((selectEl) => {
                const instance = new Choices(selectEl, {
                    searchFields: ['label', 'value', 'customProperties.searchText'],
                    fuseOptions: {
                        includeScore: true,
                        ignoreLocation: true,
                        threshold: 0.3,
                    },
                });

                // Simpan instance agar konsisten dengan halaman lain
                selectEl.choicesInstance = instance;
            });
        });
    </script>
@endpush
