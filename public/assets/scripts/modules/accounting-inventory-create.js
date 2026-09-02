document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('inv-create-page');
    if (!page || !window.StockCorrectionItemSearch) {
        return;
    }

    const searchUrl = page.dataset.searchUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
    const linesBody = document.getElementById('inv-lines');
    const template = document.getElementById('inv-row-template');
    const addBtn = document.getElementById('inv-add-row');
    const categorySelect = document.getElementById('inv-category-id');
    let rowIndex = 0;

    function getSearchUrl() {
        const url = new URL(searchUrl, window.location.origin);
        const categoryId = categorySelect?.value || '';
        if (categoryId) {
            url.searchParams.set('category_id', categoryId);
        }
        return url.pathname + url.search;
    }

    function recalcRow(row) {
        const qty = parseFloat(row.querySelector('.inv-qty')?.value || '0');
        const cost = parseFloat(row.querySelector('.inv-cost')?.value || '0');
        const amount = Math.round(qty * cost * 100) / 100;
        const amountEl = row.querySelector('.inv-amount');
        const amountInput = row.querySelector('.inv-amount-input');
        if (amountEl) {
            amountEl.textContent = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (amountInput) {
            amountInput.value = amount.toFixed(2);
        }
    }

    function bindRow(row) {
        row.querySelector('.inv-qty')?.addEventListener('input', () => recalcRow(row));
        row.querySelector('.inv-cost')?.addEventListener('input', () => recalcRow(row));
        row.querySelector('.inv-remove-row')?.addEventListener('click', () => row.remove());

        StockCorrectionItemSearch.bindPicker(row, {
            searchUrl: getSearchUrl(),
            csrf,
            onSelect: (item) => {
                if (!item) {
                    row.querySelector('.inv-available').textContent = '0.00000';
                    return;
                }

                row.querySelector('.inv-available').textContent = Number(item.balance || 0).toFixed(5);
                const uomInput = row.querySelector('.inv-uom-id');
                if (uomInput) {
                    uomInput.value = item.unit_of_measure_id || '';
                }
                const costInput = row.querySelector('.inv-cost');
                if (costInput && !costInput.value && item.unit_cost) {
                    costInput.value = Number(item.unit_cost).toFixed(4);
                }
                recalcRow(row);
            },
        });
    }

    function addRow() {
        if (!template || !linesBody) {
            return;
        }

        const html = template.innerHTML.replace(/__INDEX__/g, String(rowIndex++));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        linesBody.appendChild(row);
        bindRow(row);
    }

    addBtn?.addEventListener('click', addRow);
    categorySelect?.addEventListener('change', () => {
        linesBody.innerHTML = '';
        rowIndex = 0;
        addRow();
    });

    addRow();
});
