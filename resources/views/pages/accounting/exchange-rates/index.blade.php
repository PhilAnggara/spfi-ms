@extends('layouts.app')
@section('title', ' | Exchange Rates')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row mb-4 g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <h3 class="mb-1">Exchange Rates</h3>
                <p class="text-muted mb-0">Manage foreign currency to IDR rates used for receiving report accounting conversion.</p>
            </div>
            <div class="col-12 col-lg-4">
                <form method="GET" action="{{ route('accounting.exchange-rates.index') }}" id="currency-filter-form">
                    <input type="hidden" name="sort_by" value="{{ $historySort['sort_by'] }}">
                    <input type="hidden" name="sort_direction" value="{{ $historySort['sort_direction'] }}">
                    <label for="currency" class="form-label mb-1">Currency</label>
                    <select
                        id="currency"
                        name="currency"
                        class="form-select"
                        onchange="this.form.submit()">
                        @foreach ($supportedCurrencies as $currency)
                            <option value="{{ $currency->code }}" @selected($selectedCurrency === $currency->code)>
                                {{ $currency->code }} — {{ $currency->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Current {{ $selectedCurrency }} Rate</h5>
                        @if ($currentRate)
                            <div class="mb-2">
                                <span class="text-muted d-block small">Rate (1 {{ $selectedCurrency }})</span>
                                <span class="fs-4 fw-bold">{{ number_format((float) $currentRate->rate_to_idr, 4, '.', ',') }} IDR</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted d-block small">Effective Date</span>
                                <span class="fw-semibold">{{ $currentRate->effective_date?->format('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="mb-0">
                                <span class="text-muted d-block small">Last Updated By</span>
                                <span>{{ $currentRate->updatedBy?->name ?? $currentRate->createdBy?->name ?? '-' }}</span>
                            </div>
                        @else
                            <div class="alert alert-warning mb-0" role="alert">
                                No {{ $selectedCurrency }} exchange rate has been recorded yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Update {{ $selectedCurrency }} Rate</h5>

                        @if ($canUpdateRate)
                            <form action="{{ route('accounting.exchange-rates.store') }}" method="POST" class="row g-3">
                                @csrf
                                <input type="hidden" name="currency_code" value="{{ $selectedCurrency }}">
                                <div class="col-12 col-md-6">
                                    <label for="rate_to_idr" class="form-label">Rate to IDR</label>
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        class="form-control @error('rate_to_idr') is-invalid @enderror"
                                        id="rate_to_idr"
                                        name="rate_to_idr"
                                        value="{{ old('rate_to_idr') }}"
                                        placeholder="e.g. 16000"
                                        required>
                                    @error('rate_to_idr')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="effective_date" class="form-label">Effective Date</label>
                                    <input
                                        type="date"
                                        class="form-control @error('effective_date') is-invalid @enderror"
                                        id="effective_date"
                                        name="effective_date"
                                        value="{{ old('effective_date', now()->toDateString()) }}"
                                        required>
                                    @error('effective_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea
                                        class="form-control @error('notes') is-invalid @enderror"
                                        id="notes"
                                        name="notes"
                                        rows="2"
                                        placeholder="Optional notes">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success icon icon-left">
                                        <i class="fa-light fa-floppy-disk"></i>
                                        Save {{ $selectedCurrency }} Rate
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="alert alert-info mb-0" role="alert">
                                You can view exchange rates. Contact Accounting Manager or Supervisor to update {{ $selectedCurrency }} rates.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body position-relative">
                        <div
                            id="exchange-rate-history-panel"
                            data-history-url="{{ route('accounting.exchange-rates.index') }}"
                            data-currency="{{ $selectedCurrency }}"
                            data-sort-by="{{ $historySort['sort_by'] }}"
                            data-sort-direction="{{ $historySort['sort_direction'] }}">
                            @include('pages.accounting.exchange-rates.partials.history-panel')
                        </div>
                        <div id="exchange-rate-history-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 2;">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('addon-script')
<script>
    (function () {
        const panel = document.getElementById('exchange-rate-history-panel');
        const loadingOverlay = document.getElementById('exchange-rate-history-loading');
        const currencyFilterForm = document.getElementById('currency-filter-form');

        if (!panel || !loadingOverlay) {
            return;
        }

        let isLoading = false;

        function syncCurrencyFilterSortInputs(sortBy, sortDirection) {
            if (!currencyFilterForm) {
                return;
            }

            const sortByInput = currencyFilterForm.querySelector('input[name="sort_by"]');
            const sortDirectionInput = currencyFilterForm.querySelector('input[name="sort_direction"]');

            if (sortByInput) {
                sortByInput.value = sortBy;
            }

            if (sortDirectionInput) {
                sortDirectionInput.value = sortDirection;
            }
        }

        function readPanelState() {
            const sortBySelect = panel.querySelector('#sort_by');
            const sortDirectionSelect = panel.querySelector('#sort_direction');

            return {
                currency: panel.dataset.currency,
                sort_by: sortBySelect?.value || panel.dataset.sortBy || 'effective_date',
                sort_direction: sortDirectionSelect?.value || panel.dataset.sortDirection || 'desc',
            };
        }

        function buildHistoryUrl(params) {
            const url = new URL(panel.dataset.historyUrl, window.location.origin);

            url.searchParams.set('currency', params.currency);
            url.searchParams.set('sort_by', params.sort_by);
            url.searchParams.set('sort_direction', params.sort_direction);

            if (params.page) {
                url.searchParams.set('page', params.page);
            }

            return url;
        }

        function updateBrowserUrl(params) {
            const pageUrl = new URL(window.location.href);

            pageUrl.searchParams.set('currency', params.currency);
            pageUrl.searchParams.set('sort_by', params.sort_by);
            pageUrl.searchParams.set('sort_direction', params.sort_direction);

            if (params.page) {
                pageUrl.searchParams.set('page', params.page);
            } else {
                pageUrl.searchParams.delete('page');
            }

            window.history.replaceState({}, '', pageUrl);
        }

        async function loadHistory(params) {
            if (isLoading) {
                return;
            }

            isLoading = true;
            loadingOverlay.classList.remove('d-none');
            loadingOverlay.classList.add('d-flex');

            const url = buildHistoryUrl(params);

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Failed to load exchange rate history.');
                }

                const html = await response.text();
                panel.innerHTML = html;
                panel.dataset.currency = params.currency;
                panel.dataset.sortBy = params.sort_by;
                panel.dataset.sortDirection = params.sort_direction;

                syncCurrencyFilterSortInputs(params.sort_by, params.sort_direction);
                updateBrowserUrl(params);
                bindPanelEvents();
            } catch (error) {
                console.error(error);
            } finally {
                isLoading = false;
                loadingOverlay.classList.add('d-none');
                loadingOverlay.classList.remove('d-flex');
            }
        }

        function bindPanelEvents() {
            panel.querySelectorAll('[data-exchange-rate-sort]').forEach((element) => {
                element.addEventListener('change', () => {
                    const state = readPanelState();
                    loadHistory(state);
                });
            });

            panel.querySelectorAll('[data-exchange-rate-sort-column]').forEach((button) => {
                button.addEventListener('click', () => {
                    const column = button.dataset.exchangeRateSortColumn;
                    const sortBySelect = panel.querySelector('#sort_by');
                    const sortDirectionSelect = panel.querySelector('#sort_direction');

                    if (!sortBySelect || !sortDirectionSelect || !column) {
                        return;
                    }

                    const currentSortBy = sortBySelect.value;
                    const currentDirection = sortDirectionSelect.value;
                    const nextDirection = currentSortBy === column && currentDirection === 'desc' ? 'asc' : 'desc';

                    sortBySelect.value = column;
                    sortDirectionSelect.value = nextDirection;

                    loadHistory(readPanelState());
                });
            });

            panel.querySelectorAll('[data-exchange-rate-pagination] a').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();

                    const paginationUrl = new URL(link.href, window.location.origin);
                    const state = readPanelState();

                    loadHistory({
                        currency: paginationUrl.searchParams.get('currency') || state.currency,
                        sort_by: paginationUrl.searchParams.get('sort_by') || state.sort_by,
                        sort_direction: paginationUrl.searchParams.get('sort_direction') || state.sort_direction,
                        page: paginationUrl.searchParams.get('page') || undefined,
                    });
                });
            });
        }

        bindPanelEvents();
    })();
</script>
@endpush
