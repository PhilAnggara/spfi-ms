document.addEventListener('DOMContentLoaded', function () {
    initSwsCartPopup();
    initSwsCatalogAndCart();
});

function initSwsCartPopup() {
    const cartPopup = document.getElementById('sws-cart-popup');
    if (!cartPopup) {
        return;
    }

    let backdrop = document.getElementById('sws-cart-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sws-cart-backdrop';
        backdrop.className = 'prs-cart-backdrop is-hidden';
        document.body.appendChild(backdrop);
    }

    const toggleButton = document.getElementById('toggle-sws-cart');
    const toggleButtonMobile = document.getElementById('toggle-sws-cart-mobile');
    const hideButton = document.getElementById('hide-sws-cart');

    const setHidden = (hidden) => {
        cartPopup.classList.toggle('is-hidden', hidden);
        cartPopup.setAttribute('aria-hidden', hidden ? 'true' : 'false');
        backdrop.classList.toggle('is-hidden', hidden);
    };

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            setHidden(!cartPopup.classList.contains('is-hidden'));
        });
    }

    if (toggleButtonMobile) {
        toggleButtonMobile.addEventListener('click', () => {
            setHidden(!cartPopup.classList.contains('is-hidden'));
        });
    }

    if (hideButton) {
        hideButton.addEventListener('click', () => setHidden(true));
    }

    backdrop.addEventListener('click', () => setHidden(true));
}

