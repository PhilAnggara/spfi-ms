(function () {
    const bell = document.getElementById('notificationBell');
    if (!bell) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const authUserId = bell.dataset.authUserId || document.querySelector('meta[name="auth-user-id"]')?.content;
    const list = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const markAllBtn = document.getElementById('markAllReadBtn');
    const footer = document.getElementById('notificationFooter');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function updateBadge(count) {
        if (!badge || !markAllBtn) {
            return;
        }

        if (count > 0) {
            badge.classList.remove('d-none');
            badge.textContent = String(count);
            markAllBtn.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
            badge.textContent = '0';
            markAllBtn.classList.add('d-none');
        }
    }

    function showToast(title, message, icon) {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon || 'info',
            title: title || 'Notification',
            text: message || '',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
        });
    }

    function iconToSwal(iconClass) {
        const icon = (iconClass || '').toLowerCase();

        if (icon.includes('trash') || icon.includes('times') || icon.includes('xmark')) {
            return 'warning';
        }

        if (icon.includes('check') || icon.includes('badge-check')) {
            return 'success';
        }

        if (icon.includes('pen') || icon.includes('pause')) {
            return 'info';
        }

        return 'info';
    }

    function renderNotificationItem(notification) {
        const data = notification.data || notification;
        const isRead = !!notification.read_at;
        const actionUrl = data.action_url || '#';

        return `
            <li class="dropdown-item notification-item ${isRead ? '' : 'bg-light'}"
                style="white-space: normal; cursor: pointer;"
                data-notification-id="${escapeHtml(notification.id)}"
                data-action-url="${escapeHtml(actionUrl)}"
                data-is-read="${isRead ? '1' : '0'}">
                <a class="d-flex align-items-start text-decoration-none" href="#">
                    <div class="notification-icon ${escapeHtml(data.icon_color || 'bg-primary')} flex-shrink-0 p-1">
                        <i class="${escapeHtml(data.icon || 'fa-light fa-bell')}"></i>
                    </div>
                    <div class="notification-text ms-3 flex-grow-1">
                        <p class="notification-title font-bold mb-1">
                            ${escapeHtml(data.title || 'Notification')}
                            ${isRead ? '' : '<span class="badge bg-primary" style="font-size: 0.65rem;">NEW</span>'}
                        </p>
                        <p class="notification-subtitle text-sm mb-1">${escapeHtml(data.message || '')}</p>
                    </div>
                </a>
            </li>
        `;
    }

    function renderEmptyState() {
        return `
            <li id="notificationEmptyState" class="dropdown-item text-center py-4">
                <div class="text-muted">
                    <i class="bi bi-inbox fa-2x ms-1 d-block"></i>
                    <p class="ms-1">No notifications</p>
                </div>
            </li>
        `;
    }

    async function refreshDropdown() {
        if (!list) {
            return;
        }

        try {
            const [countResponse, recentResponse] = await Promise.all([
                fetch('/notifications/unread-count', {
                    headers: { Accept: 'application/json' },
                }),
                fetch('/notifications/recent', {
                    headers: { Accept: 'application/json' },
                }),
            ]);

            const countPayload = await countResponse.json();
            const recentPayload = await recentResponse.json();

            const notifications = recentPayload.notifications || [];
            updateBadge(Number(countPayload.count || 0));

            if (notifications.length === 0) {
                list.innerHTML = renderEmptyState();
                if (footer) {
                    footer.classList.add('d-none');
                }
                return;
            }

            list.innerHTML = notifications.map(renderNotificationItem).join('');

            if (footer) {
                footer.classList.remove('d-none');
            }
        } catch (error) {
            console.error('Failed to refresh notifications dropdown', error);
        }
    }

    async function markAsRead(notificationId) {
        await fetch(`/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });
    }

    list?.addEventListener('click', async function (event) {
        const item = event.target.closest('.notification-item');
        if (!item) {
            return;
        }

        event.preventDefault();

        const notificationId = item.dataset.notificationId;
        const actionUrl = item.dataset.actionUrl;

        if (!notificationId) {
            return;
        }

        try {
            await markAsRead(notificationId);
            await refreshDropdown();

            if (actionUrl && actionUrl !== '#') {
                window.location.href = actionUrl;
            }
        } catch (error) {
            console.error('Failed to mark notification as read', error);
        }
    });

    markAllBtn?.addEventListener('click', async function (event) {
        event.preventDefault();

        if (!window.confirm('Mark all notifications as read?')) {
            return;
        }

        try {
            await fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });

            await refreshDropdown();
        } catch (error) {
            console.error('Failed to mark all notifications as read', error);
        }
    });

    function subscribeRealtime() {
        if (!authUserId || !window.Echo || typeof window.Echo.private !== 'function') {
            return;
        }

        const channel = window.Echo.private(`App.Models.User.${authUserId}`)
            .notification((notification) => {
                const data = notification.data || notification;
                showToast(data.title, data.message, iconToSwal(data.icon));
                refreshDropdown();
            });

        if (channel && typeof channel.error === 'function') {
            channel.error((error) => {
                console.error('Realtime notification channel error', error);
            });
        }
    }

    subscribeRealtime();
    refreshDropdown();

    // Safety fallback when websocket is down or reconnecting.
    setInterval(refreshDropdown, 15000);
})();
