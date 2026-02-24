document.addEventListener('DOMContentLoaded', function () {
    const rrPage = document.getElementById('rr-page');
    if (!rrPage) {
        return;
    }

    initRrDataTable();
    initRrDeleteAction();
    initRrCreateModal(rrPage.dataset.poLookupUrl || '');
});

function initRrDataTable() {
    const tableEl = document.getElementById('rr-table');
    const keywordInput = document.getElementById('filter-rr-keyword');
    const startDateInput = document.getElementById('filter-rr-date-start');
    const endDateInput = document.getElementById('filter-rr-date-end');
    const resetButton = document.getElementById('reset-rr-filter');
    const resultBadge = document.getElementById('rr-filter-result');

    if (!tableEl || !keywordInput || !startDateInput || !endDateInput || !resetButton || !resultBadge) {
        return;
    }

    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') {
        return;
    }

    const $ = window.jQuery;
    const $table = $('#rr-table');

    const dateFilterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableEl) {
            return true;
        }

        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        if (!startDate && !endDate) {
            return true;
        }

        const rowNode = settings.aoData[dataIndex]?.nTr;
        const rowDate = rowNode?.dataset.receivedDate || '';

        if (!rowDate) {
            return false;
        }

        if (startDate && rowDate < startDate) {
            return false;
        }

        if (endDate && rowDate > endDate) {
            return false;
        }

        return true;
    };

    $.fn.dataTable.ext.search.push(dateFilterFn);

    const dataTable = $table.DataTable({
        order: [[3, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        dom: 'rt<"d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"lip>',
        language: {
            paginate: {
                previous: 'Prev',
                next: 'Next'
            },
            info: 'Showing _START_ to _END_ of _TOTAL_ data',
            lengthMenu: 'Show _MENU_ data'
        }
    });

    const updateResult = function () {
        resultBadge.textContent = dataTable.rows({ search: 'applied' }).count() + ' data';
    };

    keywordInput.addEventListener('input', function () {
        dataTable.search(keywordInput.value || '').draw();
    });

    startDateInput.addEventListener('change', function () {
        dataTable.draw();
    });

    endDateInput.addEventListener('change', function () {
        dataTable.draw();
    });

    resetButton.addEventListener('click', function () {
        keywordInput.value = '';
        startDateInput.value = '';
        endDateInput.value = '';
        dataTable.search('').draw();
    });

    $table.on('draw.dt', updateResult);
    updateResult();
}

function initRrDeleteAction() {
    window.confirmDeleteRr = function (rrId, rrNumber) {
        Swal.fire({
            title: 'Delete RR',
            text: 'Are you sure want to delete RR ' + rrNumber + '?',
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
