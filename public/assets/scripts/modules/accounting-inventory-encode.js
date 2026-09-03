window.initAccountingInventoryEncodeForm = function (root) {
    if (!root) {
        return null;
    }

    const panel = root.querySelector('[data-inventory-encode-panel]') || root;
    const form = panel.querySelector('#inventory-encode-form') || panel.querySelector('.inv-encode-form');
    if (!form) {
        return null;
    }

    if (panel.dataset.encodeBound === '1') {
        return {
            getForm: () => form,
            getPanel: () => panel,
        };
    }
    panel.dataset.encodeBound = '1';

    function recalc(index) {
        const qty = parseFloat(panel.querySelector(`.inv-qty[data-index="${index}"]`)?.value || '0');
        const cost = parseFloat(panel.querySelector(`.inv-cost[data-index="${index}"]`)?.value || '0');
        const amount = Math.round(qty * cost * 100) / 100;
        const amountEl = panel.querySelector(`.inv-amount[data-index="${index}"]`);
        const amountInput = panel.querySelector(`.inv-amount-input[data-index="${index}"]`);
        if (amountEl) {
            amountEl.textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (amountInput) {
            amountInput.value = amount.toFixed(2);
        }
        updateFooterTotal();
    }

    function updateFooterTotal() {
        const footerTotal = document.querySelector('#inv-encode-footer-total');
        if (!footerTotal) {
            return;
        }
        let total = 0;
        panel.querySelectorAll('.inv-amount-input').forEach((input) => {
            total += parseFloat(input.value || '0');
        });
        footerTotal.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateRowDirection(row) {
        const isOut = Boolean(row.querySelector('.inv-direction-out:checked'));
        row.classList.toggle('inv-row-direction-out', isOut);
        row.classList.toggle('inv-row-direction-in', !isOut);
    }

    panel.querySelectorAll('.inv-qty, .inv-cost').forEach((input) => {
        input.addEventListener('input', () => recalc(input.dataset.index));
    });

    panel.querySelectorAll('.inv-encode-line-row, tbody tr').forEach((row) => {
        row.querySelectorAll('.inv-direction-input').forEach((input) => {
            input.addEventListener('change', () => updateRowDirection(row));
        });
        updateRowDirection(row);
    });

    function focusFirstEditableField() {
        const correctedQty = panel.querySelector('.inv-qty[data-corrected="1"]');
        const firstQty = panel.querySelector('.inv-qty');
        const target = correctedQty || firstQty;
        if (target) {
            target.focus();
            target.select?.();
        }
    }

    focusFirstEditableField();
    updateFooterTotal();

    form.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            const nextBtn = document.querySelector('#inv-encode-submit-next');
            if (nextBtn && !nextBtn.disabled) {
                nextBtn.click();
            }
        }
    });

    return {
        recalcAll: () => {
            panel.querySelectorAll('.inv-qty').forEach((input) => recalc(input.dataset.index));
        },
        focusFirstEditableField,
        getForm: () => form,
        getPanel: () => panel,
    };
};
