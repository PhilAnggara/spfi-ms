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

            updateKeywordFromUrl(normalizedUrl);
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
        replacePageContent(window.location.href, false);
    });

    window.supplierComparisonReplacePageContent = replacePageContent;

    window.submitWithReason = function (prsItemId) {
        const form = document.getElementById('form-' + prsItemId);
        const reasonInput = document.getElementById('reason-' + prsItemId);
        const reasonText = document.getElementById('reasonText-' + prsItemId);

        if (!form || !reasonInput || !reasonText) {
            return;
        }

        const checkedRadio = form.querySelector('input[name="canvassing_item_id"]:checked:not(:disabled)');
        if (!checkedRadio) {
            updateSelectionState(form);
            return;
        }

        reasonInput.value = reasonText.value;
        form.submit();
    };
})();
