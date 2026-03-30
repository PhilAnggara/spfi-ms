(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;
    const pageContainerEl = document.getElementById('employees-page-container');
    const createModalId = pageContainerEl?.dataset.createModalId || null;
    const editModalId = pageContainerEl?.dataset.editModalId || null;

    function initPageTooltips(scope = document) {
        const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

        tooltipElements.forEach((el) => {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                if (window.bootstrap.Tooltip.getInstance(el)) {
                    return;
                }

                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function getEmployeeSelectionState() {
        if (!window.__employeeSelectionState) {
            window.__employeeSelectionState = {
                filterKey: null,
                selectedIds: new Set(),
                selectingAll: false,
            };
        }

        return window.__employeeSelectionState;
    }

    function buildEmployeeFilterKey(url) {
        const parsedUrl = new URL(url, window.location.origin);
        parsedUrl.searchParams.delete('page');
        parsedUrl.searchParams.delete('selection_scope');

        const sortedParams = new URLSearchParams(
            Array.from(parsedUrl.searchParams.entries()).sort(([left], [right]) => left.localeCompare(right))
        );

        const queryString = sortedParams.toString();

        return queryString === ''
            ? parsedUrl.pathname
            : `${parsedUrl.pathname}?${queryString}`;
    }

    function syncEmployeeSelectionScope(url) {
        const selectionState = getEmployeeSelectionState();
        const nextFilterKey = buildEmployeeFilterKey(url);

        if (selectionState.filterKey !== null && selectionState.filterKey !== nextFilterKey) {
            selectionState.selectedIds.clear();
        }

        selectionState.filterKey = nextFilterKey;

        return selectionState;
    }

    async function fetchAllFilteredEmployeeIds() {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.set('selection_scope', 'all_ids');

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });

        if (!response.ok) {
            throw new Error('Failed to load employee selections.');
        }

        const payload = await response.json();

        return {
            ids: Array.isArray(payload.ids)
                ? payload.ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0)
                : [],
            total: Number(payload.total || 0),
        };
    }

    function updatePhotoUi(input, previewSrc = null) {
        const preview = input.dataset.previewTarget
            ? document.getElementById(input.dataset.previewTarget)
            : null;
        const fileNameTarget = input.dataset.fileNameTarget
            ? document.getElementById(input.dataset.fileNameTarget)
            : null;
        const clearButton = input.dataset.clearButtonId
            ? document.getElementById(input.dataset.clearButtonId)
            : null;
        const removeExistingButton = input.dataset.removeExistingButtonId
            ? document.getElementById(input.dataset.removeExistingButtonId)
            : null;
        const removeInput = input.dataset.removeInputId
            ? document.getElementById(input.dataset.removeInputId)
            : null;
        const hasExistingPhoto = input.dataset.hasExistingPhoto === 'true';
        const existingSrc = input.dataset.existingSrc || input.dataset.defaultSrc;
        const defaultSrc = input.dataset.defaultSrc || existingSrc;
        const hasFile = Boolean(input.files && input.files.length);
        const removingExisting = removeInput ? removeInput.value === '1' : false;

        if (preview) {
            preview.src = previewSrc || (hasFile ? preview.src : (removingExisting ? defaultSrc : existingSrc));
        }

        if (fileNameTarget) {
            if (hasFile) {
                fileNameTarget.textContent = input.files[0].name;
            } else if (removingExisting && hasExistingPhoto) {
                fileNameTarget.textContent = 'Current photo will be removed when you save';
            } else {
                fileNameTarget.textContent = input.dataset.existingFileName || 'No file selected';
            }
        }

        if (clearButton) {
            clearButton.classList.toggle('d-none', !hasFile);
        }

        if (removeExistingButton) {
            if (!hasExistingPhoto || hasFile) {
                removeExistingButton.classList.add('d-none');
            } else {
                removeExistingButton.classList.remove('d-none');
                removeExistingButton.innerHTML = removingExisting
                    ? '<i class="fa-light fa-arrow-rotate-left me-1"></i>Undo Remove'
                    : '<i class="fa-light fa-trash-can me-1"></i>Delete Current Photo';
            }
        }
    }

    function bindPhotoEventsOnce() {
        if (window.__employeePhotoEventsBound) {
            return;
        }

        window.__employeePhotoEventsBound = true;

        document.addEventListener('change', function (event) {
            const input = event.target.closest('.employee-photo-input');
            if (!input) {
                return;
            }

            const removeInput = input.dataset.removeInputId
                ? document.getElementById(input.dataset.removeInputId)
                : null;
            if (removeInput) {
                removeInput.value = '0';
            }

            const file = input.files && input.files[0];
            if (!file) {
                updatePhotoUi(input);
                return;
            }

            const reader = new FileReader();
            reader.onload = function (loadEvent) {
                updatePhotoUi(input, loadEvent.target?.result || null);
            };
            reader.readAsDataURL(file);
        });

        document.addEventListener('click', function (event) {
            const clearButton = event.target.closest('[data-photo-clear]');
            if (clearButton) {
                const input = document.getElementById(clearButton.dataset.photoClear);
                if (!input) {
                    return;
                }

                input.value = '';
                updatePhotoUi(input);
                return;
            }

            const removeButton = event.target.closest('[data-photo-remove-existing]');
            if (!removeButton) {
                return;
            }

            const input = document.getElementById(removeButton.dataset.photoRemoveExisting);
            if (!input) {
                return;
            }

            const removeInput = input.dataset.removeInputId
                ? document.getElementById(input.dataset.removeInputId)
                : null;
            if (!removeInput) {
                return;
            }

            removeInput.value = removeInput.value === '1' ? '0' : '1';
            updatePhotoUi(input);
        });
    }

    function initPhotoInputs(scope = document) {
        bindPhotoEventsOnce();

        scope.querySelectorAll('.employee-photo-input').forEach((input) => {
            updatePhotoUi(input);
        });

        scope.querySelectorAll('[data-photo-dropzone]').forEach((dropzone) => {
            if (dropzone.dataset.bound === 'true') {
                return;
            }

            dropzone.dataset.bound = 'true';
            const input = document.getElementById(dropzone.dataset.inputId);
            if (!input) {
                return;
            }

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragover');
                });
            });

            dropzone.addEventListener('drop', function (event) {
                const files = event.dataTransfer?.files;
                if (!files || !files.length) {
                    return;
                }

                try {
                    const dataTransfer = new DataTransfer();
                    Array.from(files).forEach((file) => dataTransfer.items.add(file));
                    input.files = dataTransfer.files;
                } catch (_) {
                    return;
                }

                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    function initIdCardPrint(scope = document) {
        const selectionState = getEmployeeSelectionState();
        const pageContainer = document.getElementById('employees-page-container');
        const rowCheckboxes = Array.from(scope.querySelectorAll('.employee-select-checkbox'));
        const headerCheckbox = scope.querySelector('#employee-select-all-checkbox');
        const selectAllButton = scope.querySelector('#employee-select-all-btn');
        const clearButton = scope.querySelector('#employee-clear-selection-btn');
        const printSelectedButton = scope.querySelector('#employee-print-selected-btn');
        const selectedBadge = scope.querySelector('#employee-selected-count');
        const printForm = document.getElementById('employee-id-card-print-form');
        const hiddenInputs = document.getElementById('employee-id-card-hidden-inputs');
        const summary = document.getElementById('employee-id-card-print-summary');
        const validUntilInput = document.getElementById('employee-id-card-valid-until');
        const printModalEl = document.getElementById('employee-id-card-print-modal');

        if (!printForm || !hiddenInputs || !summary || !validUntilInput || !printModalEl) {
            return;
        }

        const printModal = window.bootstrap?.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(printModalEl)
            : null;

        const selectedIds = () => Array.from(selectionState.selectedIds.values()).sort((left, right) => left - right);

        const getFilteredTotal = () => {
            const total = Number(pageContainer?.dataset.filteredTotal || 0);

            return Number.isFinite(total) ? total : 0;
        };

        const syncCurrentPageCheckboxes = () => {
            rowCheckboxes.forEach((input) => {
                input.checked = selectionState.selectedIds.has(Number(input.value));
            });
        };

        const setSelectionBusy = (active) => {
            selectionState.selectingAll = active;

            headerCheckbox?.toggleAttribute('disabled', active);
            selectAllButton?.toggleAttribute('disabled', active);
            clearButton?.toggleAttribute('disabled', active && selectionState.selectedIds.size === 0);
        };

        const updateSelectionUi = () => {
            syncCurrentPageCheckboxes();

            const selectedCount = selectionState.selectedIds.size;
            const total = getFilteredTotal();

            if (selectedBadge) {
                selectedBadge.textContent = `${selectedCount} selected`;
            }

            if (printSelectedButton) {
                printSelectedButton.disabled = selectedCount === 0;
            }

            if (headerCheckbox) {
                headerCheckbox.checked = total > 0 && selectedCount === total;
                headerCheckbox.indeterminate = selectedCount > 0 && selectedCount < total;
            }
        };

        const clearSelection = () => {
            selectionState.selectedIds.clear();
            updateSelectionUi();
        };

        const selectAllResults = async () => {
            if (selectionState.selectingAll) {
                return;
            }

            setSelectionBusy(true);

            try {
                const payload = await fetchAllFilteredEmployeeIds();
                selectionState.selectedIds = new Set(payload.ids);

                if (pageContainer) {
                    pageContainer.dataset.filteredTotal = String(payload.total);
                }

                updateSelectionUi();
            } catch (_) {
                window.location.href = window.location.href;
            } finally {
                setSelectionBusy(false);
            }
        };

        const openPrintModal = (employeeIds, singleLabel = null) => {
            if (!employeeIds.length) {
                return;
            }

            hiddenInputs.innerHTML = '';
            employeeIds.forEach((employeeId) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'employee_ids[]';
                input.value = String(employeeId);
                hiddenInputs.appendChild(input);
            });

            summary.textContent = singleLabel
                ? `Selected employee: ${singleLabel}`
                : `Selected employees: ${employeeIds.length}`;

            if (!validUntilInput.value) {
                const now = new Date();
                const future = new Date(now);
                // add 3 months
                future.setMonth(future.getMonth() + 3);
                const localIsoDate = new Date(future.getTime() - (future.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
                validUntilInput.value = localIsoDate;
            }

            printModal?.show();
        };

        if (!scope.dataset.idCardSelectionInit) {
            scope.dataset.idCardSelectionInit = '1';

            rowCheckboxes.forEach((input) => {
                input.addEventListener('change', function () {
                    const employeeId = Number(this.value);

                    if (!Number.isInteger(employeeId) || employeeId <= 0) {
                        return;
                    }

                    if (this.checked) {
                        selectionState.selectedIds.add(employeeId);
                    } else {
                        selectionState.selectedIds.delete(employeeId);
                    }

                    updateSelectionUi();
                });
            });

            // Make entire first cell clickable to toggle checkbox (larger hit area)
            const selectCells = Array.from(scope.querySelectorAll('.employee-select-cell'));
            selectCells.forEach((cell) => {
                cell.addEventListener('click', function (e) {
                    // ignore clicks on interactive elements inside the cell
                    if (e.target.closest('input, button, a, label')) {
                        return;
                    }

                    const checkbox = cell.querySelector('.employee-select-checkbox');
                    if (!checkbox) {
                        return;
                    }

                    const id = Number(checkbox.value);
                    if (!Number.isInteger(id) || id <= 0) {
                        return;
                    }

                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            headerCheckbox?.addEventListener('change', function () {
                if (this.checked) {
                    selectAllResults();
                    return;
                }

                clearSelection();
            });

            selectAllButton?.addEventListener('click', function () {
                selectAllResults();
            });

            clearButton?.addEventListener('click', function () {
                clearSelection();
            });

            printSelectedButton?.addEventListener('click', function () {
                openPrintModal(selectedIds());
            });

            scope.querySelectorAll('[data-print-single-id]').forEach((button) => {
                button.addEventListener('click', function () {
                    const employeeId = Number(this.dataset.printSingleId || 0);
                    if (!employeeId) {
                        return;
                    }

                    const employeeCode = this.dataset.printSingleCode || '-';
                    const employeeName = this.dataset.printSingleName || 'Employee';
                    openPrintModal([employeeId], `${employeeName} (${employeeCode})`);
                });
            });
        }

        syncCurrentPageCheckboxes();
        updateSelectionUi();
    }

    function openModalById(modalId) {
        if (!modalId || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        const modalEl = document.getElementById(modalId);
        if (!modalEl) {
            return;
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('employees-page-loading');
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

        syncEmployeeSelectionScope(normalizedUrl);

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
            const newContainer = doc.querySelector('#employees-page-container');
            const currentContainer = document.querySelector('#employees-page-container');
            const newResults = doc.querySelector('#employees-page-results');
            const currentResults = document.querySelector('#employees-page-results');
            const newModals = doc.querySelector('#employees-page-modals');
            const currentModals = document.querySelector('#employees-page-modals');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newContainer || !currentContainer || !newResults || !currentResults || !newModals || !currentModals) {
                window.location.href = normalizedUrl;
                return;
            }

            currentContainer.dataset.filteredTotal = String(newContainer.dataset.filteredTotal || 0);
            currentResults.replaceWith(newResults);
            currentModals.replaceWith(newModals);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            initPageTooltips(newResults);
            initPageTooltips(newModals);
            initPhotoInputs(document);
            initIdCardPrint(newResults);
            initIdCardPrint(newModals);

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

    window.employeeReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#employees-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        syncEmployeeSelectionScope(window.location.href);
        replacePageContent(window.location.href, false);
    });

    syncEmployeeSelectionScope(window.location.href);
    initPageTooltips(document);
    initPhotoInputs(document);
    initIdCardPrint(document.getElementById('employees-page-container') || document);
    openModalById(editModalId || createModalId);
})();
