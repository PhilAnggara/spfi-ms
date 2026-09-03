window.initAccountingInventoryCreateForm = function (root) {
    if (!root || !window.StockCorrectionItemSearch) {
        return;
    }

    const searchUrl = root.dataset.searchUrl || root.querySelector('.inv-manual-create-form')?.dataset.searchUrl;
    if (!searchUrl) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || root.querySelector('input[name="_token"]')?.value
        || '';
    const linesBody = root.querySelector('#inv-lines');
    const template = root.querySelector('#inv-row-template')?.innerHTML;
    const addBtn = root.querySelector('#inv-add-row');
    const categorySelect = root.querySelector('#inv-category-id');
    const categoryHint = root.querySelector('.inv-category-hint');
    let rowIndex = 0;

    if (!linesBody || !template) {
        return;
    }

    function getSearchUrl() {
        const url = new URL(searchUrl, window.location.origin);
        const categoryId = categorySelect?.value || '';
        if (categoryId) {
            url.searchParams.set('category_id', categoryId);
        }
        return url.pathname + url.search;
    }

    function recalcRow(row) {
        const money = window.AccountingInventoryMoney;
        const qty = money ? money.parse(row.querySelector('.inv-qty')?.value || '0') : parseFloat(row.querySelector('.inv-qty')?.value || '0');
        const cost = money ? money.parse(row.querySelector('.inv-cost')?.value || '0') : parseFloat(row.querySelector('.inv-cost')?.value || '0');
        const safeQty = Number.isFinite(qty) ? qty : 0;
        const safeCost = Number.isFinite(cost) ? cost : 0;
        const amount = Math.round(safeQty * safeCost * 100) / 100;
        const amountEl = row.querySelector('.inv-amount');
        const amountInput = row.querySelector('.inv-amount-input');
        if (amountEl) {
            amountEl.textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (amountInput) {
            amountInput.value = amount.toFixed(2);
        }
    }

    function updateCategoryState() {
        const hasCategory = Boolean(categorySelect?.value);
        if (addBtn) {
            addBtn.disabled = !hasCategory;
        }
        if (categoryHint) {
            categoryHint.classList.toggle('d-none', hasCategory);
        }
    }

    function updateRemoveButtons() {
        const rows = linesBody.querySelectorAll('tr');
        const onlyOne = rows.length <= 1;
        rows.forEach((row) => {
            const btn = row.querySelector('.inv-remove-row');
            if (!btn) {
                return;
            }
            btn.disabled = onlyOne;
            btn.title = onlyOne ? 'At least one item row is required' : 'Remove row';
        });
    }

    function updateDirectionStyle(row) {
        const isOut = Boolean(row.querySelector('.inv-direction-out:checked'));
        row.classList.toggle('inv-row-direction-out', isOut);
        row.classList.toggle('inv-row-direction-in', !isOut);
    }

    function bindDirectionToggle(row) {
        row.querySelectorAll('.inv-direction-input').forEach((input) => {
            input.addEventListener('change', () => updateDirectionStyle(row));
        });
        updateDirectionStyle(row);
    }

    function bindRow(row) {
        bindDirectionToggle(row);
        const qtyInput = row.querySelector('.inv-qty');
        const costInput = row.querySelector('.inv-cost');
        if (qtyInput && window.AccountingInventoryMoney) {
            window.AccountingInventoryMoney.bindInput(qtyInput, {
                maxDecimals: 5,
                onChange: () => recalcRow(row),
            });
        } else {
            qtyInput?.addEventListener('input', () => recalcRow(row));
        }
        if (costInput && window.AccountingInventoryMoney) {
            window.AccountingInventoryMoney.bindInput(costInput, {
                maxDecimals: 5,
                onChange: () => recalcRow(row),
            });
        } else {
            costInput?.addEventListener('input', () => recalcRow(row));
        }
        row.querySelector('.inv-remove-row')?.addEventListener('click', () => {
            if (linesBody.querySelectorAll('tr').length <= 1) {
                return;
            }
            row.remove();
            updateRemoveButtons();
        });

        StockCorrectionItemSearch.bindPicker(row, {
            searchUrl: getSearchUrl(),
            csrf,
            onSelect: (item, selectedRow) => {
                if (!item) {
                    selectedRow.querySelector('.inv-available').textContent = '0';
                    return;
                }

                const available = Number(item.balance || 0);
                const unitCost = Number(item.unit_cost || 0);
                selectedRow.querySelector('.inv-available').textContent = window.AccountingInventoryMoney
                    ? window.AccountingInventoryMoney.format(available, 5)
                    : available.toFixed(5);

                const selectedSub = selectedRow.querySelector('.sc-item-selected-sub');
                if (selectedSub) {
                    selectedSub.textContent = `Accounting available ${StockCorrectionItemSearch.formatNumber(available)} · ${item.unit || 'PCS'} · Unit cost ${StockCorrectionItemSearch.formatNumber(unitCost)}`;
                }

                const uomInput = selectedRow.querySelector('.inv-uom-id');
                if (uomInput) {
                    uomInput.value = item.unit_of_measure_id || '';
                }
                const selectedCostInput = selectedRow.querySelector('.inv-cost');
                if (selectedCostInput && !selectedCostInput.value && unitCost > 0) {
                    selectedCostInput.value = window.AccountingInventoryMoney
                        ? window.AccountingInventoryMoney.format(unitCost, 5)
                        : unitCost.toFixed(5);
                }
                recalcRow(selectedRow);
            },
        });
    }

    function addRow() {
        if (!categorySelect?.value) {
            return;
        }

        const html = template.replaceAll('__INDEX__', String(rowIndex++));
        linesBody.insertAdjacentHTML('beforeend', html);
        const row = linesBody.lastElementChild;
        bindRow(row);
        updateRemoveButtons();
    }

    addBtn?.addEventListener('click', addRow);
    categorySelect?.addEventListener('change', () => {
        linesBody.innerHTML = '';
        rowIndex = 0;
        updateCategoryState();
        if (categorySelect.value) {
            addRow();
        }
    });

    const form = root.querySelector('.inv-manual-create-form') || root.querySelector('form');
    form?.addEventListener('submit', () => {
        window.AccountingInventoryMoney?.normalizeForm(form);
    });

    updateCategoryState();
    if (categorySelect?.value) {
        addRow();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('inv-create-page');
    if (page) {
        page.dataset.searchUrl = page.querySelector('.inv-manual-create-form')?.dataset.searchUrl || '';
        window.initAccountingInventoryCreateForm(page);
    }
});
