document.addEventListener('DOMContentLoaded', function () {
    const currencySelect = document.getElementById('currency-id');

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

        const fees = parseFloat(document.getElementById('fees').value || 0);
        const total = itemsTotal + fees;

        document.getElementById('subtotal').textContent = formatCurrency(subtotal);
        document.getElementById('discount-amount').textContent = formatSignedCurrency(-discountTotal);
        document.getElementById('ppn-amount').textContent = formatCurrency(ppnTotal);
        document.getElementById('pph-amount').textContent = formatSignedCurrency(-pphTotal);
        document.getElementById('fees-amount').textContent = formatCurrency(fees);
        document.getElementById('total').textContent = formatCurrency(total);
    };

    document.querySelectorAll('.qty-input, .price-input, .discount-input, .ppn-input, .pph-input, #fees').forEach((input) => {
        input.addEventListener('input', updateTotals);
    });

    if (currencySelect) {
        currencySelect.addEventListener('change', updateTotals);
    }

    updateTotals();
});
