(function (window) {
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const buildStockAttrs = (item, withStockAttrs) => {
        if (!withStockAttrs) {
            return '';
        }

        const stock = Number(item.stock_on_hand || 0);
        const unit = escapeHtml(item.unit || 'PCS');

        return ` data-stock="${stock}" data-unit="${unit}"`;
    };

    const buildGridCardMarkup = (item, options = {}) => {
        const withStockAttrs = options.withStockAttrs !== false;
        const itemName = escapeHtml(item.name);
        const itemCode = escapeHtml(item.code);
        const itemCategory = escapeHtml(item.category || 'Uncategorized');
        const unit = escapeHtml(item.unit || 'PCS');
        const stock = escapeHtml(item.stock_on_hand ?? 0);
        const stockAttrs = buildStockAttrs(item, withStockAttrs);

        return `
            <div class="prs-item-card"
                data-item-id="${item.id}"
                data-name="${itemName.toLowerCase()}"
                data-code="${itemCode.toLowerCase()}"
                data-category="${itemCategory.toLowerCase()}"${stockAttrs}>
                <div class="prs-item-body">
                    <div class="prs-item-title">${itemName}</div>
                    <div class="prs-item-meta">
                        <span class="badge bg-light-primary">${itemCode}</span>
                        <span class="text-muted">Stock ${stock} ${unit}</span>
                    </div>
                    <div class="prs-item-meta text-muted">${itemCategory}</div>
                    <div class="prs-item-actions">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity">
                            <i class="fa-light fa-minus"></i>
                        </button>
                        <input type="number" min="0.00001" step="0.00001" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Increase quantity">
                            <i class="fa-light fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="${item.id}">
                            <i class="fa-light fa-plus"></i>
                            Add
                        </button>
                    </div>
                </div>
            </div>
        `;
    };

    const buildListRowMarkup = (item, options = {}) => {
        const withStockAttrs = options.withStockAttrs !== false;
        const itemName = escapeHtml(item.name);
        const itemCode = escapeHtml(item.code);
        const itemCategory = escapeHtml(item.category || 'Uncategorized');
        const unit = escapeHtml(item.unit || 'PCS');
        const stock = escapeHtml(item.stock_on_hand ?? 0);
        const stockAttrs = buildStockAttrs(item, withStockAttrs);

        return `
            <tr class="prs-catalog-row"
                data-item-id="${item.id}"
                data-name="${itemName.toLowerCase()}"
                data-code="${itemCode.toLowerCase()}"
                data-category="${itemCategory.toLowerCase()}"${stockAttrs}>
                <td data-label="Code"><span class="badge bg-light-primary">${itemCode}</span></td>
                <td data-label="Name"><span class="prs-catalog-item-name">${itemName}</span></td>
                <td data-label="Category" class="text-muted">${itemCategory}</td>
                <td data-label="Stock" class="text-muted">${stock} ${unit}</td>
                <td data-label="Qty">
                    <div class="prs-catalog-list-qty">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity">
                            <i class="fa-light fa-minus"></i>
                        </button>
                        <input type="number" min="0.00001" step="0.00001" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Increase quantity">
                            <i class="fa-light fa-plus"></i>
                        </button>
                    </div>
                </td>
                <td data-label="Action" class="text-end">
                    <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="${item.id}">
                        <i class="fa-light fa-plus"></i>
                        Add
                    </button>
                </td>
            </tr>
        `;
    };

    const renderCatalog = (grid, items, layout, emptyStateMarkup, options = {}) => {
        const normalizedLayout = layout === 'list' ? 'list' : 'grid';

        grid.dataset.layout = normalizedLayout;
        grid.classList.toggle('prs-item-grid', normalizedLayout === 'grid');
        grid.classList.toggle('prs-catalog-list-mode', normalizedLayout === 'list');

        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = emptyStateMarkup;
            return;
        }

        if (normalizedLayout === 'list') {
            grid.innerHTML = `
                <div class="prs-item-list">
                    <table class="table table-sm prs-catalog-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item) => buildListRowMarkup(item, options)).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            return;
        }

        grid.innerHTML = items.map((item) => buildGridCardMarkup(item, options)).join('');
    };

    const readLayout = (storageKey) => (
        localStorage.getItem(storageKey) === 'list' ? 'list' : 'grid'
    );

    const saveLayout = (storageKey, layout) => {
        localStorage.setItem(storageKey, layout === 'list' ? 'list' : 'grid');
    };

    const setToggleActive = (layoutToggle, layout) => {
        if (!layoutToggle) {
            return;
        }

        layoutToggle.querySelectorAll('[data-layout]').forEach((button) => {
            const isActive = button.dataset.layout === layout;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const getCatalogRows = (grid) => Array.from(
        grid.querySelectorAll('.prs-item-card, .prs-catalog-row')
    );

    const getRowPayload = (row) => {
        const itemId = parseInt(row.dataset.itemId || '0', 10);
        const name = String(row.querySelector('.prs-item-title, .prs-catalog-item-name')?.textContent || '').trim();
        const code = String(row.querySelector('.badge')?.textContent || '').trim();
        const unit = String(row.dataset.unit || 'PCS').trim();
        const stock = Number(row.dataset.stock || 0);

        return {
            itemId,
            name,
            code,
            unit,
            stock,
        };
    };

    window.CatalogLayout = {
        escapeHtml,
        buildGridCardMarkup,
        buildListRowMarkup,
        renderCatalog,
        readLayout,
        saveLayout,
        setToggleActive,
        getCatalogRows,
        getRowPayload,
    };
}(window));
