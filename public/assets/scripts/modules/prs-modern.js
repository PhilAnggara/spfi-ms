document.addEventListener('DOMContentLoaded', function () {
    initPrsFilters();
    initPrsCartPopup();
    initPrsCatalog();
    initPrsCartCount();
});

function initPrsFilters() {
    const filterForm = document.getElementById('prs-filter-form');
    if (!filterForm) {
        return;
    }

    if (filterForm.dataset.filterInitialized === '1') {
        return;
    }
    filterForm.dataset.filterInitialized = '1';

    const filterElements = {
        keyword: document.getElementById('filter-keyword'),
        status: document.getElementById('filter-status'),
        department: document.getElementById('filter-department'),
        prsStart: document.getElementById('filter-prs-start'),
        prsEnd: document.getElementById('filter-prs-end'),
        neededStart: document.getElementById('filter-needed-start'),
        neededEnd: document.getElementById('filter-needed-end'),
        reset: document.getElementById('reset-prs-filter'),
    };

    const setQueryParam = (searchParams, key, value) => {
        const normalizedValue = String(value || '').trim();
        if (normalizedValue === '') {
            searchParams.delete(key);
            return;
        }

        searchParams.set(key, normalizedValue);
    };

    const buildFilterUrl = () => {
        const url = new URL(window.location.href);

        setQueryParam(url.searchParams, 'keyword', filterElements.keyword?.value);
        setQueryParam(url.searchParams, 'status', filterElements.status?.value);
        setQueryParam(url.searchParams, 'department', filterElements.department?.value);
        setQueryParam(url.searchParams, 'prs_start', filterElements.prsStart?.value);
        setQueryParam(url.searchParams, 'prs_end', filterElements.prsEnd?.value);
        setQueryParam(url.searchParams, 'needed_start', filterElements.neededStart?.value);
        setQueryParam(url.searchParams, 'needed_end', filterElements.neededEnd?.value);

        url.searchParams.delete('page');

        return url.toString();
    };

    let debounceTimer = null;
    const applyServerFilter = (useDebounce = false) => {
        const doRequest = () => {
            const url = buildFilterUrl();
            if (typeof window.prsReplacePageContent === 'function') {
                window.prsReplacePageContent(url, true);
                return;
            }

            window.location.href = url;
        };

        if (!useDebounce) {
            doRequest();
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doRequest, 400);
    };

    if (filterElements.keyword) {
        filterElements.keyword.addEventListener('input', () => applyServerFilter(true));
    }
    if (filterElements.status) {
        filterElements.status.addEventListener('change', () => applyServerFilter(false));
    }
    if (filterElements.department) {
        filterElements.department.addEventListener('change', () => applyServerFilter(false));
    }
    if (filterElements.prsStart) {
        filterElements.prsStart.addEventListener('change', () => applyServerFilter(false));
    }
    if (filterElements.prsEnd) {
        filterElements.prsEnd.addEventListener('change', () => applyServerFilter(false));
    }
    if (filterElements.neededStart) {
        filterElements.neededStart.addEventListener('change', () => applyServerFilter(false));
    }
    if (filterElements.neededEnd) {
        filterElements.neededEnd.addEventListener('change', () => applyServerFilter(false));
    }

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', () => {
            if (filterElements.keyword) filterElements.keyword.value = '';
            if (filterElements.status) filterElements.status.value = '';
            if (filterElements.department) filterElements.department.value = '';
            if (filterElements.prsStart) filterElements.prsStart.value = '';
            if (filterElements.prsEnd) filterElements.prsEnd.value = '';
            if (filterElements.neededStart) filterElements.neededStart.value = '';
            if (filterElements.neededEnd) filterElements.neededEnd.value = '';

            applyServerFilter(false);
        });
    }
}

