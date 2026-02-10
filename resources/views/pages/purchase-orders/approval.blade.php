@extends('layouts.app')
@section('title', ' | PO Approval')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>PO Approval</h3>
                <p class="text-muted mb-0">Review submitted and approved purchase orders.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                @if ($purchaseOrders->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="fa-duotone fa-solid fa-inbox"></i>
                        <p class="mb-0 mt-2">No purchase orders awaiting approval.</p>
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
                                                @if ($po->status === 'PENDING_APPROVAL')
                                                    <form method="post" action="{{ route('purchase-orders.approve', $po) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#requestChanges-{{ $po->id }}">
                                                        Request Changes
                                                    </button>
                                                @endif
                                                <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @foreach ($purchaseOrders as $po)
                        <div class="modal fade" id="requestChanges-{{ $po->id }}" tabindex="-1" aria-labelledby="requestChangesLabel-{{ $po->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="post" action="{{ route('purchase-orders.request-changes', $po) }}" class="modal-content">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="requestChangesLabel-{{ $po->id }}">Request Changes</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label" for="message-{{ $po->id }}">Message</label>
                                        <textarea id="message-{{ $po->id }}" name="message" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">Send</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
