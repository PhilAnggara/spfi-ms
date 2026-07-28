(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function initPageTooltips(scope = document) {
        const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

        tooltipElements.forEach((el) => {
            if (!window.bootstrap || !window.bootstrap.Tooltip) {
                return;
            }

            if (window.bootstrap.Tooltip.getInstance(el)) {
                return;
            }

            new window.bootstrap.Tooltip(el);
        });
    }

    function getCanvassingSelectionState() {
        if (!window.__canvassingSelectionState) {
            window.__canvassingSelectionState = {
                filterKey: null,
                selectedItems: new Map(),
            };
        }

        return window.__canvassingSelectionState;
    }

    function buildCanvassingFilterKey(url) {
        const parsedUrl = new URL(url, window.location.origin);
        parsedUrl.searchParams.delete('page');

        const sortedParams = new URLSearchParams(
            Array.from(parsedUrl.searchParams.entries()).sort(([left], [right]) => left.localeCompare(right))
        );

        const queryString = sortedParams.toString();

        return queryString === ''
            ? parsedUrl.pathname
            : `${parsedUrl.pathname}?${queryString}`;
    }

    function syncCanvassingSelectionScope(url) {
        const selectionState = getCanvassingSelectionState();
        const nextFilterKey = buildCanvassingFilterKey(url);

        if (selectionState.filterKey !== null && selectionState.filterKey !== nextFilterKey) {
            selectionState.selectedItems.clear();
        }

        selectionState.filterKey = nextFilterKey;

        return selectionState;
    }

    function readCheckboxMeta(input) {
        return {
            id: Number(input.value),
            prsNumber: input.dataset.prsNumber || '-',
            itemCode: input.dataset.itemCode || '-',
            itemName: input.dataset.itemName || '-',
        };
    }

    function updateSelectionToolbar(scope = document) {
        const selectionState = getCanvassingSelectionState();
        const selectedCount = selectionState.selectedItems.size;
        const selectedBadge = document.getElementById('canvassing-selected-count');
        const printSelectedButton = document.getElementById('canvassing-print-selected-btn');
        const headerCheckbox = scope.querySelector('#canvassing-select-page-checkbox');
        const enabledCheckboxes = Array.from(scope.querySelectorAll('.canvassing-select-checkbox:not(:disabled)'));

        if (selectedBadge) {
            selectedBadge.textContent = `${selectedCount} selected`;
        }

        if (printSelectedButton) {
            printSelectedButton.disabled = selectedCount === 0;
        }

        if (!headerCheckbox) {
            return;
        }

        if (enabledCheckboxes.length === 0) {
            headerCheckbox.checked = false;
            headerCheckbox.indeterminate = false;
            headerCheckbox.disabled = true;
            return;
        }

        headerCheckbox.disabled = false;

        const selectedOnPage = enabledCheckboxes.filter((input) => (
            selectionState.selectedItems.has(Number(input.value))
        )).length;

        headerCheckbox.checked = selectedOnPage === enabledCheckboxes.length;
        headerCheckbox.indeterminate = selectedOnPage > 0 && selectedOnPage < enabledCheckboxes.length;
    }

    function syncCurrentPageCheckboxes(scope = document) {
        const selectionState = getCanvassingSelectionState();
        const rowCheckboxes = Array.from(scope.querySelectorAll('.canvassing-select-checkbox'));

        rowCheckboxes.forEach((input) => {
            if (input.disabled) {
                input.checked = false;
                return;
            }

            input.checked = selectionState.selectedItems.has(Number(input.value));
        });

        updateSelectionToolbar(scope);
    }

    function openPrintModal() {
        const selectionState = getCanvassingSelectionState();
        const printForm = document.getElementById('canvassing-print-form');
        const hiddenInputs = document.getElementById('canvassing-print-hidden-inputs');
        const summary = document.getElementById('canvassing-print-summary');
        const printList = document.getElementById('canvassing-print-list');
        const printModalEl = document.getElementById('canvassing-print-modal');

        if (!printForm || !hiddenInputs || !summary || !printList || !printModalEl) {
            return;
        }

        const selectedItems = Array.from(selectionState.selectedItems.values())
            .sort((left, right) => {
                const prsCompare = String(left.prsNumber).localeCompare(String(right.prsNumber));
                if (prsCompare !== 0) {
                    return prsCompare;
                }

                return String(left.itemCode).localeCompare(String(right.itemCode));
            });

        if (selectedItems.length === 0) {
            return;
        }

        summary.textContent = `Selected items: ${selectedItems.length}`;
        hiddenInputs.innerHTML = '';
        printList.innerHTML = '';

        selectedItems.forEach((item) => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'prs_item_ids[]';
            hiddenInput.value = String(item.id);
            hiddenInputs.appendChild(hiddenInput);

            const listItem = document.createElement('li');
            listItem.className = 'canvassing-print-list-item';
            listItem.innerHTML = `
                <div class="fw-semibold">${item.prsNumber} — ${item.itemCode}</div>
                <small class="text-muted">${item.itemName}</small>
            `;
            printList.appendChild(listItem);
        });

        const printModal = window.bootstrap?.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(printModalEl)
            : null;

        printModal?.show();
    }

    function initReportSelection(scope = document) {
        syncCanvassingSelectionScope(window.location.href);

        const selectionState = getCanvassingSelectionState();
        const rowCheckboxes = Array.from(scope.querySelectorAll('.canvassing-select-checkbox'));
        const headerCheckbox = scope.querySelector('#canvassing-select-page-checkbox');
        const clearButton = document.getElementById('canvassing-clear-selection-btn');
        const printSelectedButton = document.getElementById('canvassing-print-selected-btn');

        syncCurrentPageCheckboxes(scope);

        rowCheckboxes.forEach((input) => {
            input.addEventListener('change', function () {
                if (this.disabled) {
                    return;
                }

                const meta = readCheckboxMeta(this);
                if (!Number.isInteger(meta.id) || meta.id <= 0) {
                    return;
                }

                if (this.checked) {
                    selectionState.selectedItems.set(meta.id, meta);
                } else {
                    selectionState.selectedItems.delete(meta.id);
                }

                updateSelectionToolbar(scope);
            });
        });

        const selectCells = Array.from(scope.querySelectorAll('.canvassing-select-cell'));
        selectCells.forEach((cell) => {
            cell.addEventListener('click', function (event) {
                if (event.target.closest('input, button, a, label')) {
                    return;
                }

                const checkbox = cell.querySelector('.canvassing-select-checkbox');
                if (!checkbox || checkbox.disabled) {
                    return;
                }

                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        headerCheckbox?.addEventListener('change', function () {
            const enabledCheckboxes = Array.from(scope.querySelectorAll('.canvassing-select-checkbox:not(:disabled)'));

            enabledCheckboxes.forEach((input) => {
                const meta = readCheckboxMeta(input);

                if (this.checked) {
                    selectionState.selectedItems.set(meta.id, meta);
                    input.checked = true;
                } else {
                    selectionState.selectedItems.delete(meta.id);
                    input.checked = false;
                }
            });

            updateSelectionToolbar(scope);
        });

        if (clearButton && !clearButton.dataset.bound) {
            clearButton.dataset.bound = '1';
            clearButton.addEventListener('click', function () {
                selectionState.selectedItems.clear();
                syncCurrentPageCheckboxes(document.getElementById('canvassing-page-results') || document);
            });
        }

        if (printSelectedButton && !printSelectedButton.dataset.bound) {
            printSelectedButton.dataset.bound = '1';
            printSelectedButton.addEventListener('click', function () {
                openPrintModal();
            });
        }
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('canvassing-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
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
            const newResults = doc.querySelector('#canvassing-page-results');
            const currentResults = document.querySelector('#canvassing-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                window.location.href = normalizedUrl;
                return;
            }

            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            syncCanvassingSelectionScope(normalizedUrl);
            initPageTooltips(newResults);
            initReportSelection(newResults);

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

    window.canvassingReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#canvassing-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });

    initPageTooltips(document);
    initReportSelection(document.getElementById('canvassing-page-results') || document);
})();
