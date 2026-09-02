window.StockCorrectionItemSearch = (function () {
    function debounce(fn, wait) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function deltaClass(delta) {
        if (delta > 0.00001) return 'is-up';
        if (delta < -0.00001) return 'is-down';
        return 'is-zero';
    }

    function applyDeltaBadge(el, delta, hookClass) {
        if (!el) {
            return;
        }

        const prefix = delta > 0 ? '+' : '';
        el.textContent = prefix + formatNumber(delta);
        el.classList.remove('is-up', 'is-down', 'is-zero');
        el.classList.add('sc-delta', hookClass, deltaClass(delta));
    }

    function bindPicker(row, options) {
        const searchUrl = options.searchUrl;
        const csrf = options.csrf;
        const picker = row.querySelector('.sc-item-picker');
        const input = row.querySelector('.sc-item-search');
        const results = row.querySelector('.sc-item-results');
        const selected = row.querySelector('.sc-item-selected');
        const selectedTitle = row.querySelector('.sc-item-selected-title');
        const selectedSub = row.querySelector('.sc-item-selected-sub');
        const clearBtn = row.querySelector('.sc-item-clear');
        const hiddenId = row.querySelector('.sc-item-id');
        const onSelect = options.onSelect || function () {};

        let activeIndex = -1;
        let currentItems = [];
        const modalBody = picker.closest('.modal-body');
        let floatingScrollTarget = null;

        function isInsideModal() {
            return Boolean(picker.closest('.modal'));
        }

        function positionFloatingResults() {
            if (!isInsideModal() || !results.classList.contains('is-open')) {
                return;
            }

            const anchor = input.classList.contains('d-none') ? selected : input;
            const rect = anchor.getBoundingClientRect();
            const viewportPadding = 8;
            const gap = 4;
            const maxHeight = 16 * 16;
            const availableBelow = window.innerHeight - rect.bottom - viewportPadding;
            const availableAbove = rect.top - viewportPadding;
            const openBelow = availableBelow >= 120 || availableBelow >= availableAbove;
            const maxVisibleHeight = Math.min(
                maxHeight,
                openBelow ? availableBelow - gap : availableAbove - gap,
            );

            results.classList.add('sc-item-results--floating');
            results.style.position = 'fixed';
            results.style.left = `${Math.max(viewportPadding, rect.left)}px`;
            results.style.width = `${rect.width}px`;
            results.style.maxHeight = `${Math.max(80, maxVisibleHeight)}px`;
            results.style.zIndex = '1060';

            if (openBelow) {
                results.style.top = `${rect.bottom + gap}px`;
                results.style.bottom = 'auto';
            } else {
                results.style.top = 'auto';
                results.style.bottom = `${window.innerHeight - rect.top + gap}px`;
            }
        }

        function resetFloatingResults() {
            results.classList.remove('sc-item-results--floating');
            results.style.position = '';
            results.style.left = '';
            results.style.top = '';
            results.style.bottom = '';
            results.style.width = '';
            results.style.maxHeight = '';
            results.style.zIndex = '';
        }

        function bindFloatingListeners() {
            if (!isInsideModal()) {
                return;
            }

            floatingScrollTarget = modalBody || picker.closest('.modal');
            window.addEventListener('scroll', positionFloatingResults, true);
            window.addEventListener('resize', positionFloatingResults);

            if (floatingScrollTarget) {
                floatingScrollTarget.addEventListener('scroll', positionFloatingResults, { passive: true });
            }
        }

        function unbindFloatingListeners() {
            window.removeEventListener('scroll', positionFloatingResults, true);
            window.removeEventListener('resize', positionFloatingResults);

            if (floatingScrollTarget) {
                floatingScrollTarget.removeEventListener('scroll', positionFloatingResults);
                floatingScrollTarget = null;
            }
        }

        function closeResults() {
            results.classList.remove('is-open');
            picker.classList.remove('is-open');
            results.innerHTML = '';
            activeIndex = -1;
            currentItems = [];
            resetFloatingResults();
            unbindFloatingListeners();
        }

        function openResultsShell(html) {
            results.innerHTML = html;
            results.classList.add('is-open');
            picker.classList.add('is-open');
            bindFloatingListeners();
            positionFloatingResults();
        }

        function renderResults(items) {
            currentItems = items;
            activeIndex = items.length ? 0 : -1;
            if (!items.length) {
                openResultsShell('<div class="px-3 py-2 text-muted small">No items found</div>');
                return;
            }

            openResultsShell(items.map((item, index) => `
                <button type="button" class="sc-item-result${index === 0 ? ' is-active' : ''}" data-index="${index}">
                    <span class="sc-item-result-code">${item.code}</span>
                    <span class="sc-item-result-name">${item.name}</span>
                    <span class="sc-item-result-meta">Stock ${formatNumber(item.balance)} · ${item.unit || 'PCS'}</span>
                </button>
            `).join(''));
        }

        async function search(term) {
            const q = term.trim();
            if (q.length < 2) {
                closeResults();
                return;
            }

            openResultsShell('<div class="px-3 py-2 text-muted small">Searching…</div>');

            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', q);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });

            if (!response.ok) {
                openResultsShell('<div class="px-3 py-2 text-danger small">Search failed</div>');
                return;
            }

            const data = await response.json();
            renderResults(data.items || []);
        }

        const runSearch = debounce(function () {
            search(input.value);
        }, 300);

        function selectItem(item) {
            hiddenId.value = item.id;
            input.classList.add('d-none');
            selected.classList.add('is-visible');
            selectedTitle.textContent = `${item.code} — ${item.name}`;
            selectedSub.textContent = `Current stock ${formatNumber(item.balance)} · ${item.unit || 'PCS'}`;
            row.dataset.balance = String(item.balance ?? 0);
            row.dataset.itemCode = item.code || '';
            closeResults();
            onSelect(item, row);
        }

        input.addEventListener('input', runSearch);
        input.addEventListener('keydown', function (event) {
            if (!results.classList.contains('is-open') || !currentItems.length) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                selectItem(currentItems[activeIndex]);
                return;
            } else if (event.key === 'Escape') {
                closeResults();
                return;
            } else {
                return;
            }

            Array.from(results.querySelectorAll('.sc-item-result')).forEach((el, idx) => {
                el.classList.toggle('is-active', idx === activeIndex);
            });
        });

        results.addEventListener('click', function (event) {
            const button = event.target.closest('.sc-item-result');
            if (!button) return;
            const index = Number(button.dataset.index);
            if (currentItems[index]) {
                selectItem(currentItems[index]);
            }
        });

        clearBtn.addEventListener('click', function () {
            hiddenId.value = '';
            input.value = '';
            input.classList.remove('d-none');
            selected.classList.remove('is-visible');
            row.dataset.balance = '0';
            row.dataset.itemCode = '';
            closeResults();
            onSelect(null, row);
            input.focus();
        });

        document.addEventListener('click', function (event) {
            if (!row.contains(event.target)) {
                closeResults();
            }
        });
    }

    return {
        debounce,
        formatNumber,
        deltaClass,
        applyDeltaBadge,
        bindPicker,
    };
})();
