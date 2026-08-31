document.addEventListener('DOMContentLoaded', function () {
    initObcPage();
});

window.initObcPage = initObcPage;
window.initObcFilters = initObcFilters;

function initObcPage() {
    initObcFilters();
}

function initObcFilters() {
    const filterForm = document.getElementById('obc-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-obc-keyword'),
        status: document.getElementById('filter-obc-status'),
        period: document.getElementById('filter-obc-period'),
        reset: document.getElementById('reset-obc-filter'),
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
        setQueryParam(url.searchParams, 'period', filterElements.period?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.obcReplacePageContent === 'function') {
                window.obcReplacePageContent(url, true);
                return;
            }

            window.location.href = url;
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

    if (filterElements.period) {
        filterElements.period.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) {
                filterElements.keyword.value = '';
            }
            if (filterElements.status) {
                filterElements.status.value = '';
            }
            if (filterElements.period) {
                filterElements.period.value = '';
            }

            applyServerFilter(false);
        });
    }
}
