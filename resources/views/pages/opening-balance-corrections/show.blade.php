@extends('layouts.app')
@section('title', ' | Opening Balance ' . $correction->obc_number)

@section('content')
@php
    $deltaClass = function (float $delta): string {
        if ($delta > 0.00001) {
            return 'is-up';
        }
        if ($delta < -0.00001) {
            return 'is-down';
        }

        return 'is-zero';
    };
@endphp
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Opening Balance {{ $correction->obc_number }}</h3>
                    <div class="sc-meta-chips mt-2">
                        <span class="sc-meta-chip"><i class="fa-regular fa-calendar"></i> Period {{ $correction->period_month?->format('Y-m') }}</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-user"></i> {{ $correction->createdBy?->name ?? '-' }}</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-list"></i> {{ $correction->items->count() }} lines</span>
                        @if ($correction->isReversed())
                            <span class="badge bg-secondary">Reversed</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5 text-lg-end d-flex gap-2 justify-content-lg-end flex-wrap">
                <a href="{{ route('opening-balance-corrections.index') }}" class="btn btn-light-secondary icon icon-left">
                    <i class="fa-light fa-arrow-left"></i>
                    Back
                </a>
                @can('delete-opening-balance-correction')
                    @unless ($correction->isReversed())
                        <form method="POST" action="{{ route('opening-balance-corrections.reverse', $correction) }}" onsubmit="return confirm('Reverse this correction? Stock will be rebuilt to the previous beginning and OBC ADJ ledger rows will be removed.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger icon icon-left">
                                <i class="fa-regular fa-rotate-left"></i>
                                Reverse Correction
                            </button>
                        </form>
                    @endunless
                @endcan
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    @if ($correction->isReversed())
        <div class="alert alert-secondary shadow-sm border-0">
            Reversed on {{ $correction->reversed_at?->format('Y-m-d H:i') }}
            by {{ $correction->reversedBy?->name ?? '-' }}.
            Document kept for history; opening ADJ ledger for this OBC was cleared.
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="fw-semibold mb-1">Reason</div>
            <div>{{ $correction->reason }}</div>
            @if ($correction->allow_negative_balance)
                <div class="mt-2 text-warning small">Replay allowed negative balances.</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle sc-lines-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>WH</th>
                        <th class="text-end">Previous Begin</th>
                        <th class="text-end">New Begin</th>
                        <th class="text-end">Delta</th>
                        <th class="text-end">Replayed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($correction->items as $line)
                        @php $delta = (float) $line->delta_qty; @endphp
                        <tr>
                            <td>{{ $line->product_code }}</td>
                            <td>{{ $line->item?->name ?? '-' }}</td>
                            <td>{{ $line->wh_code }}</td>
                            <td class="text-end">{{ number_format((float) $line->previous_beginning, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->new_beginning, 2) }}</td>
                            <td class="text-end">
                                <span class="sc-delta {{ $deltaClass($delta) }}">
                                    {{ ($delta > 0 ? '+' : '').number_format($delta, 2) }}
                                </span>
                            </td>
                            <td class="text-end">{{ $line->replayed_movements }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush
