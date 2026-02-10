@extends('layouts.app')
@section('title', ' | PO List')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Purchase Orders</h3>
                <p class="text-muted mb-0">Track submitted and approved PO for printing.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 text-md-end">
                <a href="{{ route('purchase-orders.draft') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa-duotone fa-solid fa-bag-shopping-plus"></i>
                    Create PO
                </a>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm {{ $status ? 'btn-outline-secondary' : 'btn-secondary' }}">All</a>
                    <a href="{{ route('purchase-orders.index', ['status' => 'PENDING_APPROVAL']) }}" class="btn btn-sm {{ $status === 'PENDING_APPROVAL' ? 'btn-secondary' : 'btn-outline-secondary' }}">Pending Approval</a>
                    <a href="{{ route('purchase-orders.index', ['status' => 'APPROVED']) }}" class="btn btn-sm {{ $status === 'APPROVED' ? 'btn-secondary' : 'btn-outline-secondary' }}">Approved</a>
                    <a href="{{ route('purchase-orders.index', ['status' => 'CHANGES_REQUESTED']) }}" class="btn btn-sm {{ $status === 'CHANGES_REQUESTED' ? 'btn-secondary' : 'btn-outline-secondary' }}">Changes Requested</a>
                    <a href="{{ route('purchase-orders.index', ['status' => 'DRAFT']) }}" class="btn btn-sm {{ $status === 'DRAFT' ? 'btn-secondary' : 'btn-outline-secondary' }}">Draft</a>
                </div>

                @if ($purchaseOrders->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fa-duotone fa-solid fa-inbox"></i>
                        <p class="mb-0 mt-2">No purchase orders found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>PO ID</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchaseOrders as $po)
                                    <tr>
                                        <td>#{{ $po->id }}</td>
                                        <td>{{ $po->supplier?->name }}</td>
                                        <td>{{ itemOrItems($po->items->count()) }}</td>
                                        <td>{{ number_format($po->total, 2) }}</td>
                                        <td>
                                            <span class="badge bg-light-info">{{ $po->status }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                                                <a href="{{ route('purchase-orders.print', $po) }}" class="btn btn-sm btn-primary {{ $po->status !== 'APPROVED' ? 'disabled' : '' }}">Print</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
