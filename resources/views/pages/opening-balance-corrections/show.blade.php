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

    $netDelta = (float) $correction->items->sum('delta_qty');
    $replayTotal = (int) $correction->items->sum('replayed_movements');
@endphp
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Opening Balance {{ $correction->obc_number }}</h3>
                    <div class="sc-meta-chips mt-2">
                        <span class="sc-meta-chip"><i class="fa-regular fa-calendar"></i> Period {{ $correction->period_month?->format('Y-m') }}</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-list"></i> {{ $correction->items->count() }} lines</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-arrows-rotate"></i> {{ $replayTotal }} replayed</span>
                        @if ($correction->isReversed())
                            <span class="sc-status-badge is-reversed">Reversed</span>
                        @else
                            <span class="sc-status-badge is-posted">Posted</span>
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
                        <button
                            type="button"
                            class="btn btn-outline-danger icon icon-left"
                            onclick="hapusData({{ $correction->id }}, 'Reverse Opening Correction', 'Movements since period start will be rebuilt (including docs after this OBC). Beginning restores to previous; OBC ADJ ledger is cleared. Document stays as Reversed history.', 'Yes, reverse!')"
                        >
                            <i class="fa-regular fa-rotate-left"></i>
                            Reverse Correction
                        </button>
                        <form action="{{ route('opening-balance-corrections.reverse', $correction) }}" id="hapus-{{ $correction->id }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    @endunless
                @endcan
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    @if ($errors->has('correction'))
        <div class="alert alert-danger shadow-sm border-0">{{ $errors->first('correction') }}</div>
    @endif

    @if ($correction->isReversed())
        <div class="sc-reversed-banner mb-4">
            <div class="fw-semibold mb-1">Reversed</div>
            <div>
                Reversed on {{ $correction->reversed_at?->format('Y-m-d H:i') }}
                by {{ $correction->reversedBy?->name ?? '-' }}.
                Document kept for history; opening ADJ ledger for this OBC was cleared.
            </div>
        </div>
    @endif

    <div class="sc-summary-strip">
        <div class="sc-summary-card">
            <div class="label">Period</div>
            <div class="value">{{ $correction->period_month?->format('Y-m') }}</div>
        </div>
        <div class="sc-summary-card">
            <div class="label">Lines</div>
            <div class="value">{{ $correction->items->count() }}</div>
        </div>
        <div class="sc-summary-card">
            <div class="label">Net Delta</div>
            <div class="value">
                <span class="sc-delta {{ $deltaClass($netDelta) }}">
                    {{ ($netDelta > 0 ? '+' : '').number_format($netDelta, 2) }}
                </span>
            </div>
        </div>
        <div class="sc-summary-card">
            <div class="label">Created</div>
            <div class="value">{{ $correction->created_at?->format('Y-m-d') ?? '-' }}</div>
            @if ($correction->created_at)
                <div class="subvalue">
                    {{ $correction->created_at->format('H:i') }}
                    @if ($correction->createdBy)
                        <span class="subvalue-sep">·</span>{{ $correction->createdBy->name }}
                    @endif
                </div>
            @elseif ($correction->createdBy)
                <div class="subvalue">{{ $correction->createdBy->name }}</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0 sc-reason-panel mb-4">
        <div class="card-body">
            <div class="label">Reason</div>
            <div>{{ $correction->reason }}</div>
            @if ($correction->allow_negative_balance)
                <div class="mt-2 text-warning small">Replay allowed negative balances.</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent">
            <span class="sc-section-title"><i class="fa-regular fa-boxes-stacked"></i> Line Items</span>
        </div>
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
