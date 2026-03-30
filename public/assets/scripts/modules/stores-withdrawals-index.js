(function () {
    let isLoading = false;

    function initPageTooltips(scope = document) {
        const tooltipElements = scope.querySelectorAll('[data-bstooltip-toggle="tooltip"]');

        tooltipElements.forEach((el) => {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                if (window.bootstrap.Tooltip.getInstance(el)) {
                    return;
                }

                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function initSwsEditFormBehavior() {
        document.addEventListener('change', function (event) {
            const checkbox = event.target.closest('[data-sws-remove-toggle]');
            if (!checkbox) {
                return;
            }

            const row = checkbox.closest('[data-sws-edit-row]');
            const quantityInput = row ? row.querySelector('[data-sws-qty-input]') : null;
            if (quantityInput) {
                quantityInput.disabled = checkbox.checked;
            }

            if (row) {
                row.classList.toggle('table-danger', checkbox.checked);
            }
        });

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('.sws-edit-form');
            if (!form) {
                return;
            }

            const removeChecks = Array.from(form.querySelectorAll('[data-sws-remove-toggle]'));
            if (removeChecks.length === 0) {
                return;
            }

            const removeCount = removeChecks.filter((input) => input.checked).length;
            if (removeCount >= removeChecks.length) {
                event.preventDefault();
                window.Swal?.fire({
                    icon: 'warning',
                    title: 'Cannot remove all items',
                    text: 'At least one item must remain in the stores withdrawal.',
                });
            }
        });
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('sws-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    async function replacePageContent(url, pushState = true) {
        if (isLoading) {
            return;
        }

        isLoading = true;
        setLoading(true);

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                window.location.href = url;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContainer = doc.querySelector('#sws-page-container');
            const currentContainer = document.querySelector('#sws-page-container');

            if (!newContainer || !currentContainer) {
                window.location.href = url;
                return;
            }

            currentContainer.replaceWith(newContainer);

            if (pushState) {
                window.history.pushState({}, '', url);
            }

            if (typeof initStoreWithdrawalFilters === 'function') {
                initStoreWithdrawalFilters();
            }

            initPageTooltips(newContainer);

            if (window.feather && typeof window.feather.replace === 'function') {
                window.feather.replace();
            }
        } catch (_) {
            window.location.href = url;
        } finally {
            isLoading = false;
            setLoading(false);
        }
    }

    window.swsReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#sws-page-container a[href*="page="]');
        if (!link) return;

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });

    initPageTooltips(document);
    initSwsEditFormBehavior();
})();
