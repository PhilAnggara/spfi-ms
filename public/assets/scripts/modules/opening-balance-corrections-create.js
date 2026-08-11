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
    const summary = document.getElementById('obc-preview-summary');
    const summaryDelta = document.getElementById('obc-summary-delta');
    const summaryReplay = document.getElementById('obc-summary-replay');
    const periodInput = document.getElementById('obc-period');
    let index = 0;
    let previewSeq = 0;

    function clearDeltaBadge(deltaEl) {
        deltaEl.textContent = '-';
        deltaEl.classList.remove('is-up', 'is-down', 'is-zero');
        deltaEl.classList.add('sc-delta', 'obc-delta', 'is-zero');
    }

    function clearRowPreview(row) {
        row.dataset.implied = '';
        row.dataset.delta = '';
        row.dataset.replay = '';
        row.querySelector('.obc-implied').textContent = '-';
        row.querySelector('.obc-replay').textContent = '-';
        clearDeltaBadge(row.querySelector('.obc-delta'));
    }

    function updateSummary() {
        let totalDelta = 0;
        let totalReplay = 0;
        let hasData = false;

        tbody.querySelectorAll('tr').forEach(function (row) {
            if (row.dataset.implied === '' || row.dataset.implied === undefined) {
                return;
            }
            hasData = true;
            if (row.dataset.delta !== '') {
                totalDelta += parseFloat(row.dataset.delta || '0');
            }
            totalReplay += parseInt(row.dataset.replay || '0', 10);
        });

        if (!hasData) {
            summary.classList.remove('is-visible');
            return;
        }

        summaryDelta.textContent = (totalDelta > 0 ? '+' : '') + StockCorrectionItemSearch.formatNumber(totalDelta);
        summaryReplay.textContent = String(totalReplay);
        summary.classList.add('is-visible');
    }

    async function fetchRowPreview(row) {
        const itemId = row.querySelector('.sc-item-id')?.value;
        const period = periodInput.value;
        const beginningInput = row.querySelector('.obc-new-beginning');
        const beginningRaw = beginningInput.value;
        const hasBeginning = beginningRaw !== '';

        if (!itemId || !period) {
            clearRowPreview(row);
            updateSummary();
            return;
        }

        // When New Beginning is still empty, request with 0 only to resolve implied begin + replay count.
        const beginningForRequest = hasBeginning ? beginningRaw : '0';

        const seq = ++previewSeq;
        row.dataset.previewSeq = String(seq);
        row.querySelector('.obc-replay').textContent = '…';

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    period_month: period,
                    items: [{
                        item_id: parseInt(itemId, 10),
                        new_beginning: beginningForRequest,
                        wh_code: 'MAIN',
                    }],
                }),
            });

            if (!response.ok) {
                if (row.dataset.previewSeq === String(seq)) {
                    row.querySelector('.obc-replay').textContent = '!';
                }
                return;
            }

            const data = await response.json();
            const preview = (data.previews || [])[0];
            if (!preview || row.dataset.previewSeq !== String(seq)) {
                return;
            }

            row.dataset.implied = String(preview.previous_beginning ?? 0);
            row.dataset.replay = String(preview.replay_count ?? 0);
            row.querySelector('.obc-implied').textContent = StockCorrectionItemSearch.formatNumber(preview.previous_beginning);
            row.querySelector('.obc-replay').textContent = String(preview.replay_count ?? 0);

            if (hasBeginning) {
                const delta = Number(preview.delta_qty || 0);
                row.dataset.delta = String(delta);
                StockCorrectionItemSearch.applyDeltaBadge(row.querySelector('.obc-delta'), delta, 'obc-delta');
            } else {
                row.dataset.delta = '';
                clearDeltaBadge(row.querySelector('.obc-delta'));
            }

            updateSummary();
        } catch (error) {
            if (row.dataset.previewSeq === String(seq)) {
                row.querySelector('.obc-replay').textContent = '!';
            }
        }
    }

    function scheduleRowPreview(row) {
        if (!row._obcPreviewDebounced) {
            row._obcPreviewDebounced = StockCorrectionItemSearch.debounce(function () {
                fetchRowPreview(row);
            }, 300);
        }
        row._obcPreviewDebounced();
    }

    function refreshAllRows() {
        tbody.querySelectorAll('tr').forEach(function (row) {
            if (row.querySelector('.sc-item-id')?.value) {
                scheduleRowPreview(row);
            }
        });
    }

    function addRow() {
        const html = template.replaceAll('__INDEX__', String(index++));
        tbody.insertAdjacentHTML('beforeend', html);
        const row = tbody.lastElementChild;

        StockCorrectionItemSearch.bindPicker(row, {
            searchUrl,
            csrf,
            onSelect(item, selectedRow) {
                selectedRow.querySelector('.obc-new-beginning').value = '';
                if (item) {
                    selectedRow.dataset.balance = String(item.balance ?? 0);
                    selectedRow.querySelector('.obc-current').textContent = StockCorrectionItemSearch.formatNumber(item.balance);
                    scheduleRowPreview(selectedRow);
                } else {
                    selectedRow.dataset.balance = '0';
                    selectedRow.querySelector('.obc-current').textContent = '0.00';
                    clearRowPreview(selectedRow);
                    updateSummary();
                }
            },
        });

        row.querySelector('.obc-new-beginning').addEventListener('input', function () {
            if (row.querySelector('.sc-item-id')?.value) {
                scheduleRowPreview(row);
            }
        });

        row.querySelector('.obc-remove-row').addEventListener('click', function () {
            row.remove();
            updateSummary();
        });
    }

    periodInput.addEventListener('change', refreshAllRows);
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
