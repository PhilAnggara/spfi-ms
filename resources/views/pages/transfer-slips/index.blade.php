@extends('layouts.app')
@section('title', ' | Transfer Slips')

@section('content')
<div id="ts-page-container">
<div
    class="page-heading po-page"
    id="ts-page"
    data-sws-lookup-url="{{ route('transfer-slips.sws-by-number') }}"
    data-open-create-modal="{{ $errors->any() ? '1' : '0' }}"
    data-old-sws-number="{{ old('sws_number', '') }}"
>
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Transfer Slips</h3>
                    <p class="text-muted mb-0">Monitor transfer activity from stores withdrawal, search quickly, and create new TS directly from the listing page.</p>
                </div>
            </div>
            @can('create-transfer')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <button type="button" class="btn btn-success icon icon-left" data-bs-toggle="modal" data-bs-target="#create-ts-modal">
                            <i class="fa-duotone fa-solid fa-right-left"></i>
                            Create Transfer Slip
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0">
            <div class="fw-semibold mb-1">Transfer slip could not be saved.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end" id="ts-filter-form">
                    <div class="col-12 col-md-6 col-xl-5">
                        <label for="filter-ts-keyword" class="form-label mb-1">Search Transfer Slip</label>
                        <input type="text" id="filter-ts-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="TS number / SWS / dept / remarks / creator">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ts-production" class="form-label mb-1">For Production</label>
                        <select id="filter-ts-production" class="form-select">
                            <option value="">All</option>
                            <option value="1" @selected(($filters['production'] ?? '') === '1')>Yes</option>
                            <option value="0" @selected(($filters['production'] ?? '') === '0')>No</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ts-date-start" class="form-label mb-1">TS Date (from)</label>
                        <input type="date" id="filter-ts-date-start" class="form-control" value="{{ $filters['ts_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ts-date-end" class="form-label mb-1">TS Date (to)</label>
                        <input type="date" id="filter-ts-date-end" class="form-control" value="{{ $filters['ts_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-ts-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="ts-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title mb-0">Transfer Slip Data</h5>
                    <span class="badge bg-light-primary" id="ts-filter-result">{{ number_format($transferSlips->total()) }} records</span>
                </div>

                @if ($transferSlips->isEmpty())
                    <div class="po-empty-state text-center text-muted py-5">
                        <i class="fa-duotone fa-solid fa-file-circle-question po-empty-icon"></i>
                        <p class="mb-0 mt-2 fw-semibold">No transfer slip found.</p>
                        <small>Try changing the search or filter criteria.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle po-table text-nowrap" id="ts-table">
                            <thead>
                                <tr>
                                    <th>TS Number</th>
                                    <th>TS Date</th>
                                    <th>SWS</th>
                                    <th>Department</th>
                                    <th>Production</th>
                                    <th>Items</th>
                                    <th>Total Qty</th>
                                    <th>Remarks</th>
                                    <th>Created By</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transferSlips as $transferSlip)
                                    <tr>
                                        <td class="fw-semibold">{{ $transferSlip->ts_number }}</td>
                                        <td>{{ format_date($transferSlip->ts_date) }}</td>
                                        <td>{{ $transferSlip->sws_number ?? '-' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $transferSlip->department_code ?? '-' }}</div>
                                            <small class="text-muted">{{ $transferSlip->department_name ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ (int) $transferSlip->for_production === 1 ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary' }}">
                                                {{ (int) $transferSlip->for_production === 1 ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((int) ($transferSlip->item_count ?? 0)) }}</td>
                                        <td>{{ number_format((float) ($transferSlip->total_quantity ?? 0), 3) }}</td>
                                        <td class="text-wrap" style="max-width: 240px;">{{ $transferSlip->remarks ?: '-' }}</td>
                                        <td>{{ $transferSlip->created_by_name ?? '-' }}</td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn icon" data-bs-toggle="modal" data-bs-target="#ts-detail-modal-{{ $transferSlip->id }}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="View detail">
                                                    <i class="fa-duotone fa-solid fa-eye"></i>
                                                </button>
                                                @can('delete-transfer')
                                                    <button type="button" class="btn icon text-danger" onclick="confirmDeleteTransferSlip({{ $transferSlip->id }}, '{{ $transferSlip->ts_number }}')" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete transfer slip">
                                                        <i class="fa-duotone fa-solid fa-trash-can"></i>
                                                    </button>
                                                    <form action="{{ route('transfer-slips.destroy', $transferSlip->id) }}" id="hapus-ts-{{ $transferSlip->id }}" method="POST" class="d-none">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        {{ $transferSlips->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>

                    @foreach ($transferSlips as $transferSlip)
                        @php
                            $detailItems = collect($transferSlipItems[$transferSlip->id] ?? []);
                        @endphp

                        <div class="modal fade" id="ts-detail-modal-{{ $transferSlip->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header ts-modal-header">
                                        <div>
                                            <h5 class="modal-title mb-1">Transfer Detail - {{ $transferSlip->ts_number }}</h5>
                                            <div class="text-muted small">Linked SWS: {{ $transferSlip->sws_number ?? '-' }}</div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <div class="ts-info-card">
                                                    <small>TS Date</small>
                                                    <div>{{ format_date($transferSlip->ts_date) }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="ts-info-card">
                                                    <small>Department</small>
                                                    <div>{{ $transferSlip->department_code ?? '-' }} / {{ $transferSlip->department_name ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="ts-info-card">
                                                    <small>For Production</small>
                                                    <div>{{ (int) $transferSlip->for_production === 1 ? 'Yes' : 'No' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="ts-info-card">
                                                    <small>Total Items</small>
                                                    <div>{{ number_format((int) ($transferSlip->item_count ?? 0)) }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="ts-info-card">
                                                    <small>Total Quantity</small>
                                                    <div>{{ number_format((float) ($transferSlip->total_quantity ?? 0), 3) }}</div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="ts-info-card">
                                                    <small>Remarks</small>
                                                    <div>{{ $transferSlip->remarks ?: '-' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-striped align-middle ts-detail-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Product Code</th>
                                                        <th>Item Name</th>
                                                        <th class="text-end">Qty Out</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($detailItems as $detailItem)
                                                        <tr>
                                                            <td>{{ $detailItem->product_code ?? $detailItem->item_code ?? '-' }}</td>
                                                            <td>{{ $detailItem->item_name ?? '-' }}</td>
                                                            <td class="text-end">{{ number_format((float) $detailItem->quantity, 3) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3" class="text-center text-muted py-4">No transfer item found.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    @can('create-transfer')
        <div class="modal fade" id="create-ts-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <form method="POST" action="{{ route('transfer-slips.store') }}" id="create-ts-form">
                        @csrf
                        <div class="modal-header ts-modal-header">
                            <div>
                                <h5 class="modal-title mb-1">Create Transfer Slip</h5>
                                <div class="text-muted small">Load an SWS first, then input the quantity out for each item you want to transfer.</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label for="create_ts_number" class="form-label">TS Number</label>
                                    <input type="text" class="form-control" id="create_ts_number" name="ts_number" value="{{ old('ts_number') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="create_ts_date" class="form-label">TS Date</label>
                                    <input type="date" class="form-control" id="create_ts_date" name="ts_date" value="{{ old('ts_date', now()->toDateString()) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="create_for_production" class="form-label">For Production</label>
                                    <select class="form-select" id="create_for_production" name="for_production" required>
                                        <option value="1" @selected(old('for_production', '0') === '1')>Yes</option>
                                        <option value="0" @selected(old('for_production', '0') === '0')>No</option>
                                    </select>
                                    <div class="form-text" id="create-production-help">If select yes, this transfer slip will be counted on iCore Template - Consumption report.</div>
                                </div>
                                <div class="col-12">
                                    <label for="create_remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="create_remarks" name="remarks" rows="2" placeholder="Optional transfer remarks">{{ old('remarks') }}</textarea>
                                </div>
                            </div>

                            <div class="card border-0 bg-light-subtle mb-3">
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-8">
                                            <label for="create_sws_number" class="form-label">SWS Code</label>
                                            <input type="text" class="form-control" id="create_sws_number" name="sws_number" value="{{ old('sws_number') }}" placeholder="Input SWS number, for example 7056-120326-001" required>
                                            <input type="hidden" id="create_store_withdrawal_id" name="store_withdrawal_id" value="{{ old('store_withdrawal_id') }}">
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-primary w-100" id="create-load-sws">
                                                <i class="fa-duotone fa-solid fa-magnifying-glass me-1"></i>
                                                Load SWS
                                            </button>
                                        </div>
                                    </div>
                                    <div class="alert alert-danger d-none mt-3 mb-0" id="create-sws-error"></div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3 d-none" id="create-sws-details">
                                <div class="col-md-4">
                                    <div class="ts-info-card">
                                        <small>SWS Number</small>
                                        <div id="create-sws-detail-number">-</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ts-info-card">
                                        <small>SWS Date</small>
                                        <div id="create-sws-detail-date">-</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ts-info-card">
                                        <small>Department</small>
                                        <div id="create-sws-detail-department">-</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ts-info-card">
                                        <small>Type</small>
                                        <div id="create-sws-detail-type">-</div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="ts-info-card">
                                        <small>Info</small>
                                        <div id="create-sws-detail-info">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped align-middle ts-create-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product Code</th>
                                            <th>Item Name</th>
                                            <th class="text-end">SWS Qty</th>
                                            <th class="text-end">Transferred</th>
                                            <th class="text-end">Remaining</th>
                                            <th style="min-width: 180px;">Qty Out</th>
                                        </tr>
                                    </thead>
                                    <tbody id="create-ts-items-body">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Load an SWS number to display transferable items.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <div class="ts-summary-card">
                                        <small>Selected Lines</small>
                                        <div id="create-ts-summary-lines">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ts-summary-card">
                                        <small>Total Qty Out</small>
                                        <div id="create-ts-summary-qty">0.000</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ts-summary-card">
                                        <small>Production Mode</small>
                                        <div id="create-ts-summary-production">No</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="create-ts-save-btn">Save Transfer Slip</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <style>
        .ts-modal-header {
            background: linear-gradient(135deg, #f8fafc, #eff6ff);
            border-bottom: 1px solid #e2e8f0;
        }

        .ts-info-card,
        .ts-summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 0.85rem;
            min-height: 82px;
        }

        .ts-info-card small,
        .ts-summary-card small {
            color: #64748b;
            display: block;
            margin-bottom: 0.2rem;
        }

        .ts-info-card div,
        .ts-summary-card div {
            color: #0f172a;
            font-weight: 600;
            line-height: 1.35;
            word-break: break-word;
        }

        .ts-create-table thead th,
        .ts-detail-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .ts-qty-input {
            min-width: 140px;
        }
    </style>
@endpush

@push('addon-script')
    <script>
        window.transferSlipCreatePrefill = {
            shouldOpenModal: @json($errors->any()),
            swsNumber: @json(old('sws_number', '')),
            items: @json(old('items', [])),
        };
    </script>
    <script src="{{ url('assets/scripts/modules/transfer-slips-modern.js') }}"></script>
    <script>
        (function () {
            let isLoading = false;

            function initPageTooltips(scope = document) {
                const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

                tooltipElements.forEach((el) => {
                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        if (window.bootstrap.Tooltip.getInstance(el)) {
                            return;
                        }

                        new window.bootstrap.Tooltip(el);
                    }
                });
            }

            function setLoading(active) {
                const loadingEl = document.getElementById('ts-page-loading');
                if (!loadingEl) {
                    return;
                }

                loadingEl.classList.toggle('d-none', !active);
                loadingEl.classList.toggle('d-flex', active);
            }

            async function replacePageContent(url, pushState = true) {
                if (isLoading) {
                    return;
                }

                isLoading = true;
                setLoading(true);

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.querySelector('#ts-page-container');
                    const currentContainer = document.querySelector('#ts-page-container');

                    if (!newContainer || !currentContainer) {
                        window.location.href = url;
                        return;
                    }

                    currentContainer.replaceWith(newContainer);

                    if (pushState) {
                        window.history.pushState({}, '', url);
                    }

                    if (typeof window.initTransferSlipPage === 'function') {
                        window.initTransferSlipPage();
                    }

                    initPageTooltips(newContainer);

                    if (window.feather && typeof window.feather.replace === 'function') {
                        window.feather.replace();
                    }
                } catch (_) {
                    window.location.href = url;
                } finally {
                    isLoading = false;
                    setLoading(false);
                }
            }

            window.tsReplacePageContent = replacePageContent;

            document.addEventListener('click', function (event) {
                const link = event.target.closest('#ts-page-container a[href*="page="]');
                if (!link) {
                    return;
                }

                event.preventDefault();
                replacePageContent(link.href, true);
            });

            window.addEventListener('popstate', function () {
                replacePageContent(window.location.href, false);
            });

            initPageTooltips(document);
        })();
    </script>
@endpush
