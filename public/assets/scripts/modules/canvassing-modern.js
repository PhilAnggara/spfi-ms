(function () {
    let debounceTimer = null;

    function setQueryParam(searchParams, key, value) {
        const normalized = String(value ?? '').trim();

        if (normalized === '') {
            searchParams.delete(key);
            return;
        }

        searchParams.set(key, normalized);
    }

    function clearDebounceTimer() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    function initCanvassingFilters() {
        const filterElements = {
            keyword: document.getElementById('filter-canvassing-keyword'),
            department: document.getElementById('filter-canvassing-department'),
            dateStart: document.getElementById('filter-canvassing-date-start'),
            dateEnd: document.getElementById('filter-canvassing-date-end'),
            reset: document.getElementById('reset-canvassing-filter'),
            resetTop: document.getElementById('reset-canvassing-top'),
        };

        if (!filterElements.keyword || !filterElements.department || !filterElements.dateStart || !filterElements.dateEnd) {
            return;
        }

        const buildFilterUrl = () => {
            const url = new URL(window.location.href);

            setQueryParam(url.searchParams, 'keyword', filterElements.keyword.value);
            setQueryParam(url.searchParams, 'department', filterElements.department.value);
            setQueryParam(url.searchParams, 'date_needed_start', filterElements.dateStart.value);
            setQueryParam(url.searchParams, 'date_needed_end', filterElements.dateEnd.value);
            url.searchParams.delete('page');

            return url.toString();
        };

        const applyServerFilter = (useDebounce = false) => {
            const doRequest = () => {
                debounceTimer = null;
                const url = buildFilterUrl();

                if (typeof window.canvassingReplacePageContent === 'function') {
                    window.canvassingReplacePageContent(url, true);
                    return;
                }

                window.location.href = url;
            };

            if (!useDebounce) {
                clearDebounceTimer();
                doRequest();
                return;
            }

            clearDebounceTimer();
            debounceTimer = window.setTimeout(doRequest, 350);
        };

        filterElements.keyword.addEventListener('input', function () {
            applyServerFilter(true);
        });

        filterElements.department.addEventListener('change', function () {
            applyServerFilter(false);
        });

        filterElements.dateStart.addEventListener('change', function () {
            applyServerFilter(false);
        });

        filterElements.dateEnd.addEventListener('change', function () {
            applyServerFilter(false);
        });

        if (filterElements.reset) {
            filterElements.reset.addEventListener('click', function () {
                filterElements.keyword.value = '';
                filterElements.department.value = '';
                filterElements.dateStart.value = '';
                filterElements.dateEnd.value = '';
                applyServerFilter(false);
            });
        }

        if (filterElements.resetTop) {
            filterElements.resetTop.addEventListener('click', function (event) {
                event.preventDefault();

                filterElements.keyword.value = '';
                filterElements.department.value = '';
                filterElements.dateStart.value = '';
                filterElements.dateEnd.value = '';
                applyServerFilter(false);
            });
        }

        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!link) {
                return;
            }

            const href = (link.getAttribute('href') || '').trim();
            if (href === '' || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) {
                return;
            }

            clearDebounceTimer();
        });

        window.addEventListener('pagehide', clearDebounceTimer);
    }

    document.addEventListener('DOMContentLoaded', initCanvassingFilters);
})();
