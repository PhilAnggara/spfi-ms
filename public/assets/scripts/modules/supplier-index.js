document.addEventListener('DOMContentLoaded', function () {
    const table = $('#supplier-table');
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
    const canDelete = table.data('canDelete') === 1 || table.data('canDelete') === '1';
    const canViewPo = table.data('canViewPo') === 1 || table.data('canViewPo') === '1';
    const openCreateModal = table.data('openCreateModal') === 1 || table.data('openCreateModal') === '1';
    const editingSupplierId = String(table.data('editingSupplierId') || '');

    const filterForm = document.getElementById('supplier-filter-form');
    const loadingEl = document.getElementById('supplier-page-loading');
    const resultBadge = document.getElementById('supplier-filter-result');

    const filterElements = {
        keyword: document.getElementById('filter-supplier-keyword'),
        hasPo: document.getElementById('filter-supplier-has-po'),
        sort: document.getElementById('filter-supplier-sort'),
        reset: document.getElementById('reset-supplier-filter'),
    };

    const DEFAULT_SORT = 'name_asc';
    const SORT_OPTIONS = {
        name_asc: { column: 2, dir: 'asc' },
        name_desc: { column: 2, dir: 'desc' },
        code_asc: { column: 1, dir: 'asc' },
        code_desc: { column: 1, dir: 'desc' },
        po_count_asc: { column: 4, dir: 'asc' },
        po_count_desc: { column: 4, dir: 'desc' },
        total_amount_asc: { column: 5, dir: 'asc' },
        total_amount_desc: { column: 5, dir: 'desc' },
        last_po_date_asc: { column: 6, dir: 'asc' },
        last_po_date_desc: { column: 6, dir: 'desc' },
    };
    const COLUMN_TO_SORT = {
        '2:asc': 'name_asc',
        '2:desc': 'name_desc',
        '1:asc': 'code_asc',
        '1:desc': 'code_desc',
        '4:asc': 'po_count_asc',
        '4:desc': 'po_count_desc',
        '5:asc': 'total_amount_asc',
        '5:desc': 'total_amount_desc',
        '6:asc': 'last_po_date_asc',
        '6:desc': 'last_po_date_desc',
    };

    const editModalElement = document.getElementById('edit-modal');
    const editModal = editModalElement && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(editModalElement)
        : null;

    const historyModalElement = document.getElementById('supplier-purchase-history-modal');
    const historyModal = historyModalElement && window.bootstrap && window.bootstrap.Modal
        ? new window.bootstrap.Modal(historyModalElement)
        : null;

    let historyDataTable = null;
    let supplierDataTable = null;
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

    const truncateText = (value, maxLength = 80) => {
        const rawValue = value ?? '-';
        const safeValue = escapeHtml(rawValue);
        if (rawValue.length > maxLength) {
            return {
                display: `${escapeHtml(rawValue.slice(0, maxLength))}...`,
                full: safeValue,
            };
        }
        return {
            display: safeValue,
            full: safeValue,
        };
    };

    const escapeAttr = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

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

    const formatQty = (value) => {
        const number = Number(value);
        if (Number.isNaN(number)) {
            return '-';
        }
        return number.toLocaleString('en-US', {
            minimumFractionDigits: 0,
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
        setQueryParam(url.searchParams, 'has_po', filterElements.hasPo?.value);
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
        has_po: filterElements.hasPo?.value || '',
    });

    const applyFilters = (useDebounce = false) => {
        const reload = () => {
            syncFilterUrl(true);
            if (supplierDataTable) {
                supplierDataTable.ajax.reload();
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
        if (supplierDataTable) {
            supplierDataTable.order([[sortOrder.column, sortOrder.dir]]).draw();
        }
    };

    const syncSortDropdownFromTable = () => {
        if (!supplierDataTable || !filterElements.sort) {
            return;
        }

        const order = supplierDataTable.order();
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

    const resetHistoryTable = () => {
        if (historyDataTable) {
            historyDataTable.destroy();
            historyDataTable = null;
        }
        $('#supplier-purchase-history-table tbody').empty();
        updatePurchaseSummary([]);
    };

    const destroyHistoryTable = () => {
        resetHistoryTable();
        clearSupplierDetail();
    };

    const clearSupplierDetail = () => {
        const fields = {
            'supplier-detail-address': '-',
            'supplier-detail-phone': '-',
            'supplier-detail-fax': '-',
            'supplier-detail-contact': '-',
            'supplier-detail-email': '-',
            'supplier-detail-remarks': '-',
        };

        Object.entries(fields).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) {
                el.innerHTML = value === '-' ? '<span class="text-muted">-</span>' : value;
            }
        });
    };

    const updateSupplierDetail = (detail) => {
        const setField = (id, value, options = {}) => {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }

            const normalized = String(value ?? '').trim();
            if (normalized === '') {
                el.innerHTML = '<span class="text-muted">-</span>';
                return;
            }

            if (options.isEmail) {
                const safeEmail = escapeHtml(normalized);
                el.innerHTML = `<a href="mailto:${safeEmail}" class="supplier-info-link">${safeEmail}</a>`;
                return;
            }

            if (options.isPhone) {
                const safePhone = escapeHtml(normalized);
                el.innerHTML = `<a href="tel:${safePhone}" class="supplier-info-link">${safePhone}</a>`;
                return;
            }

            el.textContent = normalized;
        };

        setField('supplier-detail-address', detail.address);
        setField('supplier-detail-phone', detail.phone, { isPhone: true });
        setField('supplier-detail-fax', detail.fax);
        setField('supplier-detail-contact', detail.contact);
        setField('supplier-detail-email', detail.email, { isEmail: true });
        setField('supplier-detail-remarks', detail.remarks);
    };

    const updatePurchaseSummary = (summaryRows) => {
        const summaryEl = document.getElementById('supplier-purchase-history-summary');
        const summaryBodyEl = document.getElementById('supplier-purchase-history-summary-body');
        if (!summaryEl || !summaryBodyEl) {
            return;
        }

        const rows = Array.isArray(summaryRows) ? summaryRows : [];
        summaryBodyEl.classList.toggle('supplier-summary-body--stacked', rows.length > 1);

        if (!rows.length) {
            summaryBodyEl.innerHTML = '<span class="text-muted small">No purchase data available.</span>';
            return;
        }

        summaryBodyEl.innerHTML = rows.map((row) => {
            const currency = escapeHtml(row.currency ?? '-');
            const avgPrice = formatNumber(row.avg_unit_price);
            const totalQty = formatQty(row.total_qty);
            const totalAmount = formatNumber(row.total_amount);

            return `
                <div class="supplier-summary-card">
                    <div class="supplier-summary-card-title">${currency}</div>
                    <div class="supplier-summary-metrics">
                        <div>
                            <div class="supplier-summary-metric-label">Avg Price</div>
                            <div class="supplier-summary-metric-value">${currency} ${avgPrice}</div>
                        </div>
                        <div>
                            <div class="supplier-summary-metric-label">Total Qty</div>
                            <div class="supplier-summary-metric-value">${totalQty}</div>
                        </div>
                        <div>
                            <div class="supplier-summary-metric-label">Total Amount</div>
                            <div class="supplier-summary-metric-value">${currency} ${totalAmount}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const initHistoryTable = (supplierId) => {
        resetHistoryTable();

        const historyUrl = String(historyRouteTemplate || '').replace('__ID__', supplierId);

        historyDataTable = $('#supplier-purchase-history-table').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ajax: {
                url: historyUrl,
                type: 'GET',
                dataSrc: function (json) {
                    updatePurchaseSummary(json.avgUnitPrices);
                    return json.data;
                },
            },
            order: [[1, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: 'No purchase history found for this supplier.',
                zeroRecords: 'No purchase history found for this supplier.',
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
                    data: 'item_code',
                    render: function (data) {
                        return escapeHtml(data ?? '-');
                    },
                },
                {
                    data: 'item_name',
                    render: function (data) {
                        const truncated = truncateText(data);
                        return `<span class="text-start d-inline-block text-truncate" style="max-width: 220px;" title="${truncated.full}">${truncated.display}</span>`;
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

    supplierDataTable = table.DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 10,
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6 text-end'>>rtip",
        language: {
            emptyTable: 'No suppliers found.',
            zeroRecords: 'No suppliers found. Try changing your keyword or filters.',
        },
        ajax: {
            url: tableUrl,
            type: 'GET',
            data: function (data) {
                const filters = getFilterPayload();
                data.keyword = filters.keyword;
                data.has_po = filters.has_po;
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
                    const truncated = truncateText(data);
                    return `<span class="copy-name" data-name="${truncated.full}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="${truncated.full}" style="cursor: pointer">${truncated.display}</span>`;
                },
            },
            {
                data: 'address',
                render: function (data) {
                    const truncated = truncateText(data, 40);
                    return `<span data-bstooltip-toggle="tooltip" data-bs-placement="top" title="${truncated.full}">${truncated.display}</span>`;
                },
            },
            {
                data: 'po_count',
                className: 'text-end',
                render: function (data) {
                    const count = Number(data) || 0;
                    if (count > 0) {
                        return `<span class="badge bg-light-primary">${count.toLocaleString()} PO</span>`;
                    }
                    return '<span class="badge bg-light-secondary">0 PO</span>';
                },
            },
            {
                data: 'primary_total_amount',
                className: 'text-end',
                render: function (data, type, row) {
                    const totals = Array.isArray(row.purchase_totals) ? row.purchase_totals : [];
                    if (!totals.length) {
                        return '<span class="text-muted">-</span>';
                    }

                    return totals.map((item) => {
                        const currency = escapeHtml(item.currency ?? '-');
                        const amount = formatNumber(item.total_amount);
                        return `<div class="fw-semibold">${currency} ${amount}</div>`;
                    }).join('');
                },
            },
            {
                data: 'last_po_date',
                render: function (data) {
                    return formatDate(data);
                },
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (row) {
                    let manageButtons = '';
                    if (canManage) {
                        const editAttrs = `
                            data-id="${row.id}"
                            data-code="${escapeAttr(row.code)}"
                            data-name="${escapeAttr(row.name)}"
                            data-address="${escapeAttr(row.address)}"
                            data-phone="${escapeAttr(row.phone)}"
                            data-fax="${escapeAttr(row.fax)}"
                            data-email="${escapeAttr(row.email)}"
                            data-contact-person="${escapeAttr(row.contact_person)}"
                            data-remarks="${escapeAttr(row.remarks)}"
                        `;
                        manageButtons += `
                            <button type="button" class="btn icon edit-supplier" ${editAttrs} data-bs-toggle="modal" data-bs-target="#edit-modal" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Edit">
                                <i class="fa-light fa-edit text-primary"></i>
                            </button>
                        `;
                    }

                    if (canDelete) {
                        manageButtons += `
                            <button type="button" class="btn icon delete-supplier" data-id="${row.id}" data-name="${escapeAttr(row.name)}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Delete">
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
                            <button type="button" class="btn icon view-supplier-detail" data-id="${row.id}" data-bstooltip-toggle="tooltip" data-bs-placement="top" title="Detail & Purchase History">
                                <i class="fa-light fa-circle-info text-info"></i>
                            </button>
                            ${manageButtons}
                        </div>
                    `;
                },
            },
        ],
        drawCallback: function () {
            const info = supplierDataTable.page.info();
            if (resultBadge) {
                const total = info.recordsDisplay ?? 0;
                resultBadge.textContent = `${total.toLocaleString()} record${total === 1 ? '' : 's'}`;
            }

            if (window.bootstrap && window.bootstrap.Tooltip) {
                document.querySelectorAll('#supplier-table [data-bstooltip-toggle="tooltip"]').forEach((el) => {
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

        if (filterElements.hasPo) {
            filterElements.hasPo.addEventListener('change', () => applyFilters(false));
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
                if (filterElements.hasPo) filterElements.hasPo.value = '';
                if (filterElements.sort) filterElements.sort.value = DEFAULT_SORT;
                applySort();
            });
        }

        window.addEventListener('popstate', function () {
            const url = new URL(window.location.href);
            if (filterElements.keyword) filterElements.keyword.value = url.searchParams.get('keyword') || '';
            if (filterElements.hasPo) filterElements.hasPo.value = url.searchParams.get('has_po') || '';
            if (filterElements.sort) {
                filterElements.sort.value = resolveSortValue(url.searchParams.get('sort'));
            }
            applySort();
        });
    }

    $('#supplier-table tbody').on('click', '.view-supplier-detail', function () {
        const button = $(this);
        const row = supplierDataTable.row(button.closest('tr')).data();
        if (!row) {
            return;
        }

        const code = row.code || '-';
        const name = row.name || '-';

        const title = document.getElementById('supplierPurchaseHistoryLabel');
        const meta = document.getElementById('supplier-purchase-history-meta');
        if (title) {
            title.textContent = `${code} — ${name}`;
        }
        if (meta) {
            meta.textContent = 'Supplier information and purchase history';
        }

        initHistoryTable(row.id);

        updateSupplierDetail({
            address: row.address,
            phone: row.phone,
            fax: row.fax,
            email: row.email,
            contact: row.contact_person,
            remarks: row.remarks,
        });

        if (historyModal) {
            historyModal.show();
        }
    });

    if (historyModalElement) {
        historyModalElement.addEventListener('hidden.bs.modal', destroyHistoryTable);
    }

    if (canManage) {
        $('#supplier-table tbody').on('click', '.edit-supplier', function () {
            const button = $(this);
            const supplierId = button.data('id');
            const updateUrl = String(updateRouteTemplate || '').replace('__ID__', supplierId);

            document.getElementById('edit-code').value = button.attr('data-code') || '';
            document.getElementById('edit-name').value = button.attr('data-name') || '';
            document.getElementById('edit-address').value = button.attr('data-address') || '';
            document.getElementById('edit-phone').value = button.attr('data-phone') || '';
            document.getElementById('edit-fax').value = button.attr('data-fax') || '';
            document.getElementById('edit-email').value = button.attr('data-email') || '';
            document.getElementById('edit-contact-person').value = button.attr('data-contact-person') || '';
            document.getElementById('edit-remarks').value = button.attr('data-remarks') || '';
            document.getElementById('edit-form').setAttribute('action', updateUrl);

            const editLabel = document.getElementById('editSupplierLabel');
            if (editLabel) {
                editLabel.textContent = `Edit Supplier - ${button.attr('data-name') || ''}`;
            }

            if (editModal) {
                editModal.show();
            }
        });
    }

    if (canDelete) {
        $('#supplier-table tbody').on('click', '.delete-supplier', function () {
            const supplierId = $(this).data('id');
            const name = $(this).attr('data-name');
            hapusData(supplierId, 'Delete Supplier', `Are you sure want to delete ${name}?`);
        });
    }

    $('#supplier-table tbody').on('click', '.copy-code', function () {
        const code = $(this).data('code');
        copyToClipboard(code);
    });

    $('#supplier-table tbody').on('click', '.copy-name', function () {
        const name = $(this).data('name');
        copyToClipboard(name);
    });

    if (canManage) {
        if (editingSupplierId && editModal) {
            editModal.show();
        } else if (openCreateModal && window.bootstrap && window.bootstrap.Modal) {
            const createModalElement = document.getElementById('create-modal');
            if (createModalElement) {
                const createModal = new window.bootstrap.Modal(createModalElement);
                createModal.show();
            }
        }
    }
});
