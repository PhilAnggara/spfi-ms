(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;
    let encodeSubmitting = false;
    let encodeListNeedsRefresh = false;

    function setLoading(active) {
        const loadingEl = document.getElementById('inventory-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function getQueueFilterParams() {
        const params = new URLSearchParams();
        const docType = document.getElementById('filter-inventory-doc-type')?.value || 'all';
        const category = document.getElementById('filter-inventory-category')?.value || '';
        const keyword = document.getElementById('filter-inventory-keyword')?.value || '';
        const dateFrom = document.getElementById('filter-inventory-date-from')?.value || '';
        const dateTo = document.getElementById('filter-inventory-date-to')?.value || '';

        if (docType) {
            params.set('queue_doc_type', docType);
        }
        if (category) {
            params.set('queue_category_id', category);
        }
        if (keyword) {
            params.set('queue_keyword', keyword);
        }
        if (dateFrom) {
            params.set('queue_date_from', dateFrom);
        }
        if (dateTo) {
            params.set('queue_date_to', dateTo);
        }

        return params;
    }

    function syncQueueHiddenFields(form) {
        if (!form) {
            return;
        }

        const docType = document.getElementById('filter-inventory-doc-type')?.value || 'all';
        const category = document.getElementById('filter-inventory-category')?.value || '';
        const keyword = document.getElementById('filter-inventory-keyword')?.value || '';
        const dateFrom = document.getElementById('filter-inventory-date-from')?.value || '';
        const dateTo = document.getElementById('filter-inventory-date-to')?.value || '';

        const docTypeInput = form.querySelector('[name="queue_doc_type"]');
        const categoryInput = form.querySelector('[name="queue_category_id"]');
        const keywordInput = form.querySelector('[name="queue_keyword"]');
        const dateFromInput = form.querySelector('[name="queue_date_from"]');
        const dateToInput = form.querySelector('[name="queue_date_to"]');

        if (docTypeInput) {
            docTypeInput.value = docType;
        }
        if (categoryInput) {
            categoryInput.value = category;
        }
        if (keywordInput) {
            keywordInput.value = keyword;
        }
        if (dateFromInput) {
            dateFromInput.value = dateFrom;
        }
        if (dateToInput) {
            dateToInput.value = dateTo;
        }
    }

    function updateSummaryBadges(stats) {
        if (!stats) {
            return;
        }

        const pendingEl = document.querySelector('[data-summary-pending]');
        const encodedEl = document.querySelector('[data-summary-encoded]');
        const totalEl = document.querySelector('[data-summary-total]');

        if (pendingEl && stats.pending !== undefined) {
            pendingEl.textContent = `Pending ${Number(stats.pending || 0).toLocaleString()}`;
        }
        if (encodedEl && stats.encoded !== undefined) {
            encodedEl.textContent = `Encoded ${Number(stats.encoded || 0).toLocaleString()}`;
        }
        if (totalEl && stats.pending !== undefined && stats.encoded !== undefined) {
            totalEl.textContent = `Total ${Number(stats.pending + stats.encoded).toLocaleString()}`;
        } else if (totalEl && stats.pending !== undefined && stats.encoded === undefined) {
            const encodedText = encodedEl?.textContent || '';
            const encodedMatch = encodedText.match(/(\d[\d,]*)/);
            const encodedCount = encodedMatch ? Number(encodedMatch[1].replace(/,/g, '')) : 0;
            totalEl.textContent = `Total ${Number(stats.pending + encodedCount).toLocaleString()}`;
        }
    }

    function showEncodeToast(message) {
        const container = document.getElementById('inventory-encode-toast-container');
        if (!container) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'inv-encode-toast';
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 2800);
    }

    function showEncodeErrors(errors) {
        const modalBody = document.getElementById('inventory-encode-body');
        const errorBox = modalBody?.querySelector('.inv-encode-errors');
        if (!errorBox) {
            return;
        }

        const messages = [];
        if (typeof errors === 'object') {
            Object.values(errors).forEach((value) => {
                if (Array.isArray(value)) {
                    messages.push(...value);
                } else if (value) {
                    messages.push(String(value));
                }
            });
        }

        if (messages.length === 0) {
            errorBox.classList.add('d-none');
            errorBox.innerHTML = '';
            return;
        }

        errorBox.classList.remove('d-none');
        errorBox.innerHTML = `<ul class="mb-0 ps-3">${messages.map((msg) => `<li>${msg}</li>`).join('')}</ul>`;
        errorBox.scrollIntoView({ block: 'nearest' });
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

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-bulk-payload]').forEach((node) => node.remove());

            Array.from(root.querySelectorAll('.bulk-encode-checkbox:checked')).forEach((checkbox, index) => {
                const fields = {
                    doc_type: checkbox.dataset.bulkDocType,
                    source_id: checkbox.dataset.bulkSourceId,
                    category_id: checkbox.dataset.bulkCategoryId,
                };

                Object.entries(fields).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `documents[${index}][${key}]`;
                    input.value = value ?? '';
                    input.setAttribute('data-bulk-payload', '1');
                    form.appendChild(input);
                });
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

    function initEncodeModal() {
        const modalEl = document.getElementById('inventory-encode-modal');
        const modalBody = document.getElementById('inventory-encode-body');
        const modalFooter = document.getElementById('inv-encode-modal-footer');
        const modalTitle = document.getElementById('inventory-encode-modal-title');
        const modalSubtitle = document.getElementById('inventory-encode-modal-subtitle');
        const submitNextBtn = document.getElementById('inv-encode-submit-next');
        const submitCloseBtn = document.getElementById('inv-encode-submit-close');
        const nextUpEl = document.getElementById('inv-encode-next-up');
        const nextTypeEl = document.getElementById('inv-encode-next-type');
        const nextNumberEl = document.getElementById('inv-encode-next-number');
        const nextMetaEl = document.getElementById('inv-encode-next-meta');
        const pageContainer = document.getElementById('inventory-page-container');

        if (!modalEl || !modalBody || !window.bootstrap) {
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function formatNextMeta(next) {
            const parts = [];

            if (next.category_name) {
                parts.push(next.category_name);
            }
            if (next.doc_date_label) {
                parts.push(next.doc_date_label);
            }
            if (next.party_name) {
                parts.push(next.party_name);
            }
            if (next.amount_label) {
                parts.push(next.amount_label);
            }

            return parts.join(' · ');
        }

        function updateNextPreview(next) {
            if (!nextUpEl) {
                return;
            }

            if (!next || !next.doc_number) {
                nextUpEl.classList.add('d-none');
                if (nextTypeEl) {
                    nextTypeEl.textContent = '';
                }
                if (nextNumberEl) {
                    nextNumberEl.textContent = '';
                }
                if (nextMetaEl) {
                    nextMetaEl.textContent = '';
                }
                return;
            }

            nextUpEl.classList.remove('d-none');
            if (nextTypeEl) {
                nextTypeEl.textContent = next.doc_type || '';
            }
            if (nextNumberEl) {
                nextNumberEl.textContent = next.doc_number || '';
            }
            if (nextMetaEl) {
                nextMetaEl.textContent = formatNextMeta(next);
            }
        }

        function syncNextPreviewFromPanel(panel) {
            if (!panel?.dataset.nextDocument) {
                updateNextPreview(null);
                return;
            }

            try {
                updateNextPreview(JSON.parse(panel.dataset.nextDocument));
            } catch (_) {
                updateNextPreview(null);
            }
        }

        function setEncodeFooterVisible(visible, canEncode) {
            if (!modalFooter) {
                return;
            }
            modalFooter.classList.toggle('d-none', !visible);
            if (submitNextBtn) {
                submitNextBtn.disabled = !canEncode || encodeSubmitting;
            }
            if (submitCloseBtn) {
                submitCloseBtn.disabled = !canEncode || encodeSubmitting;
            }
        }

        function prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        function wait(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        }

        function ensureBodyStage() {
            let stage = modalBody.querySelector('[data-inv-encode-stage]');
            if (!stage) {
                stage = document.createElement('div');
                stage.className = 'inv-encode-body-stage';
                stage.dataset.invEncodeStage = '1';
                while (modalBody.firstChild) {
                    stage.appendChild(modalBody.firstChild);
                }
                modalBody.appendChild(stage);
            }
            return stage;
        }

        function ensureBodyOverlay() {
            let overlay = modalBody.querySelector('[data-inv-encode-overlay]');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'inv-encode-body-overlay';
                overlay.dataset.invEncodeOverlay = '1';
                overlay.innerHTML = `
                    <div class="inv-encode-body-overlay-card">
                        <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                        <span>Loading next document...</span>
                    </div>
                `;
                modalBody.appendChild(overlay);
            }
            return overlay;
        }

        function setBodyOverlayVisible(visible) {
            const overlay = ensureBodyOverlay();
            overlay.classList.toggle('is-visible', visible);
        }

        async function swapEncodeBody(html, { animate = false, skipLeave = false } = {}) {
            const reduceMotion = prefersReducedMotion();
            const stage = ensureBodyStage();

            if (animate && !reduceMotion && !skipLeave) {
                stage.classList.remove('is-entering');
                stage.classList.add('is-leaving');
                await wait(500);
            }

            stage.classList.remove('is-leaving', 'is-entering');
            stage.innerHTML = html;
            stage.scrollTop = 0;

            if (animate && !reduceMotion) {
                void stage.offsetWidth;
                stage.classList.add('is-entering');
                await wait(520);
                stage.classList.remove('is-entering');
            }
        }

        async function loadEncodePanel(url, title, options = {}) {
            const animate = Boolean(options.animate);
            const keepChrome = Boolean(options.keepChrome);
            const useTransition = animate || keepChrome;
            const reduceMotion = prefersReducedMotion();
            const fetchUrl = new URL(url, window.location.origin);
            const queueParams = getQueueFilterParams();
            queueParams.set('modal', '1');
            queueParams.forEach((value, key) => fetchUrl.searchParams.set(key, value));

            showEncodeErrors([]);
            const stage = ensureBodyStage();

            if (useTransition) {
                setBodyOverlayVisible(true);
                setEncodeFooterVisible(true, false);
            } else {
                setBodyOverlayVisible(false);
                await swapEncodeBody(`
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                        <div class="mt-2">Loading document...</div>
                    </div>
                `);
                setEncodeFooterVisible(false, false);
            }

            if (modalTitle) {
                modalTitle.textContent = title || 'Encode Inventory';
            }
            if (modalSubtitle) {
                modalSubtitle.textContent = 'Review lines and encode to accounting inventory';
            }

            try {
                const leavePromise = (useTransition && !reduceMotion)
                    ? (async () => {
                        stage.classList.remove('is-entering');
                        stage.classList.add('is-leaving');
                        await wait(500);
                    })()
                    : Promise.resolve();

                const fetchPromise = fetch(fetchUrl.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                    credentials: 'same-origin',
                });

                const [response] = await Promise.all([fetchPromise, leavePromise]);

                if (!response.ok) {
                    throw new Error('Failed to load encode panel');
                }

                const html = await response.text();
                await swapEncodeBody(html, {
                    animate: useTransition,
                    skipLeave: true,
                });
                setBodyOverlayVisible(false);

                const panel = modalBody.querySelector('[data-inventory-encode-panel]');
                const form = modalBody.querySelector('#inventory-encode-form');
                const canEncode = Boolean(form);

                if (panel) {
                    syncNextPreviewFromPanel(panel);
                } else {
                    updateNextPreview(null);
                }

                syncQueueHiddenFields(form);

                if (canEncode && window.initAccountingInventoryEncodeForm) {
                    window.initAccountingInventoryEncodeForm(modalBody);
                    setEncodeFooterVisible(true, true);
                } else {
                    setEncodeFooterVisible(false, false);
                }
            } catch (_) {
                setBodyOverlayVisible(false);
                await swapEncodeBody('<div class="alert alert-danger mb-0">Unable to load encode panel. Please try again.</div>');
                setEncodeFooterVisible(false, false);
            }
        }

        async function submitEncode(closeAfter) {
            const form = modalBody.querySelector('#inventory-encode-form');
            if (!form || encodeSubmitting) {
                return;
            }

            encodeSubmitting = true;
            setEncodeFooterVisible(true, false);
            syncQueueHiddenFields(form);

            const formData = new FormData(form);
            window.AccountingInventoryMoney?.writeNormalizedFormData(form, formData);
            formData.append('close_after', closeAfter ? '1' : '0');

            const encodeUrl = form.dataset.encodeUrl || form.getAttribute('action');

            try {
                const response = await fetch(encodeUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: formData,
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (response.status === 422 && data.errors) {
                        showEncodeErrors(data.errors);
                    } else {
                        showEncodeErrors({ message: data.message || 'Encode failed. Please review the form.' });
                    }
                    setEncodeFooterVisible(true, true);
                    encodeSubmitting = false;
                    return;
                }

                updateSummaryBadges(data.queue_stats);
                encodeListNeedsRefresh = true;

                if (modalFooter) {
                    modalFooter.classList.add('is-success-flash');
                    setTimeout(() => modalFooter.classList.remove('is-success-flash'), 700);
                }

                const encodedLabel = `${data.encoded?.doc_type || ''} ${data.encoded?.doc_number || ''}`.trim();
                const nextLabel = data.next ? `${data.next.doc_type} ${data.next.doc_number}` : null;
                showEncodeToast(nextLabel ? `Encoded ${encodedLabel} · Next: ${nextLabel}` : `Encoded ${encodedLabel}`);
                updateNextPreview(data.next);

                // Avoid reloading the full queue UNION on every Encode & Next; refresh once when closing.
                if (closeAfter || !data.next?.url) {
                    if (encodeListNeedsRefresh) {
                        replacePageContent(window.location.href, false);
                        encodeListNeedsRefresh = false;
                    }
                    updateNextPreview(null);
                    setEncodeFooterVisible(false, false);
                    encodeSubmitting = false;
                    modal.hide();
                } else {
                    encodeSubmitting = false;
                    await loadEncodePanel(data.next.url, data.next.title, {
                        animate: true,
                        keepChrome: true,
                    });
                }
            } catch (_) {
                showEncodeErrors({ message: 'Network error while encoding. Please try again.' });
                setEncodeFooterVisible(true, true);
                encodeSubmitting = false;
            }
        }

        function openEncodeModal(url, title) {
            modal.show();
            loadEncodePanel(url, title, { animate: false, keepChrome: false });
        }

        submitNextBtn?.addEventListener('click', () => submitEncode(false));
        submitCloseBtn?.addEventListener('click', () => submitEncode(true));

        modalEl.addEventListener('hidden.bs.modal', () => {
            if (encodeListNeedsRefresh) {
                replacePageContent(window.location.href, false);
                encodeListNeedsRefresh = false;
            }
            setBodyOverlayVisible(false);
            const stage = ensureBodyStage();
            stage.classList.remove('is-leaving', 'is-entering');
            stage.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border text-primary" role="status" aria-hidden="true"></div><div class="mt-2">Loading document...</div></div>';
            setEncodeFooterVisible(false, false);
            updateNextPreview(null);
        });

        pageContainer?.addEventListener('click', (event) => {
            const encodeLink = event.target.closest('[data-inventory-encode-open]');
            if (encodeLink) {
                event.preventDefault();
                openEncodeModal(encodeLink.href, encodeLink.dataset.title);
                return;
            }

            const startBtn = event.target.closest('#inventory-start-encode-btn');
            if (startBtn) {
                event.preventDefault();
                openEncodeModal(startBtn.dataset.openUrl, startBtn.dataset.title);
            }
        });
    }

    initManualCreateModal();
    initEncodeModal();
})();
