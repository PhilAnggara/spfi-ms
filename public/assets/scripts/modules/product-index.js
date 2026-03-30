document.addEventListener('DOMContentLoaded', function () {
    const table = $('#product-table');
    if (!table.length) {
        return;
    }

    const tableUrl = table.data('source');
    const csrfToken = table.data('csrfToken');
    const updateRouteTemplate = table.data('updateRouteTemplate');
    const destroyRouteTemplate = table.data('destroyRouteTemplate');
    const openCreateModal = table.data('openCreateModal') === 1 || table.data('openCreateModal') === '1';
    const editingProductId = String(table.data('editingProductId') || '');

    const editModalElement = document.getElementById('edit-modal');
    const editModal = editModalElement && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(editModalElement)
        : null;

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

    const dataTable = table.DataTable({
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
                data: 'code',
                render: function (data) {
                    const safeCode = escapeHtml(data ?? '-');
                    return `
                        <button class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill copy-code" data-code="${safeCode}">
                            <i class="fa-solid fa-regular fa-clipboard"></i>
                            ${safeCode}
                        </button>
                    `;
                }
            },
            {
                data: 'name',
                render: function (data) {
                    const rawName = data ?? '-';
                    const safeName = escapeHtml(rawName);
                    const displayName = rawName.length > 30 ? `${escapeHtml(rawName.slice(0, 30))}...` : safeName;
                    return `<span class="copy-name" data-name="${safeName}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="${safeName}" style="cursor: pointer">${displayName}</span>`;
                }
            },
            {
                data: 'unit_name',
                render: function (data) {
                    return `<span class="badge bg-light-secondary">${escapeHtml(data ?? '-')}</span>`;
                }
            },
            {
                data: 'category_name',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: 'type',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (row) {
                    const safeName = escapeHtml(row.name ?? '-');
                    const editAttrs = `
                        data-id="${row.id}"
                        data-code="${escapeHtml(row.code ?? '')}"
                        data-name="${safeName}"
                        data-unit-id="${row.unit_of_measurement_id ?? ''}"
                        data-category-id="${row.category_id ?? ''}"
                        data-type="${escapeHtml(row.type ?? '')}"
                    `;

                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn icon edit-product" ${editAttrs} data-bs-toggle="modal" data-bs-target="#edit-modal" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                <i class="fa-light fa-edit text-primary"></i>
                            </button>
                            <button type="button" class="btn icon delete-product" data-id="${row.id}" data-name="${safeName}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
                                <i class="fa-light fa-trash text-secondary"></i>
                            </button>
                            <form action="${String(destroyRouteTemplate || '').replace('__ID__', row.id)}" id="hapus-${row.id}" method="POST">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="delete">
                            </form>
                        </div>
                    `;
                }
            }
        ]
    });

    $('#product-table tbody').on('click', '.edit-product', function () {
        const button = $(this);
        const itemId = button.data('id');
        const updateUrl = String(updateRouteTemplate || '').replace('__ID__', itemId);

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

        document.getElementById('edit-code').value = button.data('code') || '';
        document.getElementById('edit-name').value = button.data('name') || '';
        setSelectValue('edit-unit', button.data('unit-id'));
        setSelectValue('edit-category', button.data('category-id'));
        setSelectValue('edit-type', button.data('type'));
        document.getElementById('edit-form').setAttribute('action', updateUrl);

        const editLabel = document.getElementById('editProductLabel');
        if (editLabel) {
            editLabel.textContent = `Edit Product - ${button.data('name') || ''}`;
        }

        if (editModal) {
            editModal.show();
        }
    });

    $('#product-table tbody').on('click', '.copy-code', function () {
        const code = $(this).data('code');
        copyToClipboard(code);
    });

    $('#product-table tbody').on('click', '.copy-name', function () {
        const name = $(this).data('name');
        copyToClipboard(name);
    });

    $('#product-table tbody').on('click', '.delete-product', function () {
        const itemId = $(this).data('id');
        const name = $(this).data('name');
        hapusData(itemId, 'Delete Product', `Are you sure want to delete ${name}?`);
    });

    if (openCreateModal) {
        if (editingProductId && editModal) {
            editModal.show();
        } else if (window.bootstrap && window.bootstrap.Modal) {
            const createModalElement = document.getElementById('create-modal');
            if (createModalElement) {
                const createModal = new window.bootstrap.Modal(createModalElement);
                createModal.show();
            }
        }
    }
});
