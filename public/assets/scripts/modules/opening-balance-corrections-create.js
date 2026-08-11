document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('obc-create-page');
    if (!page || !window.StockCorrectionItemSearch) {
        return;
    }

    const searchUrl = page.dataset.searchUrl;
    const previewUrl = page.dataset.previewUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
    const tbody = document.getElementById('obc-lines');
    const template = document.getElementById('obc-row-template').innerHTML;
    const previewBtn = document.getElementById('obc-preview-btn');
    const summary = document.getElementById('obc-preview-summary');
    const summaryDelta = document.getElementById('obc-summary-delta');
    const summaryReplay = document.getElementById('obc-summary-replay');
    let index = 0;

    function addRow() {
        const html = template.replaceAll('__INDEX__', String(index++));
        tbody.insertAdjacentHTML('beforeend', html);
        const row = tbody.lastElementChild;

        StockCorrectionItemSearch.bindPicker(row, {
            searchUrl,
            csrf,
            onSelect(item, selectedRow) {
                selectedRow.querySelector('.obc-current').textContent = item
                    ? StockCorrectionItemSearch.formatNumber(item.balance)
                    : '0.00';
            },
        });

        row.querySelector('.obc-remove-row').addEventListener('click', function () {
            row.remove();
        });
    }

    previewBtn.addEventListener('click', async function () {
        const period = document.getElementById('obc-period').value;
        const payloadItems = [];

        tbody.querySelectorAll('tr').forEach(function (row) {
            const itemId = row.querySelector('.sc-item-id').value;
            const beginning = row.querySelector('.obc-new-beginning').value;
            if (!itemId || beginning === '') return;
            payloadItems.push({
                item_id: parseInt(itemId, 10),
                new_beginning: beginning,
                wh_code: 'MAIN',
            });
        });

        if (!period || payloadItems.length === 0) {
            alert('Select a period and at least one item with a new beginning.');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<i class="fa-regular fa-spinner-third fa-spin me-1"></i> Previewing…';

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ period_month: period, items: payloadItems }),
            });

            if (!response.ok) {
                alert('Preview failed. Check your inputs.');
                return;
            }

            const data = await response.json();
            const byItem = {};
            (data.previews || []).forEach(function (p) {
                byItem[String(p.item_id)] = p;
            });

            let totalDelta = 0;
            let totalReplay = 0;

            tbody.querySelectorAll('tr').forEach(function (row) {
                const itemId = row.querySelector('.sc-item-id').value;
                const preview = byItem[String(itemId)];
                if (!preview) return;

                row.querySelector('.obc-implied').textContent = StockCorrectionItemSearch.formatNumber(preview.previous_beginning);
                const delta = Number(preview.delta_qty || 0);
                const deltaEl = row.querySelector('.obc-delta');
                deltaEl.textContent = (delta > 0 ? '+' : '') + StockCorrectionItemSearch.formatNumber(delta);
                deltaEl.className = 'sc-delta ' + StockCorrectionItemSearch.deltaClass(delta);
                row.querySelector('.obc-replay').textContent = String(preview.replay_count);
                totalDelta += delta;
                totalReplay += Number(preview.replay_count || 0);
            });

            summaryDelta.textContent = (totalDelta > 0 ? '+' : '') + StockCorrectionItemSearch.formatNumber(totalDelta);
            summaryReplay.textContent = String(totalReplay);
            summary.classList.add('is-visible');
        } finally {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="fa-regular fa-eye me-1"></i> Preview Impact';
        }
    });

    document.getElementById('obc-add-row').addEventListener('click', addRow);

    document.getElementById('obc-create-form').addEventListener('submit', function (event) {
        Array.from(tbody.querySelectorAll('tr')).forEach(function (row) {
            const itemId = row.querySelector('.sc-item-id')?.value;
            const beginning = row.querySelector('.obc-new-beginning')?.value;
            if (!itemId || beginning === '') {
                row.remove();
            }
        });

        if (!tbody.querySelector('tr')) {
            event.preventDefault();
            alert('Add at least one item with a new beginning.');
        }
    });

    addRow();
});
