window.AccountingInventoryMoney = (function () {
    function parse(value) {
        const cleaned = String(value ?? '')
            .replace(/,/g, '')
            .replace(/[^\d.-]/g, '')
            .trim();

        if (cleaned === '' || cleaned === '-' || cleaned === '.' || cleaned === '-.') {
            return NaN;
        }

        return Number(cleaned);
    }

    function format(value, maxDecimals = 5) {
        const number = typeof value === 'number' ? value : parse(value);
        if (!Number.isFinite(number)) {
            return '';
        }

        const fixed = number.toFixed(maxDecimals);
        const [intPart, decPart = ''] = fixed.split('.');
        const withCommas = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const trimmedDec = decPart.replace(/0+$/, '');

        return trimmedDec ? `${withCommas}.${trimmedDec}` : withCommas;
    }

    function formatWhileTyping(value, maxDecimals = 5) {
        let raw = String(value ?? '').replace(/[^\d.,]/g, '');
        const hasDot = raw.includes('.');
        raw = raw.replace(/,/g, '');

        if (raw === '') {
            return '';
        }

        const parts = raw.split('.');
        let intPart = parts[0].replace(/\D/g, '');
        let decPart = parts.length > 1 ? parts.slice(1).join('').replace(/\D/g, '').slice(0, maxDecimals) : '';

        if (intPart.length > 1) {
            intPart = intPart.replace(/^0+(?=\d)/, '');
        }

        const withCommas = (intPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        if (hasDot) {
            return `${withCommas}.${decPart}`;
        }

        return withCommas;
    }

    function caretRawPosition(value, caret) {
        const safeCaret = Math.max(0, Math.min(caret ?? 0, String(value).length));
        return String(value).slice(0, safeCaret).replace(/,/g, '').length;
    }

    function caretFromRawPosition(value, rawPos) {
        let seen = 0;
        for (let i = 0; i < value.length; i += 1) {
            if (value[i] !== ',') {
                if (seen === rawPos) {
                    return i;
                }
                seen += 1;
            }
        }
        return value.length;
    }

    function maxDecimalsFor(input, fallback = 5) {
        const fromData = Number(input?.dataset?.maxDecimals);
        if (Number.isFinite(fromData) && fromData >= 0) {
            return fromData;
        }
        return fallback;
    }

    function bindInput(input, { maxDecimals = 5, onChange } = {}) {
        if (!input || input.dataset.moneyBound === '1') {
            return;
        }
        input.dataset.moneyBound = '1';
        const decimals = maxDecimalsFor(input, maxDecimals);

        const initial = parse(input.value);
        if (Number.isFinite(initial)) {
            input.value = format(initial, decimals);
        }

        input.addEventListener('input', () => {
            const previous = input.value;
            const rawPos = caretRawPosition(previous, input.selectionStart);
            const next = formatWhileTyping(previous, decimals);
            input.value = next;
            const nextPos = caretFromRawPosition(next, rawPos);
            input.setSelectionRange(nextPos, nextPos);
            onChange?.();
        });

        input.addEventListener('blur', () => {
            const number = parse(input.value);
            input.value = Number.isFinite(number) ? format(number, decimals) : '';
            onChange?.();
        });
    }

    function normalizeForm(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.inv-cost, .inv-qty').forEach((input) => {
            const number = parse(input.value);
            input.value = Number.isFinite(number) ? String(number) : '0';
        });
    }

    function writeNormalizedFormData(form, formData) {
        if (!form || !formData) {
            return formData;
        }

        form.querySelectorAll('.inv-cost, .inv-qty').forEach((input) => {
            if (!input.name) {
                return;
            }
            const number = parse(input.value);
            formData.set(input.name, Number.isFinite(number) ? String(number) : '0');
        });

        return formData;
    }

    function formatForm(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.inv-cost, .inv-qty').forEach((input) => {
            const number = parse(input.value);
            input.value = Number.isFinite(number) ? format(number, maxDecimalsFor(input)) : '';
        });
    }

    return {
        parse,
        format,
        bindInput,
        normalizeForm,
        writeNormalizedFormData,
        formatForm,
    };
})();

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

    const money = window.AccountingInventoryMoney;

    function recalc(index) {
        const qty = money.parse(panel.querySelector(`.inv-qty[data-index="${index}"]`)?.value || '0');
        const cost = money.parse(panel.querySelector(`.inv-cost[data-index="${index}"]`)?.value || '0');
        const safeQty = Number.isFinite(qty) ? qty : 0;
        const safeCost = Number.isFinite(cost) ? cost : 0;
        const amount = Math.round(safeQty * safeCost * 100) / 100;
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

    panel.querySelectorAll('.inv-qty').forEach((input) => {
        money.bindInput(input, {
            maxDecimals: 5,
            onChange: () => recalc(input.dataset.index),
        });
    });

    panel.querySelectorAll('.inv-cost').forEach((input) => {
        money.bindInput(input, {
            maxDecimals: 5,
            onChange: () => recalc(input.dataset.index),
        });
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

    form.addEventListener('submit', () => {
        money.normalizeForm(form);
    });

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
