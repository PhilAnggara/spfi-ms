@extends('layouts.app')
@section('title', ' | Active Users / Sessions')

@section('content')
<div class="page-heading" id="active-sessions-page"
     data-list-url="{{ route('active-sessions.index') }}">
    <div class="page-title">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h3 class="mb-0">Active Users / Sessions</h3>
                <small class="text-muted">Monitor presence, devices, and recent activity across all accounts</small>
            </div>
            <div class="col-auto d-flex flex-wrap gap-2">
                @if (auth()->user()?->hasRole('administrator'))
                    <button type="button" id="as-reset-logs" class="btn btn-outline-danger icon icon-left">
                        <i class="fa-duotone fa-solid fa-trash-can"></i>
                        Reset activity logs
                    </button>
                    <form action="{{ route('active-sessions.reset-activity-logs') }}" id="as-reset-logs-form" method="POST" class="d-none">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="reset_password" id="as-reset-password-input" value="">
                    </form>
                @endif
                <button type="button" id="as-refresh" class="btn btn-outline-primary icon icon-left">
                    <i class="fa-duotone fa-solid fa-arrows-rotate" id="as-refresh-icon"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="as-toolbar mb-4" data-aos="fade-down">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label for="as-search" class="form-label mb-1">Search</label>
                    <div class="as-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="as-search" class="form-control" placeholder="Search name or username..." autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label for="as-status" class="form-label mb-1">Status</label>
                    <select id="as-status" class="form-select" aria-label="Filter by status">
                        <option value="all" selected>All Status</option>
                        <option value="online">Online only</option>
                        <option value="offline">Offline only</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="as-sort" class="form-label mb-1">Sort by</label>
                    <select id="as-sort" class="form-select" aria-label="Sort users">
                        <option value="last_seen" selected>Last Activity</option>
                        <option value="online">Online First</option>
                        <option value="name_asc">Name A–Z</option>
                        <option value="name_desc">Name Z–A</option>
                        <option value="department">Department</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button type="button" id="as-filter-reset" class="btn btn-light border">Reset</button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted">Filters update instantly — refresh keeps your search.</small>
                <small class="text-muted"><span id="as-visible-count">{{ $totalCount }}</span> visible</small>
            </div>
        </div>

        <div id="as-live">
            @include('pages.partials.active-session-list')
        </div>
    </section>
</div>

<div class="offcanvas offcanvas-end as-offcanvas" tabindex="-1" id="as-detail-offcanvas" aria-labelledby="as-detail-title">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="as-detail-title">User Activity</h5>
        <div class="d-flex align-items-center gap-2">
            <button type="button"
                    id="as-detail-refresh"
                    class="btn btn-sm btn-outline-primary icon"
                    title="Refresh activity"
                    disabled>
                <i class="fa-duotone fa-solid fa-arrows-rotate" id="as-detail-refresh-icon"></i>
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body" id="as-detail-body">
        <div class="as-detail-loading text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Loading activity...</div>
        </div>
    </div>
</div>
@endsection

@push('addon-style')
<link rel="stylesheet" href="{{ url('assets/css/modules/active-sessions.css') }}">
@endpush
@push('addon-script')
<script src="{{ url('assets/scripts/modules/active-sessions.js') }}"></script>
@endpush
