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
                                    <th>Department Name</th>
                                    <th>Info</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($storeWithdrawals as $sws)
                                    <tr>
                                        <td class="fw-semibold">{{ $sws['sws_number'] }}</td>
                                        <td>
                                            <i class="fa-duotone fa-solid fa-calendar-days text-danger"></i>
                                            {{ \Carbon\Carbon::parse($sws['sws_date'])->format('d M Y') }}
                                        </td>
                                        <td>
                                            <span class="badge bg-light-primary">{{ $sws['department_code'] }}</span>
                                        </td>
                                        <td>{{ $sws['department_name'] }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($sws['info'], 50) }}</td>
                                        <td>{{ $sws['created_by_name'] }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('stores-withdrawals.show', $sws['id']) }}" class="btn btn-outline-secondary" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail">
                                                    <i class="fa-light fa-eye"></i>
                                                </a>
                                                <a href="{{ route('stores-withdrawals.edit', $sws['id']) }}" class="btn btn-outline-primary" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="fa-light fa-pen"></i>
                                                </a>
                                                <form action="{{ route('stores-withdrawals.destroy', $sws['id']) }}" method="POST" onsubmit="return confirm('Delete this stores withdrawal?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                                        <i class="fa-light fa-trash"></i>
                                                    </button>
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
                @endif
            </div>
        </div>
    </section>
</div>
</div>
@endsection

@push('addon-style')
    <link rel="stylesheet" href="{{ url('assets/css/purchase-orders-modern.css') }}">
@endpush

@push('addon-script')
    <script src="{{ url('assets/scripts/modules/stores-withdrawals-modern.js') }}"></script>
    <script>
        (function () {
            let isLoading = false;

            function setLoading(active) {
                const loadingEl = document.getElementById('sws-page-loading');
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
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        window.location.href = url;
                        return;
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.querySelector('#sws-page-container');
                    const currentContainer = document.querySelector('#sws-page-container');

                    if (!newContainer || !currentContainer) {
                        window.location.href = url;
                        return;
                    }

                    currentContainer.replaceWith(newContainer);

                    if (pushState) {
                        window.history.pushState({}, '', url);
                    }

                    if (typeof initStoreWithdrawalFilters === 'function') {
                        initStoreWithdrawalFilters();
                    }

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

            window.swsReplacePageContent = replacePageContent;

            document.addEventListener('click', function (event) {
                const link = event.target.closest('#sws-page-container a[href*="page="]');
                if (!link) return;

                event.preventDefault();
                replacePageContent(link.href, true);
            });

            window.addEventListener('popstate', function () {
                replacePageContent(window.location.href, false);
            });
        })();
    </script>
@endpush
