(function () {
    const root = document.getElementById('chat-widget');
    if (!root) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const authUserId = Number(root.dataset.authUserId || document.querySelector('meta[name="auth-user-id"]')?.content || 0);

    const fab = document.getElementById('chat-widget-fab');
    const panel = document.getElementById('chat-widget-panel');
    const badge = document.getElementById('chat-widget-badge');
    const conversationList = document.getElementById('chat-conversation-list');
    const listFilter = document.getElementById('chat-list-filter');
    const userSearch = document.getElementById('chat-user-search');
    const userResults = document.getElementById('chat-user-results');
    const messagesEl = document.getElementById('chat-messages');
    const typingEl = document.getElementById('chat-typing');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send-btn');
    const attachInput = document.getElementById('chat-attachment');
    const attachPreview = document.getElementById('chat-attach-preview');
    const attachName = document.getElementById('chat-attach-name');
    const attachClear = document.getElementById('chat-attach-clear');
    const emojiBtn = document.getElementById('chat-emoji-btn');
    const emojiPicker = document.getElementById('chat-emoji-picker');
    const threadName = document.getElementById('chat-thread-name');
    const threadStatus = document.getElementById('chat-thread-status');
    const threadAvatar = document.getElementById('chat-thread-avatar');

    const state = {
        open: false,
        view: 'list',
        conversations: [],
        messages: [],
        activeConversationId: null,
        activePeer: null,
        onlineUserIds: new Set(),
        subscribedConversations: new Set(),
        presenceChannel: null,
        conversationChannel: null,
        typingTimer: null,
        typingHideTimer: null,
        pollTimer: null,
        searchTimer: null,
        pendingAttachment: null,
        loadingMessages: false,
    };

    const EMOJIS = ['😀','😁','😂','🤣','😊','😍','😘','😎','🤔','😅','😢','😭','😡','👍','👎','👏','🙏','🔥','✨','🎉','❤️','💙','💚','💛','🧡','💜','✅','❌','📌','📎','📷','📁','☕','🚀'];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '?';
        }
        return ((parts[0][0] || '') + (parts[1]?.[0] || '')).toUpperCase();
    }

    function formatTime(iso) {
        if (!iso) {
            return '';
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        const now = new Date();
        const sameDay = date.toDateString() === now.toDateString();
        if (sameDay) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function urlTemplate(template, id) {
        return String(template || '').replace('__ID__', String(id));
    }

    async function api(url, options = {}) {
        const isForm = options.body instanceof FormData;
        const headers = {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };

        if (!isForm && options.body && typeof options.body === 'object') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
        });

        if (!response.ok) {
            let message = 'Request failed';
            try {
                const payload = await response.json();
                message = payload.message || Object.values(payload.errors || {})[0]?.[0] || message;
            } catch (e) {
                // ignore
            }
            throw new Error(message);
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    }

    function updateBadge(count) {
        if (!badge) {
            return;
        }
        if (count > 0) {
            badge.classList.remove('d-none');
            badge.textContent = count > 99 ? '99+' : String(count);
        } else {
            badge.classList.add('d-none');
            badge.textContent = '0';
        }
    }

    function showView(view) {
        state.view = view;
        root.querySelectorAll('.chat-widget__view').forEach((el) => {
            el.classList.toggle('is-active', el.dataset.view === view);
        });
    }

    function openPanel() {
        state.open = true;
        panel.classList.remove('d-none');
        fab.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        showView('list');
        loadConversations();
    }

    function closePanel() {
        state.open = false;
        panel.classList.add('d-none');
        fab.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        emojiPicker.classList.add('d-none');
        leaveConversationChannel();
    }

    function togglePanel() {
        if (state.open) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function isPeerOnline(peer) {
        if (!peer) {
            return false;
        }
        if (state.onlineUserIds.has(Number(peer.id))) {
            return true;
        }
        return !!peer.is_online;
    }

    function previewText(message) {
        if (!message) {
            return 'No messages yet';
        }
        if (message.type === 'image') {
            return '📷 Photo';
        }
        if (message.type === 'file') {
            return '📎 ' + (message.attachment_original_name || 'File');
        }
        return message.body || '';
    }

    function renderConversationList(filter = '') {
        const term = filter.trim().toLowerCase();
        const items = state.conversations.filter((item) => {
            const name = (item.peer?.name || '').toLowerCase();
            const username = (item.peer?.username || '').toLowerCase();
            return !term || name.includes(term) || username.includes(term);
        });

        if (!items.length) {
            conversationList.innerHTML = '<div class="chat-widget__empty">No conversations found.</div>';
            return;
        }

        conversationList.innerHTML = items.map((item) => {
            const peer = item.peer || {};
            const online = isPeerOnline(peer);
            const unread = Number(item.unread_count || 0);
            return `
                <button type="button" class="chat-widget__item ${Number(item.id) === Number(state.activeConversationId) ? 'is-active' : ''}" data-open-conversation="${item.id}">
                    <div class="chat-widget__avatar ${online ? 'is-online' : ''}">${escapeHtml(initials(peer.name))}</div>
                    <div class="chat-widget__item-body">
                        <div class="chat-widget__item-top">
                            <p class="chat-widget__item-name">${escapeHtml(peer.name || 'User')}</p>
                            <span class="chat-widget__item-time">${escapeHtml(formatTime(item.latest_message?.created_at || item.updated_at))}</span>
                        </div>
                        <div class="chat-widget__item-bottom">
                            <span class="chat-widget__item-preview">${escapeHtml(previewText(item.latest_message))}</span>
                            ${unread > 0 ? `<span class="chat-widget__unread">${unread > 99 ? '99+' : unread}</span>` : ''}
                        </div>
                    </div>
                </button>
            `;
        }).join('');
    }

    function ticksHtml(status, isMine) {
        if (!isMine) {
            return '';
        }
        if (status === 'read') {
            return '<span class="chat-bubble__ticks is-read" title="Read">✓✓</span>';
        }
        if (status === 'delivered') {
            return '<span class="chat-bubble__ticks" title="Delivered">✓✓</span>';
        }
        return '<span class="chat-bubble__ticks" title="Sent">✓</span>';
    }

    function messageBodyHtml(message) {
        let html = '';
        if (message.type === 'image' && message.attachment_url) {
            html += `<a href="${escapeHtml(message.attachment_url)}" target="_blank" rel="noopener"><img class="chat-bubble__image" src="${escapeHtml(message.attachment_url)}" alt="${escapeHtml(message.attachment_original_name || 'Image')}"></a>`;
        } else if (message.type === 'file' && message.attachment_url) {
            html += `<a class="chat-bubble__file" href="${escapeHtml(message.attachment_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-file"></i><span>${escapeHtml(message.attachment_original_name || 'File')}</span></a>`;
        }
        if (message.body) {
            html += `<div>${escapeHtml(message.body)}</div>`;
        }
        return html || '<div></div>';
    }

    function renderMessages() {
        const sorted = [...state.messages].sort((a, b) => Number(a.id) - Number(b.id));
        let lastDay = '';
        const chunks = [];

        sorted.forEach((message) => {
            const day = message.created_at ? new Date(message.created_at).toDateString() : '';
            if (day && day !== lastDay) {
                lastDay = day;
                chunks.push(`<div class="chat-widget__day">${escapeHtml(formatTime(message.created_at) ? new Date(message.created_at).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) : '')}</div>`);
            }

            const isMine = Number(message.user_id) === authUserId;
            chunks.push(`
                <div class="chat-bubble ${isMine ? 'is-mine' : 'is-theirs'}" data-message-id="${message.id}">
                    <div class="chat-bubble__content">${messageBodyHtml(message)}</div>
                    <div class="chat-bubble__meta">
                        <span>${escapeHtml(formatTime(message.created_at))}</span>
                        ${ticksHtml(message.status, isMine)}
                    </div>
                </div>
            `);
        });

        messagesEl.innerHTML = chunks.join('') || '<div class="chat-widget__empty">Say hello 👋</div>';
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function updateThreadHeader() {
        const peer = state.activePeer || {};
        threadName.textContent = peer.name || 'Chat';
        threadAvatar.textContent = initials(peer.name);
        threadAvatar.classList.toggle('is-online', isPeerOnline(peer));
        threadStatus.textContent = isPeerOnline(peer)
            ? 'Online'
            : (peer.last_seen_at ? `Last seen ${formatTime(peer.last_seen_at)}` : 'Offline');
    }

    async function refreshUnread() {
        try {
            const payload = await api(root.dataset.unreadUrl);
            updateBadge(Number(payload.count || 0));
        } catch (e) {
            // ignore polling errors
        }
    }

    async function loadConversations() {
        try {
            const payload = await api(root.dataset.conversationsUrl);
            state.conversations = payload.data || [];
            updateBadge(Number(payload.unread_count || 0));
            renderConversationList(listFilter.value || '');
            state.conversations.forEach((item) => subscribeConversation(item.id));
        } catch (e) {
            conversationList.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
        }
    }

    async function searchUsers(query) {
        try {
            const url = new URL(root.dataset.searchUsersUrl, window.location.origin);
            url.searchParams.set('q', query || '');
            const payload = await api(url.toString());
            const users = payload.data || [];
            if (!users.length) {
                userResults.innerHTML = '<div class="chat-widget__empty">No users found.</div>';
                return;
            }
            userResults.innerHTML = users.map((user) => {
                const online = isPeerOnline(user) || !!user.is_online;
                return `
                    <button type="button" class="chat-widget__item" data-start-user="${user.id}">
                        <div class="chat-widget__avatar ${online ? 'is-online' : ''}">${escapeHtml(initials(user.name))}</div>
                        <div class="chat-widget__item-body">
                            <div class="chat-widget__item-top">
                                <p class="chat-widget__item-name">${escapeHtml(user.name)}</p>
                            </div>
                            <div class="chat-widget__item-bottom">
                                <span class="chat-widget__item-preview">@${escapeHtml(user.username)}</span>
                            </div>
                        </div>
                    </button>
                `;
            }).join('');
        } catch (e) {
            userResults.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
        }
    }

    async function startConversation(userId) {
        const payload = await api(root.dataset.storeConversationUrl, {
            method: 'POST',
            body: { user_id: Number(userId) },
        });
        const conversation = payload.data;
        const existingIndex = state.conversations.findIndex((item) => Number(item.id) === Number(conversation.id));
        if (existingIndex >= 0) {
            state.conversations[existingIndex] = conversation;
        } else {
            state.conversations.unshift(conversation);
        }
        await openConversation(conversation.id);
    }

    function leaveConversationChannel() {
        state.conversationChannel = null;
    }

    function subscribeConversation(conversationId) {
        if (!window.Echo || !conversationId || state.subscribedConversations.has(Number(conversationId))) {
            return;
        }

        state.subscribedConversations.add(Number(conversationId));
        window.Echo.private(`conversation.${conversationId}`)
            .listen('.chat.message.sent', (event) => {
                handleIncomingMessage(event.message);
            })
            .listen('.chat.message.delivered', (event) => {
                if (Number(event.user_id) === authUserId) {
                    return;
                }
                updateOutgoingStatus(event.conversation_id, 'delivered', event.delivered_at);
            })
            .listen('.chat.conversation.read', (event) => {
                if (Number(event.user_id) === authUserId) {
                    return;
                }
                updateOutgoingStatus(event.conversation_id, 'read', event.read_at);
            })
            .listenForWhisper('typing', (payload) => {
                if (Number(payload.user_id) === authUserId) {
                    return;
                }
                if (Number(state.activeConversationId) !== Number(conversationId)) {
                    return;
                }
                typingEl.textContent = `${payload.name || 'Someone'} is typing...`;
                typingEl.classList.remove('d-none');
                clearTimeout(state.typingHideTimer);
                state.typingHideTimer = setTimeout(() => typingEl.classList.add('d-none'), 1800);
            });
    }

    function joinActiveConversationChannel(conversationId) {
        leaveConversationChannel();
        subscribeConversation(conversationId);
        if (!window.Echo) {
            return;
        }
        state.conversationChannel = window.Echo.private(`conversation.${conversationId}`);
        state.activeConversationId = Number(conversationId);
    }

    function updateOutgoingStatus(conversationId, status, at) {
        if (Number(state.activeConversationId) !== Number(conversationId)) {
            state.messages = state.messages.map((message) => {
                if (Number(message.user_id) === authUserId) {
                    return { ...message, status };
                }
                return message;
            });
            return;
        }

        state.messages = state.messages.map((message) => {
            if (Number(message.user_id) !== authUserId) {
                return message;
            }
            if (!at || !message.created_at || new Date(message.created_at) <= new Date(at)) {
                return { ...message, status };
            }
            return message;
        });
        renderMessages();
    }

    async function markDelivered(conversationId) {
        try {
            await api(urlTemplate(root.dataset.deliveredUrlTemplate, conversationId), { method: 'POST', body: {} });
        } catch (e) {
            // ignore
        }
    }

    async function markRead(conversationId) {
        try {
            await api(urlTemplate(root.dataset.readUrlTemplate, conversationId), { method: 'POST', body: {} });
            const item = state.conversations.find((c) => Number(c.id) === Number(conversationId));
            if (item) {
                item.unread_count = 0;
            }
            renderConversationList(listFilter.value || '');
            refreshUnread();
        } catch (e) {
            // ignore
        }
    }

    async function openConversation(conversationId) {
        const conversation = state.conversations.find((item) => Number(item.id) === Number(conversationId));
        state.activeConversationId = Number(conversationId);
        state.activePeer = conversation?.peer || null;
        state.messages = [];
        updateThreadHeader();
        showView('thread');
        joinActiveConversationChannel(conversationId);
        await loadMessages(conversationId);
        await markDelivered(conversationId);
        await markRead(conversationId);
    }

    async function loadMessages(conversationId) {
        state.loadingMessages = true;
        try {
            const payload = await api(urlTemplate(root.dataset.messagesUrlTemplate, conversationId));
            state.messages = (payload.data || []).slice().reverse();
            renderMessages();
        } catch (e) {
            messagesEl.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
        } finally {
            state.loadingMessages = false;
        }
    }

    function upsertConversationPreview(message) {
        const index = state.conversations.findIndex((item) => Number(item.id) === Number(message.conversation_id));
        if (index >= 0) {
            const item = state.conversations[index];
            item.latest_message = message;
            item.updated_at = message.created_at;
            if (Number(message.user_id) !== authUserId && Number(state.activeConversationId) !== Number(message.conversation_id)) {
                item.unread_count = Number(item.unread_count || 0) + 1;
            }
            state.conversations.splice(index, 1);
            state.conversations.unshift(item);
        } else {
            loadConversations();
            return;
        }
        renderConversationList(listFilter.value || '');
    }

    function handleIncomingMessage(message) {
        if (!message) {
            return;
        }

        upsertConversationPreview(message);
        subscribeConversation(message.conversation_id);

        if (Number(state.activeConversationId) === Number(message.conversation_id)) {
            if (!state.messages.some((item) => Number(item.id) === Number(message.id))) {
                state.messages.push(message);
                renderMessages();
            }
            if (Number(message.user_id) !== authUserId) {
                markDelivered(message.conversation_id);
                markRead(message.conversation_id);
            }
        } else if (Number(message.user_id) !== authUserId) {
            refreshUnread();
            markDelivered(message.conversation_id);
        }
    }

    async function sendMessage() {
        const conversationId = state.activeConversationId;
        if (!conversationId) {
            return;
        }

        const body = (input.value || '').trim();
        const file = state.pendingAttachment;
        if (!body && !file) {
            return;
        }

        const formData = new FormData();
        if (body) {
            formData.append('body', body);
        }
        if (file) {
            formData.append('attachment', file);
        }

        sendBtn.disabled = true;
        try {
            const payload = await api(urlTemplate(root.dataset.storeMessageUrlTemplate, conversationId), {
                method: 'POST',
                body: formData,
            });
            const message = payload.data;
            if (!state.messages.some((item) => Number(item.id) === Number(message.id))) {
                state.messages.push(message);
                renderMessages();
            }
            upsertConversationPreview(message);
            input.value = '';
            clearAttachment();
            autoGrow();
            whisperTyping(false);
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: e.message, showConfirmButton: false, timer: 3000 });
            }
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    function clearAttachment() {
        state.pendingAttachment = null;
        attachInput.value = '';
        attachPreview.classList.add('d-none');
        attachName.textContent = '';
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    }

    function whisperTyping(isTyping) {
        if (!window.Echo || !state.activeConversationId) {
            return;
        }
        window.Echo.private(`conversation.${state.activeConversationId}`).whisper('typing', {
            user_id: authUserId,
            name: document.querySelector('meta[name="auth-user-name"]')?.content || 'Someone',
            typing: !!isTyping,
        });
    }

    function setupEmojiPicker() {
        emojiPicker.innerHTML = EMOJIS.map((emoji) => `<button type="button" data-emoji="${emoji}">${emoji}</button>`).join('');
    }

    function setupPresence() {
        if (!window.Echo) {
            return;
        }

        state.presenceChannel = window.Echo.join('chat.presence')
            .here((users) => {
                state.onlineUserIds = new Set(users.map((user) => Number(user.id)));
                renderConversationList(listFilter.value || '');
                updateThreadHeader();
            })
            .joining((user) => {
                state.onlineUserIds.add(Number(user.id));
                if (state.activePeer && Number(state.activePeer.id) === Number(user.id)) {
                    state.activePeer.is_online = true;
                    updateThreadHeader();
                }
                renderConversationList(listFilter.value || '');
            })
            .leaving((user) => {
                state.onlineUserIds.delete(Number(user.id));
                if (state.activePeer && Number(state.activePeer.id) === Number(user.id)) {
                    state.activePeer.is_online = false;
                    updateThreadHeader();
                }
                renderConversationList(listFilter.value || '');
            });
    }

    fab.addEventListener('click', togglePanel);
    document.getElementById('chat-close-btn')?.addEventListener('click', closePanel);
    document.getElementById('chat-close-new-btn')?.addEventListener('click', closePanel);
    document.getElementById('chat-close-thread-btn')?.addEventListener('click', closePanel);
    document.getElementById('chat-new-btn')?.addEventListener('click', () => {
        showView('new');
        userSearch.value = '';
        searchUsers('');
        userSearch.focus();
    });

    root.querySelectorAll('[data-chat-back]').forEach((btn) => {
        btn.addEventListener('click', () => {
            leaveConversationChannel();
            state.activeConversationId = null;
            showView('list');
            loadConversations();
        });
    });

    listFilter.addEventListener('input', () => renderConversationList(listFilter.value));

    userSearch.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => searchUsers(userSearch.value), 250);
    });

    conversationList.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-open-conversation]');
        if (!btn) {
            return;
        }
        openConversation(btn.getAttribute('data-open-conversation'));
    });

    userResults.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-start-user]');
        if (!btn) {
            return;
        }
        try {
            await startConversation(btn.getAttribute('data-start-user'));
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: e.message, showConfirmButton: false, timer: 3000 });
            }
        }
    });

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('input', () => {
        autoGrow();
        whisperTyping(true);
        clearTimeout(state.typingTimer);
        state.typingTimer = setTimeout(() => whisperTyping(false), 1200);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    attachInput.addEventListener('change', () => {
        const file = attachInput.files?.[0] || null;
        state.pendingAttachment = file;
        if (file) {
            attachName.textContent = file.name;
            attachPreview.classList.remove('d-none');
        } else {
            clearAttachment();
        }
    });
    attachClear.addEventListener('click', clearAttachment);

    emojiBtn.addEventListener('click', () => {
        emojiPicker.classList.toggle('d-none');
    });
    emojiPicker.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-emoji]');
        if (!btn) {
            return;
        }
        const emoji = btn.getAttribute('data-emoji');
        const start = input.selectionStart || input.value.length;
        const end = input.selectionEnd || input.value.length;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.focus();
        input.selectionStart = input.selectionEnd = start + emoji.length;
        autoGrow();
        emojiPicker.classList.add('d-none');
    });

    setupEmojiPicker();
    setupPresence();
    refreshUnread();
    loadConversations();
    state.pollTimer = setInterval(() => {
        refreshUnread();
        if (state.open && state.view === 'list') {
            loadConversations();
        }
    }, 15000);
})();
