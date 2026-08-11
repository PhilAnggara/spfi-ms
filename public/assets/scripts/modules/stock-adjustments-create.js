document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('sa-create-page');
    if (!page || !window.StockCorrectionItemSearch) {
        return;
    }

    const searchUrl = page.dataset.searchUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
    const tbody = document.getElementById('sa-lines');
    const template = document.getElementById('sa-row-template').innerHTML;
    const submitBtn = document.getElementById('sa-submit-btn');
    let index = 0;

    function clearDelta(deltaEl) {
        if (!deltaEl) {
            return;
        }
        deltaEl.textContent = '-';
        deltaEl.classList.remove('is-up', 'is-down', 'is-zero');
        deltaEl.classList.add('sc-delta', 'sa-delta', 'is-zero');
    }

    function refreshRow(row) {
        const balance = parseFloat(row.dataset.balance || '0');
        const newInput = row.querySelector('.sa-new-balance');
        const currentEl = row.querySelector('.sa-current');
        const deltaEl = row.querySelector('.sa-delta');

        currentEl.textContent = row.querySelector('.sc-item-id')?.value
            ? StockCorrectionItemSearch.formatNumber(balance)
            : '0.00';

        if (newInput.value === '') {
            clearDelta(deltaEl);
            updateSubmitState();
            return;
        }

        const neu = parseFloat(newInput.value || '0');
        const delta = neu - balance;
        StockCorrectionItemSearch.applyDeltaBadge(deltaEl, delta, 'sa-delta');
        updateSubmitState();
    }

    function updateSubmitState() {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const hasDelta = rows.some((row) => {
            const itemId = row.querySelector('.sc-item-id')?.value;
            const raw = row.querySelector('.sa-new-balance')?.value;
            if (!itemId || raw === '') return false;
            const balance = parseFloat(row.dataset.balance || '0');
            const neu = parseFloat(raw || '0');
            return Math.abs(neu - balance) >= 0.00001;
        });
        submitBtn.disabled = !hasDelta;
    }

    function addRow() {
        const html = template.replaceAll('__INDEX__', String(index++));
        tbody.insertAdjacentHTML('beforeend', html);
        const row = tbody.lastElementChild;

        StockCorrectionItemSearch.bindPicker(row, {
            searchUrl,
            csrf,
            onSelect(item, selectedRow) {
                selectedRow.querySelector('.sa-new-balance').value = '';
                if (item) {
                    selectedRow.dataset.balance = String(item.balance ?? 0);
                } else {
                    selectedRow.dataset.balance = '0';
                }
                refreshRow(selectedRow);
            },
        });

        row.querySelector('.sa-new-balance').addEventListener('input', function () {
            refreshRow(row);
        });
        row.querySelector('.sa-remove-row').addEventListener('click', function () {
            row.remove();
            updateSubmitState();
        });

        refreshRow(row);
    }

    document.getElementById('sa-add-row').addEventListener('click', addRow);
    document.getElementById('sa-create-form').addEventListener('submit', function (event) {
        Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
            const itemId = row.querySelector('.sc-item-id')?.value;
            const raw = row.querySelector('.sa-new-balance')?.value;
            if (!itemId || raw === '') {
                row.remove();
            }
        });

        updateSubmitState();
        if (submitBtn.disabled || !tbody.querySelector('tr')) {
            event.preventDefault();
            alert('Add at least one item with a balance change.');
        }
    });

    addRow();
});
