@extends('layouts.app')
@section('title', ' | Stock Adjustment ' . $adjustment->sa_number)

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
                    <h3 class="mb-1">Stock Adjustment {{ $adjustment->sa_number }}</h3>
                    <div class="sc-meta-chips mt-2">
                        <span class="sc-meta-chip"><i class="fa-regular fa-calendar"></i> {{ $adjustment->sa_date?->format('Y-m-d') }}</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-user"></i> {{ $adjustment->createdBy?->name ?? '-' }}</span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-list"></i> {{ $adjustment->items->count() }} lines</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5 text-lg-end d-flex gap-2 justify-content-lg-end flex-wrap">
                <a href="{{ route('stock-adjustments.index') }}" class="btn btn-light-secondary icon icon-left">
                    <i class="fa-light fa-arrow-left"></i>
                    Back
                </a>
                @can('delete-stock-adjustment')
                    <form method="POST" action="{{ route('stock-adjustments.destroy', $adjustment) }}" onsubmit="return confirm('Delete this adjustment and reverse stock?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger icon icon-left">
                            <i class="fa-regular fa-trash"></i>
                            Delete & Reverse
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm border-0">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="fw-semibold mb-1">Reason</div>
            <div>{{ $adjustment->reason }}</div>
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
                        <th class="text-end">Previous</th>
                        <th class="text-end">New</th>
                        <th class="text-end">Delta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($adjustment->items as $line)
                        @php $delta = (float) $line->delta_qty; @endphp
                        <tr>
                            <td>{{ $line->product_code }}</td>
                            <td>{{ $line->item?->name ?? '-' }}</td>
                            <td>{{ $line->wh_code }}</td>
                            <td class="text-end">{{ number_format((float) $line->previous_balance, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->new_balance, 2) }}</td>
                            <td class="text-end">
                                <span class="sc-delta {{ $deltaClass($delta) }}">
                                    {{ ($delta > 0 ? '+' : '').number_format($delta, 2) }}
                                </span>
                            </td>
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
