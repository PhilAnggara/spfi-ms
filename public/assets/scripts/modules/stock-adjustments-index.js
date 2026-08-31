(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function setLoading(active) {
        const loadingEl = document.getElementById('sa-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
    }

    function showPaginationError() {
        setLoading(false);
        window.alert('Could not load this page. Please try again.');
    }

    async function replacePageContent(url, pushState = true) {
        const normalizedUrl = new URL(url, window.location.origin).toString();

        if (isLoading) {
            pendingReplaceRequest = {
                url: normalizedUrl,
                pushState,
            };
            return;
        }

        isLoading = true;
        setLoading(true);

        try {
            const response = await fetch(normalizedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                showPaginationError();
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#sa-page-results');
            const currentResults = document.querySelector('#sa-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                showPaginationError();
                return;
            }

            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            if (typeof window.initStockAdjustmentPage === 'function') {
                window.initStockAdjustmentPage();
            }
        } catch (_) {
            showPaginationError();
        } finally {
            isLoading = false;
            setLoading(false);

            if (pendingReplaceRequest) {
                const nextRequest = pendingReplaceRequest;
                pendingReplaceRequest = null;

                replacePageContent(nextRequest.url, nextRequest.pushState);
            }
        }
    }

    window.saReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#sa-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });
})();
