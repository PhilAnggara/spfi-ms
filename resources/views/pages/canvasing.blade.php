@extends('layouts.app')
@section('title', ' | Canvasing')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4">
            <div class="col-12 col-md-6 order-md-1">
                <h3>Canvasing</h3>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped align-middle text-nowrap" id="table1">
                    <thead>
                        <tr>
                            <th>PRS Number</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Date Needed</th>
                            {{-- <th>Status</th> --}}
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prsItems as $prsItem)
                            <tr>
                                <td>
                                    <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $prsItem->prs->prs_number }}')">
                                        <i class="fa-solid fa-regular fa-clipboard"></i>
                                        {{ $prsItem->prs->prs_number }}
                                    </button>
                                </td>
                                <td>
                                    <span class="badge bg-light-secondary" role="button" onclick="copyToClipboard('{{ $prsItem->item->code }}')">{{ $prsItem->item->code }}</span>
                                </td>
                                <td>{{ $prsItem->item->name }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $prsItem->quantity }}</span>
                                    <small class="text-muted">{{ $prsItem->item->unit?->name ?? 'PCS' }}</small>
                                </td>
                                <td>
                                    <i class="fa-duotone fa-solid fa-calendar-star text-primary"></i>
                                    {{ tgl($prsItem->prs->date_needed) }}
                                </td>
                                {{-- <td>
                                    @if ($prsItem->canvasingItem?->unit_price)
                                        <span class="badge bg-light-success">
                                            <i class="fa-duotone fa-solid fa-circle-check"></i>
                                            Completed
                                        </span>
                                    @else
                                        <span class="badge bg-light-warning">
                                            <i class="fa-duotone fa-solid fa-hourglass"></i>
                                            Pending
                                        </span>
                                    @endif
                                </td> --}}
                                <td class="text-center">
                                    <a href="{{ route('canvasing.show', $prsItem->id) }}" class="btn btn-sm {{ $prsItem->canvasingItem?->unit_price ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa-duotone fa-solid fa-pen-to-square"></i>
                                        {{ $prsItem->canvasingItem?->unit_price ? 'Edit Supplier' : 'Add Supplier' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fa-duotone fa-solid fa-inbox"></i>
                                    <p class="mb-0 mt-2">No canvasing items assigned yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/extensions/simple-datatables/style.css') }}">
    <link rel="stylesheet" href="{{ url('assets/compiled/css/table-datatable.css') }}">
@endpush
@push('addon-script')
    <script src="{{ url('assets/extensions/simple-datatables/umd/simple-datatables.js') }}"></script>
    <script src="{{ url('assets/static/js/pages/simple-datatables.js') }}"></script>
@endpush
