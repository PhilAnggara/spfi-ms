document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('as-search');
    const statusSelect = document.getElementById('as-status');
    const sortSelect = document.getElementById('as-sort');
    const resetButton = document.getElementById('as-filter-reset');
    const list = document.getElementById('as-list');
    const emptyState = document.getElementById('as-empty');
    const visibleCount = document.getElementById('as-visible-count');
    const detailBody = document.getElementById('as-detail-body');
    const detailTitle = document.getElementById('as-detail-title');
    const offcanvasEl = document.getElementById('as-detail-offcanvas');

    if (!searchInput || !statusSelect || !sortSelect || !resetButton || !list || !emptyState || !visibleCount) {
        return;
    }

    const rows = Array.from(list.querySelectorAll('[data-as-row="true"]'));

    const loadingHtml = `
        <div class="as-detail-loading text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <div>Loading activity...</div>
        </div>
    `;

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function compareRows(a, b, sort) {
        const nameA = (a.dataset.name || '').toLowerCase();
        const nameB = (b.dataset.name || '').toLowerCase();
        const deptA = (a.dataset.department || '').toLowerCase();
        const deptB = (b.dataset.department || '').toLowerCase();
        const seenA = Number(a.dataset.lastSeen || 0);
        const seenB = Number(b.dataset.lastSeen || 0);
        const onlineA = a.dataset.status === 'online' ? 0 : 1;
        const onlineB = b.dataset.status === 'online' ? 0 : 1;
        const orderA = Number(a.dataset.order || 0);
        const orderB = Number(b.dataset.order || 0);

        if (sort === 'name_asc') {
            return nameA.localeCompare(nameB) || orderA - orderB;
        }

        if (sort === 'name_desc') {
            return nameB.localeCompare(nameA) || orderA - orderB;
        }

        if (sort === 'department') {
            return deptA.localeCompare(deptB) || nameA.localeCompare(nameB) || orderA - orderB;
        }

        if (sort === 'online') {
            if (onlineA !== onlineB) {
                return onlineA - onlineB;
            }

            if (seenA === 0 && seenB !== 0) return 1;
            if (seenB === 0 && seenA !== 0) return -1;
            return seenB - seenA || nameA.localeCompare(nameB);
        }

        if (seenA === 0 && seenB !== 0) return 1;
        if (seenB === 0 && seenA !== 0) return -1;
        return seenB - seenA || nameA.localeCompare(nameB);
    }

    function applyFilters() {
        const query = normalizeText(searchInput.value);
        const status = statusSelect.value;
        const sort = sortSelect.value;
        const shown = [];

        rows.forEach(function (row) {
            const name = normalizeText(row.dataset.name || '');
            const username = normalizeText(row.dataset.username || '');
            const rowStatus = row.dataset.status || 'offline';

            const passSearch = !query || name.includes(query) || username.includes(query);
            const passStatus = status === 'all' || rowStatus === status;
            const visible = passSearch && passStatus;

            row.style.display = visible ? '' : 'none';

            if (visible) {
                shown.push(row);
            }
        });

        shown.sort(function (a, b) {
            return compareRows(a, b, sort);
        });

        shown.forEach(function (row) {
            list.insertBefore(row, emptyState);
        });

        visibleCount.textContent = String(shown.length);
        emptyState.classList.toggle('d-none', shown.length !== 0);
    }

    let searchTimer;
    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(applyFilters, 90);
    });

    statusSelect.addEventListener('change', applyFilters);
    sortSelect.addEventListener('change', applyFilters);

    resetButton.addEventListener('click', function () {
        searchInput.value = '';
        statusSelect.value = 'all';
        sortSelect.value = 'last_seen';
        applyFilters();
        searchInput.focus();
    });

    document.querySelectorAll('.as-btn-detail').forEach(function (button) {
        button.addEventListener('click', async function () {
            const url = button.dataset.detailUrl;
            const name = button.dataset.userName || 'User Activity';

            if (!url || !offcanvasEl || !window.bootstrap) {
                return;
            }

            detailTitle.textContent = name;
            detailBody.innerHTML = loadingHtml;

            const canvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            canvas.show();

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load detail');
                }

                detailBody.innerHTML = await response.text();
            } catch (error) {
                detailBody.innerHTML = `
                    <div class="text-danger text-center py-5">
                        Unable to load activity history.
                    </div>
                `;
            }
        });
    });

    if (window.bootstrap && window.bootstrap.Tooltip) {
        document.querySelectorAll('[data-bstooltip-toggle="tooltip"]').forEach(function (el) {
            if (!window.bootstrap.Tooltip.getInstance(el)) {
                new window.bootstrap.Tooltip(el);
            }
        });
    }

    applyFilters();
});