function initPrsCartPopup() {
    const cartPopup = document.getElementById('prs-cart-popup');
    if (!cartPopup) {
        return;
    }

    let backdrop = document.getElementById('prs-cart-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'prs-cart-backdrop';
        backdrop.className = 'prs-cart-backdrop is-hidden';
        document.body.appendChild(backdrop);
    }

    const toggleButton = document.getElementById('toggle-prs-cart');
    const toggleButtonMobile = document.getElementById('toggle-prs-cart-mobile');
    const hideButton = document.getElementById('hide-prs-cart');

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

function initPrsCatalog() {
    const grid = document.getElementById('prs-item-grid');
    if (!grid) {
        return;
    }

    const LAYOUT_KEY = 'prs-create-catalog-layout';
    const filterBar = document.getElementById('prs-catalog-filter-form');
    const baseUrl = filterBar?.dataset.baseUrl || window.location.pathname;
    const searchInput = document.getElementById('prs-item-search');
    const categoryFilter = document.getElementById('prs-category-filter');
    const stockFilter = document.getElementById('prs-stock-filter');
    const resetFilterButton = document.getElementById('prs-reset-filter');
    const layoutToggle = filterBar?.querySelector('.prs-layout-toggle');
    const paginationContainer = document.getElementById('prs-pagination');
    const initialCurrentPage = parseInt(paginationContainer?.dataset.currentPage || '1', 10);
    const initialLastPage = parseInt(paginationContainer?.dataset.lastPage || '1', 10);
    const cartItems = new Set();
    let navigationTimer = null;
    let catalogAbortController = null;
    let catalogRequestSeq = 0;
    let catalogLayout = localStorage.getItem(LAYOUT_KEY) === 'list' ? 'list' : 'grid';
    let lastCatalogItems = [];
    const state = {
        page: Number.isNaN(initialCurrentPage) ? 1 : initialCurrentPage,
        lastPage: Number.isNaN(initialLastPage) ? 1 : initialLastPage,
    };

    const getCatalogRows = () => Array.from(grid.querySelectorAll('.prs-item-card, .prs-catalog-row'));

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

    const buildGridCardMarkup = (item) => {
        const itemName = escapeHtml(item.name);
        const itemCode = escapeHtml(item.code);
        const itemCategory = escapeHtml(item.category || 'Uncategorized');
        const unit = escapeHtml(item.unit || 'PCS');
        const stock = escapeHtml(item.stock_on_hand ?? 0);

        return `
            <div class="prs-item-card" data-name="${itemName.toLowerCase()}" data-code="${itemCode.toLowerCase()}" data-category="${itemCategory.toLowerCase()}" data-item-id="${item.id}">
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
    };

    const buildListRowMarkup = (item) => {
        const itemName = escapeHtml(item.name);
        const itemCode = escapeHtml(item.code);
        const itemCategory = escapeHtml(item.category || 'Uncategorized');
        const unit = escapeHtml(item.unit || 'PCS');
        const stock = escapeHtml(item.stock_on_hand ?? 0);

        return `
            <tr class="prs-catalog-row" data-name="${itemName.toLowerCase()}" data-code="${itemCode.toLowerCase()}" data-category="${itemCategory.toLowerCase()}" data-item-id="${item.id}">
                <td data-label="Code"><span class="badge bg-light-primary">${itemCode}</span></td>
                <td data-label="Name"><span class="prs-catalog-item-name">${itemName}</span></td>
                <td data-label="Category" class="text-muted">${itemCategory}</td>
                <td data-label="Stock" class="text-muted">${stock} ${unit}</td>
                <td data-label="Qty">
                    <div class="prs-catalog-list-qty">
                        <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Decrease quantity">
                            <i class="fa-light fa-minus"></i>
                        </button>
                        <input type="number" min="1" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
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

    const renderCatalog = (items, layout = catalogLayout) => {
        grid.dataset.layout = layout;
        grid.classList.toggle('prs-item-grid', layout === 'grid');
        grid.classList.toggle('prs-catalog-list-mode', layout === 'list');

        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = buildEmptyStateMarkup();
            return;
        }

        if (layout === 'list') {
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
                            ${items.map((item) => buildListRowMarkup(item)).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            return;
        }

        grid.innerHTML = items.map((item) => buildGridCardMarkup(item)).join('');
    };

    const setLayoutActiveState = () => {
        if (!layoutToggle) {
            return;
        }

        layoutToggle.querySelectorAll('[data-layout]').forEach((button) => {
            const isActive = button.dataset.layout === catalogLayout;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const applyLayout = (layout, { persist = true, rerender = true } = {}) => {
        catalogLayout = layout === 'list' ? 'list' : 'grid';

        if (persist) {
            localStorage.setItem(LAYOUT_KEY, catalogLayout);
        }

        setLayoutActiveState();

        if (!rerender) {
            return;
        }

        if (lastCatalogItems.length > 0) {
            renderCatalog(lastCatalogItems, catalogLayout);
            updateInCartState();
            return;
        }

        fetchCatalog(state.page);
    };

    const buildPageItems = (current, last) => {
        const pages = [];
        const add = (value) => pages.push(value);
        add(1);

        for (let page = current - 1; page <= current + 1; page++) {
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
        if (!paginationContainer) return;

        if (state.lastPage <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        const pageItems = buildPageItems(state.page, state.lastPage);
        const pageButtons = pageItems.map((item) => {
            if (item === '...') {
                return '<span class="prs-page-ellipsis">…</span>';
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
                    'Accept': 'application/json',
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
            renderCatalog(lastCatalogItems, catalogLayout);
            state.page = Number(result.meta.current_page || 1);
            state.lastPage = Number(result.meta.last_page || 1);
            renderPagination();
            updateInCartState();

            // Preserve scroll position or scroll smoothly to grid on pagination
            if (page > 1) {
                if (grid) {
                    setTimeout(() => {
                        grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 50);
                }
            } else {
                // Restore scroll position for search/filter
                window.scrollTo(0, scrollPos);
            }

            const cleanUrl = new URL(window.location.href);
            cleanUrl.search = query.toString();
            window.history.replaceState({}, '', cleanUrl.toString());
        } catch (error) {
            if (error?.name === 'AbortError' || requestSeq !== catalogRequestSeq) {
                return;
            }

            console.error('Catalog fetch error:', error);
            setCatalogLoading(false);
            renderCatalogError();
            state.page = 1;
            state.lastPage = 1;
            window.scrollTo(0, scrollPos);
            renderPagination();
        }
    };

    const triggerFilter = (immediate = false) => {
        const run = () => fetchCatalog(1);

        if (immediate) {
            run();
            return;
        }

        if (navigationTimer) {
            clearTimeout(navigationTimer);
        }

        navigationTimer = setTimeout(run, 350);
    };

    const updateInCartState = () => {
        getCatalogRows().forEach((row) => {
            const itemId = parseInt(row.dataset.itemId || '0', 10);
            const addButton = row.querySelector('.prs-item-add');
            if (!itemId || !addButton) {
                return;
            }

            const isInCart = cartItems.has(itemId);
            addButton.classList.toggle('btn-primary', !isInCart);
            addButton.classList.toggle('btn-outline-success', isInCart);
            addButton.innerHTML = isInCart
                ? '<i class="fa-light fa-cart-plus"></i> Update'
                : '<i class="fa-light fa-plus"></i> Add';
        });
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
        const qtyPlus = event.target.closest('.prs-qty-plus');
        if (qtyPlus) {
            const row = qtyPlus.closest('.prs-item-card, .prs-catalog-row');
            const qtyInput = row?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const current = parseInt(qtyInput.value || '1', 10);
                qtyInput.value = Number.isNaN(current) ? 1 : current + 1;
            }
            return;
        }

        const qtyMinus = event.target.closest('.prs-qty-minus');
        if (qtyMinus) {
            const row = qtyMinus.closest('.prs-item-card, .prs-catalog-row');
            const qtyInput = row?.querySelector('.prs-item-qty');
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

        const row = addButton.closest('.prs-item-card, .prs-catalog-row');
        if (!row) {
            return;
        }

        const qtyInput = row.querySelector('.prs-item-qty');
        const qtyValue = parseInt(qtyInput?.value || '1', 10);
        const quantity = Number.isNaN(qtyValue) || qtyValue < 1 ? 1 : qtyValue;
        if (qtyInput) {
            qtyInput.value = quantity;
        }

        const itemId = parseInt(addButton.dataset.itemId || '0', 10);
        if (!itemId) {
            return;
        }

        const livewireHost = document.querySelector('#prs-cart-component [wire\\:id]');
        const componentId = livewireHost?.getAttribute('wire:id');
        const component = componentId ? window.Livewire?.find(componentId) : null;
        if (component) {
            component.call('addFromCatalog', itemId, quantity);
        }

        cartItems.add(itemId);
        updateInCartState();
    });

    window.addEventListener('prs-cart-count', (event) => {
        const ids = Array.isArray(event.detail?.itemIds) ? event.detail.itemIds : [];
        cartItems.clear();
        ids.forEach((id) => cartItems.add(parseInt(id, 10)));
        updateInCartState();
    });

    setLayoutActiveState();

    if (catalogLayout === 'list') {
        fetchCatalog(state.page);
    } else {
        renderPagination();
        updateInCartState();
    }
}

function initPrsCartCount() {
    const countEl = document.getElementById('prs-cart-count');
    const floatingCartBtn = document.getElementById('toggle-prs-cart-mobile');
    const topCartBtn = document.getElementById('toggle-prs-cart');

    if (!countEl || !floatingCartBtn) {
        return;
    }

    // Create badge for floating cart button if it doesn't exist
    let floatingBadge = floatingCartBtn.querySelector('.prs-cart-badge');
    if (!floatingBadge) {
        floatingBadge = document.createElement('span');
        floatingBadge.className = 'prs-cart-badge';
        floatingBadge.textContent = '0';
        floatingCartBtn.appendChild(floatingBadge);
    }

    // Initialize Intersection Observer to show/hide floating button based on top button visibility
    if (topCartBtn && IntersectionObserver) {
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                // Top button is visible, hide floating button
                floatingCartBtn.classList.remove('show');
            } else {
                // Top button is not visible, show floating button
                floatingCartBtn.classList.add('show');
            }
        }, {
            threshold: 0.5
        });

        observer.observe(topCartBtn);
    }

    // Update both badges when cart count changes
    window.addEventListener('prs-cart-count', (event) => {
        const count = event.detail?.count ?? 0;
        countEl.textContent = count;
        floatingBadge.textContent = count;
    });
}
