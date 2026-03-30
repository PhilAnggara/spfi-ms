window.handleNotificationClick = function (notificationId, actionUrl) {
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => {
        if (actionUrl && actionUrl !== '#') {
            window.location.href = actionUrl;
        } else {
            window.location.reload();
        }
    });
};

window.markAsRead = function (notificationId) {
    fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(() => window.location.reload());
};

window.deleteNotification = function (notificationId) {
    if (window.confirm('Delete this notification?')) {
        fetch(`/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        }).then(() => window.location.reload());
    }
};
