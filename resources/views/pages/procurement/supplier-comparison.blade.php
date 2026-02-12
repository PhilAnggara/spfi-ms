@extends('layouts.app')
@section('title', ' | Supplier Comparison')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Supplier Comparison</h3>
                <p class="text-muted mb-0">Select the winning supplier quote per item.</p>
            </div>
        </div>
    </div>

    <section class="section">
        @if ($prsItems->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-4">
                    <i class="fa-duotone fa-solid fa-inbox"></i>
                    <p class="mb-0 mt-2">No canvasing items available for comparison.</p>
                </div>
            </div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach ($prsItems as $prsItem)
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                                <div>
                                    <div class="text-muted small">PRS Number</div>
                                    <div class="fw-semibold">{{ $prsItem->prs?->prs_number ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-muted small">Item</div>
                                    <div class="fw-semibold">{{ $prsItem->item?->name }}</div>
                                    <div class="text-muted small">{{ $prsItem->item?->code }}</div>
                                </div>
                                <div>
                                    <div class="text-muted small">Quantity</div>
                                    <div class="fw-semibold">{{ $prsItem->quantity }} {{ $prsItem->item?->unit?->name ?? 'PCS' }}</div>
                                </div>
                                <div>
                                    <div class="text-muted small">Canvasser</div>
                                    <div class="fw-semibold">{{ $prsItem->canvaser?->name ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-muted small">Selected</div>
                                    <div class="fw-semibold">{{ $prsItem->selectedCanvasingItem?->supplier?->name ?? 'Not selected' }}</div>
                                </div>
                            </div>

                            <form method="post" action="{{ route('procurement.supplier-comparison.select', $prsItem) }}">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Select</th>
                                                <th>Supplier</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-center">Lead Time</th>
                                                <th>Term of Payment</th>
                                                <th>Term of Delivery</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($prsItem->canvasingItems as $canvasing)
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="radio" name="canvasing_item_id" value="{{ $canvasing->id }}" @checked($prsItem->selected_canvasing_item_id === $canvasing->id) @if ($loop->first) required @endif>
                                                    </td>
                                                    <td>{{ $canvasing->supplier?->name ?? '-' }}</td>
                                                    <td class="text-end">{{ number_format($canvasing->unit_price, 2) }}</td>
                                                    <td class="text-center">{{ $canvasing->lead_time_days ?? '-' }}</td>
                                                    <td>
                                                        @php
                                                            $payment = trim(($canvasing->term_of_payment ? $canvasing->term_of_payment . ' ' : '') . ($canvasing->term_of_payment_type ?? ''));
                                                        @endphp
                                                        {{ $payment !== '' ? $payment : '-' }}
                                                    </td>
                                                    <td>{{ $canvasing->term_of_delivery ?? '-' }}</td>
                                                    <td>{{ $canvasing->notes ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Save Selection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
