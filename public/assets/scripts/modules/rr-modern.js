document.addEventListener('DOMContentLoaded', function () {
    initReceivingReportPage();
});

window.initReceivingReportPage = initReceivingReportPage;
window.initReceivingReportFilters = initReceivingReportFilters;

function initReceivingReportPage() {
    const rrPage = document.getElementById('rr-page');
    if (!rrPage) {
        return;
    }

    initReceivingReportFilters();
    initRrDeleteAction();
    initRrCreateModal(rrPage.dataset.poLookupUrl || '');
    initRrCustomsDocumentFields();
}

function initReceivingReportFilters() {
    const filterForm = document.getElementById('rr-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-rr-keyword'),
        dateStart: document.getElementById('filter-rr-date-start'),
        dateEnd: document.getElementById('filter-rr-date-end'),
        reset: document.getElementById('reset-rr-filter'),
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
        setQueryParam(url.searchParams, 'date_from', filterElements.dateStart?.value);
        setQueryParam(url.searchParams, 'date_to', filterElements.dateEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();

            if (typeof window.rrReplacePageContent === 'function') {
                window.rrReplacePageContent(url, true);
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

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', function () {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.dateStart) filterElements.dateStart.value = '';
            if (filterElements.dateEnd) filterElements.dateEnd.value = '';

            applyServerFilter(false);
        });
    }
}

function initRrCustomsDocumentFields() {
    const rrPage = document.getElementById('rr-page');
    if (!rrPage) {
        return;
    }

    const createToggles = document.querySelectorAll('.create-customs-choice');
    const createFields = document.getElementById('create-customs-fields');
    const createNumber = document.getElementById('create_customs_document_number');
    const createType = document.getElementById('create_customs_document_type_id');
    const createDate = document.getElementById('create_customs_document_date');

    if (createFields && createFields.dataset.customsInitialized === '1') {
        return;
    }
    if (createFields) {
        createFields.dataset.customsInitialized = '1';
    }

    const syncCreateCustomsFields = function () {
        if (!createToggles.length || !createFields || !createNumber || !createType || !createDate) {
            return;
        }

        const selectedToggle = Array.from(createToggles).find((input) => input.checked);
        const isRequired = selectedToggle ? selectedToggle.value === '1' : false;

        createFields.classList.toggle('d-none', !isRequired);
        createNumber.required = isRequired;
        createType.required = isRequired;
        createDate.required = isRequired;

        if (!isRequired) {
            createNumber.value = '';
            createType.value = '';
            createDate.value = '';
        }
    };

    createToggles.forEach((input) => {
        input.addEventListener('change', syncCreateCustomsFields);
    });
    syncCreateCustomsFields();

    document.querySelectorAll('.rr-edit-customs-toggle').forEach((toggleGroup) => {
        if (toggleGroup.dataset.customsInitialized === '1') {
            return;
        }
        toggleGroup.dataset.customsInitialized = '1';

        const targetSelector = toggleGroup.getAttribute('data-target');
        if (!targetSelector) {
            return;
        }

        const target = document.querySelector(targetSelector);
        if (!target) {
            return;
        }

        const toggleInputs = toggleGroup.querySelectorAll('.rr-edit-customs-choice');
        if (!toggleInputs.length) {
            return;
        }

        const numberInput = target.querySelector('.rr-edit-customs-number');
        const typeInput = target.querySelector('.rr-edit-customs-type');
        const dateInput = target.querySelector('.rr-edit-customs-date');

        const syncEditCustomsFields = function () {
            const selectedToggle = Array.from(toggleInputs).find((input) => input.checked);
            const isRequired = selectedToggle ? selectedToggle.value === '1' : false;

            target.classList.toggle('d-none', !isRequired);

            if (numberInput) {
                numberInput.required = isRequired;
                if (!isRequired) {
                    numberInput.value = '';
                }
            }

            if (typeInput) {
                typeInput.required = isRequired;
                if (!isRequired) {
                    typeInput.value = '';
                }
            }

            if (dateInput) {
                dateInput.required = isRequired;
                if (!isRequired) {
                    dateInput.value = '';
                }
            }
        };

        toggleInputs.forEach((input) => {
            input.addEventListener('change', syncEditCustomsFields);
        });
        syncEditCustomsFields();
    });
}

