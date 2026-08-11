document.addEventListener('DOMContentLoaded', function () {
    const table = $('#product-table');
    if (!table.length) {
        return;
    }

    const tableUrl = table.data('source');
    const csrfToken = table.data('csrfToken');
    const updateRouteTemplate = table.data('updateRouteTemplate');
    const destroyRouteTemplate = table.data('destroyRouteTemplate');
    const historyRouteTemplate = table.data('historyRouteTemplate');
    const poShowRouteTemplate = table.data('poShowRouteTemplate');
    const canManage = table.data('canManage') === 1 || table.data('canManage') === '1';
    const canCreate = table.data('canCreate') === 1 || table.data('canCreate') === '1';
    const canViewPo = table.data('canViewPo') === 1 || table.data('canViewPo') === '1';
    const openCreateModal = table.data('openCreateModal') === 1 || table.data('openCreateModal') === '1';
    const editingProductId = String(table.data('editingProductId') || '');

    const filterForm = document.getElementById('product-filter-form');
    const loadingEl = document.getElementById('product-page-loading');
    const resultBadge = document.getElementById('product-filter-result');

    const filterElements = {
        keyword: document.getElementById('filter-product-keyword'),
        category: document.getElementById('filter-product-category'),
        unit: document.getElementById('filter-product-unit'),
        type: document.getElementById('filter-product-type'),
        sort: document.getElementById('filter-product-sort'),
        reset: document.getElementById('reset-product-filter'),
    };

    const DEFAULT_SORT = 'name_asc';
    const SORT_OPTIONS = {
        name_asc: { column: 2, dir: 'asc' },
        name_desc: { column: 2, dir: 'desc' },
        code_asc: { column: 1, dir: 'asc' },
        code_desc: { column: 1, dir: 'desc' },
        category_asc: { column: 4, dir: 'asc' },
        category_desc: { column: 4, dir: 'desc' },
        avg_unit_price_asc: { column: 6, dir: 'asc' },
        avg_unit_price_desc: { column: 6, dir: 'desc' },
    };
    const COLUMN_TO_SORT = {
        '2:asc': 'name_asc',
        '2:desc': 'name_desc',
        '1:asc': 'code_asc',
        '1:desc': 'code_desc',
        '4:asc': 'category_asc',
        '4:desc': 'category_desc',
        '6:asc': 'avg_unit_price_asc',
        '6:desc': 'avg_unit_price_desc',
    };

    const editModalElement = document.getElementById('edit-modal');
    const editModal = editModalElement && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(editModalElement)
        : null;

    const historyModalElement = document.getElementById('product-purchase-history-modal');
    const historyModal = historyModalElement && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(historyModalElement)
        : null;

    let historyDataTable = null;
    let productDataTable = null;
    let filterDebounceTimer = null;

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

    const formatDate = (value) => {
        if (!value) {
            return '-';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return escapeHtml(value);
        }
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const formatNumber = (value) => {
        const number = Number(value);
        if (Number.isNaN(number)) {
            return '-';
        }
        return number.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const setLoading = (active) => {
        if (!loadingEl) {
            return;
        }
        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    };

    const setQueryParam = (searchParams, key, value) => {
        const normalizedValue = String(value || '').trim();
        if (normalizedValue === '') {
            searchParams.delete(key);
            return;
        }
        searchParams.set(key, normalizedValue);
    };

    const syncFilterUrl = (pushState = false) => {
        const url = new URL(window.location.href);
        setQueryParam(url.searchParams, 'keyword', filterElements.keyword?.value);
        setQueryParam(url.searchParams, 'category_id', filterElements.category?.value);
        setQueryParam(url.searchParams, 'unit_id', filterElements.unit?.value);
        setQueryParam(url.searchParams, 'type', filterElements.type?.value);
        setQueryParam(url.searchParams, 'sort', resolveSortValue(filterElements.sort?.value));

        if (pushState) {
            window.history.pushState({}, '', url.toString());
        } else {
            window.history.replaceState({}, '', url.toString());
        }
    };

    const resolveSortValue = (value) => {
        const normalized = String(value || '').trim();
        return Object.prototype.hasOwnProperty.call(SORT_OPTIONS, normalized) ? normalized : DEFAULT_SORT;
    };

    const resolveSortOrder = (value) => SORT_OPTIONS[resolveSortValue(value)];

    const getFilterPayload = () => ({
        keyword: filterElements.keyword?.value?.trim() || '',
        category_id: filterElements.category?.value || '',
        unit_id: filterElements.unit?.value || '',
        type: filterElements.type?.value || '',
    });

    const applyFilters = (useDebounce = false) => {
        const reload = () => {
            syncFilterUrl(true);
            if (productDataTable) {
                productDataTable.ajax.reload();
            }
        };

        if (!useDebounce) {
            reload();
            return;
        }

        clearTimeout(filterDebounceTimer);
        filterDebounceTimer = setTimeout(reload, 350);
    };

    const applySort = () => {
        const sortOrder = resolveSortOrder(filterElements.sort?.value);
        if (filterElements.sort) {
            filterElements.sort.value = resolveSortValue(filterElements.sort.value);
        }
        syncFilterUrl(true);
        if (productDataTable) {
            productDataTable.order([[sortOrder.column, sortOrder.dir]]).draw();
        }
    };

    const syncSortDropdownFromTable = () => {
        if (!productDataTable || !filterElements.sort) {
            return;
        }

        const order = productDataTable.order();
        if (!Array.isArray(order) || !order.length) {
            return;
        }

        const [column, dir] = order[0];
        const sortValue = COLUMN_TO_SORT[`${column}:${dir}`];
        if (!sortValue) {
            return;
        }

        if (filterElements.sort.value !== sortValue) {
            filterElements.sort.value = sortValue;
            syncFilterUrl(true);
        }
    };

    const destroyHistoryTable = () => {
        if (historyDataTable) {
            historyDataTable.destroy();
            historyDataTable = null;
        }
        $('#product-purchase-history-table tbody').empty();
        updateAvgSummary([]);
    };

    const updateAvgSummary = (avgUnitPrices) => {
        const summaryEl = document.getElementById('product-purchase-history-summary');
        const summaryBodyEl = document.getElementById('product-purchase-history-summary-body');
        if (!summaryEl || !summaryBodyEl) {
            return;
        }

        const rows = Array.isArray(avgUnitPrices) ? avgUnitPrices : [];
        if (!rows.length) {
            summaryEl.classList.add('d-none');
            summaryBodyEl.innerHTML = '';
            return;
        }

        summaryBodyEl.innerHTML = rows.map((row) => {
            const currency = escapeHtml(row.currency ?? '-');
            const price = formatNumber(row.avg_unit_price);
            return `<span class="badge bg-light-primary fs-6">${currency} ${price}</span>`;
        }).join('');

        summaryEl.classList.remove('d-none');
    };

    const initHistoryTable = (itemId) => {
        destroyHistoryTable();

        const historyUrl = String(historyRouteTemplate || '').replace('__ID__', itemId);

        historyDataTable = $('#product-purchase-history-table').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ajax: {
                url: historyUrl,
                type: 'GET',
                dataSrc: function (json) {
                    updateAvgSummary(json.avgUnitPrices);
                    return json.data;
                },
            },
            order: [[1, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: 'No purchase history found for this item.',
                zeroRecords: 'No purchase history found for this item.',
            },
            columns: [
                {
                    data: 'po_number',
                    render: function (data, type, row) {
                        const safeNumber = escapeHtml(data ?? '-');
                        if (!canViewPo || !row.purchase_order_id) {
                            return safeNumber;
                        }
                        const poUrl = String(poShowRouteTemplate || '').replace('__ID__', row.purchase_order_id);
                        return `<a href="${poUrl}" class="text-primary fw-semibold">${safeNumber}</a>`;
                    },
                },
                {
                    data: 'po_date',
                    render: function (data) {
                        return formatDate(data);
                    },
                },
                {
                    data: 'currency',
                    render: function (data) {
                        return `<span class="badge bg-light-secondary">${escapeHtml(data ?? '-')}</span>`;
                    },
                },
                {
                    data: 'supplier_code',
                    render: function (data) {
                        return escapeHtml(data ?? '-');
                    },
                },
                {
                    data: 'supplier_name',
                    render: function (data) {
                        const safeName = escapeHtml(data ?? '-');
                        return `<span class="text-start d-inline-block text-truncate" style="max-width: 220px;" title="${safeName}">${safeName}</span>`;
                    },
                },
                {
                    data: 'quantity',
                    className: 'text-end',
                    render: function (data) {
                        return formatNumber(data);
                    },
                },
                {
                    data: 'unit_price',
                    className: 'text-end',
                    render: function (data, type, row) {
                        const currency = escapeHtml(row.currency ?? '');
                        return `${currency} ${formatNumber(data)}`;
                    },
                },
                {
                    data: 'canvasser',
                    render: function (data) {
                        return escapeHtml(data ?? '-');
                    },
                },
            ],
        });
    };

    const initialSortOrder = resolveSortOrder(filterElements.sort?.value);

    productDataTable = table.DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 10,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6 text-end'>>rtip",
        language: {
            emptyTable: 'No products found.',
            zeroRecords: 'No products found. Try changing your keyword or filters.',
        },
        ajax: {
            url: tableUrl,
            type: 'GET',
            data: function (data) {
                const filters = getFilterPayload();
                data.keyword = filters.keyword;
                data.category_id = filters.category_id;
                data.unit_id = filters.unit_id;
                data.type = filters.type;
            },
            beforeSend: function () {
                setLoading(true);
            },
            complete: function () {
                setLoading(false);
            },
        },
        order: [[initialSortOrder.column, initialSortOrder.dir]],
        columns: [
            {
                data: 'id',
                visible: false,
                searchable: false,
            },
            {
                data: 'code',
                render: function (data) {
                    const safeCode = escapeHtml(data ?? '-');
                    return `
                        <button type="button" class="btn btn-sm icon icon-left btn-outline-secondary rounded-pill copy-code" data-code="${safeCode}">
                            <i class="fa-solid fa-regular fa-clipboard"></i>
                            ${safeCode}
                        </button>
                    `;
                },
            },
            {
                data: 'name',
                render: function (data) {
                    const rawName = data ?? '-';
                    const safeName = escapeHtml(rawName);
                    const displayName = rawName.length > 80 ? `${escapeHtml(rawName.slice(0, 80))}...` : safeName;
                    return `<span class="copy-name" data-name="${safeName}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="${safeName}" style="cursor: pointer">${displayName}</span>`;
                },
            },
            {
                data: 'unit_name',
                render: function (data) {
                    return `<span class="badge bg-light-secondary">${escapeHtml(data ?? '-')}</span>`;
                },
            },
            {
                data: 'category_name',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                },
            },
            {
                data: 'type',
                render: function (data) {
                    return escapeHtml(data ?? '-');
                },
            },
            {
                data: 'avg_unit_price',
                className: 'text-end',
                render: function (data, type, row) {
                    if (data === null || data === undefined || data === '') {
                        return '<span class="text-muted">-</span>';
                    }
                    const currency = escapeHtml(row.avg_price_currency ?? '');
                    return `<span class="fw-semibold">${currency} ${formatNumber(data)}</span>`;
                },
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (row) {
                    const safeName = escapeHtml(row.name ?? '-');
                    const safeCode = escapeHtml(row.code ?? '-');
                    const safeUnit = escapeHtml(row.unit_name ?? '-');
                    const safeCategory = escapeHtml(row.category_name ?? '-');
                    const historyAttrs = `
                        data-id="${row.id}"
                        data-code="${safeCode}"
                        data-name="${safeName}"
                        data-unit="${safeUnit}"
                        data-category="${safeCategory}"
                    `;

                    let manageButtons = '';
                    if (canManage) {
                        const editAttrs = `
                            data-id="${row.id}"
                            data-code="${safeCode}"
                            data-name="${safeName}"
                            data-unit-id="${row.unit_of_measure_id ?? ''}"
                            data-category-id="${row.category_id ?? ''}"
                            data-type="${escapeHtml(row.type ?? '')}"
                        `;
                        manageButtons = `
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
                        `;
                    }

                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn icon view-purchase-history" ${historyAttrs} data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Purchase History">
                                <i class="fa-light fa-clock-rotate-left text-info"></i>
                            </button>
                            ${manageButtons}
                        </div>
                    `;
                },
            },
        ],
        drawCallback: function () {
            const info = productDataTable.page.info();
            if (resultBadge) {
                const total = info.recordsDisplay ?? 0;
                resultBadge.textContent = `${total.toLocaleString()} record${total === 1 ? '' : 's'}`;
            }

            if (window.bootstrap && window.bootstrap.Tooltip) {
                document.querySelectorAll('#product-table [data-bstooltip-toggle="tooltip"]').forEach((el) => {
                    if (!window.bootstrap.Tooltip.getInstance(el)) {
                        new window.bootstrap.Tooltip(el);
                    }
                });
            }
        },
    });

    if (filterForm && filterForm.dataset.filterInitialized !== '1') {
        filterForm.dataset.filterInitialized = '1';

        if (filterElements.keyword) {
            filterElements.keyword.addEventListener('input', () => applyFilters(true));
        }

        if (filterElements.category) {
            filterElements.category.addEventListener('change', () => applyFilters(false));
        }

        if (filterElements.unit) {
            filterElements.unit.addEventListener('change', () => applyFilters(false));
        }

        if (filterElements.type) {
            filterElements.type.addEventListener('change', () => applyFilters(false));
        }

        if (filterElements.sort) {
            filterElements.sort.addEventListener('change', () => applySort());
        }

        table.on('order.dt', function () {
            syncSortDropdownFromTable();
        });

        if (filterElements.reset) {
            filterElements.reset.addEventListener('click', function () {
                if (filterElements.keyword) filterElements.keyword.value = '';
                if (filterElements.category) filterElements.category.value = '';
                if (filterElements.unit) filterElements.unit.value = '';
                if (filterElements.type) filterElements.type.value = '';
                if (filterElements.sort) filterElements.sort.value = DEFAULT_SORT;
                applySort();
            });
        }

        window.addEventListener('popstate', function () {
            const url = new URL(window.location.href);
            if (filterElements.keyword) filterElements.keyword.value = url.searchParams.get('keyword') || '';
            if (filterElements.category) filterElements.category.value = url.searchParams.get('category_id') || '';
            if (filterElements.unit) filterElements.unit.value = url.searchParams.get('unit_id') || '';
            if (filterElements.type) filterElements.type.value = url.searchParams.get('type') || '';
            if (filterElements.sort) {
                filterElements.sort.value = resolveSortValue(url.searchParams.get('sort'));
            }
            applySort();
        });
    }

    $('#product-table tbody').on('click', '.view-purchase-history', function () {
        const button = $(this);
        const itemId = button.data('id');
        const code = button.data('code') || '-';
        const name = button.data('name') || '-';
        const unit = button.data('unit') || '-';
        const category = button.data('category') || '-';

        const title = document.getElementById('productPurchaseHistoryLabel');
        const meta = document.getElementById('product-purchase-history-meta');
        if (title) {
            title.textContent = `Purchase History — ${code}`;
        }
        if (meta) {
            meta.textContent = `${name} · ${unit} · ${category}`;
        }

        initHistoryTable(itemId);

        if (historyModal) {
            historyModal.show();
        }
    });

    if (historyModalElement) {
        historyModalElement.addEventListener('hidden.bs.modal', destroyHistoryTable);
    }

    if (canManage) {
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

        $('#product-table tbody').on('click', '.delete-product', function () {
            const itemId = $(this).data('id');
            const name = $(this).data('name');
            hapusData(itemId, 'Delete Product', `Are you sure want to delete ${name}?`);
        });
    }

    $('#product-table tbody').on('click', '.copy-code', function () {
        const code = $(this).data('code');
        copyToClipboard(code);
    });

    $('#product-table tbody').on('click', '.copy-name', function () {
        const name = $(this).data('name');
        copyToClipboard(name);
    });

    if (openCreateModal) {
        if (editingProductId && canManage && editModal) {
            editModal.show();
        } else if (canCreate && window.bootstrap && window.bootstrap.Modal) {
            const createModalElement = document.getElementById('create-modal');
            if (createModalElement) {
                const createModal = new window.bootstrap.Modal(createModalElement);
                createModal.show();
            }
        }
    }
});
