document.addEventListener('DOMContentLoaded', function () {
    const currencySelect = document.getElementById('currency-id');
    const feeItemsContainer = document.getElementById('fee-items-container');
    const addFeeBtn = document.getElementById('add-fee-btn');

    const getCurrencySymbol = () => {
        if (!currencySelect) {
            return 'Rp';
        }

        const option = currencySelect.options[currencySelect.selectedIndex];
        if (!option) {
            return 'Rp';
        }

        return option.dataset.symbol || option.dataset.code || 'Rp';
    };

    const updateCurrencySymbols = () => {
        const symbol = getCurrencySymbol();
        document.querySelectorAll('.currency-symbol').forEach((el) => {
            el.textContent = symbol;
        });

        return symbol;
    };

    const formatCurrency = (value) => {
        const symbol = getCurrencySymbol();
        const number = Number(value || 0);
        return symbol + ' ' + number.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    };

    const formatSignedCurrency = (value) => {
        const symbol = getCurrencySymbol();
        const number = Math.abs(Number(value || 0));
        const formatted = number.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });

        return (value < 0 ? '- ' : '') + symbol + ' ' + formatted;
    };

    const getFeeItemsTotal = () => {
        if (!feeItemsContainer) {
            return 0;
        }

        return Array.from(feeItemsContainer.querySelectorAll('.fee-amount-input')).reduce((sum, input) => {
            return sum + parseFloat(input.value || 0);
        }, 0);
    };

    const reindexFeeRows = () => {
        if (!feeItemsContainer) {
            return;
        }

        feeItemsContainer.querySelectorAll('.fee-item-row').forEach((row, index) => {
            row.setAttribute('data-fee-index', String(index));

            const typeInput = row.querySelector('input[type="text"]');
            const amountInput = row.querySelector('.fee-amount-input');

            if (typeInput) {
                typeInput.setAttribute('name', `fee_items[${index}][type]`);
            }

            if (amountInput) {
                amountInput.setAttribute('name', `fee_items[${index}][amount]`);
            }
        });
    };

    const createFeeRow = (index) => {
        const row = document.createElement('div');
        row.className = 'fee-item-row border rounded-2 p-2 bg-white';
        row.setAttribute('data-fee-index', String(index));

        row.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <label class="form-label form-label-sm mb-1">Charge Type</label>
                    <input
                        type="text"
                        name="fee_items[${index}][type]"
                        class="form-control form-control-sm"
                        placeholder="e.g. Freight, Insurance, Handling"
                    >
                </div>
                <div class="col-10 col-md-4">
                    <label class="form-label form-label-sm mb-1">Amount</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text currency-symbol">${getCurrencySymbol()}</span>
                        <input
                            type="number"
                            name="fee_items[${index}][amount]"
                            class="form-control text-end fee-amount-input"
                            min="0"
                            step="0.01"
                            placeholder="0"
                        >
                    </div>
                </div>
                <div class="col-2 col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-fee-btn" aria-label="Remove charge">
                        <i class="fa-duotone fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        return row;
    };

    const updateTotals = () => {
        let subtotal = 0;
        let discountTotal = 0;
        let ppnTotal = 0;
        let pphTotal = 0;
        let itemsTotal = 0;

        updateCurrencySymbols();

        document.querySelectorAll('#po-preview-table tbody tr').forEach((row) => {
            const rowId = row.getAttribute('data-row');
            const qtyInput = row.querySelector(`.qty-input[data-row="${rowId}"]`);
            const priceInput = row.querySelector(`.price-input[data-row="${rowId}"]`);
            const discountInput = row.querySelector(`.discount-input[data-row="${rowId}"]`);
            const ppnInput = row.querySelector(`.ppn-input[data-row="${rowId}"]`);
            const pphInput = row.querySelector(`.pph-input[data-row="${rowId}"]`);
            const lineTotalEl = row.querySelector(`.line-total[data-row="${rowId}"]`);

            const qty = parseFloat(qtyInput.value || 0);
            const price = parseFloat(priceInput.value || 0);
            const discountRate = parseFloat(discountInput?.value || 0);
            const ppnRate = parseFloat(ppnInput?.value || 0);
            const pphRate = parseFloat(pphInput?.value || 0);
            const lineSubtotal = qty * price;
            const discountAmount = lineSubtotal * (discountRate / 100);
            const baseAmount = lineSubtotal - discountAmount;
            const ppnAmount = baseAmount * (ppnRate / 100);
            const pphAmount = baseAmount * (pphRate / 100);
            const lineTotal = baseAmount + ppnAmount - pphAmount;

            subtotal += lineSubtotal;
            discountTotal += discountAmount;
            ppnTotal += ppnAmount;
            pphTotal += pphAmount;
            itemsTotal += lineTotal;
            lineTotalEl.textContent = formatCurrency(lineTotal);
        });

        const fees = getFeeItemsTotal();
        const total = itemsTotal + fees;

        document.getElementById('subtotal').textContent = formatCurrency(subtotal);
        document.getElementById('discount-amount').textContent = formatSignedCurrency(-discountTotal);
        document.getElementById('ppn-amount').textContent = formatCurrency(ppnTotal);
        document.getElementById('pph-amount').textContent = formatSignedCurrency(-pphTotal);
        document.getElementById('fees-amount').textContent = formatCurrency(fees);
        document.getElementById('total').textContent = formatCurrency(total);
    };

    document.querySelectorAll('.qty-input, .price-input, .discount-input, .ppn-input, .pph-input').forEach((input) => {
        input.addEventListener('input', updateTotals);
    });

    if (feeItemsContainer) {
        feeItemsContainer.addEventListener('input', function (event) {
            if (event.target instanceof HTMLElement && event.target.classList.contains('fee-amount-input')) {
                updateTotals();
            }
        });

        feeItemsContainer.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const removeBtn = target.closest('.remove-fee-btn');
            if (!removeBtn) {
                return;
            }

            const row = removeBtn.closest('.fee-item-row');
            if (!row) {
                return;
            }

            const rows = feeItemsContainer.querySelectorAll('.fee-item-row');
            if (rows.length <= 1) {
                const typeInput = row.querySelector('input[type="text"]');
                const amountInput = row.querySelector('.fee-amount-input');
                if (typeInput) {
                    typeInput.value = '';
                }
                if (amountInput) {
                    amountInput.value = '';
                }
            } else {
                row.remove();
                reindexFeeRows();
            }

            updateTotals();
        });
    }

    if (addFeeBtn && feeItemsContainer) {
        addFeeBtn.addEventListener('click', function () {
            const nextIndex = feeItemsContainer.querySelectorAll('.fee-item-row').length;
            feeItemsContainer.appendChild(createFeeRow(nextIndex));
            reindexFeeRows();
            updateTotals();
        });
    }

    if (currencySelect) {
        currencySelect.addEventListener('change', updateTotals);
    }

    updateTotals();
});
