document.addEventListener('DOMContentLoaded', function () {
    initPrsFilters();
    initPrsCartPopup();
    initPrsCatalog();
    initPrsCartCount();
});

function initPrsFilters() {
    const tableBody = document.getElementById('prs-table-body');
    if (!tableBody) {
        return;
    }

    const rows = Array.from(tableBody.querySelectorAll('tr'));
    const resultEl = document.getElementById('prs-filter-result');

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

    const inRange = (dateValue, start, end) => {
        if (!dateValue) return false;
        if (start && dateValue < start) return false;
        if (end && dateValue > end) return false;
        return true;
    };

    const runFilter = () => {
        const keyword = (filterElements.keyword?.value || '').trim().toLowerCase();
        const status = (filterElements.status?.value || '').trim().toUpperCase();
        const department = (filterElements.department?.value || '').trim().toLowerCase();

        const prsStart = filterElements.prsStart?.value || '';
        const prsEnd = filterElements.prsEnd?.value || '';
        const neededStart = filterElements.neededStart?.value || '';
        const neededEnd = filterElements.neededEnd?.value || '';

        let visibleCount = 0;

        rows.forEach((row) => {
            const dataPrs = row.dataset.prsNumber || '';
            const dataDepartment = row.dataset.department || '';
            const dataStatus = row.dataset.status || '';
            const dataPrsDate = row.dataset.prsDate || '';
            const dataNeededDate = row.dataset.neededDate || '';
            const dataRemarks = row.dataset.remarks || '';

            const keywordText = `${dataPrs} ${dataDepartment} ${dataRemarks}`;

            const passKeyword = !keyword || keywordText.includes(keyword);
            const passStatus = !status || dataStatus === status;
            const passDepartment = !department || dataDepartment === department;
            const passPrsDate = inRange(dataPrsDate, prsStart, prsEnd);
            const passNeededDate = inRange(dataNeededDate, neededStart, neededEnd);

            const visible = passKeyword && passStatus && passDepartment && passPrsDate && passNeededDate;
            row.style.display = visible ? '' : 'none';

            if (visible) {
                visibleCount++;
            }
        });

        if (resultEl) {
            resultEl.textContent = `${visibleCount} data`;
        }
    };

    [
        filterElements.keyword,
        filterElements.status,
        filterElements.department,
        filterElements.prsStart,
        filterElements.prsEnd,
        filterElements.neededStart,
        filterElements.neededEnd,
    ].forEach((el) => {
        if (!el) return;
        el.addEventListener('input', runFilter);
        el.addEventListener('change', runFilter);
    });

    if (filterElements.reset) {
        filterElements.reset.addEventListener('click', () => {
            Object.values(filterElements).forEach((el) => {
                if (!el || el.tagName !== 'INPUT' && el.tagName !== 'SELECT') {
                    return;
                }
                el.value = '';
            });

            if (filterElements.status) {
                filterElements.status.value = '';
            }

            if (filterElements.department) {
                filterElements.department.value = '';
            }

            runFilter();
        });
    }

    runFilter();
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

    const filterBar = document.getElementById('prs-catalog-filter-form');
    const baseUrl = filterBar?.dataset.baseUrl || window.location.pathname;
    const searchInput = document.getElementById('prs-item-search');
    const categoryFilter = document.getElementById('prs-category-filter');
    const resetFilterButton = document.getElementById('prs-reset-filter');
    const paginationContainer = document.getElementById('prs-pagination');
    const initialCurrentPage = parseInt(paginationContainer?.dataset.currentPage || '1', 10);
    const initialLastPage = parseInt(paginationContainer?.dataset.lastPage || '1', 10);
    const cartItems = new Set();
    let navigationTimer = null;
    const state = {
        page: Number.isNaN(initialCurrentPage) ? 1 : initialCurrentPage,
        lastPage: Number.isNaN(initialLastPage) ? 1 : initialLastPage,
    };

    const getCards = () => Array.from(grid.querySelectorAll('.prs-item-card'));

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderGrid = (items) => {
        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = '<div class="text-muted">Item tidak ditemukan.</div>';
            return;
        }

        grid.innerHTML = items.map((item) => {
            const itemName = escapeHtml(item.name);
            const itemCode = escapeHtml(item.code);
            const itemCategory = escapeHtml(item.category || 'Uncategorized');
            const unit = escapeHtml(item.unit || 'PCS');
            const stock = escapeHtml(item.stock_on_hand ?? 0);
            const categoryIcon = escapeHtml(item.category_icon || 'fa-box');
            const categoryData = escapeHtml(item.category_data || 'other');

            return `
                <div class="prs-item-card" data-name="${itemName.toLowerCase()}" data-code="${itemCode.toLowerCase()}" data-category="${itemCategory.toLowerCase()}" data-item-id="${item.id}">
                    <div class="prs-item-thumb" data-category="${categoryData}">
                        <div class="prs-item-thumb-icon">
                            <i class="fa-duotone fa-solid ${categoryIcon}"></i>
                        </div>
                    </div>
                    <div class="prs-item-body">
                        <div class="prs-item-title">${itemName}</div>
                        <div class="prs-item-meta">
                            <span class="badge bg-light-primary">${itemCode}</span>
                            <span class="text-muted">Stock ${stock} ${unit}</span>
                        </div>
                        <div class="prs-item-meta text-muted">${itemCategory}</div>
                        <div class="prs-item-actions">
                            <button type="button" class="btn btn-sm btn-light-secondary prs-qty-minus" aria-label="Kurangi qty">
                                <i class="fa-light fa-minus"></i>
                            </button>
                            <input type="number" min="1" value="1" class="form-control form-control-sm prs-item-qty" aria-label="Quantity">
                            <button type="button" class="btn btn-sm btn-light-secondary prs-qty-plus" aria-label="Tambah qty">
                                <i class="fa-light fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary prs-item-add" data-item-id="${item.id}">
                                <i class="fa-light fa-plus"></i>
                                Add
                            </button>
                            <span class="prs-in-cart-label d-none">Sudah di cart</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
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
        const query = new URLSearchParams();
        const search = (searchInput?.value || '').trim();
        const category = (categoryFilter?.value || '').trim();

        if (search) query.set('search', search);
        if (category) query.set('category', category);
        query.set('page', String(page));

        const targetUrl = `${baseUrl}?${query.toString()}`;

        try {
            const response = await fetch(targetUrl, {
                headers: {
                    'Accept': 'application/json',
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
            updateInCartState();

            // Scroll to top of grid smoothly
            if (grid) {
                grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            const cleanUrl = new URL(window.location.href);
            cleanUrl.search = query.toString();
            window.history.replaceState({}, '', cleanUrl.toString());
        } catch (error) {
            console.error('Catalog fetch error:', error);
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
        getCards().forEach((card) => {
            const itemId = parseInt(card.dataset.itemId || '0', 10);
            const addButton = card.querySelector('.prs-item-add');
            const inCartLabel = card.querySelector('.prs-in-cart-label');
            if (!itemId || !addButton || !inCartLabel) {
                return;
            }

            const isInCart = cartItems.has(itemId);
            inCartLabel.classList.toggle('d-none', !isInCart);
            addButton.classList.toggle('btn-primary', !isInCart);
            addButton.classList.toggle('btn-outline-success', isInCart);
            addButton.innerHTML = isInCart
                ? '<i class="fa-light fa-cart-plus"></i> Update'
                : '<i class="fa-light fa-plus"></i> Add';
        });
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
        const qtyPlus = event.target.closest('.prs-qty-plus');
        if (qtyPlus) {
            const card = qtyPlus.closest('.prs-item-card');
            const qtyInput = card?.querySelector('.prs-item-qty');
            if (qtyInput) {
                const current = parseInt(qtyInput.value || '1', 10);
                qtyInput.value = Number.isNaN(current) ? 1 : current + 1;
            }
            return;
        }

        const qtyMinus = event.target.closest('.prs-qty-minus');
        if (qtyMinus) {
            const card = qtyMinus.closest('.prs-item-card');
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

        const qtyInput = card.querySelector('.prs-item-qty');
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

    renderPagination();
    updateInCartState();
}

function initPrsCartCount() {
    const countEl = document.getElementById('prs-cart-count');
    if (!countEl) {
        return;
    }

    window.addEventListener('prs-cart-count', (event) => {
        const count = event.detail?.count ?? 0;
        countEl.textContent = count;
    });
}
