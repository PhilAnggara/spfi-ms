(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function setLoading(active) {
        const loadingEl = document.getElementById('ct-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function showPaginationError() {
        setLoading(false);
        window.alert('Could not load this page. Please try again.');
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
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                showPaginationError();
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#ct-page-results');
            const currentResults = document.querySelector('#ct-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                showPaginationError();
                return;
            }

            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            if (typeof window.initCountTagNonFgPage === 'function') {
                window.initCountTagNonFgPage();
            }
        } catch (_) {
            showPaginationError();
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

    window.ctReplacePageContent = replacePageContent;

    function initCountTagNonFgFilters() {
        const filterForm = document.getElementById('ct-filter-form');
        if (!filterForm || filterForm.dataset.filterInitialized === '1') {
            return;
        }
        filterForm.dataset.filterInitialized = '1';

        const filterElements = {
            keyword: document.getElementById('filter-ct-keyword'),
            dateStart: document.getElementById('filter-ct-date-start'),
            dateEnd: document.getElementById('filter-ct-date-end'),
            location: document.getElementById('filter-ct-location'),
            category: document.getElementById('filter-ct-category'),
            reset: document.getElementById('reset-ct-filter'),
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
            setQueryParam(url.searchParams, 'location_id', filterElements.location?.value);
            setQueryParam(url.searchParams, 'category_id', filterElements.category?.value);
            url.searchParams.delete('page');

            return url.toString();
        };

        let debounceTimer = null;
        const applyServerFilter = (useDebounce = false) => {
            const doRequest = () => {
                const url = buildFilterUrl();

                if (typeof window.ctReplacePageContent === 'function') {
                    window.ctReplacePageContent(url, true);
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

        if (filterElements.location) {
            filterElements.location.addEventListener('change', () => applyServerFilter(false));
        }

        if (filterElements.category) {
            filterElements.category.addEventListener('change', () => applyServerFilter(false));
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
                if (filterElements.location) {
                    filterElements.location.value = '';
                }
                if (filterElements.category) {
                    filterElements.category.value = '';
                }

                applyServerFilter(false);
            });
        }
    }

    function displayValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return String(value);
    }

    function setField(root, name, value, { html = false } = {}) {
        if (!root) {
            return;
        }

        root.querySelectorAll(`[data-field="${name}"]`).forEach((el) => {
            if (html) {
                el.innerHTML = value;
                return;
            }

            el.textContent = value;
        });
    }

    function positionLine(data) {
        const parts = [];

        if (data.section_code) {
            parts.push(`Sec ${data.section_code}`);
        }

        const row = displayValue(data.row);
        const col = displayValue(data.col);
        const level = displayValue(data.level);
        if (row !== '—' || col !== '—' || level !== '—') {
            parts.push(`R${row}/C${col}/L${level}`);
        }

        return parts.length ? parts.join(' · ') : '—';
    }

    function conditionBadgeHtml(condition) {
        if (!condition) {
            return '—';
        }

        const map = {
            Good: 'is-good',
            Damaged: 'is-damaged',
            Expired: 'is-expired',
            Quarantine: 'is-quarantine',
        };
        const cls = map[condition] || 'is-good';

        return `<span class="ct-condition-badge ${cls}">${condition}</span>`;
    }

    async function openDetailModal(url) {
        const modalEl = document.getElementById('ct-detail-modal');
        if (!modalEl || !window.bootstrap) {
            return;
        }

        const loadingEl = document.getElementById('ct-detail-loading');
        const errorEl = document.getElementById('ct-detail-error');
        const contentEl = document.getElementById('ct-detail-content');
        const titleEl = document.getElementById('ct-detail-modal-title');

        loadingEl?.classList.remove('d-none');
        errorEl?.classList.add('d-none');
        contentEl?.classList.add('d-none');

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load count tag detail.');
            }

            const data = await response.json();
            const qtyText = Number(data.qty ?? 0).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 5,
            });
            const uom = data.uom_code || data.uom_name || '';

            if (titleEl) {
                titleEl.textContent = data.count_tag_number
                    ? data.count_tag_number
                    : 'Count Tag Detail';
            }

            setField(modalEl, 'count_tag_number', displayValue(data.count_tag_number));
            setField(modalEl, 'count_tag_date', displayValue(data.count_tag_date));
            setField(modalEl, 'tran_date', displayValue(data.tran_date));
            setField(modalEl, 'created_by_name', displayValue(data.created_by_name));
            setField(modalEl, 'item_code', displayValue(data.item_code));
            setField(modalEl, 'item_name', displayValue(data.item_name));
            setField(modalEl, 'category_name', displayValue(data.category_name));
            setField(modalEl, 'uom', displayValue(data.uom_name || data.uom_code));
            setField(modalEl, 'qty_display', uom ? `${qtyText} ${uom}` : qtyText);
            setField(modalEl, 'location_name', displayValue(data.location_name));
            setField(modalEl, 'position_line', positionLine(data));
            setField(modalEl, 'section_code', displayValue(data.section_code));
            setField(modalEl, 'row', displayValue(data.row));
            setField(modalEl, 'col', displayValue(data.col));
            setField(modalEl, 'level', displayValue(data.level));
            setField(modalEl, 'size', displayValue(data.size));
            setField(modalEl, 'condition_html', conditionBadgeHtml(data.condition), { html: true });

            loadingEl?.classList.add('d-none');
            contentEl?.classList.remove('d-none');
        } catch (error) {
            loadingEl?.classList.add('d-none');
            if (errorEl) {
                errorEl.textContent = error.message || 'Unable to load count tag detail.';
                errorEl.classList.remove('d-none');
            }
        }
    }

    function initCountTagNonFgDetailButtons() {
        document.querySelectorAll('.ct-view-btn').forEach(function (button) {
            if (button.dataset.bound === '1') {
                return;
            }
            button.dataset.bound = '1';

            button.addEventListener('click', function () {
                const url = button.dataset.detailUrl;
                if (!url) {
                    return;
                }

                openDetailModal(url);
            });
        });
    }

    function initCountTagNonFgPage() {
        initCountTagNonFgFilters();
        initCountTagNonFgDetailButtons();
    }

    window.initCountTagNonFgPage = initCountTagNonFgPage;

    document.addEventListener('DOMContentLoaded', function () {
        initCountTagNonFgPage();
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#count-tag-non-fg-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });
})();
