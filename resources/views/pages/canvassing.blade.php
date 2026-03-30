@extends('layouts.app')
@section('title', ' | Canvassing')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Canvassing</h3>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped align-middle text-nowrap" id="table1">
                    <thead>
                        <tr>
                            <th>PRS Number</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Date Needed</th>
                            <th>Suppliers</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prsItems as $prsItem)
                            @php
                                $prs = $prsItem->prs;
                                $item = $prsItem->item;
                                $prsNumber = $prs?->prs_number ?? '-';
                                $itemCode = $item?->code ?? 'N/A';
                                $itemName = $item?->name ?? 'Item not found';
                            @endphp
                            <tr>
                                <td>
                                    <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $prsNumber }}')">
                                        <i class="fa-solid fa-regular fa-clipboard"></i>
                                        {{ $prsNumber }}
                                    </button>
                                </td>
                                <td>
                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $itemCode }}')">{{ $itemCode }}</span>
                                </td>
                                <td data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $itemName }}">{{ Str::limit($itemName, 30) }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $prsItem->quantity }}</span>
                                    <small class="text-muted">{{ $item?->unit?->name ?? 'PCS' }}</small>
                                </td>
                                <td>
                                    <i class="fa-duotone fa-solid fa-calendar-star text-primary"></i>
                                    {{ $prs?->date_needed ? tgl($prs->date_needed) : '-' }}
                                </td>
                                <td>
                                    <div class="small text-muted">Quotes: {{ $prsItem->canvassingItems->count() }}</div>
                                    <div class="fw-semibold" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $prsItem->selectedCanvassingItem?->supplier?->name ?? 'Not selected' }}">
                                        <span class="{{ $prsItem->selectedCanvassingItem?->supplier?->name ? 'text-primary' : 'text-muted' }}">{{ Str::limit($prsItem->selectedCanvassingItem?->supplier?->name ?? 'Not selected', 15) }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    {{-- <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('canvassing.show', $prsItem->id) }}" class="btn btn-sm {{ $prsItem->canvassingItems->isNotEmpty() ? 'btn-primary' : 'btn-outline-primary' }}">
                                            <i class="fa-duotone fa-solid fa-pen-to-square"></i>
                                            {{ $prsItem->canvassingItems->isNotEmpty() ? 'Manage Suppliers' : 'Add Supplier' }}
                                        </a>
                                        @if (!$prsItem->purchase_order_id)
                                            <form action="{{ route('canvassing.toggle-direct-purchase', $prsItem->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="is_direct_purchase" value="{{ $prsItem->is_direct_purchase ? '0' : '1' }}">
                                                <button type="submit" class="btn btn-sm {{ $prsItem->is_direct_purchase ? 'btn-info' : 'btn-outline-info' }}" title="{{ $prsItem->is_direct_purchase ? 'Revert to Needs PO' : 'Mark as Direct Purchase' }}">
                                                    <i class="fa-duotone fa-solid fa-basket-shopping"></i>
                                                    {{ $prsItem->is_direct_purchase ? 'Unmark DP' : 'Direct Purchase' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div> --}}

                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('canvassing.show', $prsItem->id) }}" class="btn icon {{ $prsItem->canvassingItems->isNotEmpty() ? 'btn-outline-primary' : '' }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $prsItem->canvassingItems->isNotEmpty() ? 'Manage Suppliers' : 'Add Supplier' }}">
                                            <i class="fa-light fa-pen-to-square"></i>
                                        </a>
                                        @if (!$prsItem->purchase_order_id)
                                            <form action="{{ route('canvassing.toggle-direct-purchase', $prsItem->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="is_direct_purchase" value="{{ $prsItem->is_direct_purchase ? '0' : '1' }}">
                                                <button type="submit" class="btn icon {{ $prsItem->is_direct_purchase ? 'btn-outline-info' : '' }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="{{ $prsItem->is_direct_purchase ? 'Revert to Needs PO' : 'Mark as Direct Purchase' }}">
                                                    <i class="fa-light fa-basket-shopping"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fa-duotone fa-solid fa-inbox"></i>
                                    <p class="mb-0 mt-2">No canvassing items assigned yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush
