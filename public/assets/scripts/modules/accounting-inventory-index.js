(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function setLoading(active) {
        const loadingEl = document.getElementById('inventory-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function initBulkEncodeControls(root) {
        const form = root.querySelector('#bulk-encode-form');
        const selectAll = root.querySelector('#bulk-select-all');
        const checkboxes = root.querySelectorAll('.bulk-encode-checkbox');
        const submit = root.querySelector('#bulk-encode-submit');

        if (!form || !submit) {
            return;
        }

        const updateSubmit = () => {
            const selected = root.querySelectorAll('.bulk-encode-checkbox:checked').length;
            submit.disabled = selected === 0;
        };

        selectAll?.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateSubmit();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (selectAll) {
                    const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every((item) => item.checked);
                    selectAll.checked = allChecked;
                }
                updateSubmit();
            });
        });

        updateSubmit();
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
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                window.location.href = normalizedUrl;
                return;
            }

            const html = await response.text();
            const currentResults = document.querySelector('#inventory-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!currentResults) {
                window.location.href = normalizedUrl;
                return;
            }

            currentResults.innerHTML = html;
            initBulkEncodeControls(currentResults);

            const statusSelect = document.getElementById('filter-inventory-status');
            if (statusSelect) {
                currentResults.querySelectorAll('.po-status-chip').forEach((chip) => {
                    chip.classList.toggle('active', chip.dataset.statusValue === statusSelect.value);
                });
            }

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }
        } catch (_) {
            window.location.href = normalizedUrl;
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

    window.inventoryReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#inventory-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });

    const initialResults = document.querySelector('#inventory-page-results');
    if (initialResults) {
        initBulkEncodeControls(initialResults);
    }

    function initManualCreateModal() {
        const modalEl = document.getElementById('inventory-manual-create-modal');
        const modalBody = document.getElementById('inventory-manual-create-body');
        const openBtn = document.getElementById('inventory-manual-create-btn');

        if (!modalEl || !modalBody || !openBtn || !window.bootstrap) {
            return;
        }

        const loadForm = async () => {
            const categorySelect = document.getElementById('filter-inventory-category');
            const categoryId = categorySelect?.value || '';
            const url = new URL(openBtn.dataset.createUrl || '', window.location.origin);

            if (categoryId) {
                url.searchParams.set('category_id', categoryId);
            }
            url.searchParams.set('modal', '1');

            modalBody.innerHTML = '<div class="text-center text-muted py-5">Loading...</div>';

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Failed to load create form');
                }

                modalBody.innerHTML = await response.text();

                if (window.initAccountingInventoryCreateForm) {
                    window.initAccountingInventoryCreateForm(modalBody);
                }
            } catch (_) {
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">Unable to load create form. Please try again.</div>';
            }
        };

        modalEl.addEventListener('show.bs.modal', () => {
            loadForm();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            modalBody.innerHTML = '<div class="text-center text-muted py-5">Loading...</div>';
        });
    }

    initManualCreateModal();
})();
