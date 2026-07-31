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

        // Keep the submitted value when reopening after a validation failure.
        if (modalEl.getAttribute('data-auto-show') === '1') {
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

    function showPrintConfirmModal(modalEl) {
        if (!window.bootstrap?.Modal) {
            return;
        }

        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        const numberInput = modalEl.querySelector('.document-print-confirm-number, .po-print-confirm-number');

        modalEl.addEventListener('shown.bs.modal', function onShown() {
            modalEl.removeEventListener('shown.bs.modal', onShown);

            if (numberInput instanceof HTMLElement) {
                numberInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                numberInput.focus();
            }
        });

        modal.show();
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

        // Closing happens before the response; failed validation reopens via data-auto-show.
        closePrintConfirmModal(form);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.modal[data-auto-show="1"]').forEach((modalEl) => {
            if (!isPrintConfirmModal(modalEl)) {
                return;
            }

            showPrintConfirmModal(modalEl);
        });
    });
})();
