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

    function refreshRow(row) {
        const balance = parseFloat(row.dataset.balance || '0');
        const newInput = row.querySelector('.sa-new-balance');
        const currentEl = row.querySelector('.sa-current');
        const deltaEl = row.querySelector('.sa-delta');

        currentEl.textContent = StockCorrectionItemSearch.formatNumber(balance);

        if (newInput.value === '' && row.querySelector('.sc-item-id').value) {
            newInput.value = Number(balance).toFixed(5);
        }

        const neu = parseFloat(newInput.value || '0');
        const delta = neu - balance;
        const prefix = delta > 0 ? '+' : '';
        deltaEl.textContent = prefix + StockCorrectionItemSearch.formatNumber(delta);
        deltaEl.className = 'sc-delta ' + StockCorrectionItemSearch.deltaClass(delta);
        updateSubmitState();
    }

    function updateSubmitState() {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const hasDelta = rows.some((row) => {
            const itemId = row.querySelector('.sc-item-id')?.value;
            if (!itemId) return false;
            const balance = parseFloat(row.dataset.balance || '0');
            const neu = parseFloat(row.querySelector('.sa-new-balance')?.value || '0');
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
                if (item) {
                    selectedRow.querySelector('.sa-new-balance').value = Number(item.balance || 0).toFixed(5);
                } else {
                    selectedRow.querySelector('.sa-new-balance').value = '';
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
            if (!itemId) {
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
