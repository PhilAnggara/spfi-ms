@extends('layouts.app')
@section('title', ' | Doc Entry')

@section('content')
<div class="page-heading">
    <div class="page-title mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-12">
                <h3 class="mb-1">Document Entry</h3>
                <p class="text-muted mb-0">
                    Encode journal entries for Receiving Reports and Delivery Receipts. Totals reflect the SPFI encode queue (RR/DR), not every journal in legacy General Ledger.
                </p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="section">
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Pending Entry</div>
                        <div class="fs-3 fw-bold text-warning" id="summary-pending">{{ number_format($summary['pending']) }}</div>
                        <div class="text-muted small mt-1">SPFI RR/DR not yet encoded</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Encoded</div>
                        <div class="fs-3 fw-bold text-success" id="summary-encoded">{{ number_format($summary['encoded']) }}</div>
                        <div class="text-muted small mt-1">Encoded RR/DR in SPFI</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Total in Queue</div>
                        <div class="fs-3 fw-bold" id="summary-total">{{ number_format($summary['total']) }}</div>
                        <div class="text-muted small mt-1">Pending + Encoded (RR/DR only)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form id="doc-entry-filters" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label mb-1">Document Type</label>
                        <select name="doc_type" class="form-select">
                            <option value="all" @selected(($filters['doc_type'] ?? 'all') === 'all')>All</option>
                            <option value="RR" @selected(($filters['doc_type'] ?? '') === 'RR')>RR</option>
                            <option value="DR" @selected(($filters['doc_type'] ?? '') === 'DR')>DR</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending Entry</option>
                            <option value="encoded" @selected(($filters['status'] ?? '') === 'encoded')>Encoded</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label mb-1">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label mb-1">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label mb-1">Search</label>
                        <input type="search" name="keyword" class="form-control" placeholder="Doc no, reference, party..." value="{{ $filters['keyword'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 col-xl-1">
                        <button type="button" id="reset-doc-entry-filter" class="btn btn-light-secondary w-100">
                            <i class="fa-regular fa-rotate-left me-1"></i>
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body position-relative">
                <div id="doc-entry-list-loading" class="d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 20;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2 text-muted">Loading data...</div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Document Register</h5>
                </div>
                <div id="doc-entry-list-panel">
                    @include('pages.accounting.doc-entries.partials.list-panel')
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="doc-entry-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-0" id="doc-entry-modal-title">Document Entry</h5>
                    <div class="text-muted small">Review and encode journal lines</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="doc-entry-modal-body">
                <div class="text-center text-muted py-5">Loading...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-script')
<script>
(function () {
    const form = document.getElementById('doc-entry-filters');
    const panel = document.getElementById('doc-entry-list-panel');
    const loading = document.getElementById('doc-entry-list-loading');
    const resetBtn = document.getElementById('reset-doc-entry-filter');
    const modalEl = document.getElementById('doc-entry-modal');
    const modalBody = document.getElementById('doc-entry-modal-body');
    const modalTitle = document.getElementById('doc-entry-modal-title');
    if (!form || !panel) {
        return;
    }

    let timer = null;
    const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;

    const setLoading = (isLoading) => {
        if (!loading) {
            return;
        }
        loading.classList.toggle('d-none', !isLoading);
        loading.classList.toggle('d-flex', isLoading);
    };

    const refresh = (page) => {
        const params = new URLSearchParams(new FormData(form));
        if (page) {
            params.set('page', page);
        }

        setLoading(true);
        fetch(`{{ route('accounting.doc-entries.index') }}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        })
            .then((response) => response.text())
            .then((html) => {
                panel.innerHTML = html;
            })
            .catch((error) => console.error(error))
            .finally(() => setLoading(false));
    };

    form.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => refresh(), 300);
    });
    form.addEventListener('change', () => refresh());

    resetBtn?.addEventListener('click', () => {
        form.reset();
        form.querySelector('[name="doc_type"]').value = 'all';
        form.querySelector('[name="status"]').value = 'all';
        form.querySelector('[name="date_from"]').value = '';
        form.querySelector('[name="date_to"]').value = '';
        form.querySelector('[name="keyword"]').value = '';
        refresh();
    });

    panel.addEventListener('click', (event) => {
        const pageLink = event.target.closest('.pagination a');
        if (pageLink) {
            event.preventDefault();
            const url = new URL(pageLink.href);
            refresh(url.searchParams.get('page') || '1');
            return;
        }

        const openBtn = event.target.closest('[data-doc-entry-open]');
        if (!openBtn || !modal || !modalBody) {
            return;
        }

        event.preventDefault();
        const url = openBtn.getAttribute('href');
        modalTitle.textContent = openBtn.dataset.title || 'Document Entry';
        modalBody.innerHTML = '<div class="text-center text-muted py-5">Loading...</div>';
        modal.show();

        fetch(`${url}${url.includes('?') ? '&' : '?'}modal=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to load entry');
                }
                return response.text();
            })
            .then((html) => {
                modalBody.innerHTML = html;
                modalBody.querySelectorAll('script').forEach((oldScript) => {
                    const script = document.createElement('script');
                    script.textContent = oldScript.textContent;
                    oldScript.replaceWith(script);
                });
            })
            .catch((error) => {
                console.error(error);
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">Unable to load document entry.</div>';
            });
    });
})();
</script>
@endpush
