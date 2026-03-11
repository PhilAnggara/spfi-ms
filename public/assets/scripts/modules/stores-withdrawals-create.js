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

    const filterBar = document.getElementById('sws-catalog-filter-form');
    const baseUrl = filterBar?.dataset.baseUrl || window.location.pathname;
    const searchInput = document.getElementById('sws-item-search');
    const categoryFilter = document.getElementById('sws-category-filter');
    const resetFilterButton = document.getElementById('sws-reset-filter');
    const paginationContainer = document.getElementById('sws-pagination');
    const typeSelect = document.getElementById('sws-type');
    const cartItemsContainer = document.getElementById('sws-cart-list');
    const cartEmpty = document.getElementById('sws-cart-empty');
    const hiddenInputsContainer = document.getElementById('sws-cart-hidden-inputs');
    const cartCount = document.getElementById('sws-cart-count');
    const floatingCartBtn = document.getElementById('toggle-sws-cart-mobile');
    const topCartBtn = document.getElementById('toggle-sws-cart');
    const stockRuleHint = document.getElementById('sws-stock-rule-hint');
    const form = document.getElementById('sws-create-form');

    const state = {
        page: parseInt(paginationContainer?.dataset.currentPage || '1', 10) || 1,
        lastPage: parseInt(paginationContainer?.dataset.lastPage || '1', 10) || 1,
        cart: new Map(),
    };

    const isConfirmatoryType = () => String(typeSelect?.value || 'NORMAL') === 'CONFIRMATORY';

    const canSelectStock = (stockValue) => {
        if (isConfirmatoryType()) {
            return true;
        }

        return Number(stockValue) > 0;
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
                addButton.title = 'Normal type does not allow zero-stock items.';
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
            const stockLabelClass = Number(item.stock) <= 0 ? 'text-danger fw-semibold' : 'text-muted';

            return `
                <div class="prs-cart-item" data-item-id="${item.itemId}">
                    <div class="prs-cart-item-info">
                        <div class="prs-cart-thumb">
                            <i class="fa-duotone fa-solid fa-box"></i>
                        </div>
                        <div class="prs-cart-text">
                            <div class="fw-semibold">${escapeHtml(item.name)}</div>
                            <small class="text-muted">${escapeHtml(item.code)} · <span class="${stockLabelClass}">Stock ${item.stock}</span> ${escapeHtml(item.unit)}</small>
                        </div>
                    </div>
                    <div class="prs-cart-item-actions">
                        <div class="prs-cart-item-qty">
                            <div class="input-group input-group-sm">
                                <button type="button" class="btn btn-light-secondary sws-cart-decrement" data-item-id="${item.itemId}" aria-label="Decrease quantity">
                                    <i class="fa-light fa-minus"></i>
                                </button>
                                <input type="number" min="1" class="form-control sws-cart-qty" value="${item.quantity}" data-item-id="${item.itemId}">
                                <button type="button" class="btn btn-light-secondary sws-cart-increment" data-item-id="${item.itemId}" aria-label="Increase quantity">
                                    <i class="fa-light fa-plus"></i>
                                </button>
                                <span class="input-group-text">${escapeHtml(item.unit)}</span>
                            </div>
                        </div>
                        <div class="prs-cart-item-remove">
                            <button type="button" class="btn btn-sm btn-outline-danger sws-cart-remove" data-item-id="${item.itemId}">
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

    const removeZeroStockItemsIfNormal = () => {
        if (isConfirmatoryType()) {
            return;
        }

        let removedCount = 0;
        Array.from(state.cart.entries()).forEach(([itemId, item]) => {
            if (Number(item.stock) <= 0) {
                state.cart.delete(itemId);
                removedCount += 1;
            }
        });

        if (removedCount > 0) {
            showStockRuleHint(`Normal type is active. ${removedCount} zero-stock item(s) were removed from the cart.`);
        } else {
            showStockRuleHint('');
        }
    };

    const addToCart = (payload, quantity) => {
        if (!payload.itemId) {
            return;
        }

        if (!canSelectStock(payload.stock)) {
            showStockRuleHint('Normal type does not allow zero-stock items. Switch to Confirmatory if needed.');
            return;
        }

        showStockRuleHint('');

        const current = state.cart.get(payload.itemId);
        state.cart.set(payload.itemId, {
            ...payload,
            quantity: Math.max(1, Number(quantity) || 1),
            quantityInputValue: current?.quantityInputValue,
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
            grid.innerHTML = '<div class="text-muted">No items found.</div>';
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
        const query = new URLSearchParams();
        const search = (searchInput?.value || '').trim();
        const category = (categoryFilter?.value || '').trim();

        if (search) query.set('search', search);
        if (category) query.set('category', category);
        query.set('page', String(page));

        const targetUrl = `${baseUrl}?${query.toString()}`;
        const scrollPos = window.scrollY || window.pageYOffset;

        try {
            const response = await fetch(targetUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                renderPagination();
                return;
            }

            const result = await response.json();
            if (!result || !Array.isArray(result.data) || !result.meta) {
                renderPagination();
                return;
            }

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
        } catch (_) {
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

    if (resetFilterButton) {
        resetFilterButton.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = '';
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
                const current = parseInt(qtyInput.value || '1', 10);
                qtyInput.value = Number.isNaN(current) ? 1 : current + 1;
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
        const quantity = Math.max(1, parseInt(qtyInput?.value || '1', 10) || 1);

        if (qtyInput) {
            qtyInput.value = String(quantity);
        }

        addToCart(payload, quantity);
    });

    if (cartItemsContainer) {
        cartItemsContainer.addEventListener('input', (event) => {
            const qtyInput = event.target.closest('.sws-cart-qty');
            if (!qtyInput) {
                return;
            }

            const itemId = parseInt(qtyInput.dataset.itemId || '0', 10);
            if (!itemId || !state.cart.has(itemId)) {
                return;
            }

            const quantity = Math.max(1, parseInt(qtyInput.value || '1', 10) || 1);
            qtyInput.value = String(quantity);

            const current = state.cart.get(itemId);
            state.cart.set(itemId, {
                ...current,
                quantity,
            });

            renderCart();
        });

        cartItemsContainer.addEventListener('click', (event) => {
            const incrementButton = event.target.closest('.sws-cart-increment');
            if (incrementButton) {
                const itemId = parseInt(incrementButton.dataset.itemId || '0', 10);
                if (!itemId || !state.cart.has(itemId)) {
                    return;
                }

                const current = state.cart.get(itemId);
                state.cart.set(itemId, {
                    ...current,
                    quantity: Math.max(1, Number(current.quantity || 1) + 1),
                });

                renderCart();
                return;
            }

            const decrementButton = event.target.closest('.sws-cart-decrement');
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

            const removeButton = event.target.closest('.sws-cart-remove');
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

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            removeZeroStockItemsIfNormal();
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

            if (!isConfirmatoryType()) {
                const hasZeroStock = Array.from(state.cart.values()).some((item) => Number(item.stock) <= 0);
                if (hasZeroStock) {
                    event.preventDefault();
                    showStockRuleHint('Normal type cannot contain zero-stock items.');
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

    renderPagination();
    renderCart();
    updateCatalogButtons();
}
