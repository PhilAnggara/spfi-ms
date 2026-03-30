(function () {
    let isLoading = false;

    async function replacePageContent(url, pushState = true) {
        if (isLoading) {
            return;
        }

        isLoading = true;

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
            const newContainer = doc.querySelector('#prs-approval-page-container');
            const currentContainer = document.querySelector('#prs-approval-page-container');

            if (!newContainer || !currentContainer) {
                window.location.href = url;
                return;
            }

            currentContainer.replaceWith(newContainer);

            if (pushState) {
                window.history.pushState({}, '', url);
            }

            if (window.feather && typeof window.feather.replace === 'function') {
                window.feather.replace();
            }
        } catch (_) {
            window.location.href = url;
        } finally {
            isLoading = false;
        }
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#prs-approval-page-container a[href*="page="]');
        if (!link) return;

        event.preventDefault();
        replacePageContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        replacePageContent(window.location.href, false);
    });
})();