function initRrDeleteAction() {
    window.confirmDeleteRr = function (rrId, rrNumber) {
        Swal.fire({
            title: 'Delete RR',
            text: 'Are you sure you want to delete RR ' + rrNumber + '?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('hapus-rr-' + rrId);
                if (form) {
                    form.submit();
                }
            }
        });
    };
}

function initRrCreateModal(poLookupUrl) {
    const createForm = document.getElementById('create-rr-form');
    if (!createForm || createForm.dataset.rrCreateInitialized === '1') {
        return;
    }
    createForm.dataset.rrCreateInitialized = '1';

    const createPoNumberInput = document.getElementById('create_po_number');
    const createLoadPoButton = document.getElementById('create-load-po');
    const createPoError = document.getElementById('create-po-error');
    const createPoDetails = document.getElementById('create-po-details');
    const createPoItemsBody = document.getElementById('create-po-items-body');
    const createPurchaseOrderIdInput = document.getElementById('create_purchase_order_id');
    const createSelectAllButton = document.getElementById('create-select-all');
    const createClearAllButton = document.getElementById('create-clear-all');
    const createSummaryItems = document.getElementById('create-summary-items');
    const createSummaryGood = document.getElementById('create-summary-good');
    const createSummaryBad = document.getElementById('create-summary-bad');
    const createSaveButton = document.getElementById('create-save-btn');

    const createPoDetailNumber = document.getElementById('create-po-detail-number');
    const createPoDetailSupplier = document.getElementById('create-po-detail-supplier');
    const createPoDetailDate = document.getElementById('create-po-detail-date');

    if (!createPoNumberInput || !createLoadPoButton || !poLookupUrl) {
        return;
    }

    function formatNumber(value) {
        const number = Number(value || 0);

        return Number.isInteger(number)
            ? number.toLocaleString('en-US')
            : number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showCreateError(message) {
        createPoError.classList.remove('d-none');
        createPoError.textContent = message;
    }

    function hideCreateError() {
        createPoError.classList.add('d-none');
        createPoError.textContent = '';
    }

    function updateCreateSummary() {
        let selected = 0;
        let totalGood = 0;
        let totalBad = 0;
        let hasInvalid = false;

        document.querySelectorAll('#create-po-items-body tr').forEach((row) => {
            const checkbox = row.querySelector('.create-item-check');
            const goodInput = row.querySelector('.create-qty-good');
            const badInput = row.querySelector('.create-qty-bad');
            const max = Number(goodInput.getAttribute('max') || 0);

            const good = Number(goodInput.value || 0);
            const bad = Number(badInput.value || 0);
            const total = good + bad;

            goodInput.classList.remove('is-invalid');
            badInput.classList.remove('is-invalid');

            if (checkbox.checked) {
                selected += 1;
                totalGood += good;
                totalBad += bad;

                if (total <= 0 || total > max) {
                    hasInvalid = true;
                    goodInput.classList.add('is-invalid');
                    badInput.classList.add('is-invalid');
                }
            }
        });

        createSummaryItems.textContent = String(selected);
        createSummaryGood.textContent = formatNumber(totalGood);
        createSummaryBad.textContent = formatNumber(totalBad);
        createSaveButton.disabled = createPurchaseOrderIdInput.value === '' || selected === 0 || hasInvalid;
    }

    function toggleCreateInputs(checkbox) {
        const row = checkbox.closest('tr');
        const hiddenSelected = row.querySelector('.create-selected-hidden');
        const goodInput = row.querySelector('.create-qty-good');
        const badInput = row.querySelector('.create-qty-bad');

        hiddenSelected.value = checkbox.checked ? '1' : '0';
        goodInput.disabled = !checkbox.checked;
        badInput.disabled = !checkbox.checked;

        if (!checkbox.checked) {
            goodInput.value = '';
            badInput.value = '';
        }

        updateCreateSummary();
    }

    function bindCreateRows() {
        document.querySelectorAll('.create-item-check').forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                toggleCreateInputs(checkbox);
            });
        });

        document.querySelectorAll('.create-qty-good, .create-qty-bad').forEach((input) => {
            input.addEventListener('input', updateCreateSummary);
        });

        updateCreateSummary();
    }

    function createCreateItemRow(item, index) {
        const isDisabled = Number(item.qty_remaining) <= 0;

        return `
                <tr>
                    <td>
                        <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${item.purchase_order_item_id}">
                        <input type="hidden" name="items[${index}][selected]" value="0" class="create-selected-hidden">
                        <input type="checkbox" class="form-check-input create-item-check" ${isDisabled ? 'disabled' : ''}>
                    </td>
                    <td>${item.item_name ?? '-'}</td>
                    <td>${item.item_code ?? '-'}</td>
                    <td>${item.unit_name ?? '-'}</td>
                    <td class="text-end">${formatNumber(item.qty_ordered)}</td>
                    <td class="text-end">${formatNumber(item.qty_received)}</td>
                    <td class="text-end">${formatNumber(item.qty_remaining)}</td>
                    <td><input type="number" step="0.01" min="0" max="${item.qty_remaining}" name="items[${index}][qty_good]" class="form-control form-control-sm text-end create-qty-good" disabled></td>
                    <td><input type="number" step="0.01" min="0" max="${item.qty_remaining}" name="items[${index}][qty_bad]" class="form-control form-control-sm text-end create-qty-bad" disabled></td>
                </tr>
            `;
    }

    async function loadPoForCreate() {
        hideCreateError();
        createPoDetails.classList.add('d-none');
        createPoItemsBody.innerHTML = '';
        createPurchaseOrderIdInput.value = '';
        updateCreateSummary();

        const poNumber = createPoNumberInput.value.trim();
        if (!poNumber) {
            showCreateError('PO number is required.');

            return;
        }

        createLoadPoButton.disabled = true;
        createLoadPoButton.textContent = 'Loading...';

        try {
            const url = `${poLookupUrl}?po_number=${encodeURIComponent(poNumber)}`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                showCreateError(data.message ?? 'Failed to load PO detail.');

                return;
            }

            if (!Array.isArray(data.items) || data.items.length === 0) {
                showCreateError('No items found in selected PO.');

                return;
            }

            createPurchaseOrderIdInput.value = data.purchase_order.id;
            createPoDetailNumber.textContent = data.purchase_order.po_number ?? '-';
            createPoDetailSupplier.textContent = data.purchase_order.supplier_name ?? '-';
            createPoDetailDate.textContent = data.purchase_order.po_date ?? '-';

            createPoItemsBody.innerHTML = data.items.map((item, index) => createCreateItemRow(item, index)).join('');
            bindCreateRows();
            createPoDetails.classList.remove('d-none');
        } catch (error) {
            showCreateError('Failed to load PO detail. Please try again.');
        } finally {
            createLoadPoButton.disabled = false;
            createLoadPoButton.textContent = 'Load PO';
        }
    }

    createLoadPoButton.addEventListener('click', loadPoForCreate);
    createPoNumberInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadPoForCreate();
        }
    });

    createSelectAllButton?.addEventListener('click', function () {
        document.querySelectorAll('.create-item-check').forEach((checkbox) => {
            if (!checkbox.disabled) {
                checkbox.checked = true;
                toggleCreateInputs(checkbox);
            }
        });
        updateCreateSummary();
    });

    createClearAllButton?.addEventListener('click', function () {
        document.querySelectorAll('.create-item-check').forEach((checkbox) => {
            checkbox.checked = false;
            toggleCreateInputs(checkbox);
        });
        updateCreateSummary();
    });
}
