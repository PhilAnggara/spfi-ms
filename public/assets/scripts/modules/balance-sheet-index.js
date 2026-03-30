document.addEventListener('DOMContentLoaded', function () {
    const table = $('#balance-sheet-table');
    const tableUrl = table.data('source');
    const csrfToken = table.data('csrf-token');
    const updateRouteTemplate = table.data('update-route-template');
    const destroyRouteTemplate = table.data('destroy-route-template');
    const openCreateModal = table.data('open-create-modal') === '1';
    const editingId = table.data('editing-id');
    const editModalElement = document.getElementById('edit-modal');
    const editModal = editModalElement ? new bootstrap.Modal(editModalElement) : null;

    const escapeHtml = (value) => {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: tableUrl,
            type: 'GET'
        },
        order: [[0, 'desc']],
        columns: [
            {
                data: 'id',
                visible: false,
                searchable: false
            },
            {
                data: 'group_code',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: 'accounting_code',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: 'grouping_code',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: 'major',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    const safeGroupCode = escapeHtml(row.group_code ?? '-');
                    const safeAccountingCode = escapeHtml(row.accounting_code ?? '-');
                    const editAttrs = `
                        data-id="${row.id}"
                        data-group-code-id="${row.group_code_id ?? ''}"
                        data-accounting-code-id="${row.accounting_code_id ?? ''}"
                        data-grouping-id="${row.grouping_id ?? ''}"
                        data-major="${escapeHtml(row.major ?? '')}"
                        data-group-code="${safeGroupCode}"
                        data-accounting-code="${safeAccountingCode}"
                    `;

                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn icon edit-balance-sheet" ${editAttrs} data-bs-toggle="modal" data-bs-target="#edit-modal" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                <i class="fa-light fa-edit text-primary"></i>
                            </button>
                            <button type="button" class="btn icon delete-balance-sheet" data-id="${row.id}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                <i class="fa-light fa-trash text-secondary"></i>
                            </button>
                            <form action="${destroyRouteTemplate.replace('__ID__', row.id)}" id="hapus-${row.id}" method="POST">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="delete">
                            </form>
                        </div>
                    `;
                }
            }
        ]
    });

    $('#balance-sheet-table tbody').on('click', '.edit-balance-sheet', function () {
        const button = $(this);
        const itemId = button.data('id');
        const updateUrl = updateRouteTemplate.replace('__ID__', itemId);
        const setSelectValue = (elementId, value) => {
            const selectElement = document.getElementById(elementId);
            if (!selectElement) {
                return;
            }

            const normalizedValue = value === null || value === undefined ? '' : String(value);
            if (selectElement.choicesInstance) {
                selectElement.choicesInstance.removeActiveItems();
                if (normalizedValue) {
                    selectElement.choicesInstance.setChoiceByValue(normalizedValue);
                }
            } else {
                selectElement.value = normalizedValue;
            }

            selectElement.dispatchEvent(new Event('change'));
        };

        setSelectValue('edit-group-code-id', button.data('group-code-id'));
        setSelectValue('edit-accounting-code-id', button.data('accounting-code-id'));
        setSelectValue('edit-grouping-id', button.data('grouping-id'));
        document.getElementById('edit-major').value = button.data('major') || '';
        document.getElementById('edit-form').setAttribute('action', updateUrl);

        const editLabel = document.getElementById('edit-modal-label');
        if (editLabel) {
            editLabel.textContent = `Edit Mapping - ${button.data('group-code') || ''} / ${button.data('accounting-code') || ''}`;
        }

        if (editModal) {
            editModal.show();
        }
    });

    $('#balance-sheet-table tbody').on('click', '.delete-balance-sheet', function () {
        const itemId = $(this).data('id');
        hapusData(itemId, 'Delete Mapping', 'Are you sure want to delete this mapping?');
    });

    if (openCreateModal) {
        if (editingId) {
            if (editModal) {
                editModal.show();
            }
        } else {
            const createModal = new bootstrap.Modal(document.getElementById('create-modal'));
            createModal.show();
        }
    }
});
