document.addEventListener('DOMContentLoaded', function () {
    const pages = document.querySelectorAll('[data-modal-restore="true"]');
    if (!pages.length || !window.bootstrap || !window.bootstrap.Modal) {
        return;
    }

    pages.forEach((page) => {
        const shouldOpenCreate = page.dataset.openCreateModal === '1';
        if (!shouldOpenCreate) {
            return;
        }

        const editModalId = page.dataset.editModalId || '';
        const altModalId = page.dataset.altModalId || '';
        const createModalId = page.dataset.createModalId || 'create-modal';

        const modalId = editModalId || altModalId || createModalId;
        if (!modalId) {
            return;
        }

        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            return;
        }

        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
    });
});
