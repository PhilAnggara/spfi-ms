document.addEventListener('DOMContentLoaded', function () {
    initTransferSlipPage();
});

window.initTransferSlipPage = initTransferSlipPage;
window.initTransferSlipFilters = initTransferSlipFilters;

function initTransferSlipPage() {
    const tsPage = document.getElementById('ts-page');
    if (!tsPage) {
        return;
    }

    initTransferSlipFilters();
    initTransferSlipDeleteAction();
    initTransferSlipCreateModal(tsPage.dataset.swsLookupUrl || '');
}

function initTransferSlipFilters() {
    const filterForm = document.getElementById('ts-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-ts-keyword'),
        department: document.getElementById('filter-ts-department'),
        production: document.getElementById('filter-ts-production'),
        tsStart: document.getElementById('filter-ts-date-start'),
        tsEnd: document.getElementById('filter-ts-date-end'),
        reset: document.getElementById('reset-ts-filter'),
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
        setQueryParam(url.searchParams, 'department', filterElements.department?.value);
        setQueryParam(url.searchParams, 'production', filterElements.production?.value);
        setQueryParam(url.searchParams, 'ts_start', filterElements.tsStart?.value);
        setQueryParam(url.searchParams, 'ts_end', filterElements.tsEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.tsReplacePageContent === 'function') {
                window.tsReplacePageContent(url, true);
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

    if (filterElements.department) {
        filterElements.department.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.production) {
        filterElements.production.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.tsStart) {
        filterElements.tsStart.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.tsEnd) {
        filterElements.tsEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.department) filterElements.department.value = '';
            if (filterElements.production) filterElements.production.value = '';
            if (filterElements.tsStart) filterElements.tsStart.value = '';
            if (filterElements.tsEnd) filterElements.tsEnd.value = '';

            applyServerFilter(false);
        });
    }
}

function initTransferSlipDeleteAction() {
    window.confirmDeleteTransferSlip = function (tsId, tsNumber) {
        Swal.fire({
            title: 'Delete Transfer Slip',
            text: 'Are you sure you want to delete transfer slip ' + tsNumber + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('hapus-ts-' + tsId);
                if (form) {
                    form.submit();
                }
            }
        });
    };
}

