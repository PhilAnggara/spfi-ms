(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

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
        const loadingEl = document.getElementById('prs-approval-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function openRequestedPrsModal() {
        const container = document.getElementById('prs-approval-page-container');
        const prsId = container?.dataset.autoOpenPrsId;
        if (!prsId || !window.bootstrap?.Modal) {
            return;
        }

        const modalEl = document.getElementById(`detail-modal-${prsId}`);
        if (!modalEl) {
            return;
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        container.dataset.autoOpenPrsId = '';
    }

    async function replacePageContent(url, pushState = true) {
        const normalizedUrl = new URL(url, window.location.origin).toString();

        if (isLoading) {
            pendingReplaceRequest = {
                url: normalizedUrl,
                pushState,
            };
            return;
        }

        isLoading = true;
        setLoading(true);

        try {
            const response = await fetch(normalizedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                window.location.href = normalizedUrl;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#prs-approval-page-results');
            const currentResults = document.querySelector('#prs-approval-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                window.location.href = normalizedUrl;
                return;
            }

            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            initPageTooltips(newResults);
            openRequestedPrsModal();

            if (window.feather && typeof window.feather.replace === 'function') {
                window.feather.replace();
            }
        } catch (_) {
            window.location.href = normalizedUrl;
        } finally {
            isLoading = false;
            setLoading(false);

            if (pendingReplaceRequest) {
                const nextRequest = pendingReplaceRequest;
                pendingReplaceRequest = null;

                replacePageContent(nextRequest.url, nextRequest.pushState);
            }
        }
    }

    function initFilters() {
        const filterForm = document.getElementById('prs-approval-filter-form');
        if (!filterForm) {
            return;
        }

        if (filterForm.dataset.filterInitialized === '1') {
            return;
        }
        filterForm.dataset.filterInitialized = '1';

        const filterElements = {
            keyword: document.getElementById('filter-prs-approval-keyword'),
            status: document.getElementById('filter-prs-approval-status'),
            dateStart: document.getElementById('filter-prs-approval-date-start'),
            dateEnd: document.getElementById('filter-prs-approval-date-end'),
            reset: document.getElementById('reset-prs-approval-filter'),
        };

        const setQueryParam = (searchParams, key, value) => {
            const normalizedValue = String(value || '').trim();
            if (normalizedValue === '') {
                searchParams.delete(key);
                return;
            }

            searchParams.set(key, normalizedValue);
        };

        const buildFilterUrl = () => {
            const url = new URL(window.location.href);

            setQueryParam(url.searchParams, 'keyword', filterElements.keyword?.value);
            setQueryParam(url.searchParams, 'status', filterElements.status?.value);
            setQueryParam(url.searchParams, 'date_from', filterElements.dateStart?.value);
            setQueryParam(url.searchParams, 'date_to', filterElements.dateEnd?.value);

            url.searchParams.delete('prs');
            url.searchParams.delete('open');
            url.searchParams.delete('page');

            return url.toString();
        };

        let debounceTimer = null;
        const applyServerFilter = (useDebounce = false) => {
            const doRequest = () => {
                replacePageContent(buildFilterUrl(), true);
            };

            if (!useDebounce) {
                doRequest();
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(doRequest, 350);
        };

        if (filterElements.keyword) {
            filterElements.keyword.addEventListener('input', () => applyServerFilter(true));
        }

        if (filterElements.status) {
            filterElements.status.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.dateStart) {
            filterElements.dateStart.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.dateEnd) {
            filterElements.dateEnd.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.reset) {
            filterElements.reset.addEventListener('click', function () {
                if (filterElements.keyword) filterElements.keyword.value = '';
                if (filterElements.status) filterElements.status.value = '';
                if (filterElements.dateStart) filterElements.dateStart.value = '';
                if (filterElements.dateEnd) filterElements.dateEnd.value = '';

                applyServerFilter(false);
            });
        }
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#prs-approval-page-container a[href*="page="]');
        if (!link) return;

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });

    initFilters();
    initPageTooltips(document);
    openRequestedPrsModal();
    if (window.feather && typeof window.feather.replace === 'function') {
        window.feather.replace();
    }
})();
