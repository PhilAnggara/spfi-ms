document.addEventListener('DOMContentLoaded', function () {
    const supplierTermMapNode = document.getElementById('canvassing-supplier-term-map');
    const supplierListNode = document.getElementById('canvassing-supplier-list');

    const supplierTermMap = supplierTermMapNode ? JSON.parse(supplierTermMapNode.textContent || '{}') : {};
    const suppliers = supplierListNode ? JSON.parse(supplierListNode.textContent || '[]') : [];

    const rowsContainer = document.getElementById('supplier-rows');
    const template = document.getElementById('supplier-row-template');
    const addSupplierButton = document.getElementById('add-supplier');
    const form = document.getElementById('canvassing-form');
    const supplierSummary = document.getElementById('supplier-summary');
    const formNotice = document.getElementById('form-notice');

    const pickerModalEl = document.getElementById('supplierPickerModal');
    const pickerSearchInput = document.getElementById('supplier-picker-search');
    const pickerList = document.getElementById('supplier-picker-list');
    const pickerModal = pickerModalEl && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(pickerModalEl)
        : null;

    if (!rowsContainer || !form) {
        return;
    }

    let activeRow = null;

    const getSupplierNameById = (supplierId) => {
        const found = suppliers.find((supplier) => String(supplier.id) === String(supplierId));
        return found ? found.name : '';
    };

    const getRows = () => Array.from(rowsContainer.querySelectorAll('.supplier-row'));

    const showFormNotice = (message) => {
        if (!formNotice) {
            return;
        }

        formNotice.textContent = message;
        formNotice.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const clearFormNotice = () => {
        if (!formNotice) {
            return;
        }

        formNotice.textContent = '';
        formNotice.classList.add('d-none');
    };

    const setRowInvalidState = (row, invalid = false) => {
        if (!row) {
            return;
        }

        row.classList.toggle('border-danger', invalid);
        row.classList.toggle('border-2', invalid);
    };

    const getSelectedSupplierIds = (exceptRow = null) => {
        return getRows()
            .filter((row) => row !== exceptRow)
            .map((row) => row.querySelector('.supplier-id-input')?.value)
            .filter(Boolean);
    };

    const applySupplierTerms = (row, force = false) => {
        if (!row) {
            return;
        }

        const supplierId = row.querySelector('.supplier-id-input')?.value;
        const paymentTypeInput = row.querySelector('select[name$="[term_of_payment_type]"]');
        const paymentInput = row.querySelector('input[name$="[term_of_payment]"]');
        const deliveryInput = row.querySelector('input[name$="[term_of_delivery]"]');

        if (!supplierId || !paymentTypeInput || !paymentInput || !deliveryInput) {
            return;
        }

        const terms = supplierTermMap[supplierId] || {};

        if (force || !paymentTypeInput.value) {
            paymentTypeInput.value = terms.term_of_payment_type ?? '';
        }

        if (force || !paymentInput.value.trim()) {
            paymentInput.value = terms.term_of_payment ?? '';
        }

        if (force || !deliveryInput.value.trim()) {
            deliveryInput.value = terms.term_of_delivery ?? '';
        }
    };

    const updateSupplierBadge = (row) => {
        const supplierNameEl = row.querySelector('.supplier-name');
        const supplierNameText = row.querySelector('.supplier-name-text');
        const clearButton = row.querySelector('.clear-supplier');
        const supplierId = row.querySelector('.supplier-id-input')?.value;
        if (!supplierNameEl || !supplierNameText) {
            return;
        }

        const placeholder = supplierNameEl.dataset.placeholder || 'Belum dipilih';
        const supplierName = supplierId ? getSupplierNameById(supplierId) : '';

        supplierNameEl.classList.remove('bg-light-primary', 'bg-body', 'text-dark', 'text-muted', 'fw-semibold');

        if (supplierName) {
            supplierNameText.textContent = supplierName;
            supplierNameEl.classList.add('bg-body', 'text-dark', 'fw-semibold');
            if (clearButton) {
                clearButton.style.display = 'flex';
            }
        } else {
            supplierNameText.textContent = placeholder;
            supplierNameEl.classList.add('bg-light-primary', 'text-muted');
            if (clearButton) {
                clearButton.style.display = 'none';
            }
        }
    };

    const updateSupplierSummary = () => {
        if (!supplierSummary) {
            return;
        }

        const rows = getRows();
        const selectedCount = rows.filter((row) => row.querySelector('.supplier-id-input')?.value).length;
        supplierSummary.textContent = `${selectedCount}/${rows.length} supplier dipilih`;
    };

    const updateRemoveButtons = () => {
        const rows = getRows();
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-supplier');
            const numberBadge = row.querySelector('.supplier-number');

            if (removeBtn) {
                removeBtn.disabled = rows.length === 1;
            }

            if (numberBadge) {
                numberBadge.textContent = `Supplier #${index + 1}`;
            }
        });
    };

    const showSupplierPicker = (row) => {
        activeRow = row;
        renderSupplierPickerList('');
        if (pickerSearchInput) {
            pickerSearchInput.value = '';
        }
        if (pickerModal) {
            pickerModal.show();
        }
    };

    const renderSupplierPickerList = (searchText) => {
        if (!pickerList) {
            return;
        }

        const keyword = (searchText || '').trim().toLowerCase();
        const selectedByOthers = getSelectedSupplierIds(activeRow);
        const currentSupplierId = activeRow?.querySelector('.supplier-id-input')?.value || null;

        const filteredSuppliers = suppliers.filter((supplier) => {
            if (!keyword) {
                return true;
            }

            return supplier.name.toLowerCase().includes(keyword);
        })
            .sort((a, b) => {
                if (!keyword) {
                    return a.name.localeCompare(b.name);
                }

                const aName = a.name.toLowerCase();
                const bName = b.name.toLowerCase();
                const aStarts = aName.startsWith(keyword) ? 0 : 1;
                const bStarts = bName.startsWith(keyword) ? 0 : 1;

                if (aStarts !== bStarts) {
                    return aStarts - bStarts;
                }

                const aIndex = aName.indexOf(keyword);
                const bIndex = bName.indexOf(keyword);
                if (aIndex !== bIndex) {
                    return aIndex - bIndex;
                }

                return a.name.localeCompare(b.name);
            });

        if (!filteredSuppliers.length) {
            pickerList.innerHTML = '<div class="text-muted small p-2">Supplier tidak ditemukan.</div>';
            return;
        }

        pickerList.innerHTML = filteredSuppliers
            .map((supplier) => {
                const isTaken = selectedByOthers.includes(String(supplier.id));
                const isSelected = String(supplier.id) === String(currentSupplierId || '');

                return `
                    <button
                        type="button"
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${isSelected ? 'active' : ''}"
                        data-supplier-id="${supplier.id}"
                        ${isTaken ? 'disabled' : ''}
                    >
                        <span>${supplier.name}</span>
                        ${isTaken ? '<span class="badge bg-light-secondary">Sudah dipilih</span>' : ''}
                    </button>
                `;
            })
            .join('');
    };

    const selectSupplierFromButton = (supplierButton) => {
        if (!supplierButton || !activeRow) {
            return false;
        }

        const selectedByOthers = getSelectedSupplierIds(activeRow);
        const supplierId = supplierButton.dataset.supplierId;

        if (!supplierId || selectedByOthers.includes(String(supplierId))) {
            return false;
        }

        assignSupplierToRow(activeRow, supplierId);

        if (pickerModal) {
            pickerModal.hide();
        }

        return true;
    };

    const assignSupplierToRow = (row, supplierId) => {
        if (!row) {
            return;
        }

        const hiddenSupplierInput = row.querySelector('.supplier-id-input');
        if (!hiddenSupplierInput) {
            return;
        }

        hiddenSupplierInput.value = supplierId ? String(supplierId) : '';
        updateSupplierBadge(row);
        applySupplierTerms(row, true);
        setRowInvalidState(row, false);
        clearFormNotice();
        updateSupplierSummary();
    };

    const addSupplierRow = () => {
        if (!template || !rowsContainer) {
            return;
        }

        const nextIndex = Number(rowsContainer.dataset.nextIndex || getRows().length);
        const nextNumber = getRows().length + 1;

        const html = template.innerHTML
            .replaceAll('__INDEX__', String(nextIndex))
            .replaceAll('__NUMBER__', String(nextNumber));

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        rowsContainer.appendChild(row);

        rowsContainer.dataset.nextIndex = String(nextIndex + 1);
        updateSupplierBadge(row);
        clearFormNotice();
        updateRemoveButtons();
        updateSupplierSummary();
    };

    if (addSupplierButton) {
        addSupplierButton.addEventListener('click', addSupplierRow);
    }

    rowsContainer.addEventListener('click', (event) => {
        const clearButton = event.target.closest('.clear-supplier');
        if (clearButton) {
            const row = clearButton.closest('.supplier-row');
            setRowInvalidState(row, false);
            assignSupplierToRow(row, null);
            return;
        }

        const removeButton = event.target.closest('.remove-supplier');
        if (removeButton) {
            const row = removeButton.closest('.supplier-row');
            if (!row) {
                return;
            }

            row.remove();
            clearFormNotice();
            updateRemoveButtons();
            updateSupplierSummary();
            return;
        }

        const supplierName = event.target.closest('.supplier-name');
        if (supplierName && !event.target.closest('.clear-supplier')) {
            const row = supplierName.closest('.supplier-row');
            if (row) {
                showSupplierPicker(row);
            }
            return;
        }
    });

    if (pickerModalEl) {
        pickerModalEl.addEventListener('shown.bs.modal', () => {
            pickerSearchInput?.focus();
        });
    }

    rowsContainer.addEventListener('keydown', (event) => {
        const supplierName = event.target.closest('.supplier-name');
        if (!supplierName) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const row = supplierName.closest('.supplier-row');
            if (row) {
                showSupplierPicker(row);
            }
        }
    });

    if (pickerSearchInput) {
        pickerSearchInput.addEventListener('input', (event) => {
            renderSupplierPickerList(event.target.value || '');
        });

        pickerSearchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            const firstAvailableButton = pickerList?.querySelector('[data-supplier-id]:not([disabled])');
            if (!firstAvailableButton) {
                return;
            }

            selectSupplierFromButton(firstAvailableButton);
        });
    }

    if (pickerList) {
        pickerList.addEventListener('click', (event) => {
            const supplierButton = event.target.closest('[data-supplier-id]');
            if (!supplierButton) {
                return;
            }

            selectSupplierFromButton(supplierButton);
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            const rows = getRows();
            rows.forEach((row) => setRowInvalidState(row, false));

            const selectedIds = rows
                .map((row) => row.querySelector('.supplier-id-input')?.value)
                .filter(Boolean);

            const unselectedRows = rows.filter((row) => !row.querySelector('.supplier-id-input')?.value);
            if (unselectedRows.length) {
                unselectedRows.forEach((row) => setRowInvalidState(row, true));
                event.preventDefault();
                showFormNotice('Mohon pilih supplier untuk setiap baris terlebih dahulu.');
                return;
            }

            const uniqueCount = new Set(selectedIds).size;
            if (uniqueCount !== selectedIds.length) {
                const countMap = new Map();
                selectedIds.forEach((supplierId) => {
                    countMap.set(supplierId, (countMap.get(supplierId) || 0) + 1);
                });

                rows.forEach((row) => {
                    const supplierId = row.querySelector('.supplier-id-input')?.value;
                    if (supplierId && (countMap.get(supplierId) || 0) > 1) {
                        setRowInvalidState(row, true);
                    }
                });

                event.preventDefault();
                showFormNotice('Supplier yang sama tidak boleh dipilih lebih dari satu kali.');
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                showFormNotice('Mohon lengkapi field wajib seperti Unit Price pada setiap baris.');
                form.reportValidity();
                return;
            }

            clearFormNotice();
        });
    }

    getRows().forEach((row) => {
        updateSupplierBadge(row);
        applySupplierTerms(row, false);
    });
    updateRemoveButtons();
    updateSupplierSummary();
});
