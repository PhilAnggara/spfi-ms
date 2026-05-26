document.addEventListener('DOMContentLoaded', function () {
    initDeliveryCartPopup();
    initDeliverySupplierPicker();
    initDeliveryCatalogAndCart();
});

function initDeliverySupplierPicker() {
    const supplierDataScript = document.getElementById('delivery-supplier-data');

    const supplierIdInput = document.getElementById('delivery-supplier-id');
    const supplierNameInput = document.getElementById('delivery-to-name-display');
    const toLocationInput = document.getElementById('delivery-to-location');
    const pickSupplierButton = document.getElementById('delivery-pick-supplier');
    const modalEl = document.getElementById('deliverySupplierPickerModal');
    const pickerList = document.getElementById('delivery-supplier-picker-list');
    const pickerSearchInput = document.getElementById('delivery-supplier-picker-search');

    if (!supplierDataScript || !supplierIdInput || !supplierNameInput || !modalEl || !pickerList) {
        return;
    }

    let suppliers = [];
    try {
        suppliers = JSON.parse(supplierDataScript.textContent || '[]');
    } catch (_) {
        suppliers = [];
    }

    const modal = window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const assignSupplier = (supplier) => {
        if (!supplier) {
            return;
        }

        supplierIdInput.value = String(supplier.id);
        supplierNameInput.value = String(supplier.name || '');
        supplierNameInput.classList.remove('is-invalid');

        if (toLocationInput) {
            toLocationInput.value = String(supplier.address || '');
        }
    };

    const renderSupplierList = (keyword = '') => {
        const normalizedKeyword = String(keyword || '').trim().toLowerCase();

        const rows = suppliers
            .filter((supplier) => {
                if (!normalizedKeyword) {
                    return true;
                }

                return String(supplier.name || '').toLowerCase().includes(normalizedKeyword)
                    || String(supplier.address || '').toLowerCase().includes(normalizedKeyword);
            })
            .sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));

        if (rows.length === 0) {
            pickerList.innerHTML = '<div class="text-muted small p-2">Supplier not found.</div>';
            return;
        }

        pickerList.innerHTML = rows.map((supplier) => {
            const activeClass = String(supplier.id) === String(supplierIdInput.value || '') ? 'active' : '';
            const supplierName = escapeHtml(supplier.name || '');
            const supplierAddress = escapeHtml(supplier.address || '-');

            return `
                <button type="button" class="list-group-item list-group-item-action ${activeClass}" data-supplier-id="${supplier.id}">
                    <div class="fw-semibold">${supplierName}</div>
                    <div class="small text-muted">${supplierAddress}</div>
                </button>
            `;
        }).join('');
    };

    const showPicker = () => {
        renderSupplierList('');
        if (pickerSearchInput) {
            pickerSearchInput.value = '';
        }

        if (modal) {
            modal.show();
        }
    };

    if (pickSupplierButton) {
        pickSupplierButton.addEventListener('click', showPicker);
    }

    supplierNameInput.addEventListener('click', showPicker);

    if (pickerSearchInput) {
        pickerSearchInput.addEventListener('input', (event) => {
            renderSupplierList(event.target.value || '');
        });

        pickerSearchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            const firstSupplierButton = pickerList.querySelector('[data-supplier-id]');
            if (!firstSupplierButton) {
                return;
            }

            firstSupplierButton.click();
        });
    }

    pickerList.addEventListener('click', (event) => {
        const supplierButton = event.target.closest('[data-supplier-id]');
        if (!supplierButton) {
            return;
        }

        const supplierId = Number(supplierButton.dataset.supplierId || 0);
        if (!supplierId) {
            return;
        }

        const supplier = suppliers.find((item) => Number(item.id) === supplierId);
        assignSupplier(supplier);

        if (modal) {
            modal.hide();
        }
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        if (pickerSearchInput) {
            pickerSearchInput.focus();
        }
    });
}

