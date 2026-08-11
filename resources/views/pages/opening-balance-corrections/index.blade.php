@extends('layouts.app')
@section('title', ' | Opening Balance Corrections')

@section('content')
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Opening Balance Corrections</h3>
                    <p class="text-muted mb-0">Set period beginning stock and rebuild RR/TS/DR from that month start. Use for migration fixes.</p>
                </div>
            </div>
            @can('create-opening-balance-correction')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <a href="{{ route('opening-balance-corrections.create') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-calendar-pen"></i>
                            Correct Opening
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
                        <label class="form-label mb-1" for="filter-obc-keyword">Search</label>
                        <input type="text" id="filter-obc-keyword" name="keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="OBC number / reason">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary icon icon-left">
                            <i class="fa-regular fa-magnifying-glass"></i>
                            Filter
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('opening-balance-corrections.index') }}" class="btn btn-light-secondary icon icon-left">
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
                            <th>OBC Number</th>
                            <th>Period</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Lines</th>
                            <th>Created By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($corrections as $correction)
                            <tr>
                                <td><span class="sc-doc-badge">{{ $correction->obc_number }}</span></td>
                                <td>{{ $correction->period_month?->format('Y-m') }}</td>
                                <td>
                                    <div class="sc-reason-truncate" title="{{ $correction->reason }}">{{ $correction->reason }}</div>
                                </td>
                                <td>
                                    @if ($correction->isReversed())
                                        <span class="sc-status-badge is-reversed">Reversed</span>
                                    @else
                                        <span class="sc-status-badge is-posted">Posted</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light-primary text-primary">{{ $correction->items->count() }}</span>
                                </td>
                                <td>{{ $correction->createdBy?->name ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('opening-balance-corrections.show', $correction) }}" class="btn btn-sm btn-outline-primary icon icon-left">
                                        <i class="fa-regular fa-eye"></i>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="sc-empty-state">
                                        <div class="sc-empty-icon"><i class="fa-duotone fa-solid fa-calendar-pen"></i></div>
                                        <div class="fw-semibold mb-1">No opening corrections yet</div>
                                        <p class="text-muted mb-3">Correct period beginning when migration stock does not match Excel.</p>
                                        @can('create-opening-balance-correction')
                                            <a href="{{ route('opening-balance-corrections.create') }}" class="btn btn-success btn-sm">Correct Opening</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($corrections->hasPages())
                <div class="card-footer bg-transparent">{{ $corrections->links() }}</div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush
