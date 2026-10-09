@extends('layouts.app')
@section('title', ' | Count Tag Non-FG')

@section('content')
<div id="count-tag-non-fg-page-container">
<div class="page-heading po-page sc-page ct-page">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="po-hero">
                    <h3 class="mb-1">Count Tag Non-FG</h3>
                    <p class="text-muted mb-0">Physical counts for Non-Finish Good items by location.</p>
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
        <div class="card shadow-sm border-0 mb-3 ct-filter-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end" id="ct-filter-form">
                    <div class="col-12 col-lg-3">
                        <label for="filter-ct-keyword" class="form-label mb-1">Search</label>
                        <div class="ct-search-wrap">
                            <i class="fa-regular fa-magnifying-glass"></i>
                            <input type="text" id="filter-ct-keyword" class="form-control" value="{{ $filters['keyword'] ?? '' }}" placeholder="Tag, item, location..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter-ct-category" class="form-label mb-1">Category</label>
                        <select id="filter-ct-category" class="form-select">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter-ct-location" class="form-label mb-1">Location</label>
                        <select id="filter-ct-location" class="form-select">
                            <option value="">All</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter-ct-date-start" class="form-label mb-1">From</label>
                        <input type="date" id="filter-ct-date-start" class="form-control" value="{{ $filters['date_start'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter-ct-date-end" class="form-label mb-1">To</label>
                        <input type="date" id="filter-ct-date-end" class="form-control" value="{{ $filters['date_end'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-4 col-lg-1">
                        <button type="button" id="reset-ct-filter" class="btn btn-light-secondary w-100" title="Reset filters">
                            <i class="fa-regular fa-rotate-left"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="ct-page-results">
            <div class="card shadow-sm border-0 position-relative">
                <div class="card-body position-relative p-0">
                    <div id="ct-page-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2 text-muted">Loading data...</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center px-3 py-3 border-bottom">
                        <h5 class="card-title mb-0">Count Tags</h5>
                        <span class="badge bg-light-primary" id="ct-filter-result">{{ number_format($tags->total()) }} records</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle ct-index-table mb-0">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end" style="width: 4.5rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tags as $tag)
                                    @php
                                        $locationLabel = $tag->location?->name ?? ($tag->location_name ?: null);
                                        $positionParts = array_filter([
                                            $tag->section_code ? 'Sec '.$tag->section_code : null,
                                            ($tag->row || $tag->col || $tag->level)
                                                ? 'R'.($tag->row ?? '-').'/C'.($tag->col ?? '-').'/L'.($tag->level ?? '-')
                                                : null,
                                        ]);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="ct-tag-no">{{ $tag->count_tag_number ?: '—' }}</div>
                                        </td>
                                        <td class="text-nowrap ct-cell-muted">{{ $tag->count_tag_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            <div class="ct-item-code">{{ $tag->item_code }}</div>
                                            @if ($tag->item?->name)
                                                <div class="ct-item-name">{{ $tag->item->name }}</div>
                                            @endif
                                        </td>
                                        <td class="ct-cell-muted">{{ $tag->itemCategory?->name ?: '—' }}</td>
                                        <td>
                                            <div>{{ $locationLabel ?: '—' }}</div>
                                            @if ($positionParts !== [])
                                                <div class="ct-item-name">{{ implode(' · ', $positionParts) }}</div>
                                            @endif
                                        </td>
                                        <td class="ct-qty-cell">
                                            <span class="ct-qty-value">{{ number_format((float) $tag->qty, 2) }}</span>
                                            @if ($tag->uom_code)
                                                <span class="ct-qty-uom">{{ $tag->uom_code }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary ct-view-btn"
                                                data-detail-url="{{ route('count-tags.non-fg.show', $tag) }}"
                                                title="View detail"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="sc-empty-state py-5">
                                                <div class="sc-empty-icon"><i class="fa-duotone fa-solid fa-qrcode"></i></div>
                                                <div class="fw-semibold mb-1">No count tags yet</div>
                                                <p class="text-muted mb-3">Scan product QR codes to start recording counts.</p>
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
                        <div class="px-3 py-3 border-top">
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ct-detail-modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-0" id="ct-detail-modal-title">Count Tag Detail</h5>
                    <div class="sc-meta-chips" id="ct-detail-chips">
                        <span class="sc-meta-chip"><i class="fa-regular fa-calendar"></i> <span data-field="count_tag_date">—</span></span>
                        <span class="sc-meta-chip"><i class="fa-regular fa-user"></i> <span data-field="created_by_name">—</span></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div id="ct-detail-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <div class="mt-2 text-muted">Loading detail...</div>
                </div>
                <div id="ct-detail-error" class="alert alert-danger d-none mb-0"></div>
                <div id="ct-detail-content" class="d-none">
                    <div class="ct-detail-hero">
                        <div class="ct-detail-hero-card is-qty">
                            <div class="label">Quantity</div>
                            <div class="value" data-field="qty_display">—</div>
                            <div class="subvalue" data-field="count_tag_number">—</div>
                        </div>
                        <div class="ct-detail-hero-card">
                            <div class="label">Location</div>
                            <div class="value" data-field="location_name">—</div>
                            <div class="subvalue" data-field="position_line">—</div>
                        </div>
                        <div class="ct-detail-hero-card">
                            <div class="label">Tran Date</div>
                            <div class="value" data-field="tran_date">—</div>
                        </div>
                    </div>

                    <div class="ct-detail-panels">
                        <div class="ct-detail-panel">
                            <div class="ct-detail-panel-title"><i class="fa-regular fa-box"></i> Item</div>
                            <div class="ct-dl">
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Code</div>
                                    <div class="ct-dl-value" data-field="item_code">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Category</div>
                                    <div class="ct-dl-value" data-field="category_name">—</div>
                                </div>
                                <div class="ct-dl-item is-wide">
                                    <div class="ct-dl-label">Name</div>
                                    <div class="ct-dl-value" data-field="item_name">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">UOM</div>
                                    <div class="ct-dl-value" data-field="uom">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Condition</div>
                                    <div class="ct-dl-value" data-field="condition_html">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Size</div>
                                    <div class="ct-dl-value" data-field="size">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="ct-detail-panel">
                            <div class="ct-detail-panel-title"><i class="fa-regular fa-map-location-dot"></i> Position</div>
                            <div class="ct-dl">
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Section</div>
                                    <div class="ct-dl-value" data-field="section_code">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Row</div>
                                    <div class="ct-dl-value" data-field="row">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Col</div>
                                    <div class="ct-dl-value" data-field="col">—</div>
                                </div>
                                <div class="ct-dl-item">
                                    <div class="ct-dl-label">Level</div>
                                    <div class="ct-dl-value" data-field="level">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/stock-correction-modern.css') }}">
    <link rel="stylesheet" href="{{ url('assets/css/count-tag-non-fg.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/count-tag-non-fg-index.js') }}"></script>
@endpush