function initDeliveryCartPopup() {
    const cartPopup = document.getElementById('delivery-cart-popup');
    if (!cartPopup) {
        return;
    }

    let backdrop = document.getElementById('delivery-cart-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'delivery-cart-backdrop';
        backdrop.className = 'prs-cart-backdrop is-hidden';
        document.body.appendChild(backdrop);
    }

    const toggleButton = document.getElementById('toggle-delivery-cart');
    const toggleButtonMobile = document.getElementById('toggle-delivery-cart-mobile');
    const hideButton = document.getElementById('hide-delivery-cart');

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

function initDeliveryCatalogAndCart() {
    const grid = document.getElementById('delivery-item-grid');
    if (!grid) {
        return;
    }

    const filterBar = document.getElementById('delivery-catalog-filter-form');
    const baseUrl = filterBar?.dataset.baseUrl || window.location.pathname;
    const searchInput = document.getElementById('delivery-item-search');
    const categoryFilter = document.getElementById('delivery-category-filter');
    const stockFilter = document.getElementById('delivery-stock-filter');
    const resetFilterButton = document.getElementById('delivery-reset-filter');
    const paginationContainer = document.getElementById('delivery-pagination');
    const cartItemsContainer = document.getElementById('delivery-cart-list');
    const cartEmpty = document.getElementById('delivery-cart-empty');
    const hiddenInputsContainer = document.getElementById('delivery-cart-hidden-inputs');
    const cartCount = document.getElementById('delivery-cart-count');
    const floatingCartBtn = document.getElementById('toggle-delivery-cart-mobile');
    const topCartBtn = document.getElementById('toggle-delivery-cart');
    const stockRuleHint = document.getElementById('delivery-stock-rule-hint');
    const form = document.getElementById('delivery-create-form');
    const supplierIdInput = document.getElementById('delivery-supplier-id');
    const supplierNameInput = document.getElementById('delivery-to-name-display');
    let catalogAbortController = null;
    let catalogRequestSeq = 0;

    const state = {
        page: parseInt(paginationContainer?.dataset.currentPage || '1', 10) || 1,
        lastPage: parseInt(paginationContainer?.dataset.lastPage || '1', 10) || 1,
        cart: new Map(),
    };

    const canSelectStock = (stockValue) => Number(stockValue) > 0;

    const getAllowedQuantity = (stockValue, requestedQuantity) => {
        const normalizedQuantity = Math.max(1, Number(requestedQuantity) || 1);
        const normalizedStock = Number(stockValue) || 0;
        if (normalizedStock <= 0) {
            return 0;
        }

        return Math.min(normalizedQuantity, normalizedStock);
    };

    const syncCatalogQuantityInput = (card) => {
        const qtyInput = card?.querySelector('.prs-item-qty');
        if (!qtyInput) {
            return;
        }

        const payload = getCardPayload(card);
        const allowedQuantity = getAllowedQuantity(payload.stock, qtyInput.value);

        qtyInput.min = '1';

        if (payload.stock > 0) {
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

    const getCards = () => Array.from(grid.querySelectorAll('.prs-item-card'));

    const getCardPayload = (card) => {
        const itemId = parseInt(card.dataset.itemId || '0', 10);
        const name = String(card.querySelector('.prs-item-title')?.textContent || '').trim();
        const code = String(card.querySelector('.badge')?.textContent || '').trim();
        const unit = String(card.dataset.unit || 'PCS').trim();
        const stock = Number(card.dataset.stock || 0);

        return {
            itemId,
            name,
            code,
            unit,
            stock,
        };
    };

    const updateCatalogButtons = () => {
        getCards().forEach((card) => {
            const payload = getCardPayload(card);
            const addButton = card.querySelector('.prs-item-add');
            syncCatalogQuantityInput(card);
            if (!addButton || !payload.itemId) {
                return;
            }

            const blocked = !canSelectStock(payload.stock);
            const inCart = state.cart.has(payload.itemId);

            addButton.disabled = blocked;
            addButton.classList.toggle('btn-primary', !inCart && !blocked);
            addButton.classList.toggle('btn-outline-success', inCart && !blocked);
            addButton.classList.toggle('btn-outline-secondary', blocked);

            if (blocked) {
                addButton.innerHTML = '<i class="fa-light fa-ban"></i> Stock 0';
                addButton.title = 'Delivery cannot include zero-stock items.';
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
            const quantityMaxAttribute = Number(item.stock) > 0
                ? `max="${item.stock}"`
                : '';

            return `
                <div class="prs-cart-item" data-item-id="${item.itemId}">
                    <div class="prs-cart-item-info">
                        <div class="prs-cart-thumb">
                            <i class="fa-duotone fa-solid fa-box"></i>
                        </div>
                        <div class="prs-cart-text">
                            <div class="fw-semibold">${escapeHtml(item.name)}</div>
                            <small class="text-muted">${escapeHtml(item.code)} · Stock ${item.stock} ${escapeHtml(item.unit)}</small>
                        </div>
                    </div>
                    <div class="prs-cart-item-actions">
                        <div class="prs-cart-item-qty">
                            <div class="input-group input-group-sm">
                                <button type="button" class="btn btn-light-secondary delivery-cart-decrement" data-item-id="${item.itemId}" aria-label="Decrease quantity">
                                    <i class="fa-light fa-minus"></i>
                                </button>
                                <input type="number" min="1" ${quantityMaxAttribute} class="form-control delivery-cart-qty" value="${item.quantity}" data-item-id="${item.itemId}">
                                <button type="button" class="btn btn-light-secondary delivery-cart-increment" data-item-id="${item.itemId}" aria-label="Increase quantity">
                                    <i class="fa-light fa-plus"></i>
                                </button>
                                <span class="input-group-text">${escapeHtml(item.unit)}</span>
                            </div>
                        </div>
                        <div class="prs-cart-item-remove">
                            <button type="button" class="btn btn-sm btn-outline-danger delivery-cart-remove" data-item-id="${item.itemId}">
                                <i class="fa-regular fa-trash"></i>
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        hiddenInputsContainer.innerHTML = cartItems.map((item, index) => {
            return `
                <input type="hidden" name="items[${index}][item_id]" value="${item.itemId}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
            `;
        }).join('');

        updateCountBadge();
        updateCatalogButtons();
    };

    const addToCart = (payload, quantity) => {
        if (!payload.itemId) {
            return;
        }

        if (!canSelectStock(payload.stock)) {
            showStockRuleHint('Delivery cannot include zero-stock items.');
            return;
        }

        const allowedQuantity = getAllowedQuantity(payload.stock, quantity);
        if (allowedQuantity <= 0) {
            showStockRuleHint('Delivery cannot include zero-stock items.');
            return;
        }

        if (Number(quantity) > allowedQuantity) {
            showStockRuleHint('Quantity cannot exceed available stock. Quantity was adjusted automatically.');
        } else {
            showStockRuleHint('');
        }

        state.cart.set(payload.itemId, {
            ...payload,
            quantity: allowedQuantity,
        });

        renderCart();
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
        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = buildEmptyStateMarkup();
            updateCatalogButtons();
            return;
        }

        grid.innerHTML = items.map((item) => {
            const itemName = escapeHtml(item.name);
            const itemCode = escapeHtml(item.code);
            const itemCategory = escapeHtml(item.category || 'Uncategorized');
            const unit = escapeHtml(item.unit || 'PCS');
            const stock = Number(item.stock_on_hand || 0);

            return `
                <div class="prs-item-card"
                    data-item-id="${item.id}"
                    data-name="${itemName.toLowerCase()}"
                    data-code="${itemCode.toLowerCase()}"
                    data-category="${itemCategory.toLowerCase()}"
                    data-stock="${stock}"
                    data-unit="${unit}">
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
                            <input type="number" min="1" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
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
        }).join('');

        updateCatalogButtons();
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
        const category = (categoryFilter?.value || '').trim();
        const stock = (stockFilter?.value || '').trim();

        if (search) query.set('search', search);
        if (category) query.set('category', category);
        if (stock) query.set('stock', stock);
        query.set('page', String(page));

        const targetUrl = `${baseUrl}?${query.toString()}`;
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
            renderGrid(result.data);
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
            const card = plus.closest('.prs-item-card');
            const qtyInput = card?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const payload = card ? getCardPayload(card) : null;
                const current = Number(qtyInput.value || '1');
                const nextValue = (Number.isNaN(current) ? 1 : current + 1);
                const allowedQuantity = payload ? getAllowedQuantity(payload.stock, nextValue) : nextValue;
                qtyInput.value = String(allowedQuantity <= 0 ? 1 : allowedQuantity);

                if (payload && nextValue > allowedQuantity) {
                    showStockRuleHint('Quantity cannot exceed available stock.');
                }
            }
            return;
        }

        const minus = event.target.closest('.prs-qty-minus');
        if (minus) {
            const card = minus.closest('.prs-item-card');
            const qtyInput = card?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const current = parseInt(qtyInput.value || '1', 10);
                qtyInput.value = Math.max(1, Number.isNaN(current) ? 1 : current - 1);
            }
            return;
        }

        const addButton = event.target.closest('.prs-item-add');
        if (!addButton) {
            return;
        }

        const card = addButton.closest('.prs-item-card');
        if (!card) {
            return;
        }

        const payload = getCardPayload(card);
        const qtyInput = card.querySelector('.prs-item-qty');
        const quantity = Math.max(1, Number(qtyInput?.value || '1') || 1);

        if (qtyInput) {
            qtyInput.value = String(getAllowedQuantity(payload.stock, quantity) || 1);
        }

        addToCart(payload, quantity);
    });

    grid.addEventListener('input', (event) => {
        const qtyInput = event.target.closest('.prs-item-qty');
        if (!qtyInput) {
            return;
        }

        const card = qtyInput.closest('.prs-item-card');
        const payload = card ? getCardPayload(card) : null;
        const quantity = Math.max(1, Number(qtyInput.value || '1') || 1);
        const allowedQuantity = payload ? getAllowedQuantity(payload.stock, quantity) : quantity;

        qtyInput.value = String(allowedQuantity <= 0 ? 1 : allowedQuantity);

        if (payload && quantity > allowedQuantity) {
            showStockRuleHint('Quantity cannot exceed available stock.');
        }
    });

    if (cartItemsContainer) {
        cartItemsContainer.addEventListener('input', (event) => {
            const qtyInput = event.target.closest('.delivery-cart-qty');
            if (!qtyInput) {
                return;
            }

            const itemId = parseInt(qtyInput.dataset.itemId || '0', 10);
            if (!itemId || !state.cart.has(itemId)) {
                return;
            }

            const current = state.cart.get(itemId);
            const quantity = Math.max(1, Number(qtyInput.value || '1') || 1);
            const allowedQuantity = getAllowedQuantity(current.stock, quantity);
            qtyInput.value = String(allowedQuantity <= 0 ? 1 : allowedQuantity);

            state.cart.set(itemId, {
                ...current,
                quantity: allowedQuantity <= 0 ? 1 : allowedQuantity,
            });

            if (quantity > allowedQuantity) {
                showStockRuleHint('Quantity cannot exceed available stock.');
            }

            renderCart();
        });

        cartItemsContainer.addEventListener('click', (event) => {
            const incrementButton = event.target.closest('.delivery-cart-increment');
            if (incrementButton) {
                const itemId = parseInt(incrementButton.dataset.itemId || '0', 10);
                if (!itemId || !state.cart.has(itemId)) {
                    return;
                }

                const current = state.cart.get(itemId);
                const nextQuantity = getAllowedQuantity(current.stock, Number(current.quantity || 1) + 1);

                if (nextQuantity === Number(current.quantity || 1)) {
                    showStockRuleHint('Quantity cannot exceed available stock.');
                }

                state.cart.set(itemId, {
                    ...current,
                    quantity: nextQuantity <= 0 ? 1 : nextQuantity,
                });

                renderCart();
                return;
            }

            const decrementButton = event.target.closest('.delivery-cart-decrement');
            if (decrementButton) {
                const itemId = parseInt(decrementButton.dataset.itemId || '0', 10);
                if (!itemId || !state.cart.has(itemId)) {
                    return;
                }

                const current = state.cart.get(itemId);
                state.cart.set(itemId, {
                    ...current,
                    quantity: Math.max(1, Number(current.quantity || 1) - 1),
                });

                renderCart();
                return;
            }

            const removeButton = event.target.closest('.delivery-cart-remove');
            if (!removeButton) {
                return;
            }

            const itemId = parseInt(removeButton.dataset.itemId || '0', 10);
            if (!itemId) {
                return;
            }

            state.cart.delete(itemId);
            renderCart();
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            if (!String(supplierIdInput?.value || '').trim()) {
                event.preventDefault();
                showStockRuleHint('Choose a supplier for destination first.');
                supplierNameInput?.classList.add('is-invalid');
                return;
            }

            supplierNameInput?.classList.remove('is-invalid');

            if (state.cart.size === 0) {
                event.preventDefault();
                showStockRuleHint('Add at least one item to the cart before submitting.');
                return;
            }

            const hasZeroStock = Array.from(state.cart.values()).some((item) => Number(item.stock) <= 0);
            if (hasZeroStock) {
                event.preventDefault();
                showStockRuleHint('Delivery cannot contain zero-stock items.');
                return;
            }

            const hasOverStock = Array.from(state.cart.values()).some((item) => Number(item.quantity) > Number(item.stock || 0));
            if (hasOverStock) {
                event.preventDefault();
                showStockRuleHint('Quantity cannot exceed available stock. Please review cart quantities.');
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

    renderPagination();
    renderCart();
    updateCatalogButtons();
}