function initSwsCatalogAndCart() {
    const grid = document.getElementById('sws-item-grid');
    if (!grid) {
        return;
    }

    const createPage = document.getElementById('sws-create-page') || document.getElementById('sws-edit-page');
    const filterBar = document.getElementById('sws-catalog-filter-form');
    const baseUrl = filterBar?.dataset.baseUrl || window.location.pathname;
    const capexUrl = filterBar?.dataset.capexUrl || `${window.location.pathname.replace(/\/(create|edit)$/, '')}/capex-lines`;
    const searchInput = document.getElementById('sws-item-search');
    const categoryFilter = document.getElementById('sws-category-filter');
    const stockFilter = document.getElementById('sws-stock-filter');
    const resetFilterButton = document.getElementById('sws-reset-filter');
    const layoutToggle = filterBar?.querySelector('.prs-layout-toggle');
    const paginationContainer = document.getElementById('sws-pagination');
    const typeSelect = document.getElementById('sws-type');
    const typeField = document.getElementById('sws-type-field');
    const cartItemsContainer = document.getElementById('sws-cart-list');
    const cartEmpty = document.getElementById('sws-cart-empty');
    const hiddenInputsContainer = document.getElementById('sws-cart-hidden-inputs');
    const cartCount = document.getElementById('sws-cart-count');
    const floatingCartBtn = document.getElementById('toggle-sws-cart-mobile');
    const topCartBtn = document.getElementById('toggle-sws-cart');
    const stockRuleHint = document.getElementById('sws-stock-rule-hint');
    const form = document.getElementById('sws-create-form') || document.getElementById('sws-edit-form');
    const modeToggle = document.querySelector('.sws-withdrawal-mode-toggle');
    const normalModeHint = document.getElementById('sws-normal-mode-hint');
    const capexModeHint = document.getElementById('sws-capex-mode-hint');
    const cartDepartmentSelect = document.getElementById('sws-department');
    const normalOnlyFilters = document.querySelectorAll('.sws-normal-only-filter');
    const modeLocked = createPage?.dataset.modeLocked === '1';
    const autoOpenCart = createPage?.dataset.autoOpenCart === '1';
    const excludeStoreWithdrawalId = parseInt(createPage?.dataset.excludeStoreWithdrawalId || '0', 10) || 0;
    let catalogAbortController = null;
    let catalogRequestSeq = 0;
    const LAYOUT_KEY = 'sws-create-catalog-layout';
    let catalogLayout = window.CatalogLayout?.readLayout(LAYOUT_KEY) || 'grid';
    let lastCatalogItems = [];
    let withdrawalMode = createPage?.dataset.initialMode === 'capex' ? 'capex' : 'normal';

    const state = {
        page: parseInt(paginationContainer?.dataset.currentPage || '1', 10) || 1,
        lastPage: parseInt(paginationContainer?.dataset.lastPage || '1', 10) || 1,
        cart: new Map(),
    };

    const parseQuantity = (raw, fallback = 1) => {
        const parsed = parseFloat(String(raw ?? '').trim().replace(',', '.'));
        if (Number.isNaN(parsed) || parsed < 0.00001) {
            return fallback;
        }

        return parsed;
    };

    const isCapexMode = () => withdrawalMode === 'capex';
    const isConfirmatoryType = () => !isCapexMode() && String(typeSelect?.value || 'NORMAL') === 'CONFIRMATORY';

    const getCartKey = (payload) => {
        if (isCapexMode()) {
            return `capex:${payload.receivingReportItemId || 0}`;
        }

        return `item:${payload.itemId || 0}`;
    };

    const parseCartKey = (cartKey) => String(cartKey || '');

    const canSelectStock = (stockValue) => {
        if (isCapexMode()) {
            return Number(stockValue) > 0;
        }

        if (isConfirmatoryType()) {
            return true;
        }

        return Number(stockValue) > 0;
    };

    const getTransferredQuantity = (item) => Math.max(0, Number(item?.quantityTransferred || 0));

    const isCartLineLocked = (item) => Boolean(item?.isLineLocked);

    const getAllowedQuantity = (stockValue, requestedQuantity, item = null) => {
        const transferred = getTransferredQuantity(item);
        const minQuantity = transferred > 0.00001 ? transferred : 0.00001;
        const normalizedQuantity = Math.max(minQuantity, parseQuantity(requestedQuantity, minQuantity));

        if (isCapexMode() || isConfirmatoryType()) {
            const normalizedStock = Number(stockValue) || 0;
            if (isCapexMode() && normalizedStock <= 0 && transferred <= 0.00001) {
                return 0;
            }

            if (isConfirmatoryType()) {
                return normalizedQuantity;
            }

            // Capex remaining already excludes this SWS; allow qty >= transferred up to remaining + transferred.
            return Math.max(minQuantity, Math.min(normalizedQuantity, normalizedStock + transferred));
        }

        const normalizedStock = Number(stockValue) || 0;
        const maxQuantity = normalizedStock + transferred;
        if (maxQuantity <= 0.00001 && transferred <= 0.00001) {
            return 0;
        }

        return Math.max(minQuantity, Math.min(normalizedQuantity, maxQuantity > 0 ? maxQuantity : minQuantity));
    };

    const syncCatalogQuantityInput = (card) => {
        const qtyInput = card?.querySelector('.prs-item-qty');
        if (!qtyInput) {
            return;
        }

        const payload = getCardPayload(card);
        const allowedQuantity = getAllowedQuantity(payload.stock, qtyInput.value);

        qtyInput.min = '0.00001';
        qtyInput.step = '0.00001';

        if (isConfirmatoryType()) {
            qtyInput.removeAttribute('max');
        } else if (payload.stock > 0) {
            qtyInput.max = String(payload.stock);
        } else {
            qtyInput.max = '1';
        }

        if (allowedQuantity <= 0) {
            qtyInput.value = '1';
            return;
        }

        qtyInput.value = String(allowedQuantity);
    };

    const showStockRuleHint = (message = '') => {
        if (!stockRuleHint) {
            return;
        }

        const hasMessage = String(message).trim() !== '';
        stockRuleHint.classList.toggle('d-none', !hasMessage);
        stockRuleHint.textContent = message;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const buildEmptyStateMarkup = () => `
        <div class="prs-catalog-empty-state">
            <i class="fa-duotone fa-solid fa-box-open prs-catalog-empty-icon"></i>
            <p class="mb-0 mt-2 fw-semibold">No items found.</p>
            <small>Try changing your keyword or category filter to see more results.</small>
        </div>
    `;

    const buildCatalogStatusMarkup = (icon, title, message) => `
        <div class="prs-catalog-empty-state">
            <i class="${icon} prs-catalog-empty-icon"></i>
            <p class="mb-0 mt-2 fw-semibold">${escapeHtml(title)}</p>
            <small>${escapeHtml(message)}</small>
        </div>
    `;

    const renderCatalogError = () => {
        grid.innerHTML = buildCatalogStatusMarkup(
            'fa-duotone fa-solid fa-triangle-exclamation',
            'Search could not be loaded.',
            'Check your connection and try again.'
        );
    };

    const setCatalogLoading = (isLoading) => {
        grid.setAttribute('aria-busy', isLoading ? 'true' : 'false');

        if (isLoading) {
            grid.innerHTML = buildCatalogStatusMarkup(
                'fa-duotone fa-solid fa-spinner-third fa-spin',
                'Searching items...',
                'Please wait while the catalog is updated.'
            );
            if (paginationContainer) {
                paginationContainer.innerHTML = '';
            }
        }
    };

    const getCards = () => window.CatalogLayout?.getCatalogRows(grid) || [];

    const getCardPayload = (row) => {
        if (isCapexMode()) {
            return {
                receivingReportItemId: parseInt(row.dataset.receivingReportItemId || '0', 10),
                itemId: parseInt(row.dataset.itemId || '0', 10),
                name: row.dataset.name || '',
                code: row.dataset.code || '',
                unit: row.dataset.unit || 'PCS',
                stock: Number(row.dataset.stock || 0),
                prsNumber: row.dataset.prsNumber || '',
                poNumber: row.dataset.poNumber || '',
                rrNumber: row.dataset.rrNumber || '',
            };
        }

        return window.CatalogLayout?.getRowPayload(row) || {
            itemId: 0,
            name: '',
            code: '',
            unit: 'PCS',
            stock: 0,
        };
    };

    const updateCatalogButtons = () => {
        getCards().forEach((card) => {
            const payload = getCardPayload(card);
            const addButton = card.querySelector('.prs-item-add');
            syncCatalogQuantityInput(card);
            if (!addButton || (!payload.itemId && !payload.receivingReportItemId)) {
                return;
            }

            const blocked = !canSelectStock(payload.stock);
            const cartKey = getCartKey(payload);
            const inCart = state.cart.has(cartKey);

            addButton.disabled = blocked;
            addButton.classList.toggle('btn-primary', !inCart && !blocked);
            addButton.classList.toggle('btn-outline-success', inCart && !blocked);
            addButton.classList.toggle('btn-outline-secondary', blocked);

            if (blocked) {
                addButton.innerHTML = isCapexMode()
                    ? '<i class="fa-light fa-ban"></i> No balance'
                    : '<i class="fa-light fa-ban"></i> Stock 0';
                addButton.title = isCapexMode()
                    ? 'No remaining CAPEX quantity for this RR line.'
                    : 'Normal type does not allow zero-stock items.';
            } else if (inCart) {
                addButton.innerHTML = '<i class="fa-light fa-cart-plus"></i> Update';
                addButton.title = 'Update item quantity in the cart.';
            } else {
                addButton.innerHTML = '<i class="fa-light fa-plus"></i> Add';
                addButton.title = 'Add item to cart.';
            }
        });
    };

    const updateCountBadge = () => {
        const count = state.cart.size;

        if (cartCount) {
            cartCount.textContent = String(count);
        }

        let floatingBadge = floatingCartBtn?.querySelector('.prs-cart-badge');
        if (floatingCartBtn && !floatingBadge) {
            floatingBadge = document.createElement('span');
            floatingBadge.className = 'prs-cart-badge';
            floatingCartBtn.appendChild(floatingBadge);
        }

        if (floatingBadge) {
            floatingBadge.textContent = String(count);
        }
    };

    const syncHiddenInputs = () => {
        if (!hiddenInputsContainer) {
            return;
        }

        const cartItems = Array.from(state.cart.values());
        hiddenInputsContainer.innerHTML = cartItems.map((item, index) => {
            const receivingReportItemInput = isCapexMode()
                ? `<input type="hidden" name="items[${index}][receiving_report_item_id]" value="${item.receivingReportItemId}">`
                : '';

            return `
                <input type="hidden" name="items[${index}][item_id]" value="${item.itemId}">
                ${receivingReportItemInput}
                <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
            `;
        }).join('');
    };

    const renderCart = () => {
        if (!cartItemsContainer || !hiddenInputsContainer) {
            return;
        }

        const cartItems = Array.from(state.cart.values());

        if (cartItems.length === 0) {
            cartItemsContainer.innerHTML = '';
            hiddenInputsContainer.innerHTML = '';
            if (cartEmpty) {
                cartEmpty.classList.remove('d-none');
            }
            updateCountBadge();
            updateCatalogButtons();
            return;
        }

        if (cartEmpty) {
            cartEmpty.classList.add('d-none');
        }

        cartItemsContainer.innerHTML = cartItems.map((item) => {
            const cartKey = getCartKey(item);
            const transferred = getTransferredQuantity(item);
            const lineLocked = isCartLineLocked(item);
            const stockLabelClass = Number(item.stock) <= 0 ? 'text-danger fw-semibold' : 'text-muted';
            const quantityMin = transferred > 0.00001 ? transferred : 0.00001;
            const quantityMaxAttribute = !isConfirmatoryType() && (Number(item.stock) + transferred) > 0
                ? `max="${Number(item.stock) + transferred}"`
                : '';
            const stockLabel = isCapexMode()
                ? `Remaining ${item.stock}`
                : `Stock ${item.stock}`;
            const transferLabel = transferred > 0.00001
                ? `<small class="text-muted d-block">Transferred ${transferred}${lineLocked ? ' · fully transferred (locked)' : ` · remaining ${Math.max(0, Number(item.quantity) - transferred)}`}</small>`
                : '';
            const referenceLine = isCapexMode()
                ? `<small class="text-muted d-block">PRS ${escapeHtml(item.prsNumber || '-')} · PO ${escapeHtml(item.poNumber || '-')} · RR ${escapeHtml(item.rrNumber || '-')}</small>`
                : '';
            const qtyControls = lineLocked
                ? `<input type="number" class="form-control sws-cart-qty" value="${item.quantity}" data-cart-key="${cartKey}" readonly disabled>
                   <span class="input-group-text">${escapeHtml(item.unit)}</span>`
                : `<button type="button" class="btn btn-light-secondary sws-cart-decrement" data-cart-key="${cartKey}" aria-label="Decrease quantity">
                        <i class="fa-light fa-minus"></i>
                   </button>
                   <input type="number" min="${quantityMin}" step="0.00001" ${quantityMaxAttribute} class="form-control sws-cart-qty" value="${item.quantity}" data-cart-key="${cartKey}">
                   <button type="button" class="btn btn-light-secondary sws-cart-increment" data-cart-key="${cartKey}" aria-label="Increase quantity">
                        <i class="fa-light fa-plus"></i>
                   </button>
                   <span class="input-group-text">${escapeHtml(item.unit)}</span>`;
            const removeControl = lineLocked
                ? `<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Fully transferred lines cannot be removed">
                        <i class="fa-light fa-lock"></i>
                        Locked
                   </button>`
                : `<button type="button" class="btn btn-sm btn-outline-danger sws-cart-remove" data-cart-key="${cartKey}" ${transferred > 0.00001 ? 'disabled title="Cannot remove: transfer slip already created for this line"' : ''}>
                        <i class="fa-regular fa-trash"></i>
                        Remove
                   </button>`;

            return `
                <div class="prs-cart-item" data-cart-key="${cartKey}">
                    <div class="prs-cart-item-info">
                        <div class="prs-cart-thumb">
                            <i class="fa-duotone fa-solid ${isCapexMode() ? 'fa-building-columns' : 'fa-box'}"></i>
                        </div>
                        <div class="prs-cart-text">
                            <div class="fw-semibold">${escapeHtml(item.name)}</div>
                            <small class="text-muted">${escapeHtml(item.code)} · <span class="${stockLabelClass}">${stockLabel}</span> ${escapeHtml(item.unit)}</small>
                            ${referenceLine}
                            ${transferLabel}
                        </div>
                    </div>
                    <div class="prs-cart-item-actions">
                        <div class="prs-cart-item-qty">
                            <div class="input-group input-group-sm">
                                ${qtyControls}
                            </div>
                        </div>
                        <div class="prs-cart-item-remove">
                            ${removeControl}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        syncHiddenInputs();
        updateCountBadge();
        updateCatalogButtons();
    };

    const commitCartQuantity = (qtyInput, { rewriteValue = true } = {}) => {
        const cartKey = parseCartKey(qtyInput.dataset.cartKey || '');
        if (!cartKey || !state.cart.has(cartKey)) {
            return;
        }

        const current = state.cart.get(cartKey);
        if (isCartLineLocked(current)) {
            qtyInput.value = String(current.quantity);
            return;
        }

        const transferred = getTransferredQuantity(current);
        const quantity = parseQuantity(qtyInput.value, Number(current.quantity) || transferred || 1);
        const allowedQuantity = getAllowedQuantity(current.stock, quantity, current);
        const nextQuantity = allowedQuantity <= 0 ? Math.max(0.00001, transferred || 1) : allowedQuantity;

        if (rewriteValue) {
            qtyInput.value = String(nextQuantity);
        }

        state.cart.set(cartKey, {
            ...current,
            quantity: nextQuantity,
        });

        if (!isConfirmatoryType() && !isCapexMode() && quantity > allowedQuantity) {
            showStockRuleHint('Normal type quantity cannot exceed available stock.');
        }

        if (quantity + 0.00001 < transferred) {
            showStockRuleHint('Quantity cannot be below the amount already transferred.');
        }

        syncHiddenInputs();
    };

    const seedExistingCartItems = () => {
        const raw = createPage?.dataset.existingCart || '[]';
        let items = [];

        try {
            items = JSON.parse(raw);
        } catch (_) {
            items = [];
        }

        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        items.forEach((item) => {
            const payload = {
                storeWithdrawalItemId: Number(item.storeWithdrawalItemId || 0),
                receivingReportItemId: Number(item.receivingReportItemId || 0),
                itemId: Number(item.itemId || 0),
                name: item.name || '',
                code: item.code || '',
                stock: Number(item.stock || 0),
                unit: item.unit || 'PCS',
                quantityTransferred: Number(item.quantityTransferred || 0),
                quantityRemaining: Number(item.quantityRemaining || 0),
                isLineLocked: Boolean(item.isLineLocked),
                prsNumber: item.prsNumber || '',
                poNumber: item.poNumber || '',
                rrNumber: item.rrNumber || '',
            };
            const quantity = parseQuantity(item.quantity, 1);
            state.cart.set(getCartKey(payload), {
                ...payload,
                quantity,
            });
        });
    };

    const removeZeroStockItemsIfNormal = () => {
        if (isConfirmatoryType()) {
            return;
        }

        let removedCount = 0;
        Array.from(state.cart.entries()).forEach(([cartKey, item]) => {
            if (getTransferredQuantity(item) > 0.00001 || isCartLineLocked(item)) {
                return;
            }

            if (Number(item.stock) <= 0) {
                state.cart.delete(cartKey);
                removedCount += 1;
            }
        });

        if (removedCount > 0) {
            showStockRuleHint(`Normal type is active. ${removedCount} zero-stock item(s) were removed from the cart.`);
        } else {
            showStockRuleHint('');
        }
    };

    const normalizeCartForStockRules = () => {
        if (isConfirmatoryType()) {
            showStockRuleHint('');
            return;
        }

        let removedCount = 0;
        let adjustedCount = 0;

        Array.from(state.cart.entries()).forEach(([cartKey, item]) => {
            if (isCartLineLocked(item)) {
                return;
            }

            const transferred = getTransferredQuantity(item);
            const allowedQuantity = getAllowedQuantity(item.stock, item.quantity, item);

            if (allowedQuantity <= 0 && transferred <= 0.00001) {
                state.cart.delete(cartKey);
                removedCount += 1;
                return;
            }

            if (Number(item.quantity) !== allowedQuantity) {
                state.cart.set(cartKey, {
                    ...item,
                    quantity: allowedQuantity,
                });
                adjustedCount += 1;
            }
        });

        if (removedCount > 0 || adjustedCount > 0) {
            showStockRuleHint(
                removedCount > 0
                    ? `Normal type is active. ${removedCount} item(s) were removed and ${adjustedCount} quantity value(s) were adjusted.`
                    : `Normal type is active. ${adjustedCount} quantity value(s) were adjusted to available stock.`
            );
        } else {
            showStockRuleHint('');
        }
    };

    const addToCart = (payload, quantity) => {
        if (isCapexMode() && !payload.receivingReportItemId) {
            return;
        }

        if (!isCapexMode() && !payload.itemId) {
            return;
        }

        if (!canSelectStock(payload.stock)) {
            showStockRuleHint(isCapexMode()
                ? 'This CAPEX line has no remaining quantity.'
                : 'Normal type does not allow zero-stock items. Switch to Confirmatory if needed.');
            return;
        }

        const allowedQuantity = getAllowedQuantity(payload.stock, quantity);
        if (allowedQuantity <= 0) {
            showStockRuleHint('Normal type does not allow zero-stock items. Switch to Confirmatory if needed.');
            return;
        }

        if (!isConfirmatoryType() && Number(quantity) > allowedQuantity) {
            showStockRuleHint('Normal type quantity cannot exceed available stock. Quantity was adjusted automatically.');
        } else {
            showStockRuleHint('');
        }

        const current = state.cart.get(getCartKey(payload));
        state.cart.set(getCartKey(payload), {
            ...payload,
            quantity: allowedQuantity,
            quantityInputValue: current?.quantityInputValue,
        });

        renderCart();
    };

    const renderCapexCatalogView = (items) => {
        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = buildCatalogStatusMarkup(
                'fa-duotone fa-solid fa-building-columns',
                'No CAPEX items available.',
                'Try another keyword or select a different department.'
            );
            return;
        }

        if (catalogLayout === 'list') {
            grid.innerHTML = `
                <div class="prs-item-list">
                    <table class="table table-sm prs-catalog-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>PRS / PO / RR</th>
                                <th>Remaining</th>
                                <th>Qty</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item) => `
                                <tr class="prs-catalog-row"
                                    data-receiving-report-item-id="${item.receiving_report_item_id}"
                                    data-item-id="${item.item_id}"
                                    data-name="${escapeHtml(item.name)}"
                                    data-code="${escapeHtml(item.code)}"
                                    data-unit="${escapeHtml(item.unit || 'PCS')}"
                                    data-stock="${item.qty_remaining}"
                                    data-prs-number="${escapeHtml(item.prs_number || '')}"
                                    data-po-number="${escapeHtml(item.po_number || '')}"
                                    data-rr-number="${escapeHtml(item.rr_number || '')}">
                                    <td data-label="Code"><span class="badge bg-light-primary">${escapeHtml(item.code)}</span></td>
                                    <td data-label="Name">${escapeHtml(item.name)}</td>
                                    <td data-label="References" class="text-muted small">${escapeHtml(item.prs_number)} / ${escapeHtml(item.po_number)} / ${escapeHtml(item.rr_number)}</td>
                                    <td data-label="Remaining">${escapeHtml(item.qty_remaining)} ${escapeHtml(item.unit || 'PCS')}</td>
                                    <td data-label="Qty">
                                        <div class="prs-catalog-list-qty">
                                            <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity"><i class="fa-light fa-minus"></i></button>
                                            <input type="number" min="0.00001" step="0.00001" max="${item.qty_remaining}" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                                            <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Increase quantity"><i class="fa-light fa-plus"></i></button>
                                        </div>
                                    </td>
                                    <td data-label="Action" class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="${item.item_id}">
                                            <i class="fa-light fa-plus"></i> Add
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            grid.dataset.layout = 'list';
            grid.classList.remove('prs-item-grid');
            grid.classList.add('prs-catalog-list-mode');
            updateCatalogButtons();
            return;
        }

        grid.dataset.layout = 'grid';
        grid.classList.add('prs-item-grid');
        grid.classList.remove('prs-catalog-list-mode');
        grid.innerHTML = items.map((item) => `
            <div class="prs-item-card"
                data-receiving-report-item-id="${item.receiving_report_item_id}"
                data-item-id="${item.item_id}"
                data-name="${escapeHtml(item.name)}"
                data-code="${escapeHtml(item.code)}"
                data-unit="${escapeHtml(item.unit || 'PCS')}"
                data-stock="${item.qty_remaining}"
                data-prs-number="${escapeHtml(item.prs_number || '')}"
                data-po-number="${escapeHtml(item.po_number || '')}"
                data-rr-number="${escapeHtml(item.rr_number || '')}">
                <div class="prs-item-body">
                    <div class="prs-item-title">${escapeHtml(item.name)}</div>
                    <div class="prs-item-meta">
                        <span class="badge bg-light-primary">${escapeHtml(item.code)}</span>
                        <span class="badge bg-light-warning text-dark">CAPEX</span>
                    </div>
                    <div class="prs-item-meta text-muted small">PRS ${escapeHtml(item.prs_number)} · PO ${escapeHtml(item.po_number)} · RR ${escapeHtml(item.rr_number)}</div>
                    <div class="prs-item-meta text-muted">Remaining ${escapeHtml(item.qty_remaining)} ${escapeHtml(item.unit || 'PCS')}</div>
                    <div class="prs-item-actions">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity"><i class="fa-light fa-minus"></i></button>
                        <input type="number" min="0.00001" step="0.00001" max="${item.qty_remaining}" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Increase quantity"><i class="fa-light fa-plus"></i></button>
                        <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="${item.item_id}">
                            <i class="fa-light fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        updateCatalogButtons();
    };

    const renderCatalogView = (items) => {
        if (isCapexMode()) {
            renderCapexCatalogView(items);
            return;
        }

        window.CatalogLayout?.renderCatalog(
            grid,
            items,
            catalogLayout,
            buildEmptyStateMarkup(),
            { withStockAttrs: true }
        );
        updateCatalogButtons();
    };

    const setLayoutActiveState = () => {
        window.CatalogLayout?.setToggleActive(layoutToggle, catalogLayout);
    };

    const applyLayout = (layout, { persist = true, rerender = true } = {}) => {
        catalogLayout = layout === 'list' ? 'list' : 'grid';

        if (persist) {
            window.CatalogLayout?.saveLayout(LAYOUT_KEY, catalogLayout);
        }

        setLayoutActiveState();

        if (!rerender) {
            return;
        }

        if (lastCatalogItems.length > 0) {
            renderCatalogView(lastCatalogItems);
            return;
        }

        fetchCatalog(state.page);
    };

    const buildPageItems = (current, last) => {
        const pages = [];
        const add = (value) => pages.push(value);
        add(1);

        for (let page = current - 1; page <= current + 1; page += 1) {
            if (page > 1 && page < last) add(page);
        }

        if (last > 1) add(last);

        const unique = [...new Set(pages)].sort((a, b) => a - b);
        const output = [];

        unique.forEach((page, index) => {
            output.push(page);
            const next = unique[index + 1];
            if (next && next - page > 1) output.push('...');
        });

        return output;
    };

    const renderPagination = () => {
        if (!paginationContainer) {
            return;
        }

        if (state.lastPage <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        const pageItems = buildPageItems(state.page, state.lastPage);
        const pageButtons = pageItems.map((item) => {
            if (item === '...') {
                return '<span class="prs-page-ellipsis">...</span>';
            }

            const activeClass = item === state.page ? 'active' : '';
            return `<button type="button" class="prs-page-btn ${activeClass}" data-page="${item}">${item}</button>`;
        }).join('');

        paginationContainer.innerHTML = `
            <div class="prs-pagination-inner">
                <button type="button" class="prs-page-btn" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>Prev</button>
                ${pageButtons}
                <button type="button" class="prs-page-btn" data-page="${state.page + 1}" ${state.page >= state.lastPage ? 'disabled' : ''}>Next</button>
            </div>
        `;
    };

    const renderGrid = (items) => {
        renderCatalogView(items);
    };

    const fetchCatalog = async (page = 1) => {
        const requestSeq = catalogRequestSeq + 1;
        catalogRequestSeq = requestSeq;

        if (catalogAbortController) {
            catalogAbortController.abort();
        }

        catalogAbortController = window.AbortController ? new AbortController() : null;
        const query = new URLSearchParams();
        const search = (searchInput?.value || '').trim();
        let targetUrl = baseUrl;

        if (isCapexMode()) {
            const departmentId = (cartDepartmentSelect?.value || '').trim();
            if (!departmentId) {
                setCatalogLoading(false);
                grid.innerHTML = buildCatalogStatusMarkup(
                    'fa-duotone fa-solid fa-building-columns',
                    'Select charged department in the cart.',
                    'Open the cart and choose Charged to Department to load available CAPEX RR lines.'
                );
                if (paginationContainer) {
                    paginationContainer.innerHTML = '';
                }
                return;
            }

            if (search) {
                query.set('search', search);
            }
            query.set('department_id', departmentId);
            query.set('page', String(page));
            if (excludeStoreWithdrawalId > 0) {
                query.set('exclude_store_withdrawal_id', String(excludeStoreWithdrawalId));
            }
            // On edit, use the page URL so remaining qty excludes the current SWS.
            targetUrl = `${modeLocked ? baseUrl : capexUrl}?${query.toString()}`;
        } else {
            const category = (categoryFilter?.value || '').trim();
            const stock = (stockFilter?.value || '').trim();

            if (search) query.set('search', search);
            if (category) query.set('category', category);
            if (stock) query.set('stock', stock);
            query.set('page', String(page));
            targetUrl = `${baseUrl}?${query.toString()}`;
        }

        const scrollPos = window.scrollY || window.pageYOffset;

        setCatalogLoading(true);

        try {
            const response = await fetch(targetUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: catalogAbortController?.signal,
            });

            if (requestSeq !== catalogRequestSeq) {
                return;
            }

            if (!response.ok) {
                setCatalogLoading(false);
                renderCatalogError();
                state.page = 1;
                state.lastPage = 1;
                renderPagination();
                return;
            }

            const result = await response.json();
            if (!result || !Array.isArray(result.data) || !result.meta) {
                setCatalogLoading(false);
                renderCatalogError();
                state.page = 1;
                state.lastPage = 1;
                renderPagination();
                return;
            }

            setCatalogLoading(false);
            lastCatalogItems = result.data;
            renderCatalogView(lastCatalogItems);
            state.page = Number(result.meta.current_page || 1);
            state.lastPage = Number(result.meta.last_page || 1);
            renderPagination();
            updateCatalogButtons();

            if (page > 1) {
                setTimeout(() => {
                    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 50);
            } else {
                window.scrollTo(0, scrollPos);
            }

            const cleanUrl = new URL(window.location.href);
            cleanUrl.search = query.toString();
            window.history.replaceState({}, '', cleanUrl.toString());
        } catch (error) {
            if (error?.name === 'AbortError' || requestSeq !== catalogRequestSeq) {
                return;
            }

            setCatalogLoading(false);
            renderCatalogError();
            state.page = 1;
            state.lastPage = 1;
            window.scrollTo(0, scrollPos);
            renderPagination();
        }
    };

    let filterTimer = null;
    const triggerFilter = (immediate = false) => {
        const run = () => fetchCatalog(1);

        if (immediate) {
            run();
            return;
        }

        if (filterTimer) {
            clearTimeout(filterTimer);
        }

        filterTimer = setTimeout(run, 350);
    };

    if (layoutToggle) {
        layoutToggle.addEventListener('click', (event) => {
            const button = event.target.closest('[data-layout]');
            if (!button || button.classList.contains('active')) {
                return;
            }

            applyLayout(button.dataset.layout);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => triggerFilter(false));
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', () => triggerFilter(true));
    }

    if (stockFilter) {
        stockFilter.addEventListener('change', () => triggerFilter(true));
    }

    if (resetFilterButton) {
        resetFilterButton.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (stockFilter) stockFilter.value = '';
            triggerFilter(true);
        });
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const button = event.target.closest('.prs-page-btn');
            if (!button || button.disabled || button.hasAttribute('disabled')) {
                return;
            }

            const page = parseInt(button.dataset.page || '1', 10);
            if (Number.isNaN(page) || page < 1 || page > state.lastPage) {
                return;
            }

            fetchCatalog(page);
        });
    }

    grid.addEventListener('click', (event) => {
        const plus = event.target.closest('.prs-qty-plus');
        if (plus) {
            const row = plus.closest('.prs-item-card, .prs-catalog-row');
            const qtyInput = row?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const payload = row ? getCardPayload(row) : null;
                const current = parseQuantity(qtyInput.value, 1);
                const nextValue = current + 1;
                const allowedQuantity = payload ? getAllowedQuantity(payload.stock, nextValue) : nextValue;
                qtyInput.value = String(allowedQuantity <= 0 ? 1 : allowedQuantity);

                if (!isConfirmatoryType() && payload && nextValue > allowedQuantity) {
                    showStockRuleHint('Normal type quantity cannot exceed available stock.');
                }
            }
            return;
        }

        const minus = event.target.closest('.prs-qty-minus');
        if (minus) {
            const row = minus.closest('.prs-item-card, .prs-catalog-row');
            const qtyInput = row?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const current = parseQuantity(qtyInput.value, 1);
                qtyInput.value = String(Math.max(0.00001, current - 1));
            }
            return;
        }

        const addButton = event.target.closest('.prs-item-add');
        if (!addButton) {
            return;
        }

        const row = addButton.closest('.prs-item-card, .prs-catalog-row');
        if (!row) {
            return;
        }

        const payload = getCardPayload(row);
        const qtyInput = row.querySelector('.prs-item-qty');
        const quantity = parseQuantity(qtyInput?.value, 1);

        if (qtyInput) {
            qtyInput.value = String(getAllowedQuantity(payload.stock, quantity) || 1);
        }

        addToCart(payload, quantity);
    });

    if (cartItemsContainer) {
        cartItemsContainer.addEventListener('input', (event) => {
            const qtyInput = event.target.closest('.sws-cart-qty');
            if (!qtyInput) {
                return;
            }

            commitCartQuantity(qtyInput, { rewriteValue: false });
        });

        cartItemsContainer.addEventListener('change', (event) => {
            const qtyInput = event.target.closest('.sws-cart-qty');
            if (!qtyInput) {
                return;
            }

            commitCartQuantity(qtyInput, { rewriteValue: true });
        });

        cartItemsContainer.addEventListener('click', (event) => {
            const incrementButton = event.target.closest('.sws-cart-increment');
            if (incrementButton) {
                const cartKey = parseCartKey(incrementButton.dataset.cartKey || '');
                if (!cartKey || !state.cart.has(cartKey)) {
                    return;
                }

                const current = state.cart.get(cartKey);
                if (isCartLineLocked(current)) {
                    return;
                }

                const nextQuantity = getAllowedQuantity(current.stock, parseQuantity(current.quantity, 1) + 1, current);

                if (!isConfirmatoryType() && !isCapexMode() && nextQuantity === Number(current.quantity || 1)) {
                    showStockRuleHint('Normal type quantity cannot exceed available stock.');
                }

                state.cart.set(cartKey, {
                    ...current,
                    quantity: nextQuantity <= 0 ? Math.max(0.00001, getTransferredQuantity(current) || 1) : nextQuantity,
                });

                renderCart();
                return;
            }

            const decrementButton = event.target.closest('.sws-cart-decrement');
            if (decrementButton) {
                const cartKey = parseCartKey(decrementButton.dataset.cartKey || '');
                if (!cartKey || !state.cart.has(cartKey)) {
                    return;
                }

                const current = state.cart.get(cartKey);
                if (isCartLineLocked(current)) {
                    return;
                }

                const transferred = getTransferredQuantity(current);
                const minQuantity = transferred > 0.00001 ? transferred : 0.00001;
                state.cart.set(cartKey, {
                    ...current,
                    quantity: Math.max(minQuantity, parseQuantity(current.quantity, 1) - 1),
                });

                renderCart();
                return;
            }

            const removeButton = event.target.closest('.sws-cart-remove');
            if (!removeButton || removeButton.disabled) {
                return;
            }

            const cartKey = parseCartKey(removeButton.dataset.cartKey || '');
            if (!cartKey || !state.cart.has(cartKey)) {
                return;
            }

            const current = state.cart.get(cartKey);
            if (isCartLineLocked(current) || getTransferredQuantity(current) > 0.00001) {
                showStockRuleHint('Items that already have a transfer slip cannot be removed.');
                return;
            }

            state.cart.delete(cartKey);
            renderCart();
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            removeZeroStockItemsIfNormal();
            normalizeCartForStockRules();
            renderCart();
            updateCatalogButtons();
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            if (state.cart.size === 0) {
                event.preventDefault();
                showStockRuleHint('Add at least one item to the cart before submitting.');
                return;
            }

            if (isCapexMode()) {
                if (typeSelect) {
                    typeSelect.value = 'CAPEX';
                }
                return;
            }

            if (!isConfirmatoryType()) {
                const hasZeroStock = Array.from(state.cart.values()).some((item) => {
                    const transferred = getTransferredQuantity(item);
                    const additional = Number(item.quantity) - transferred;

                    return Number(item.stock) <= 0 && additional > 0.00001;
                });
                if (hasZeroStock) {
                    event.preventDefault();
                    showStockRuleHint('Normal type cannot contain zero-stock items.');
                    return;
                }

                const hasOverStock = Array.from(state.cart.values()).some((item) => {
                    const transferred = getTransferredQuantity(item);
                    const additional = Number(item.quantity) - transferred;

                    return additional > Number(item.stock || 0);
                });
                if (hasOverStock) {
                    event.preventDefault();
                    normalizeCartForStockRules();
                    renderCart();
                    showStockRuleHint('Normal type quantity cannot exceed available stock. Review the adjusted cart and submit again.');
                }
            }
        });
    }

    if (topCartBtn && floatingCartBtn && window.IntersectionObserver) {
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                floatingCartBtn.classList.remove('show');
            } else {
                floatingCartBtn.classList.add('show');
            }
        }, {
            threshold: 0.5,
        });

        observer.observe(topCartBtn);
    }

    const applyWithdrawalModeUi = () => {
        const capexActive = isCapexMode();

        modeToggle?.querySelectorAll('[data-withdrawal-mode]').forEach((button) => {
            const active = button.dataset.withdrawalMode === withdrawalMode;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (normalModeHint) {
            normalModeHint.style.display = capexActive ? 'none' : '';
        }
        if (capexModeHint) {
            capexModeHint.style.display = capexActive ? '' : 'none';
        }
        filterBar?.classList.toggle('sws-catalog-toolbar--capex', capexActive);
        normalOnlyFilters.forEach((element) => {
            element.classList.toggle('d-none', capexActive);
        });
        if (typeField) {
            typeField.classList.toggle('d-none', capexActive);
        }
        if (typeSelect) {
            typeSelect.value = capexActive ? 'CAPEX' : (typeSelect.value === 'CAPEX' ? 'NORMAL' : typeSelect.value);
        }
        if (searchInput) {
            searchInput.placeholder = capexActive
                ? 'PRS, PO, RR, item code, or item name'
                : 'Item name, code, category, or unit';
        }
    };

    const switchWithdrawalMode = (mode) => {
        const nextMode = mode === 'capex' ? 'capex' : 'normal';
        if (nextMode === withdrawalMode) {
            return;
        }

        withdrawalMode = nextMode;
        state.cart.clear();
        state.page = 1;
        state.lastPage = 1;
        lastCatalogItems = [];
        showStockRuleHint('');
        applyWithdrawalModeUi();
        renderCart();

        if (isCapexMode()) {
            fetchCatalog(1);
            return;
        }

        fetchCatalog(1);
    };

    if (modeToggle) {
        modeToggle.addEventListener('click', (event) => {
            if (modeLocked) {
                return;
            }

            const button = event.target.closest('[data-withdrawal-mode]');
            if (!button) {
                return;
            }

            switchWithdrawalMode(button.dataset.withdrawalMode);
        });
    }

    if (cartDepartmentSelect) {
        cartDepartmentSelect.addEventListener('change', () => {
            if (isCapexMode()) {
                triggerFilter(true);
            }
        });
    }

    setLayoutActiveState();
    applyWithdrawalModeUi();
    seedExistingCartItems();

    if (autoOpenCart) {
        const cartPopup = document.getElementById('sws-cart-popup');
        const backdrop = document.getElementById('sws-cart-backdrop');
        if (cartPopup) {
            cartPopup.classList.remove('is-hidden');
            cartPopup.setAttribute('aria-hidden', 'false');
        }
        if (backdrop) {
            backdrop.classList.remove('is-hidden');
        }
    }

    if (isCapexMode()) {
        fetchCatalog(1);
        renderCart();
    } else if (catalogLayout === 'list') {
        fetchCatalog(state.page);
        renderCart();
    } else {
        renderPagination();
        renderCart();
        updateCatalogButtons();
    }
}
