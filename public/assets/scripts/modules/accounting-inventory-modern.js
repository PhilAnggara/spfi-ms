document.addEventListener('DOMContentLoaded', function () {
    initAccountingInventoryFilters();
});

function initAccountingInventoryFilters() {
    const filterForm = document.getElementById('inventory-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-inventory-keyword'),
        category: document.getElementById('filter-inventory-category'),
        docType: document.getElementById('filter-inventory-doc-type'),
        status: document.getElementById('filter-inventory-status'),
        dateFrom: document.getElementById('filter-inventory-date-from'),
        dateTo: document.getElementById('filter-inventory-date-to'),
        reset: document.getElementById('reset-inventory-filter'),
    };

    const getStatusChips = () => Array.from(document.querySelectorAll('#inventory-page-results .po-status-chip'));

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
        setQueryParam(url.searchParams, 'category_id', filterElements.category?.value);
        setQueryParam(url.searchParams, 'doc_type', filterElements.docType?.value);
        setQueryParam(url.searchParams, 'status', filterElements.status?.value);
        setQueryParam(url.searchParams, 'date_from', filterElements.dateFrom?.value);
        setQueryParam(url.searchParams, 'date_to', filterElements.dateTo?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    const syncStatusChip = () => {
        const activeStatus = String(filterElements.status?.value || '');
        getStatusChips().forEach((chip) => {
            const chipStatus = String(chip.dataset.statusValue || '');
            chip.classList.toggle('active', chipStatus === activeStatus);
        });
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.inventoryReplacePageContent === 'function') {
                window.inventoryReplacePageContent(url, true);
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

    if (filterForm.dataset.statusChipInitialized !== '1') {
        filterForm.dataset.statusChipInitialized = '1';

        document.addEventListener('click', function (event) {
            const chip = event.target.closest('#inventory-page-results .po-status-chip');
            if (!chip) {
                return;
            }

            if (!filterElements.status) {
                return;
            }

            filterElements.status.value = String(chip.dataset.statusValue || '');
            syncStatusChip();
            applyServerFilter(false);
        });
    }

    if (filterElements.keyword) {
        filterElements.keyword.addEventListener('input', () => applyServerFilter(true));
    }

    if (filterElements.category) {
        filterElements.category.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.docType) {
        filterElements.docType.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.status) {
        filterElements.status.addEventListener('change', function () {
            syncStatusChip();
            applyServerFilter(false);
        });
    }

    if (filterElements.dateFrom) {
        filterElements.dateFrom.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.dateTo) {
        filterElements.dateTo.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            const defaultDateFrom = new Date();
            defaultDateFrom.setDate(defaultDateFrom.getDate() - 90);
            const defaultDateFromValue = defaultDateFrom.toISOString().slice(0, 10);

            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.category) filterElements.category.value = '';
            if (filterElements.docType) filterElements.docType.value = 'all';
            if (filterElements.status) filterElements.status.value = 'pending';
            if (filterElements.dateFrom) filterElements.dateFrom.value = defaultDateFromValue;
            if (filterElements.dateTo) filterElements.dateTo.value = '';
            syncStatusChip();
            applyServerFilter(false);
        });
    }

    syncStatusChip();
}
