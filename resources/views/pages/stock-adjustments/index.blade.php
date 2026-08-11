@extends('layouts.app')
@section('title', ' | Stock Adjustments')

@section('content')
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
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-sa-keyword" class="form-label mb-1">Search</label>
                        <input type="text" id="filter-sa-keyword" name="keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="SA number / reason">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary icon icon-left">
                            <i class="fa-regular fa-magnifying-glass"></i>
                            Filter
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('stock-adjustments.index') }}" class="btn btn-light-secondary icon icon-left">
                            <i class="fa-regular fa-rotate-left"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle sc-index-table">
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
                <div class="card-footer bg-transparent">{{ $adjustments->links() }}</div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush
