document.addEventListener('DOMContentLoaded', function () {
    initDeliveryPage();
});

window.initDeliveryPage = initDeliveryPage;
window.initDeliveryFilters = initDeliveryFilters;

function initDeliveryPage() {
    initDeliveryFilters();
    initDeliveryDeleteAction();
}

function initDeliveryFilters() {
    const filterForm = document.getElementById('delivery-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-delivery-keyword'),
        fromLocation: document.getElementById('filter-delivery-from-location'),
        toLocation: document.getElementById('filter-delivery-to-location'),
        drStart: document.getElementById('filter-delivery-date-start'),
        drEnd: document.getElementById('filter-delivery-date-end'),
        reset: document.getElementById('reset-delivery-filter'),
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
        setQueryParam(url.searchParams, 'from_location', filterElements.fromLocation?.value);
        setQueryParam(url.searchParams, 'to_location', filterElements.toLocation?.value);
        setQueryParam(url.searchParams, 'dr_start', filterElements.drStart?.value);
        setQueryParam(url.searchParams, 'dr_end', filterElements.drEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.deliveryReplacePageContent === 'function') {
                window.deliveryReplacePageContent(url, true);
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

    if (filterElements.fromLocation) {
        filterElements.fromLocation.addEventListener('input', () => applyServerFilter(true));
    }

    if (filterElements.toLocation) {
        filterElements.toLocation.addEventListener('input', () => applyServerFilter(true));
    }

    if (filterElements.drStart) {
        filterElements.drStart.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.drEnd) {
        filterElements.drEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.fromLocation) filterElements.fromLocation.value = '';
            if (filterElements.toLocation) filterElements.toLocation.value = '';
            if (filterElements.drStart) filterElements.drStart.value = '';
            if (filterElements.drEnd) filterElements.drEnd.value = '';

            applyServerFilter(false);
        });
    }
}

function initDeliveryDeleteAction() {
    window.confirmDeleteDelivery = function (deliveryId, drNumber) {
        Swal.fire({
            title: 'Delete Delivery',
            text: 'Are you sure you want to delete delivery ' + drNumber + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('hapus-delivery-' + deliveryId);
                if (form) {
                    form.submit();
                }
            }
        });
    };
}
