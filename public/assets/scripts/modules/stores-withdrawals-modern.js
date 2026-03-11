document.addEventListener('DOMContentLoaded', function () {
    initStoreWithdrawalFilters();
});

function initStoreWithdrawalFilters() {
    const filterForm = document.getElementById('sws-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-sws-keyword'),
        department: document.getElementById('filter-sws-department'),
        swsStart: document.getElementById('filter-sws-date-start'),
        swsEnd: document.getElementById('filter-sws-date-end'),
        reset: document.getElementById('reset-sws-filter'),
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
        setQueryParam(url.searchParams, 'department', filterElements.department?.value);
        setQueryParam(url.searchParams, 'sws_start', filterElements.swsStart?.value);
        setQueryParam(url.searchParams, 'sws_end', filterElements.swsEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.swsReplacePageContent === 'function') {
                window.swsReplacePageContent(url, true);
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

    if (filterElements.department) {
        filterElements.department.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.swsStart) {
        filterElements.swsStart.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.swsEnd) {
        filterElements.swsEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.department) filterElements.department.value = '';
            if (filterElements.swsStart) filterElements.swsStart.value = '';
            if (filterElements.swsEnd) filterElements.swsEnd.value = '';

            applyServerFilter(false);
        });
    }
}
