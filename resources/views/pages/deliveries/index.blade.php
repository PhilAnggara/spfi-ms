@extends('layouts.app')
@section('title', ' | Delivery')

@section('content')
<div id="delivery-page-container">
<div class="page-heading po-page" id="delivery-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Delivery</h3>
                    <p class="text-muted mb-0">Monitor outbound deliveries to external destinations, search quickly, and create a new delivery record from item catalog.</p>
                </div>
            </div>
            @can('create-delivery')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <a href="{{ route('deliveries.create') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-truck-ramp-box"></i>
                            Create Delivery
                        </a>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end" id="delivery-filter-form">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-delivery-keyword" class="form-label mb-1">Search Delivery</label>
                        <input type="text" id="filter-delivery-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="DR number / from / to / remarks / OR / DM / creator">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-delivery-from-location" class="form-label mb-1">From Location</label>
                        <input type="text" id="filter-delivery-from-location" class="form-control" value="{{ $filters['from_location'] ?? '' }}" placeholder="From location">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-delivery-to-location" class="form-label mb-1">To Location</label>
                        <input type="text" id="filter-delivery-to-location" class="form-control" value="{{ $filters['to_location'] ?? '' }}" placeholder="To location">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-delivery-date-start" class="form-label mb-1">DR Date (from)</label>
                        <input type="date" id="filter-delivery-date-start" class="form-control" value="{{ $filters['dr_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filter-delivery-date-end" class="form-label mb-1">DR Date (to)</label>
                        <input type="date" id="filter-delivery-date-end" class="form-control" value="{{ $filters['dr_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <button type="button" id="reset-delivery-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="delivery-page-results">
        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="delivery-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Delivery Data</h5>
                    <span class="badge bg-light-primary" id="delivery-filter-result">{{ number_format($deliveries->total()) }} records</span>
                </div>

                @if ($deliveries->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No delivery found.</p>
                        <small>Try changing the search or filter criteria.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table text-nowrap" id="delivery-table">
                            <thead>
                                <tr>
                                    <th>DR Number</th>
                                    <th>DR Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>OR No.</th>
                                    <th>DM No.</th>
                                    <th>Items</th>
                                    <th>Total Qty</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deliveries as $delivery)
                                    <tr>
                                        <td>
                                            <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $delivery->dr_number }}')">
                                                <i class="fa-solid fa-regular fa-clipboard"></i>
                                                {{ $delivery->dr_number }}
                                            </button>
                                        </td>
                                        <td>
                                            <i class="fa-duotone fa-solid fa-calendar-days text-danger"></i>
                                            {{ \Carbon\Carbon::parse($delivery->dr_date)->format('d M Y') }}
                                        </td>
                                        <td class="text-wrap spfi-col-medium">
                                            <div class="fw-semibold">{{ $delivery->from_name }}</div>
                                            <small class="text-muted">{{ $delivery->from_location ?: '-' }}</small>
                                        </td>
                                        <td class="text-wrap spfi-col-medium">
                                            <div class="fw-semibold">{{ $delivery->to_name ?: '-' }}</div>
                                            <small class="text-muted">{{ $delivery->to_location ?: '-' }}</small>
                                        </td>
                                        <td>{{ $delivery->or_number ?: '-' }}</td>
                                        <td>{{ $delivery->dm_number ?: '-' }}</td>
                                        <td>{{ number_format((int) ($delivery->item_count ?? 0)) }}</td>
                                        <td>{{ number_format((float) ($delivery->total_quantity ?? 0), 3) }}</td>
                                        <td>{{ $delivery->created_by_name ?? '-' }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#delivery-detail-modal-{{ $delivery->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="View detail">
                                                    <i class="fa-light fa-eye text-primary"></i>
                                                </button>
                                                @can('delete-delivery')
                                                    <button type="button" class="btn icon" onclick="confirmDeleteDelivery({{ $delivery->id }}, '{{ $delivery->dr_number }}')" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                                        <i class="fa-light fa-trash text-secondary"></i>
                                                    </button>
                                                    <form action="{{ route('deliveries.destroy', $delivery->id) }}" id="hapus-delivery-{{ $delivery->id }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $deliveries->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>

                    @foreach ($deliveries as $delivery)
                        @php
                            $detailItems = collect($deliveryItems[$delivery->id] ?? []);
                        @endphp

                        <div class="modal fade" id="delivery-detail-modal-{{ $delivery->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header delivery-modal-header">
                                        <div>
                                            <h5 class="modal-title mb-1">Delivery Detail - {{ $delivery->dr_number }}</h5>
                                            <div class="text-muted small">Outbound Delivery Receipt</div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>DR Date</small>
                                                    <div>{{ format_date($delivery->dr_date) }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>Created By</small>
                                                    <div>{{ $delivery->created_by_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>From</small>
                                                    <div>{{ $delivery->from_name }} ({{ $delivery->from_location ?: '-' }})</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>To</small>
                                                    <div>{{ $delivery->to_name ?: '-' }} ({{ $delivery->to_location ?: '-' }})</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>OR No.</small>
                                                    <div>{{ $delivery->or_number ?: '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="delivery-info-card">
                                                    <small>DM No.</small>
                                                    <div>{{ $delivery->dm_number ?: '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="delivery-info-card">
                                                    <small>Remarks</small>
                                                    <div>{{ $delivery->remarks ?: '-' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-striped align-middle delivery-detail-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Product Code</th>
                                                        <th>Item Name</th>
                                                        <th>UoM</th>
                                                        <th class="text-end">Qty Out</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($detailItems as $detailItem)
                                                        <tr>
                                                            <td>{{ $detailItem->product_code ?? $detailItem->item_code ?? '-' }}</td>
                                                            <td>{{ $detailItem->item_name ?? '-' }}</td>
                                                            <td>{{ $detailItem->uom ?? '-' }}</td>
                                                            <td class="text-end">{{ number_format((float) $detailItem->quantity, 3) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="4" class="text-center text-muted py-4">No delivery item found.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
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
    <link rel="stylesheet" href="{{ url('assets/css/modules/deliveries-index.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/deliveries-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/deliveries-index.js') }}"></script>
@endpush
