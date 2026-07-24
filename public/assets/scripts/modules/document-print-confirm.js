(function () {
    const modalPrefixes = [
        'poPrintConfirm-',
        'tsPrintConfirm-',
        'rrPrintConfirm-',
    ];

    function isPrintConfirmModal(modalEl) {
        if (!(modalEl instanceof HTMLElement) || !modalEl.id) {
            return false;
        }

        return modalPrefixes.some((prefix) => modalEl.id.startsWith(prefix));
    }

    function syncPrintConfirmNumber(modalEl) {
        const form = modalEl.querySelector('.document-print-confirm-form, .po-print-confirm-form');
        if (!form) {
            return;
        }

        const syncFromId = form.getAttribute('data-sync-from');
        if (!syncFromId) {
            return;
        }

        const sourceInput = document.getElementById(syncFromId);
        const targetInput = form.querySelector('.document-print-confirm-number, .po-print-confirm-number');

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
        if (!isPrintConfirmModal(modalEl)) {
            return;
        }

        syncPrintConfirmNumber(modalEl);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (
            !(form instanceof HTMLFormElement)
            || (
                !form.classList.contains('document-print-confirm-form')
                && !form.classList.contains('po-print-confirm-form')
            )
        ) {
            return;
        }

        closePrintConfirmModal(form);
    });
})();
