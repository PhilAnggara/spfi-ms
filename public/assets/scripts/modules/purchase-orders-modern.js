document.addEventListener('DOMContentLoaded', function () {
    initPurchaseOrderFilters();
});

function initPurchaseOrderFilters() {
    const filterForm = document.getElementById('po-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-po-keyword'),
        status: document.getElementById('filter-po-status'),
        createdStart: document.getElementById('filter-po-created-start'),
        createdEnd: document.getElementById('filter-po-created-end'),
        reset: document.getElementById('reset-po-filter'),
        statusChips: Array.from(document.querySelectorAll('.po-status-chip')),
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
        setQueryParam(url.searchParams, 'created_start', filterElements.createdStart?.value);
        setQueryParam(url.searchParams, 'created_end', filterElements.createdEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    const syncStatusChip = () => {
        const activeStatus = String(filterElements.status?.value || '');
        filterElements.statusChips.forEach((chip) => {
            const chipStatus = String(chip.dataset.statusValue || '');
            chip.classList.toggle('active', chipStatus === activeStatus);
        });
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.poReplacePageContent === 'function') {
                window.poReplacePageContent(url, true);
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

    filterElements.statusChips.forEach((chip) => {
        chip.addEventListener('click', function () {
            if (!filterElements.status) {
                return;
            }

            filterElements.status.value = String(chip.dataset.statusValue || '');
            syncStatusChip();
            applyServerFilter(false);
        });
    });

    if (filterElements.keyword) {
        filterElements.keyword.addEventListener('input', () => applyServerFilter(true));
    }

    if (filterElements.status) {
        filterElements.status.addEventListener('change', function () {
            syncStatusChip();
            applyServerFilter(false);
        });
    }

    if (filterElements.createdStart) {
        filterElements.createdStart.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.createdEnd) {
        filterElements.createdEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.status) filterElements.status.value = '';
            if (filterElements.createdStart) filterElements.createdStart.value = '';
            if (filterElements.createdEnd) filterElements.createdEnd.value = '';

            syncStatusChip();
            applyServerFilter(false);
        });
    }

    syncStatusChip();
}
