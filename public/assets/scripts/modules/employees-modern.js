document.addEventListener('DOMContentLoaded', function () {
    initEmployeeFilters();
});

function initEmployeeFilters() {
    const filterForm = document.getElementById('employee-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-employee-keyword'),
        department: document.getElementById('filter-employee-department'),
        gender: document.getElementById('filter-employee-gender'),
        status: document.getElementById('filter-employee-status'),
        reset: document.getElementById('reset-employee-filter'),
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
        setQueryParam(url.searchParams, 'gender', filterElements.gender?.value);
        setQueryParam(url.searchParams, 'status', filterElements.status?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.employeeReplacePageContent === 'function') {
                window.employeeReplacePageContent(url, true);
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

    if (filterElements.gender) {
        filterElements.gender.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.status) {
        filterElements.status.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.department) filterElements.department.value = '';
            if (filterElements.gender) filterElements.gender.value = '';
            if (filterElements.status) filterElements.status.value = '';

            applyServerFilter(false);
        });
    }
}
