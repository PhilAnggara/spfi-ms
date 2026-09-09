(function () {
    const root = document.getElementById('screen-message-overlay');
    if (!root) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const authUserId = document.querySelector('meta[name="auth-user-id"]')?.content;
    const titleEl = document.getElementById('sm-overlay-title');
    const bodyEl = document.getElementById('sm-overlay-body');
    const timerWrap = document.getElementById('sm-overlay-timer');
    const timerBar = document.getElementById('sm-overlay-timer-bar');
    const timerText = document.getElementById('sm-overlay-timer-text');
    const replyWrap = document.getElementById('sm-overlay-reply');
    const replyForm = document.getElementById('sm-overlay-reply-form');
    const replyInput = document.getElementById('sm-overlay-reply-input');
    const replySubmitBtn = document.getElementById('sm-overlay-reply-submit');
    const replySent = document.getElementById('sm-overlay-reply-sent');
    const replySentBody = document.getElementById('sm-overlay-reply-sent-body');
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
            if (message.expires_at && !current.expires_at) {
                current.expires_at = message.expires_at;
            }
            if (message.my_reply && !current.my_reply) {
                current.my_reply = message.my_reply;
                showSentReply(message.my_reply.body);
            }
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

    function startTimerFromExpiresAt(expiresAtIso, fallbackDurationSeconds) {
        clearTimer();

        let endsAt = expiresAtIso ? Date.parse(expiresAtIso) : NaN;
        if (Number.isNaN(endsAt) && fallbackDurationSeconds && fallbackDurationSeconds > 0) {
            endsAt = Date.now() + fallbackDurationSeconds * 1000;
        }

        if (Number.isNaN(endsAt)) {
            timerWrap.hidden = true;
            return;
        }

        const remainingMs = endsAt - Date.now();
        if (remainingMs <= 0) {
            timerWrap.hidden = true;
            dismissCurrent();
            return;
        }

        timerWrap.hidden = false;
        timerEndsAt = endsAt;
        timerTotalMs = fallbackDurationSeconds && fallbackDurationSeconds > 0
            ? fallbackDurationSeconds * 1000
            : remainingMs;
        if (timerTotalMs < remainingMs) {
            timerTotalMs = remainingMs;
        }
        updateTimerUi();
        timerId = setInterval(updateTimerUi, 200);
    }

    function showReplyForm() {
        replyWrap.hidden = false;
        replyForm.hidden = false;
        replySent.hidden = true;
        replyInput.value = '';
        replySubmitBtn.hidden = false;
    }

    function showSentReply(body) {
        replyWrap.hidden = false;
        replyForm.hidden = true;
        replySent.hidden = false;
        replySentBody.innerHTML = escapeHtml(body || '').replaceAll('\n', '<br>');
        replyInput.value = '';
    }

    function hideReply() {
        replyWrap.hidden = true;
        replyForm.hidden = true;
        replySent.hidden = true;
        replyInput.value = '';
    }

    function render(message) {
        titleEl.textContent = message.title || '';
        bodyEl.innerHTML = escapeHtml(message.body || '').replaceAll('\n', '<br>');

        const isPermanent = message.display_mode === 'permanent';
        const canClose = message.display_mode === 'user_closable';

        permanentNote.hidden = !isPermanent;
        closeBtn.hidden = !canClose;

        if (message.allow_reply) {
            if (message.my_reply?.body) {
                showSentReply(message.my_reply.body);
            } else {
                showReplyForm();
            }
        } else {
            hideReply();
        }

        if (isPermanent) {
            timerWrap.hidden = true;
            clearTimer();
        } else {
            startTimerFromExpiresAt(message.expires_at, message.duration_seconds || 0);
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
            const seen = await api(`/screen-messages/inbox/${current.id}/seen`, { method: 'POST', body: '{}' });
            if (seen.expires_at) {
                current.expires_at = seen.expires_at;
                if (current.display_mode !== 'permanent' && (current.duration_seconds || seen.expires_at)) {
                    startTimerFromExpiresAt(seen.expires_at, current.duration_seconds || 0);
                }
            }
        } catch (e) {
            // Keep showing even if seen tracking fails.
        }
    }

    async function submitReplyIfNeeded() {
        if (!current?.allow_reply || current.my_reply) {
            return;
        }

        const body = (replyInput.value || '').trim();
        if (!body) {
            return;
        }

        const data = await api(`/screen-messages/inbox/${current.id}/reply`, {
            method: 'POST',
            body: JSON.stringify({ body }),
        });

        current.my_reply = data.reply || { body };
        showSentReply(current.my_reply.body);
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
        if (!current?.allow_reply || current.my_reply) {
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
