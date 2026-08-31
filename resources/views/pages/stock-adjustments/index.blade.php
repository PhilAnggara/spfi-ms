@extends('layouts.app')
@section('title', ' | Stock Adjustments')

@section('content')
<div id="sa-page-container">
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Stock Adjustments</h3>
                    <p class="text-muted mb-0">Correct current on-hand stock. Each change posts to the ledger as ADJ without rewriting RR/TS/DR history.</p>
                </div>
            </div>
            @can('create-stock-adjustment')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-sliders"></i>
                            Create Adjustment
                        </a>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end list-filter-grid" id="sa-filter-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="filter-sa-keyword" class="form-label mb-1">Search</label>
                        <input type="text" id="filter-sa-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="SA number / reason" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-sa-date-start" class="form-label mb-1">Date (from)</label>
                        <input type="date" id="filter-sa-date-start" class="form-control" value="{{ $filters['date_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-sa-date-end" class="form-label mb-1">Date (to)</label>
                        <input type="date" id="filter-sa-date-end" class="form-control" value="{{ $filters['date_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <button type="button" id="reset-sa-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="sa-page-results">
            <div class="card shadow-sm border-0 position-relative">
                <div class="card-body position-relative">
                    <div id="sa-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">Stock Adjustments</h5>
                        <span class="badge bg-light-primary" id="sa-filter-result">{{ number_format($adjustments->total()) }} records</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle list-table sc-index-table mb-0">
                            <thead>
                                <tr>
                                    <th>SA Number</th>
                                    <th>Date</th>
                                    <th>Reason</th>
                                    <th>Lines</th>
                                    <th>Created By</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($adjustments as $adjustment)
                                    <tr>
                                        <td>
                                            <span class="sc-doc-badge">{{ $adjustment->sa_number }}</span>
                                        </td>
                                        <td>{{ $adjustment->sa_date?->format('Y-m-d') }}</td>
                                        <td>
                                            <div class="sc-reason-truncate" title="{{ $adjustment->reason }}">{{ $adjustment->reason }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light-primary text-primary">{{ $adjustment->items->count() }}</span>
                                        </td>
                                        <td>{{ $adjustment->createdBy?->name ?? '-' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="btn btn-sm btn-outline-primary icon icon-left">
                                                <i class="fa-regular fa-eye"></i>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="sc-empty-state">
                                                <div class="sc-empty-icon"><i class="fa-duotone fa-solid fa-sliders"></i></div>
                                                <div class="fw-semibold mb-1">No stock adjustments yet</div>
                                                <p class="text-muted mb-3">Create an adjustment when on-hand stock needs a ledger correction.</p>
                                                @can('create-stock-adjustment')
                                                    <a href="{{ route('stock-adjustments.create') }}" class="btn btn-success btn-sm">Create Adjustment</a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($adjustments->hasPages())
                        <div class="mt-3">
                            {{ $adjustments->onEachSide(1)->links('pagination::bootstrap-5') }}
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
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stock-adjustments-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/stock-adjustments-index.js') }}"></script>
@endpush
