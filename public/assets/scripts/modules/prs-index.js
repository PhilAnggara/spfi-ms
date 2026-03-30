(function () {
    let isLoading = false;

    function setLoading(active) {
        const loadingEl = document.getElementById('prs-page-loading');
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
            const newContainer = doc.querySelector('#prs-page-container');
            const currentContainer = document.querySelector('#prs-page-container');

            if (!newContainer || !currentContainer) {
                window.location.href = url;
                return;
            }

            currentContainer.replaceWith(newContainer);

            if (pushState) {
                window.history.pushState({}, '', url);
            }

            if (typeof initPrsFilters === 'function') {
                initPrsFilters();
            }

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

    window.prsReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#prs-page-container a[href*="page="]');
        if (!link) return;

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });
})();
