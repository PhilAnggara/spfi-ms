(function () {
    let isLoading = false;
    let pendingReplaceRequest = null;

    function disposeBootstrapInstances(scope) {
        if (!window.bootstrap || !scope) {
            return;
        }

        if (window.bootstrap.Dropdown) {
            scope.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((el) => {
                window.bootstrap.Dropdown.getInstance(el)?.dispose();
            });
        }
    }

    function setLoading(active) {
        const loadingEl = document.getElementById('notifications-page-loading');
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
            const newResults = doc.querySelector('#notifications-page-results');
            const currentResults = document.querySelector('#notifications-page-results');

            const hasNewerPendingRequest = pendingReplaceRequest && pendingReplaceRequest.url !== normalizedUrl;
            if (hasNewerPendingRequest) {
                return;
            }

            if (!newResults || !currentResults) {
                showPaginationError();
                return;
            }

            disposeBootstrapInstances(currentResults);
            currentResults.replaceWith(newResults);

            if (pushState) {
                window.history.pushState({}, '', normalizedUrl);
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

    window.notificationsReplacePageContent = replacePageContent;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('#notifications-page-container a[href*="page="]');
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

window.handleNotificationClick = function (notificationId, actionUrl) {
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).then(() => {
        if (actionUrl && actionUrl !== '#') {
            window.location.href = actionUrl;
        } else if (typeof window.notificationsReplacePageContent === 'function') {
            window.notificationsReplacePageContent(window.location.href, false);
        }
    });
};

window.markAsRead = function (notificationId) {
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).then(() => {
        if (typeof window.notificationsReplacePageContent === 'function') {
            window.notificationsReplacePageContent(window.location.href, false);
        }
    });
};

window.deleteNotification = function (notificationId) {
    if (window.confirm('Delete this notification?')) {
        fetch(`/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        }).then(() => {
            if (typeof window.notificationsReplacePageContent === 'function') {
                window.notificationsReplacePageContent(window.location.href, false);
            }
        });
    }
};
