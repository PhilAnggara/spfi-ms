document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('active-sessions-page');
    const searchInput = document.getElementById('as-search');
    const statusSelect = document.getElementById('as-status');
    const sortSelect = document.getElementById('as-sort');
    const resetButton = document.getElementById('as-filter-reset');
    const refreshButton = document.getElementById('as-refresh');
    const refreshIcon = document.getElementById('as-refresh-icon');
    const liveRegion = document.getElementById('as-live');
    const visibleCount = document.getElementById('as-visible-count');
    const detailBody = document.getElementById('as-detail-body');
    const detailTitle = document.getElementById('as-detail-title');
    const detailRefreshButton = document.getElementById('as-detail-refresh');
    const detailRefreshIcon = document.getElementById('as-detail-refresh-icon');
    const offcanvasEl = document.getElementById('as-detail-offcanvas');

    if (!page || !searchInput || !statusSelect || !sortSelect || !resetButton || !liveRegion || !visibleCount) {
        return;
    }

    let rows = [];
    let list = null;
    let emptyState = null;
    let refreshing = false;
    let detailRefreshing = false;
    let currentDetailUrl = null;

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
        const historyA = Number(a.dataset.lastHistory || 0);
        const historyB = Number(b.dataset.lastHistory || 0);
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

        if (sort === 'activity_history') {
            if (historyA === 0 && historyB !== 0) return 1;
            if (historyB === 0 && historyA !== 0) return -1;
            return historyB - historyA || nameA.localeCompare(nameB);
        }

        if (seenA === 0 && seenB !== 0) return 1;
        if (seenB === 0 && seenA !== 0) return -1;
        return seenB - seenA || nameA.localeCompare(nameB);
    }

    function applyFilters() {
        if (!list || !emptyState) {
            return;
        }

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

    function setDetailRefreshEnabled(enabled) {
        if (!detailRefreshButton) {
            return;
        }

        detailRefreshButton.disabled = !enabled;
    }

    async function loadDetail(url, options) {
        const showLoading = !options || options.showLoading !== false;

        if (!url || !detailBody) {
            return;
        }

        currentDetailUrl = url;
        setDetailRefreshEnabled(true);

        if (showLoading) {
            detailBody.innerHTML = loadingHtml;
        }

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
    }

    function bindDetailButtons() {
        liveRegion.querySelectorAll('.as-btn-detail').forEach(function (button) {
            button.addEventListener('click', async function () {
                const url = button.dataset.detailUrl;
                const name = button.dataset.userName || 'User Activity';

                if (!url || !offcanvasEl || !window.bootstrap) {
                    return;
                }

                detailTitle.textContent = name;

                const canvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
                canvas.show();

                await loadDetail(url, { showLoading: true });
            });
        });
    }

    function bindTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        liveRegion.querySelectorAll('[data-bstooltip-toggle="tooltip"]').forEach(function (el) {
            if (!window.bootstrap.Tooltip.getInstance(el)) {
                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function refreshDomRefs() {
        list = document.getElementById('as-list');
        emptyState = document.getElementById('as-empty');
        rows = list ? Array.from(list.querySelectorAll('[data-as-row="true"]')) : [];
        bindDetailButtons();
        bindTooltips();
        applyFilters();
    }

    async function refreshList() {
        if (refreshing) {
            return;
        }

        const url = page.dataset.listUrl;
        if (!url) {
            return;
        }

        refreshing = true;
        if (refreshButton) {
            refreshButton.disabled = true;
        }
        if (refreshIcon) {
            refreshIcon.classList.add('fa-spin');
        }

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to refresh list');
            }

            liveRegion.innerHTML = await response.text();
            refreshDomRefs();
        } catch (error) {
            if (window.console) {
                console.error(error);
            }
        } finally {
            refreshing = false;
            if (refreshButton) {
                refreshButton.disabled = false;
            }
            if (refreshIcon) {
                refreshIcon.classList.remove('fa-spin');
            }
        }
    }

    async function refreshDetail() {
        if (detailRefreshing || !currentDetailUrl) {
            return;
        }

        detailRefreshing = true;
        if (detailRefreshButton) {
            detailRefreshButton.disabled = true;
        }
        if (detailRefreshIcon) {
            detailRefreshIcon.classList.add('fa-spin');
        }

        try {
            await loadDetail(currentDetailUrl, { showLoading: true });
        } finally {
            detailRefreshing = false;
            setDetailRefreshEnabled(Boolean(currentDetailUrl));
            if (detailRefreshIcon) {
                detailRefreshIcon.classList.remove('fa-spin');
            }
        }
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

    if (refreshButton) {
        refreshButton.addEventListener('click', refreshList);
    }

    if (detailRefreshButton) {
        detailRefreshButton.addEventListener('click', refreshDetail);
    }

    const resetLogsButton = document.getElementById('as-reset-logs');
    const resetLogsForm = document.getElementById('as-reset-logs-form');
    const resetPasswordInput = document.getElementById('as-reset-password-input');

    if (resetLogsButton && resetLogsForm && resetPasswordInput && window.Swal) {
        resetLogsButton.addEventListener('click', function () {
            Swal.fire({
                title: 'Reset activity logs?',
                html: 'This permanently deletes all activity history, resets log IDs to start from 1, and logs out every user including you.',
                icon: 'warning',
                input: 'password',
                inputLabel: 'Reset password',
                inputPlaceholder: 'Enter reset password',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocomplete: 'off',
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, reset!',
                cancelButtonText: 'Cancel',
                preConfirm: function (password) {
                    if (!password) {
                        Swal.showValidationMessage('Reset password is required.');
                        return false;
                    }

                    return password;
                },
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                resetPasswordInput.value = result.value;
                resetLogsForm.submit();
            });
        });
    }

    if (offcanvasEl) {
        offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
            currentDetailUrl = null;
            setDetailRefreshEnabled(false);
        });
    }

    refreshDomRefs();
});
