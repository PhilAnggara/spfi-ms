(function () {
    function syncPrintConfirmNumber(modalEl) {
        const form = modalEl.querySelector('.po-print-confirm-form');
        if (!form) {
            return;
        }

        const syncFromId = form.getAttribute('data-sync-from');
        if (!syncFromId) {
            return;
        }

        const sourceInput = document.getElementById(syncFromId);
        const targetInput = form.querySelector('.po-print-confirm-number');

        if (!sourceInput || !targetInput) {
            return;
        }

        const sourceValue = String(sourceInput.value || '').trim();
        if (sourceValue !== '') {
            targetInput.value = sourceValue;
        }
    }

    function closePrintConfirmModal(form) {
        const modalEl = form.closest('.modal');
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }

        window.bootstrap.Modal.getInstance(modalEl)?.hide();
    }

    document.addEventListener('show.bs.modal', function (event) {
        const modalEl = event.target;
        if (!(modalEl instanceof HTMLElement) || !modalEl.id.startsWith('poPrintConfirm-')) {
            return;
        }

        syncPrintConfirmNumber(modalEl);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('po-print-confirm-form')) {
            return;
        }

        closePrintConfirmModal(form);
    });
})();
