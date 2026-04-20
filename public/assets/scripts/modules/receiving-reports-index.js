(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

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

    function setLoading(active) {
        const loadingEl = document.getElementById('rr-page-loading');
        if (!loadingEl) {
            return;
        }

        loadingEl.classList.toggle('d-none', !active);
        loadingEl.classList.toggle('d-flex', active);
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
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                window.location.href = normalizedUrl;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.querySelector('#rr-page-results');
            const currentResults = document.querySelector('#rr-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                window.location.href = normalizedUrl;
                return;
            }

            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
            }

            initPageTooltips(newResults);

            if (window.feather && typeof window.feather.replace === 'function') {
                window.feather.replace();
            }
        } catch (_) {
            window.location.href = normalizedUrl;
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

    window.rrReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#rr-page-container a[href*="page="]');
        if (!link) {
            return;
        }

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });

    initPageTooltips(document);
})();
