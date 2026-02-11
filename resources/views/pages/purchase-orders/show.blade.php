@extends('layouts.app')
@section('title', ' | PO Detail')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Purchase Order</h3>
                <p class="text-muted mb-0">Status: {{ $purchaseOrder->status }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 text-md-end">
                @role('administrator|purchasing-manager|general-manager')
                    <a href="{{ route('purchase-orders.approval') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to PO Approval
                    </a>
                @endrole
                @role('canvaser')
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-duotone fa-solid fa-arrow-left"></i>
                        Back to PO List
                    </a>
                @endrole
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                @php
                    $currencyCode = $purchaseOrder->currency?->code ?? 'IDR';
                @endphp
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Supplier</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier?->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Created By</div>
                            <div class="fw-semibold">{{ $purchaseOrder->createdBy?->name }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">PO Number</div>
                            <div class="fw-semibold">{{ $purchaseOrder->po_number ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Currency</div>
                            <div class="fw-semibold">{{ $currencyCode }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Remark</div>
                            <div class="fw-semibold">{{ $purchaseOrder->remark_type ?? '-' }}</div>
                            <div class="text-muted small">{{ $purchaseOrder->remark_text ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                @if ($purchaseOrder->approval_notes)
                    <div class="alert alert-warning">
                        <strong>Changes Requested:</strong> {{ $purchaseOrder->approval_notes }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>PRS ID</th>
                                <th>Item</th>
                                <th>Item Code</th>
                                <th>Dept</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th class="text-end">Unit/Price</th>
                                <th class="text-end">Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->meta['prs_number'] ?? $item->prsItem?->prs?->prs_number ?? '-' }}
                                    </td>
                                    <td>{{ $item->item?->name }}</td>
                                    <td>{{ $item->item?->code ?? '-' }}</td>
                                    <td>{{ $item->prsItem?->prs?->department?->name ?? '-' }}</td>
                                    <td>{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                    <td>{{ $item->item?->unit?->name ?? 'PCS' }}</td>
                                    <td class="text-end">{{ $currencyCode }} {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($item->total, 2, ',', '.') }}</td>
                                    <td>{{ $item->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-md-6"></div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span class="fw-semibold">{{ $currencyCode }} {{ number_format($purchaseOrder->subtotal, 2, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Discount ({{ number_format($purchaseOrder->discount_rate ?? 0, 2) }}%)</span>
                                <span class="fw-semibold">- {{ number_format($purchaseOrder->discount_amount ?? 0, 2, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>PPN ({{ number_format($purchaseOrder->ppn_rate ?? 0, 2) }}%)</span>
                                <span class="fw-semibold">{{ number_format($purchaseOrder->ppn_amount ?? 0, 2, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>PPh ({{ number_format($purchaseOrder->pph_rate ?? 0, 2) }}%)</span>
                                <span class="fw-semibold">- {{ number_format($purchaseOrder->pph_amount ?? 0, 2, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Fees</span>
                                <span class="fw-semibold">{{ number_format($purchaseOrder->fees, 2, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold">{{ $currencyCode }} {{ number_format($purchaseOrder->total, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <form method="post" action="{{ route('purchase-orders.number', $purchaseOrder) }}">
                            @csrf
                            <label class="form-label">PO Number</label>
                            <div class="input-group">
                                <input type="text" name="po_number" class="form-control" value="{{ $purchaseOrder->po_number }}" required>
                                <button type="submit" class="btn btn-outline-primary">Save Number</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-md-6 text-md-end">
                        @role('administrator|canvaser')
                            @if (in_array($purchaseOrder->status, ['DRAFT', 'CHANGES_REQUESTED']))
                                <form method="post" action="{{ route('purchase-orders.submit', $purchaseOrder) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-duotone fa-solid fa-paper-plane"></i>
                                        Submit for Approval
                                    </button>
                                </form>
                            @endif
                        @endrole
                        <a href="{{ route('purchase-orders.print', $purchaseOrder) }}" class="btn btn-primary {{ $purchaseOrder->status !== 'APPROVED' ? 'disabled' : '' }}">
                            <i class="fa-duotone fa-solid fa-print"></i>
                            Print PO
                        </a>
                        @if ($purchaseOrder->status !== 'APPROVED')
                            <div class="text-muted small mt-2">PO must be approved before printing.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
