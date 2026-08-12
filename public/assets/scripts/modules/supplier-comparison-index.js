(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;
    let filterDebounceTimer = null;

    function clearFilterDebounce() {
        if (filterDebounceTimer) {
            clearTimeout(filterDebounceTimer);
            filterDebounceTimer = null;
        }
    }

    function setQueryParam(searchParams, key, value) {
        const normalizedValue = String(value || '').trim();

        if (normalizedValue === '') {
            searchParams.delete(key);
            return;
        }

        searchParams.set(key, normalizedValue);
    }

    function disposeBootstrapInstances(scope) {
        if (!window.bootstrap || !scope) {
            return;
        }

        if (window.bootstrap.Modal) {
            scope.querySelectorAll('.modal').forEach((el) => {
                window.bootstrap.Modal.getInstance(el)?.dispose();
            });
        }
    }

    function syncHighlightFromUrl(url) {
        const container = document.getElementById('supplier-comparison-page-container');
        if (!container) {
            return;
        }

        const prsItemId = new URL(url, window.location.origin).searchParams.get('prs_item') || '';
        container.dataset.highlightPrsItemId = prsItemId;
    }

    function highlightRequestedPrsItem() {
        const container = document.getElementById('supplier-comparison-page-container');
        const prsItemId = container?.dataset.highlightPrsItemId;
        if (!prsItemId) {
            return;
        }

        const card = document.getElementById(`supplier-comparison-item-${prsItemId}`);
        if (!card) {
            return;
        }

        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        card.classList.add('sc-highlighted');

        window.setTimeout(() => {
            card.classList.remove('sc-highlighted');
        }, 3000);

        container.dataset.highlightPrsItemId = '';
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('supplier-comparison-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function updateKeywordFromUrl(url) {
        const keywordInput = document.getElementById('supplier-comparison-keyword');
        if (!keywordInput) {
            return;
        }

        keywordInput.value = new URL(url, window.location.origin).searchParams.get('keyword') || '';
    }

    function updateSelectionState(form) {
        const checkedRadio = form.querySelector('input[name="canvassing_item_id"]:checked:not(:disabled)');
        const saveButton = form.querySelector('[data-save-selection-button]');

        if (saveButton) {
            saveButton.disabled = !checkedRadio;
        }

        form.querySelectorAll('[data-supplier-row]').forEach((row) => {
            const rowRadio = row.querySelector('input[name="canvassing_item_id"]');
            const isSelected = Boolean(rowRadio && rowRadio.checked);
            const badge = row.querySelector('[data-selection-badge]');

            row.classList.toggle('sc-selected-row', isSelected);

            if (badge) {
                badge.classList.toggle('d-none', !isSelected);
            }
        });
    }

    function initSelectionForms(scope = document) {
        scope.querySelectorAll('[data-selection-form]').forEach(updateSelectionState);
    }

    function selectSupplierRow(row) {
        const radio = row.querySelector('input[name="canvassing_item_id"]');

        if (!radio || radio.disabled) {
            return;
        }

        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
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
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                window.location.href = normalizedUrl;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#supplier-comparison-page-results');
            const currentResults = document.querySelector('#supplier-comparison-page-results');
            const newSummary = doc.querySelector('#supplier-comparison-total-summary');
            const currentSummary = document.querySelector('#supplier-comparison-total-summary');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                window.location.href = normalizedUrl;
                return;
            }

            disposeBootstrapInstances(currentResults);
            currentResults.replaceWith(newResults);
            if (newSummary && currentSummary) {
                currentSummary.textContent = newSummary.textContent;
            }

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            syncHighlightFromUrl(normalizedUrl);
            initSelectionForms(newResults);
            highlightRequestedPrsItem();

            if (window.feather && typeof window.feather.replace === 'function') {
                window.feather.replace();
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

    function initSupplierComparisonFilters() {
        const filterForm = document.getElementById('supplier-comparison-filter-form');
        if (!filterForm || filterForm.dataset.filterInitialized === '1') {
            return;
        }

        filterForm.dataset.filterInitialized = '1';

        const keywordInput = document.getElementById('supplier-comparison-keyword');
        const resetButton = document.getElementById('reset-supplier-comparison-filter');

        const buildFilterUrl = () => {
            const url = new URL(window.location.href);

            setQueryParam(url.searchParams, 'keyword', keywordInput?.value);
            url.searchParams.delete('prs_item');
            url.searchParams.delete('page');

            return url.toString();
        };

        const applyServerFilter = (useDebounce = false) => {
            const doRequest = () => {
                filterDebounceTimer = null;
                replacePageContent(buildFilterUrl(), true);
            };

            if (!useDebounce) {
                clearFilterDebounce();
                doRequest();
                return;
            }

            clearFilterDebounce();
            filterDebounceTimer = setTimeout(doRequest, 350);
        };

        if (keywordInput) {
            keywordInput.addEventListener('input', function () {
                applyServerFilter(true);
            });

            keywordInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyServerFilter(false);
                }
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                if (keywordInput) {
                    keywordInput.value = '';
                }

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

            clearFilterDebounce();
        });

        window.addEventListener('pagehide', clearFilterDebounce);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSupplierComparisonFilters();
        initSelectionForms(document);
        syncHighlightFromUrl(window.location.href);
        highlightRequestedPrsItem();
    });

    document.addEventListener('click', function (event) {
        const paginationLink = event.target.closest('#supplier-comparison-page-container a[href*="page="]');
        if (paginationLink) {
            event.preventDefault();
            clearFilterDebounce();
            replacePageContent(paginationLink.href, true);
            return;
        }

        const row = event.target.closest('[data-supplier-row]');

        if (!row || event.target.closest('input, button, a, textarea, select, label')) {
            return;
        }

        selectSupplierRow(row);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        if (event.target.closest('input, button, a, textarea, select, label')) {
            return;
        }

        const row = event.target.closest('[data-supplier-row]');
        if (!row) {
            return;
        }

        event.preventDefault();
        selectSupplierRow(row);
    });

    document.addEventListener('change', function (event) {
        const radio = event.target.closest('input[name="canvassing_item_id"]');
        if (!radio) {
            return;
        }

        const form = radio.closest('[data-selection-form]');
        if (form) {
            updateSelectionState(form);
        }
    });

    window.addEventListener('popstate', function () {
        clearFilterDebounce();
        updateKeywordFromUrl(window.location.href);
        replacePageContent(window.location.href, false);
    });

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function showAlert(icon, title) {
        if (typeof window.Swal === 'undefined') {
            window.alert(title);
            return;
        }

        window.Swal.fire({
            icon,
            title,
            timer: 5000,
            showConfirmButton: icon !== 'success',
        });
    }

    function setCardSaving(card, active) {
        if (!card) {
            return;
        }

        const loadingEl = card.querySelector('[data-card-loading]');
        card.classList.toggle('is-saving', active);

        if (loadingEl) {
            loadingEl.classList.toggle('d-none', !active);
            loadingEl.setAttribute('aria-hidden', active ? 'false' : 'true');
        }

        card.querySelectorAll('[data-save-selection-button], [data-reject-button]').forEach((button) => {
            if (active) {
                button.dataset.wasDisabled = button.disabled ? '1' : '0';
                button.disabled = true;
                return;
            }

            if (button.dataset.wasDisabled === '1') {
                button.disabled = true;
            } else if (button.hasAttribute('data-save-selection-button')) {
                const form = card.querySelector('[data-selection-form]');
                if (form) {
                    updateSelectionState(form);
                }
            } else {
                button.disabled = false;
            }

            delete button.dataset.wasDisabled;
        });
    }

    function closeReasonModal(prsItemId) {
        const modalEl = document.getElementById('reasonModal-' + prsItemId);
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }

        window.bootstrap.Modal.getInstance(modalEl)?.hide();
    }

    function applySelectionSuccess(card, form, payload) {
        const supplierName = payload.selected_supplier_name || 'Not selected';
        const supplierNameEl = card.querySelector('[data-selected-supplier-name]');
        if (supplierNameEl) {
            supplierNameEl.textContent = supplierName;
            supplierNameEl.classList.toggle('text-primary', Boolean(payload.selected_supplier_name));
            supplierNameEl.classList.toggle('text-muted', !payload.selected_supplier_name);
        }

        const exportLink = card.querySelector('[data-export-pdf-link]');
        if (exportLink) {
            if (payload.report_url) {
                exportLink.setAttribute('href', payload.report_url);
            }
            exportLink.classList.remove('d-none');
        }

        const reasonText = document.getElementById('reasonText-' + (payload.prs_item_id || card.dataset.prsItemId));
        if (reasonText && Object.prototype.hasOwnProperty.call(payload, 'selection_reason')) {
            reasonText.value = payload.selection_reason || '';
        }

        updateSelectionState(form);
    }

    function firstValidationMessage(payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }

        const errors = payload.errors;
        if (!errors || typeof errors !== 'object') {
            return null;
        }

        for (const key of Object.keys(errors)) {
            const value = errors[key];
            if (Array.isArray(value) && value[0]) {
                return String(value[0]);
            }
            if (typeof value === 'string' && value !== '') {
                return value;
            }
        }

        return null;
    }

    window.supplierComparisonReplacePageContent = replacePageContent;

    window.submitWithReason = async function (prsItemId) {
        const form = document.getElementById('form-' + prsItemId);
        const reasonInput = document.getElementById('reason-' + prsItemId);
        const reasonText = document.getElementById('reasonText-' + prsItemId);
        const card = document.getElementById('supplier-comparison-item-' + prsItemId);
        const modalSubmit = document.querySelector('#reasonModal-' + prsItemId + ' [data-reason-submit]');

        if (!form || !reasonInput || !reasonText || !card) {
            return;
        }

        const checkedRadio = form.querySelector('input[name="canvassing_item_id"]:checked:not(:disabled)');
        if (!checkedRadio) {
            updateSelectionState(form);
            return;
        }

        reasonInput.value = reasonText.value;

        const selectUrl = form.getAttribute('data-select-url') || form.getAttribute('action');
        if (!selectUrl) {
            form.submit();
            return;
        }

        const body = new FormData(form);
        body.set('canvassing_item_id', checkedRadio.value);
        body.set('selection_reason', reasonText.value);

        setCardSaving(card, true);
        if (modalSubmit) {
            modalSubmit.disabled = true;
        }

        try {
            const response = await fetch(selectUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body,
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (_) {
                payload = null;
            }

            if (!response.ok) {
                showAlert('error', firstValidationMessage(payload) || 'Failed to save supplier selection.');
                return;
            }

            applySelectionSuccess(card, form, payload || {});
            closeReasonModal(prsItemId);
            showAlert('success', payload?.message || 'Supplier selected for this item.');
        } catch (_) {
            showAlert('error', 'Failed to save supplier selection.');
        } finally {
            setCardSaving(card, false);
            if (modalSubmit) {
                modalSubmit.disabled = false;
            }
        }
    };
})();