function initTransferSlipCreateModal(swsLookupUrl) {
    const createForm = document.getElementById('create-ts-form');
    if (!createForm || createForm.dataset.tsCreateInitialized === '1') {
        return;
    }
    createForm.dataset.tsCreateInitialized = '1';

    const createModalEl = document.getElementById('create-ts-modal');
    const createSwsNumberInput = document.getElementById('create_sws_number');
    const createStoreWithdrawalIdInput = document.getElementById('create_store_withdrawal_id');
    const createLoadSwsButton = document.getElementById('create-load-sws');
    const createSwsError = document.getElementById('create-sws-error');
    const createSwsDetails = document.getElementById('create-sws-details');
    const createItemsBody = document.getElementById('create-ts-items-body');
    const createSummaryLines = document.getElementById('create-ts-summary-lines');
    const createSummaryQty = document.getElementById('create-ts-summary-qty');
    const createSummaryProduction = document.getElementById('create-ts-summary-production');
    const createForProduction = document.getElementById('create_for_production');
    const createProductionHelp = document.getElementById('create-production-help');
    const createSaveButton = document.getElementById('create-ts-save-btn');

    if (createSaveButton) {
        createSaveButton.classList.remove('d-none');
        createSaveButton.style.removeProperty('display');
    }

    const detailFields = {
        number: document.getElementById('create-sws-detail-number'),
        date: document.getElementById('create-sws-detail-date'),
        department: document.getElementById('create-sws-detail-department'),
        type: document.getElementById('create-sws-detail-type'),
        info: document.getElementById('create-sws-detail-info'),
    };

    if (!createModalEl || !createSwsNumberInput || !createLoadSwsButton || !swsLookupUrl) {
        return;
    }

    const prefill = window.transferSlipCreatePrefill || {
        shouldOpenModal: false,
        swsNumber: '',
        items: [],
    };

    const formatNumber = (value) => Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });

    const showSwsError = (message) => {
        if (!createSwsError) {
            return;
        }

        createSwsError.textContent = message;
        createSwsError.classList.remove('d-none');
    };

    const hideSwsError = () => {
        if (!createSwsError) {
            return;
        }

        createSwsError.textContent = '';
        createSwsError.classList.add('d-none');
    };

    const syncProductionSummary = () => {
        const isProduction = createForProduction && createForProduction.value === '1';

        if (createSummaryProduction) {
            createSummaryProduction.textContent = isProduction ? 'Yes' : 'No';
        }

        if (createProductionHelp) {
            createProductionHelp.textContent = isProduction
                ? 'If select yes, this transfer slip will be counted on iCore Template - Consumption report.'
                : 'If select no, this transfer slip will not be counted on iCore Template - Consumption report.';
        }
    };

    const updateSummary = () => {
        const qtyInputs = Array.from(createForm.querySelectorAll('.ts-qty-input'));
        const selectedLines = qtyInputs.filter((input) => Number(input.value || 0) > 0);
        const totalQty = selectedLines.reduce((sum, input) => sum + Number(input.value || 0), 0);

        if (createSummaryLines) {
            createSummaryLines.textContent = String(selectedLines.length);
        }

        if (createSummaryQty) {
            createSummaryQty.textContent = formatNumber(totalQty);
        }
    };

    const bindQtyInputs = () => {
        createItemsBody.querySelectorAll('.ts-qty-input').forEach((input) => {
            input.addEventListener('input', function () {
                const max = Number(input.dataset.max || 0);
                const current = Number(input.value || 0);

                if (current < 0) {
                    input.value = '0';
                }

                if (max > 0 && current > max) {
                    input.value = max.toFixed(3);
                }

                updateSummary();
            });
        });

        updateSummary();
    };

    const renderEmptyState = (message) => {
        createItemsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">' + message + '</td></tr>';
        if (createStoreWithdrawalIdInput) {
            createStoreWithdrawalIdInput.value = '';
        }
        if (createSwsDetails) {
            createSwsDetails.classList.add('d-none');
        }
        updateSummary();
    };

    const renderSwsDetails = (payload, preservedItems = []) => {
        const preservedMap = new Map();
        preservedItems.forEach((row) => {
            const key = Number(row.store_withdrawal_item_id || 0);
            if (key > 0) {
                preservedMap.set(key, Number(row.quantity || 0));
            }
        });

        if (createStoreWithdrawalIdInput) {
            createStoreWithdrawalIdInput.value = String(payload.store_withdrawal.id || '');
        }

        if (createSwsDetails) {
            createSwsDetails.classList.remove('d-none');
        }

        if (detailFields.number) detailFields.number.textContent = payload.store_withdrawal.sws_number || '-';
        if (detailFields.date) detailFields.date.textContent = payload.store_withdrawal.sws_date || '-';
        if (detailFields.department) {
            detailFields.department.textContent = (payload.store_withdrawal.department_code || '-') + ' / ' + (payload.store_withdrawal.department_name || '-');
        }
        if (detailFields.type) detailFields.type.textContent = payload.store_withdrawal.type || '-';
        if (detailFields.info) detailFields.info.textContent = payload.store_withdrawal.info || '-';

        if (!Array.isArray(payload.items) || payload.items.length === 0) {
            renderEmptyState('This SWS has no active item to transfer.');
            return;
        }

        createItemsBody.innerHTML = payload.items.map((item, index) => {
            const preservedQty = preservedMap.get(Number(item.store_withdrawal_item_id)) ?? 0;

            return `
                <tr>
                    <td>
                        ${item.product_code || '-'}
                        <input type="hidden" name="items[${index}][store_withdrawal_item_id]" value="${item.store_withdrawal_item_id}">
                        <input type="hidden" name="items[${index}][item_id]" value="${item.item_id}">
                    </td>
                    <td>${item.item_name || '-'}</td>
                    <td class="text-end">${formatNumber(item.quantity_source)}</td>
                    <td class="text-end">${formatNumber(item.quantity_transferred)}</td>
                    <td class="text-end">${formatNumber(item.quantity_remaining)}</td>
                    <td>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control ts-qty-input"
                                name="items[${index}][quantity]"
                                min="0"
                                max="${Number(item.quantity_remaining).toFixed(3)}"
                                step="0.001"
                                value="${Number(preservedQty || 0).toFixed(3)}"
                                data-max="${Number(item.quantity_remaining).toFixed(3)}"
                            >
                            <span class="input-group-text">${item.uom || 'PCS'}</span>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        bindQtyInputs();
    };

    const loadSws = async (preservedItems = []) => {
        const swsNumber = String(createSwsNumberInput.value || '').trim();
        if (swsNumber === '') {
            showSwsError('Input an SWS number first.');
            renderEmptyState('Load an SWS number to display transferable items.');
            return;
        }

        hideSwsError();
        createLoadSwsButton.disabled = true;

        try {
            const url = new URL(swsLookupUrl, window.location.origin);
            url.searchParams.set('sws_number', swsNumber);

            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Failed to load SWS data.');
            }

            renderSwsDetails(payload, preservedItems);
        } catch (error) {
            renderEmptyState('Load an SWS number to display transferable items.');
            showSwsError(error.message || 'Failed to load SWS data.');
        } finally {
            createLoadSwsButton.disabled = false;
        }
    };

    createLoadSwsButton.addEventListener('click', function () {
        loadSws();
    });

    createSwsNumberInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadSws();
        }
    });

    createForm.addEventListener('submit', function (event) {
        const qtyInputs = Array.from(createForm.querySelectorAll('.ts-qty-input'));
        const hasPositiveQty = qtyInputs.some((input) => Number(input.value || 0) > 0);

        if (!hasPositiveQty) {
            event.preventDefault();
            showSwsError('Fill at least one quantity out greater than 0 before saving.');
            return;
        }

        hideSwsError();
    });

    if (createForProduction) {
        createForProduction.addEventListener('change', syncProductionSummary);
    }
    syncProductionSummary();

    if (prefill.shouldOpenModal && window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getOrCreateInstance(createModalEl);
        modal.show();
    }

    if (prefill.swsNumber) {
        createSwsNumberInput.value = prefill.swsNumber;
        loadSws(Array.isArray(prefill.items) ? prefill.items : []);
    }
}
