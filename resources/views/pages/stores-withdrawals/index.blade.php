@extends('layouts.app')
@section('title', ' | Stores Withdrawal List')

@section('content')
<div id="sws-page-container">
<div class="page-heading po-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Stores Withdrawal</h3>
                    <p class="text-muted mb-0">Track warehouse withdrawals with instant search, live filters, and dynamic pagination.</p>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="po-top-actions">
                    <a href="{{ route('stores-withdrawals.create') }}" class="btn btn-success icon icon-left">
                        <i class="fa-duotone fa-solid fa-box-open-full"></i>
                        Create Stores Withdrawal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end po-filter-grid" id="sws-filter-form">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="filter-sws-keyword" class="form-label mb-1">Search Stores Withdrawal</label>
                        <input type="text" id="filter-sws-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="SWS number / dept / info / creator">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3">
                        <label for="filter-sws-department" class="form-label mb-1">Department</label>
                        <select id="filter-sws-department" class="form-select">
                            <option value="">All Department</option>
                            @foreach ($departmentOptions as $department)
                                <option value="{{ $department->code }}" @selected(($filters['department'] ?? '') === $department->code)>
                                    {{ $department->code }} - {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-sws-date-start" class="form-label mb-1">SWS Date (from)</label>
                        <input type="date" id="filter-sws-date-start" class="form-control" value="{{ $filters['sws_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-sws-date-end" class="form-label mb-1">SWS Date (to)</label>
                        <input type="date" id="filter-sws-date-end" class="form-control" value="{{ $filters['sws_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-sws-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="sws-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Stores Withdrawal Data</h5>
                    <span class="badge bg-light-primary" id="sws-filter-result">{{ $storeWithdrawals->total() }} records</span>
                </div>

                @if ($storeWithdrawals->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No stores withdrawal found.</p>
                        <small>Try changing your keyword or filters to see more results.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table text-nowrap" id="sws-table">
                            <thead>
                                <tr>
                                    <th>SWS Number</th>
                                    <th>SWS Date</th>
                                    <th>Department Code</th>
                                    <th>Info</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($storeWithdrawals as $sws)
                                    @php
                                        $detailItems = collect($storeWithdrawalItems[$sws->id] ?? []);
                                        $canRemoveItem = $detailItems->count() > 1;
                                        $isLocked = (bool) ($lockedStoreWithdrawalLookup[$sws->id] ?? false);
                                    @endphp
                                    <tr>
                                        <td>
                                            <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill" onclick="copyToClipboard('{{ $sws->sws_number }}')">
                                                <i class="fa-solid fa-regular fa-clipboard"></i>
                                                {{ $sws->sws_number }}
                                            </button>
                                        </td>
                                        <td>
                                            <i class="fa-duotone fa-solid fa-calendar-days text-danger"></i>
                                            {{ \Carbon\Carbon::parse($sws->sws_date)->format('d M Y') }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-light-primary"
                                                data-bstooltip-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $sws->department_name ?? '-' }}">{{ $sws->department_code }}</span>
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($sws->info ?? '-', 50) }}</td>
                                        <td>{{ $sws->created_by_name ?? '-' }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#detail-modal-{{ $sws->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                                    <i class="fa-light fa-eye text-primary"></i>
                                                </button>
                                                <a href="{{ route('stores-withdrawals.print', $sws->id) }}" target="_blank" class="btn icon" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Print Slip">
                                                    <i class="fa-light fa-print text-primary"></i>
                                                </a>
                                                @if ($isLocked)
                                                    <button type="button" class="btn icon" disabled data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Locked: transfer slip already created">
                                                        <i class="fa-light fa-lock text-secondary"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#edit-modal-{{ $sws->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                        <i class="fa-light fa-edit text-primary"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn icon"
                                                        data-bstooltip-toggle="tooltip"
                                                        data-bs-placement="top"
                                                        title="Delete"
                                                        onclick="hapusData({{ $sws->id }}, 'Delete Stores Withdrawal', 'Are you sure want to delete Stores Withdrawal {{ $sws->sws_number }}?')">
                                                        <i class="fa-light fa-trash text-secondary"></i>
                                                    </button>
                                                @endif
                                                <form action="{{ route('stores-withdrawals.destroy', $sws->id) }}" id="hapus-{{ $sws->id }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $storeWithdrawals->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>

                    @foreach ($storeWithdrawals as $sws)
                        @php
                            $detailItems = collect($storeWithdrawalItems[$sws->id] ?? []);
                            $canRemoveItem = $detailItems->count() > 1;
                            $isLocked = (bool) ($lockedStoreWithdrawalLookup[$sws->id] ?? false);
                        @endphp

                        <div class="modal fade" id="detail-modal-{{ $sws->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header sws-modal-header">
                                        <div>
                                            <h5 class="modal-title mb-1">Detail Stores Withdrawal</h5>
                                            <small class="text-muted">{{ $sws->sws_number }}</small>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="sws-pill-row mb-3">
                                            <span class="badge bg-light-primary">{{ strtoupper((string) ($sws->type ?? 'NORMAL')) }}</span>
                                            <span class="badge bg-light-secondary">{{ $detailItems->count() }} item(s)</span>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-4">
                                                <div class="sws-info-card">
                                                    <small>Date</small>
                                                    <div>{{ \Carbon\Carbon::parse($sws->sws_date)->format('d M Y') }}</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="sws-info-card">
                                                    <small>Department</small>
                                                    <div>{{ $sws->department_code }} - {{ $sws->department_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="sws-info-card">
                                                    <small>Created By</small>
                                                    <div>{{ $sws->created_by_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="sws-info-card">
                                                    <small>Info</small>
                                                    <div>{{ $sws->info ?? '-' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm align-middle sws-modal-table">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Item</th>
                                                        <th>Code</th>
                                                        <th>Qty</th>
                                                        <th>UoM</th>
                                                        <th>SOH Snapshot</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($detailItems as $detail)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td>{{ $detail->item_name ?? '-' }}</td>
                                                            <td>{{ $detail->item_code ?? $detail->product_code ?? '-' }}</td>
                                                            <td>{{ rtrim(rtrim(number_format((float) $detail->quantity, 3, '.', ''), '0'), '.') }}</td>
                                                            <td>{{ $detail->uom ?? '-' }}</td>
                                                            <td>{{ rtrim(rtrim(number_format((float) $detail->stock_on_hand_snapshot, 3, '.', ''), '0'), '.') }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted">No item detail found.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="{{ route('stores-withdrawals.print', $sws->id) }}" target="_blank" class="btn btn-outline-primary icon icon-left">
                                            <i class="fa-light fa-print"></i>
                                            Print Slip
                                        </a>
                                        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="edit-modal-{{ $sws->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('stores-withdrawals.update', $sws->id) }}" class="sws-edit-form" data-sws-id="{{ $sws->id }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header sws-modal-header">
                                            <div>
                                                <h5 class="modal-title mb-1">Edit Stores Withdrawal</h5>
                                                <small class="text-muted">{{ $sws->sws_number }}</small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            @if ($isLocked)
                                                <div class="alert alert-warning border-0 sws-edit-alert mb-3">
                                                    <div class="fw-semibold mb-1">Edit Locked</div>
                                                    <div class="small">Stores withdrawal ini sudah dipakai di transfer slip, jadi tidak bisa diedit lagi.</div>
                                                </div>
                                            @endif
                                            <div class="alert alert-info border-0 sws-edit-alert">
                                                <div class="fw-semibold mb-1">Quick Edit Rules</div>
                                                <div class="small">You can update quantity and remove existing items. Adding new items is disabled in this modal.</div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-striped table-sm align-middle sws-modal-table">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Item</th>
                                                            <th>Code</th>
                                                            <th style="width: 180px;">Quantity</th>
                                                            <th style="width: 160px;">Remove</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse ($detailItems as $detail)
                                                            <tr data-sws-edit-row>
                                                                <td>{{ $loop->iteration }}</td>
                                                                <td>{{ $detail->item_name ?? '-' }}</td>
                                                                <td>{{ $detail->item_code ?? $detail->product_code ?? '-' }}</td>
                                                                <td>
                                                                    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $detail->id }}">
                                                                    <div class="input-group input-group-sm">
                                                                        <input
                                                                            type="number"
                                                                            class="form-control"
                                                                            min="0.001"
                                                                            step="0.001"
                                                                            name="items[{{ $loop->index }}][quantity]"
                                                                            value="{{ number_format((float) $detail->quantity, 3, '.', '') }}"
                                                                            @disabled($isLocked)
                                                                            data-sws-qty-input>
                                                                        <span class="input-group-text">{{ $detail->uom ?? '-' }}</span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    @if ($canRemoveItem)
                                                                        <input type="hidden" name="items[{{ $loop->index }}][remove]" value="0">
                                                                        <div class="form-check m-0">
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                name="items[{{ $loop->index }}][remove]"
                                                                                value="1"
                                                                                @disabled($isLocked)
                                                                                data-sws-remove-toggle>
                                                                            <label class="form-check-label">Remove</label>
                                                                        </div>
                                                                    @else
                                                                        <span class="badge bg-light-secondary">Keep (single item)</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted">No editable item found.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary" @disabled($detailItems->isEmpty() || $isLocked)>Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/modules/stores-withdrawals-index.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stores-withdrawals-modern.js') }}"></script>
    <script src="{{ url('assets/scripts/modules/stores-withdrawals-index.js') }}"></script>
@endpush
