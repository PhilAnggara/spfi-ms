@extends('layouts.app')
@section('title', ' | Count Tag Non-FG')

@section('content')
<div id="count-tag-non-fg-page-container">
<div class="page-heading po-page sc-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Count Tag Non-FG</h3>
                    <p class="text-muted mb-0">Browse imported physical count tags and verify item, location, and category mapping.</p>
                </div>
            </div>
            @can('create-count-tag-non-fg')
                <div class="col-12 col-lg-5">
                    <div class="po-top-actions text-lg-end">
                        <a href="{{ route('count-tags.non-fg.scan') }}" class="btn btn-success icon icon-left">
                            <i class="fa-duotone fa-solid fa-camera-viewfinder"></i>
                            Scan Items
                        </a>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <section class="section">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end list-filter-grid" id="ct-filter-form">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="filter-ct-keyword" class="form-label mb-1">Search</label>
                        <input type="text" id="filter-ct-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="Tag / item code / location / creator" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ct-date-start" class="form-label mb-1">Date (from)</label>
                        <input type="date" id="filter-ct-date-start" class="form-control" value="{{ $filters['date_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ct-date-end" class="form-label mb-1">Date (to)</label>
                        <input type="date" id="filter-ct-date-end" class="form-control" value="{{ $filters['date_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="filter-ct-location" class="form-label mb-1">Location</label>
                        <select id="filter-ct-location" class="form-select">
                            <option value="">All locations</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <button type="button" id="reset-ct-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="ct-page-results">
            <div class="card shadow-sm border-0 position-relative">
                <div class="card-body position-relative">
                    <div id="ct-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">Count Tag List</h5>
                        <span class="badge bg-light-primary" id="ct-filter-result">{{ number_format($tags->total()) }} records</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle list-table sc-index-table mb-0">
                            <thead>
                                <tr>
                                    <th>Count Tag</th>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Location</th>
                                    <th>Qty</th>
                                    <th>Created By</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tags as $tag)
                                    <tr>
                                        <td>
                                            <span class="sc-doc-badge">{{ $tag->count_tag_number ?: '—' }}</span>
                                        </td>
                                        <td>{{ $tag->count_tag_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $tag->item_code }}</div>
                                            <small class="text-muted">{{ $tag->item?->name ?? ($tag->item_id ? '—' : 'Unmatched item') }}</small>
                                        </td>
                                        <td>
                                            <div>{{ $tag->location?->name ?? ($tag->location_name ?: '—') }}</div>
                                            @if ($tag->section_code)
                                                <small class="text-muted">Sec {{ $tag->section_code }}
                                                    @if ($tag->row || $tag->col || $tag->level)
                                                        · R{{ $tag->row ?? '-' }}/C{{ $tag->col ?? '-' }}/L{{ $tag->level ?? '-' }}
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $tag->qty, 2) }} {{ $tag->uom_code }}</td>
                                        <td>{{ $tag->created_by_name ?: '—' }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary icon icon-left ct-view-btn"
                                                data-detail-url="{{ route('count-tags.non-fg.show', $tag) }}"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="sc-empty-state">
                                                <div class="sc-empty-icon"><i class="fa-duotone fa-solid fa-qrcode"></i></div>
                                                <div class="fw-semibold mb-1">No count tags yet</div>
                                                <p class="text-muted mb-3">Import legacy data with <code>php artisan count-tag:import-non-fg</code>, or scan items once save is available.</p>
                                                @can('create-count-tag-non-fg')
                                                    <a href="{{ route('count-tags.non-fg.scan') }}" class="btn btn-success btn-sm">Scan Items</a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($tags->hasPages())
                        <div class="mt-3">
                            {{ $tags->onEachSide(1)->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
</div>

<div class="modal fade" id="ct-detail-modal" tabindex="-1" aria-labelledby="ct-detail-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ct-detail-modal-title">Count Tag Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ct-detail-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <div class="mt-2 text-muted">Loading detail...</div>
                </div>
                <div id="ct-detail-error" class="alert alert-danger d-none mb-0"></div>
                <div id="ct-detail-content" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Count Tag</div>
                            <div class="fw-semibold" data-field="count_tag_number">—</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Count Tag Date</div>
                            <div class="fw-semibold" data-field="count_tag_date">—</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Tran Date</div>
                            <div class="fw-semibold" data-field="tran_date">—</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Item Code</div>
                            <div class="fw-semibold" data-field="item_code">—</div>
                            <div class="small" data-field="item_match_badge"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Item Name</div>
                            <div class="fw-semibold" data-field="item_name">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Category</div>
                            <div class="fw-semibold" data-field="category_name">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">UOM</div>
                            <div class="fw-semibold" data-field="uom">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Qty</div>
                            <div class="fw-semibold" data-field="qty">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Location</div>
                            <div class="fw-semibold" data-field="location_name">—</div>
                            <div class="small" data-field="location_match_badge"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Section</div>
                            <div class="fw-semibold" data-field="section_code">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Row / Col / Level</div>
                            <div class="fw-semibold" data-field="position">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Size</div>
                            <div class="fw-semibold" data-field="size">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Condition</div>
                            <div class="fw-semibold" data-field="condition">—</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Created By</div>
                            <div class="fw-semibold" data-field="created_by_name">—</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Group</div>
                            <div class="fw-semibold" data-field="group_name">—</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Legacy Id</div>
                            <div class="fw-semibold" data-field="legacy_id">—</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small mb-1">Mapping / Meta</div>
                            <pre class="bg-light border rounded p-3 mb-0 small" data-field="meta" style="max-height: 220px; overflow: auto;">—</pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/count-tag-non-fg-index.js') }}"></script>
@endpush
