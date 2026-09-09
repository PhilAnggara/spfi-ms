(function () {
    const root = document.getElementById('screen-message-overlay');
    if (!root) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const authUserId = document.querySelector('meta[name="auth-user-id"]')?.content;
    const titleEl = document.getElementById('sm-overlay-title');
    const bodyEl = document.getElementById('sm-overlay-body');
    const badgeEl = document.getElementById('sm-overlay-badge');
    const timerWrap = document.getElementById('sm-overlay-timer');
    const timerBar = document.getElementById('sm-overlay-timer-bar');
    const timerText = document.getElementById('sm-overlay-timer-text');
    const replyWrap = document.getElementById('sm-overlay-reply');
    const replyInput = document.getElementById('sm-overlay-reply-input');
    const replySubmitBtn = document.getElementById('sm-overlay-reply-submit');
    const closeBtn = document.getElementById('sm-overlay-close');
    const permanentNote = document.getElementById('sm-overlay-permanent-note');

    const queue = [];
    const queuedIds = new Set();
    let current = null;
    let timerId = null;
    let timerEndsAt = null;
    let timerTotalMs = 0;
    let leaveTimeout = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            ...options,
        });

        if (!response.ok) {
            let message = 'Request failed';
            try {
                const data = await response.json();
                message = data.message || Object.values(data.errors || {})[0]?.[0] || message;
            } catch (e) {
                // ignore
            }
            throw new Error(message);
        }

        return response.json().catch(() => ({}));
    }

    function enqueue(message) {
        if (!message?.id || queuedIds.has(message.id)) {
            return;
        }

        if (current?.id === message.id) {
            return;
        }

        queuedIds.add(message.id);
        queue.push(message);
        maybeShowNext();
    }

    function removeFromQueue(id) {
        for (let i = queue.length - 1; i >= 0; i -= 1) {
            if (queue[i].id === id) {
                queue.splice(i, 1);
            }
        }
        queuedIds.delete(id);

        if (current?.id === id) {
            hideOverlay(true);
        }
    }

    function clearTimer() {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
        timerEndsAt = null;
        timerTotalMs = 0;
    }

    function updateTimerUi() {
        if (!timerEndsAt || !timerTotalMs) {
            return;
        }

        const remainingMs = Math.max(0, timerEndsAt - Date.now());
        const ratio = remainingMs / timerTotalMs;
        timerBar.style.width = `${Math.max(0, ratio * 100)}%`;
        timerText.textContent = `${Math.ceil(remainingMs / 1000)}s remaining`;

        if (remainingMs <= 0) {
            clearTimer();
            dismissCurrent();
        }
    }

    function startTimer(seconds) {
        clearTimer();
        if (!seconds || seconds < 1) {
            timerWrap.hidden = true;
            return;
        }

        timerWrap.hidden = false;
        timerTotalMs = seconds * 1000;
        timerEndsAt = Date.now() + timerTotalMs;
        timerBar.style.width = '100%';
        updateTimerUi();
        timerId = setInterval(updateTimerUi, 200);
    }

    function render(message) {
        titleEl.textContent = message.title || '';
        bodyEl.innerHTML = escapeHtml(message.body || '').replaceAll('\n', '<br>');

        const isPermanent = message.display_mode === 'permanent';
        const canClose = message.display_mode === 'user_closable';

        badgeEl.hidden = !isPermanent;
        permanentNote.hidden = !isPermanent;
        closeBtn.hidden = !canClose;

        if (message.allow_reply) {
            replyWrap.hidden = false;
            replyInput.value = '';
            replySubmitBtn.hidden = false;
        } else {
            replyWrap.hidden = true;
            replyInput.value = '';
            replySubmitBtn.hidden = true;
        }

        if (isPermanent) {
            timerWrap.hidden = true;
            clearTimer();
        } else {
            startTimer(message.duration_seconds || 0);
        }
    }

    function showOverlay() {
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            root.classList.add('is-visible');
            root.classList.remove('is-leaving');
        });
        document.body.style.overflow = 'hidden';
    }

    function hideOverlay(immediate) {
        clearTimer();
        current = null;

        const finish = () => {
            root.classList.remove('is-visible', 'is-leaving');
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            maybeShowNext();
        };

        if (immediate) {
            finish();
            return;
        }

        root.classList.add('is-leaving');
        root.classList.remove('is-visible');
        if (leaveTimeout) {
            clearTimeout(leaveTimeout);
        }
        leaveTimeout = setTimeout(finish, 280);
    }

    async function maybeShowNext() {
        if (current || queue.length === 0) {
            return;
        }

        current = queue.shift();
        queuedIds.delete(current.id);
        render(current);
        showOverlay();

        try {
            await api(`/screen-messages/inbox/${current.id}/seen`, { method: 'POST', body: '{}' });
        } catch (e) {
            // Keep showing even if seen tracking fails.
        }
    }

    async function submitReplyIfNeeded() {
        if (!current?.allow_reply) {
            return;
        }

        const body = (replyInput.value || '').trim();
        if (!body) {
            return;
        }

        await api(`/screen-messages/inbox/${current.id}/reply`, {
            method: 'POST',
            body: JSON.stringify({ body }),
        });

        replyInput.value = '';
    }

    async function dismissCurrent() {
        if (!current) {
            return;
        }

        const message = current;

        if (message.display_mode === 'permanent') {
            return;
        }

        try {
            await submitReplyIfNeeded();
            await api(`/screen-messages/inbox/${message.id}/dismiss`, { method: 'POST', body: '{}' });
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Unable to close message', text: e.message || '' });
            }
            return;
        }

        hideOverlay(false);
    }

    closeBtn.addEventListener('click', () => {
        dismissCurrent();
    });

    replySubmitBtn.addEventListener('click', async () => {
        if (!current?.allow_reply) {
            return;
        }

        const body = (replyInput.value || '').trim();
        if (!body) {
            return;
        }

        try {
            await submitReplyIfNeeded();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Reply sent',
                    showConfirmButton: false,
                    timer: 2500,
                });
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Unable to send reply', text: e.message || '' });
            }
        }
    });

    async function loadPending() {
        try {
            const data = await api('/screen-messages/inbox/pending');
            (data.messages || []).forEach(enqueue);
        } catch (e) {
            // Ignore bootstrap failures.
        }
    }

    function bindEcho() {
        if (!authUserId || typeof window.Echo === 'undefined') {
            return;
        }

        window.Echo.private(`App.Models.User.${authUserId}`)
            .listen('.screen-message.sent', (payload) => {
                enqueue(payload);
            })
            .listen('.screen-message.deactivated', (payload) => {
                if (payload?.id) {
                    removeFromQueue(payload.id);
                }
            });
    }

    loadPending();
    bindEcho();
    setInterval(loadPending, 15000);
})();
