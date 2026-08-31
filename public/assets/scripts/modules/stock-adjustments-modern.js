document.addEventListener('DOMContentLoaded', function () {
    initStockAdjustmentPage();
});

window.initStockAdjustmentPage = initStockAdjustmentPage;
window.initStockAdjustmentFilters = initStockAdjustmentFilters;

function initStockAdjustmentPage() {
    initStockAdjustmentFilters();
}

function initStockAdjustmentFilters() {
    const filterForm = document.getElementById('sa-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-sa-keyword'),
        dateStart: document.getElementById('filter-sa-date-start'),
        dateEnd: document.getElementById('filter-sa-date-end'),
        reset: document.getElementById('reset-sa-filter'),
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
        setQueryParam(url.searchParams, 'date_start', filterElements.dateStart?.value);
        setQueryParam(url.searchParams, 'date_end', filterElements.dateEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.saReplacePageContent === 'function') {
                window.saReplacePageContent(url, true);
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

    if (filterElements.dateStart) {
        filterElements.dateStart.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.dateEnd) {
        filterElements.dateEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) {
                filterElements.keyword.value = '';
            }
            if (filterElements.dateStart) {
                filterElements.dateStart.value = '';
            }
            if (filterElements.dateEnd) {
                filterElements.dateEnd.value = '';
            }

            applyServerFilter(false);
        });
    }
}
