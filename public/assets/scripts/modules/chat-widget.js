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
    const searchEl = document.getElementById('chat-search-bar') || root.querySelector('.chat-widget__search');
    const searchBackBtn = document.getElementById('chat-search-back');
    const listView = document.getElementById('chat-view-list');
    const listShell = root.querySelector('.chat-widget__list-shell');
    const messagesEl = document.getElementById('chat-messages');
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
    const threadView = document.getElementById('chat-view-thread');
    const dropzone = document.getElementById('chat-dropzone');
    const profileEl = document.getElementById('chat-user-profile');
    const profileAvatar = document.getElementById('chat-profile-avatar');
    const profileName = document.getElementById('chat-profile-name');
    const profileUsername = document.getElementById('chat-profile-username');
    const profileStatus = document.getElementById('chat-profile-status');
    const profileEmail = document.getElementById('chat-profile-email');
    const profileDepartment = document.getElementById('chat-profile-department');
    const profileRole = document.getElementById('chat-profile-role');
    const toastHost = document.getElementById('chat-toast-host');

    const STATUS_RANK = { failed: -1, pending: 0, sent: 1, delivered: 2, read: 3 };
    const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
    const ALLOWED_EXTENSIONS = new Set([
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        'zip', 'rar', 'ppt', 'pptx',
    ]);

    const state = {
        open: false,
        view: 'list',
        conversations: [],
        contactResults: [],
        messages: [],
        drafts: {},
        activeConversationId: null,
        draftPeer: null,
        activePeer: null,
        onlineUserIds: new Set(),
        subscribedConversations: new Set(),
        presenceChannel: null,
        conversationChannel: null,
        userChannelBound: false,
        outgoingTypingActive: false,
        typingHeartbeat: null,
        typingHideTimers: new Map(),
        pollTimer: null,
        threadPollTimer: null,
        searchTimer: null,
        pendingAttachment: null,
        loadingMessages: false,
        messageLoadToken: 0,
        typingConversationIds: new Set(),
        dragDepth: 0,
        processedMessageIds: new Set(),
        unreadRefreshTimer: null,
        stickToBottom: true,
        searchMode: false,
        contactsLoading: false,
        searchRequestId: 0,
        lastContactQuery: null,
        animateContactRows: false,
        listRenderKey: '',
        listPageTimer: null,
        loadedImageIds: new Set(),
        pendingPayloads: new Map(),
        draftSendLock: Promise.resolve(),
        enterBubbleTimers: new Map(),
        messageSeq: 0,
        toastedMessageIds: new Set(),
        catchUpToastAt: 0,
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
        if (date.toDateString() === now.toDateString()) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function formatTooltipDateTime(iso) {
        if (!iso) {
            return '';
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        const now = new Date();
        const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (date.toDateString() === now.toDateString()) {
            return time;
        }
        return `${date.toLocaleDateString([], { month: 'short', day: 'numeric' })}, ${time}`;
    }

    function urlTemplate(template, id) {
        return String(template || '').replace('__ID__', String(id));
    }

    function sameId(a, b) {
        if (a == null || b == null) {
            return false;
        }
        if (typeof a === 'string' || typeof b === 'string') {
            return String(a) === String(b);
        }
        const left = Number(a);
        const right = Number(b);
        return Number.isFinite(left) && Number.isFinite(right) && left === right;
    }

    function messageDomId(message) {
        return String(message?.id ?? '');
    }

    function isTempMessageId(id) {
        return typeof id === 'string' && id.startsWith('temp-');
    }

    function findMessageIndexById(id) {
        return state.messages.findIndex((item) => (
            sameId(item.id, id)
            || sameId(item.temp_id, id)
            || sameId(item.reconciled_from, id)
        ));
    }

    function nextClientSeq() {
        state.messageSeq += 1;
        return state.messageSeq;
    }

    function isOptimisticMessage(message) {
        if (!message) {
            return false;
        }
        return isTempMessageId(message.id)
            || message.status === 'pending'
            || message.status === 'failed'
            || state.pendingPayloads.has(String(message.id))
            || state.pendingPayloads.has(String(message.temp_id || ''));
    }

    function hasOpenOptimisticMessages() {
        if (state.pendingPayloads.size > 0) {
            return true;
        }
        return state.messages.some((message) => isOptimisticMessage(message));
    }

    function dedupeMessagesByRealId(messages) {
        const seen = new Set();
        const result = [];
        messages.forEach((message) => {
            if (isTempMessageId(message.id)) {
                result.push(message);
                return;
            }
            const key = messageDomId(message);
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            result.push(message);
        });
        return result;
    }

    function orderedMessages() {
        // Insertion/replace-in-place order is canonical for the sender UI.
        return state.messages;
    }

    function preferStatus(current, next) {
        if (!next) {
            return current;
        }
        // Never let live status events/poll rewrite local optimistic states.
        if (current === 'pending' || current === 'failed') {
            return current;
        }
        if (next === 'pending' || next === 'failed') {
            return current || next;
        }
        const currentRank = STATUS_RANK[current] || 0;
        const nextRank = STATUS_RANK[next] || 0;
        return nextRank >= currentRank ? next : current;
    }

    function typingKeyForConversation(conversationId) {
        return 'c:' + Number(conversationId);
    }

    function typingKeyForDraft(peerId) {
        return 'd:' + Number(peerId);
    }

    function currentTypingKey() {
        if (state.activeConversationId) {
            return typingKeyForConversation(state.activeConversationId);
        }
        if (state.draftPeer?.id) {
            return typingKeyForDraft(state.draftPeer.id);
        }
        return null;
    }

    function composerDraftKey() {
        return currentTypingKey();
    }

    function normalizeMessagePayload(event) {
        if (!event || typeof event !== 'object') {
            return null;
        }
        if (event.message && typeof event.message === 'object' && (event.message.id || event.message.conversation_id)) {
            return event.message;
        }
        if (event.id && event.conversation_id) {
            return event;
        }
        return null;
    }

    function isActiveThread(conversationId) {
        return state.open && state.view === 'thread' && sameId(state.activeConversationId, conversationId);
    }

    function isDraftThread(peerId) {
        return state.open && state.view === 'thread' && !state.activeConversationId && sameId(state.draftPeer?.id, peerId);
    }

    function toastError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: message,
                showConfirmButton: false,
                timer: 3000,
            });
            return;
        }
        window.alert(message);
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
        const safeCount = Math.max(0, Number(count) || 0);
        if (safeCount > 0) {
            badge.classList.remove('d-none');
            badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
        } else {
            badge.classList.add('d-none');
            badge.textContent = '0';
        }
    }

    function syncBadgeFromConversations() {
        const total = state.conversations.reduce(
            (sum, item) => sum + Math.max(0, Number(item.unread_count) || 0),
            0,
        );
        updateBadge(total);
    }

    function rememberProcessedMessage(messageId) {
        const id = Number(messageId);
        if (!Number.isFinite(id) || id <= 0) {
            return false;
        }
        if (state.processedMessageIds.has(id)) {
            return false;
        }
        state.processedMessageIds.add(id);
        if (state.processedMessageIds.size > 400) {
            const oldest = state.processedMessageIds.values().next().value;
            state.processedMessageIds.delete(oldest);
        }
        return true;
    }

    function showView(view) {
        state.view = view;
        root.querySelectorAll('.chat-widget__view').forEach((el) => {
            el.classList.toggle('is-active', el.dataset.view === view);
        });
        if (view === 'thread') {
            startThreadPolling();
        } else {
            stopThreadPolling();
            hideDropzone();
        }
    }

    function saveComposerDraft() {
        const key = composerDraftKey();
        if (!key) {
            return;
        }
        const text = input.value || '';
        if (text.trim()) {
            state.drafts[key] = { text };
        } else {
            delete state.drafts[key];
        }
    }

    function restoreComposerDraft() {
        const key = composerDraftKey();
        const draft = key ? state.drafts[key] : null;
        input.value = draft?.text || '';
        autoGrow();
    }

    function clearComposerDraftForKey(key) {
        if (key) {
            delete state.drafts[key];
        }
    }

    function releaseBootstrapFocusTraps() {
        if (!window.bootstrap?.Offcanvas) {
            return;
        }

        document.querySelectorAll('.offcanvas.show').forEach((el) => {
            const instance = window.bootstrap.Offcanvas.getInstance(el);
            if (instance?._focustrap) {
                instance._focustrap.deactivate();
            }
        });
    }

    function restoreBootstrapFocusTraps() {
        if (!window.bootstrap?.Offcanvas || state.open) {
            return;
        }

        document.querySelectorAll('.offcanvas.show').forEach((el) => {
            const instance = window.bootstrap.Offcanvas.getInstance(el);
            if (instance?._focustrap) {
                instance._focustrap.activate();
            }
        });
    }

    function openPanel() {
        state.open = true;
        panel.classList.remove('d-none');
        fab.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        releaseBootstrapFocusTraps();
        clearIncomingToasts();
        showView('list');
        loadConversations();
    }

    function clearIncomingToasts() {
        if (!toastHost) {
            return;
        }
        Array.from(toastHost.children).forEach((toast) => dismissToast(toast));
    }

    function closePanel() {
        state.messageLoadToken += 1;
        saveComposerDraft();
        state.open = false;
        panel.classList.add('d-none');
        fab.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        emojiPicker.classList.add('d-none');
        hideUserProfile();
        hideDropzone();
        stopOutgoingTyping();
        leaveConversationChannel();
        stopThreadPolling();
        restoreBootstrapFocusTraps();
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

    function isTypingForKey(key) {
        return !!key && state.typingConversationIds.has(key);
    }

    function peerStatusLabel(peer) {
        const key = currentTypingKey();
        if (isTypingForKey(key)) {
            return 'typing...';
        }
        if (isPeerOnline(peer)) {
            return 'Online';
        }
        if (peer?.last_seen_at) {
            return `Last seen ${formatTime(peer.last_seen_at)}`;
        }
        return 'Offline';
    }

    function draftPreviewForKey(key) {
        const draft = key ? state.drafts[key] : null;
        const text = (draft?.text || '').trim();
        return text || null;
    }

    function draftPreviewHtml(key) {
        const text = draftPreviewForKey(key);
        if (!text) {
            return null;
        }
        return `<span class="chat-widget__draft-label">Draft:</span> <span class="chat-widget__draft-text">${escapeHtml(text)}</span>`;
    }

    function previewText(message, conversationId = null, draftPeerId = null) {
        const key = conversationId
            ? typingKeyForConversation(conversationId)
            : (draftPeerId ? typingKeyForDraft(draftPeerId) : null);
        if (isTypingForKey(key)) {
            return 'typing...';
        }
        if (draftPreviewForKey(key)) {
            return null;
        }
        if (!message) {
            return 'No messages yet';
        }
        if (message.type === 'image') {
            return 'Photo';
        }
        if (message.type === 'file') {
            return message.attachment_original_name || 'File';
        }
        return message.body || '';
    }

    function listPreviewHtml(message, conversationId = null, draftPeerId = null) {
        const key = conversationId
            ? typingKeyForConversation(conversationId)
            : (draftPeerId ? typingKeyForDraft(draftPeerId) : null);
        if (isTypingForKey(key)) {
            return escapeHtml('typing...');
        }
        const draftHtml = draftPreviewHtml(key);
        if (draftHtml) {
            return draftHtml;
        }
        return escapeHtml(previewText(message, conversationId, draftPeerId) || '');
    }

    function ticksTooltipHtml(message) {
        const rows = [];
        if (message?.created_at) {
            rows.push(`<div class="chat-bubble__ticks-tip-row"><span class="chat-bubble__ticks-tip-label">Sent</span><span>${escapeHtml(formatTooltipDateTime(message.created_at))}</span></div>`);
        }
        if (message?.delivered_at || message?.status === 'delivered' || message?.status === 'read') {
            rows.push(`<div class="chat-bubble__ticks-tip-row"><span class="chat-bubble__ticks-tip-label">Delivered</span><span>${escapeHtml(formatTooltipDateTime(message.delivered_at) || '—')}</span></div>`);
        }
        if (message?.read_at || message?.status === 'read') {
            rows.push(`<div class="chat-bubble__ticks-tip-row"><span class="chat-bubble__ticks-tip-label">Read</span><span>${escapeHtml(formatTooltipDateTime(message.read_at) || '—')}</span></div>`);
        }
        if (!rows.length) {
            return '';
        }
        return `<span class="chat-bubble__ticks-tip" role="tooltip">${rows.join('')}</span>`;
    }

    function ticksHtml(message, isMine) {
        if (!isMine) {
            return '';
        }
        const status = message?.status || 'sent';
        if (status === 'pending') {
            return `<span class="chat-bubble__ticks is-pending" aria-label="Sending"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>`;
        }
        if (status === 'failed') {
            return `<button type="button" class="chat-bubble__ticks is-failed" data-retry-message="${escapeHtml(messageDomId(message))}" aria-label="Failed, tap to retry" title="Tap to retry"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></button>`;
        }
        const tip = ticksTooltipHtml(message);
        if (status === 'read') {
            return `<span class="chat-bubble__ticks is-read" tabindex="0" aria-label="Read">${tip}✓✓</span>`;
        }
        if (status === 'delivered') {
            return `<span class="chat-bubble__ticks" tabindex="0" aria-label="Delivered">${tip}✓✓</span>`;
        }
        return `<span class="chat-bubble__ticks" tabindex="0" aria-label="Sent">${tip}✓</span>`;
    }

    function applyStatusToBubble(bubble, message) {
        if (!bubble || !message) {
            return;
        }
        bubble.dataset.messageId = messageDomId(message);
        if (message.client_seq != null) {
            bubble.dataset.clientSeq = String(message.client_seq);
        }
        if (isTempMessageId(message.id)) {
            bubble.dataset.tempId = messageDomId(message);
        } else {
            delete bubble.dataset.tempId;
        }
        bubble.classList.toggle('is-failed', message.status === 'failed');

        const isMine = sameId(message.user_id, authUserId);
        const meta = bubble.querySelector('.chat-bubble__meta');
        if (!meta) {
            return;
        }

        // Keep the time node intact; only update ticks in place (no bubble remount).
        let timeEl = meta.querySelector('.chat-bubble__time');
        if (!timeEl) {
            const firstSpan = meta.querySelector(':scope > span:not(.chat-bubble__ticks)');
            if (firstSpan && !firstSpan.classList.contains('chat-bubble__ticks')) {
                firstSpan.classList.add('chat-bubble__time');
                timeEl = firstSpan;
            }
        }

        const nextTicks = ticksHtml(message, isMine);
        const currentTicks = meta.querySelector('.chat-bubble__ticks');
        if (!nextTicks) {
            currentTicks?.remove();
            return;
        }

        const holder = document.createElement('div');
        holder.innerHTML = nextTicks.trim();
        const nextNode = holder.firstElementChild;
        if (!nextNode) {
            return;
        }

        if (currentTicks) {
            if (
                currentTicks.className === nextNode.className
                && currentTicks.getAttribute('aria-label') === nextNode.getAttribute('aria-label')
                && currentTicks.innerHTML === nextNode.innerHTML
            ) {
                return;
            }
            // Span ↔ button (failed) needs a real node swap; otherwise mutate in place.
            if (currentTicks.tagName !== nextNode.tagName) {
                currentTicks.replaceWith(nextNode);
                return;
            }
            currentTicks.className = nextNode.className;
            ['aria-label', 'title', 'tabindex', 'type', 'data-retry-message'].forEach((attr) => {
                if (nextNode.hasAttribute(attr)) {
                    currentTicks.setAttribute(attr, nextNode.getAttribute(attr));
                } else {
                    currentTicks.removeAttribute(attr);
                }
            });
            if (currentTicks.innerHTML !== nextNode.innerHTML) {
                currentTicks.innerHTML = nextNode.innerHTML;
            }
            return;
        }

        meta.appendChild(nextNode);
    }

    function listTicksHtml(message, conversationId = null) {
        if (conversationId && draftPreviewForKey(typingKeyForConversation(conversationId))) {
            return '';
        }
        if (!message || !sameId(message.user_id, authUserId)) {
            return '';
        }
        return `<span class="chat-widget__list-ticks">${ticksHtml(message, true)}</span>`;
    }

    function normalizePeer(peer) {
        if (!peer) {
            return null;
        }
        return {
            id: Number(peer.id),
            name: peer.name || '',
            username: peer.username || '',
            email: peer.email || null,
            role: peer.role || null,
            department: peer.department || null,
            is_online: !!peer.is_online,
            last_seen_at: peer.last_seen_at || null,
        };
    }

    function showUserProfile(peer) {
        const data = normalizePeer(peer);
        if (!data || !profileEl) {
            return;
        }
        const online = isPeerOnline(data);
        profileAvatar.textContent = initials(data.name);
        profileAvatar.classList.toggle('is-online', online);
        profileName.textContent = data.name || 'User';
        profileUsername.textContent = data.username ? `@${data.username}` : '—';
        profileEmail.textContent = data.email || '—';
        profileDepartment.textContent = data.department || '—';
        profileRole.textContent = data.role || '—';

        if (profileStatus) {
            profileStatus.classList.remove('is-online', 'is-typing');
            if (online) {
                profileStatus.textContent = 'Online';
                profileStatus.classList.add('is-online');
            } else if (data.last_seen_at) {
                profileStatus.textContent = `Last seen ${formatTime(data.last_seen_at)}`;
            } else {
                profileStatus.textContent = 'Offline';
            }
        }

        profileEl.classList.remove('d-none');
        profileEl.setAttribute('aria-hidden', 'false');
    }

    function hideUserProfile() {
        if (!profileEl) {
            return;
        }
        profileEl.classList.add('d-none');
        profileEl.setAttribute('aria-hidden', 'true');
    }

    function sortedContacts() {
        return [...state.contactResults].sort((a, b) => {
            const aOnline = isPeerOnline(a) || !!a.is_online ? 1 : 0;
            const bOnline = isPeerOnline(b) || !!b.is_online ? 1 : 0;
            if (aOnline !== bOnline) {
                return bOnline - aOnline;
            }
            return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
        });
    }

    function shouldShowContacts() {
        return state.searchMode || state.contactsLoading;
    }

    function contactsSkeletonHtml() {
        return `
            <div class="chat-widget__contacts-block">
                <div class="chat-widget__section-label">Contacts</div>
                ${[0, 1, 2].map((index) => `
                    <div class="chat-widget__skeleton-item" style="--chat-stagger:${index}">
                        <div class="chat-widget__skeleton-avatar"></div>
                        <div class="chat-widget__skeleton-lines">
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function animateListPage(direction) {
        const target = listShell || conversationList;
        target.classList.remove('is-page-enter', 'is-page-enter-back');
        void target.offsetWidth;
        target.classList.add(direction === 'back' ? 'is-page-enter-back' : 'is-page-enter');
        if (state.listPageTimer) {
            clearTimeout(state.listPageTimer);
        }
        state.listPageTimer = setTimeout(() => {
            target.classList.remove('is-page-enter', 'is-page-enter-back');
            state.listPageTimer = null;
        }, 260);
    }

    function enterSearchMode() {
        if (state.searchMode) {
            listFilter.focus();
            return;
        }

        state.searchMode = true;
        listView?.classList.add('is-search-mode');
        if (searchBackBtn) {
            searchBackBtn.hidden = false;
        }
        searchEl?.classList.add('is-active', 'is-search-page');
        state.contactsLoading = true;
        state.animateContactRows = true;
        state.listRenderKey = '';
        renderConversationList();
        animateListPage('forward');
        searchContacts(listFilter.value);
        listFilter.focus();
    }

    function exitSearchMode({ animate = true } = {}) {
        if (!state.searchMode) {
            listView?.classList.remove('is-search-mode');
            searchEl?.classList.remove('is-active', 'is-search-page');
            if (searchBackBtn) {
                searchBackBtn.hidden = true;
            }
            return;
        }

        state.searchMode = false;
        state.contactsLoading = false;
        state.contactResults = [];
        state.lastContactQuery = null;
        state.animateContactRows = false;
        state.searchRequestId += 1;
        state.listRenderKey = '';
        listFilter.value = '';
        listView?.classList.remove('is-search-mode');
        searchEl?.classList.remove('is-active', 'is-search-page');
        renderConversationList();
        if (animate) {
            animateListPage('back');
        }
        if (searchBackBtn) {
            // Keep in DOM until width transition finishes.
            window.setTimeout(() => {
                if (!state.searchMode) {
                    searchBackBtn.hidden = true;
                }
            }, 240);
        }
    }

    function buildListRenderKey(chatItems, contacts) {
        const chatKey = chatItems.map((item) => {
            const peer = item.peer || {};
            const draftKey = typingKeyForConversation(item.id);
            return [
                item.id,
                Number(item.unread_count || 0),
                item.latest_message?.id || '',
                item.latest_message?.status || '',
                item.latest_message?.body || '',
                item.latest_message?.type || '',
                item.latest_message?.created_at || item.updated_at || '',
                isPeerOnline(peer) ? 1 : 0,
                isTypingForKey(draftKey) ? 1 : 0,
                draftPreviewForKey(draftKey) || '',
                sameId(item.id, state.activeConversationId) ? 1 : 0,
            ].join(':');
        }).join('|');

        const contactKey = contacts.map((user) => {
            const draftKey = typingKeyForDraft(user.id);
            const online = isPeerOnline(user) || !!user.is_online ? 1 : 0;
            return [
                user.id,
                online,
                isTypingForKey(draftKey) ? 1 : 0,
                draftPreviewForKey(draftKey) || '',
            ].join(':');
        }).join('|');

        return [
            state.searchMode ? 1 : 0,
            state.contactsLoading ? 1 : 0,
            (listFilter.value || '').trim().toLowerCase(),
            chatKey,
            contactKey,
        ].join('::');
    }

    function renderConversationList() {
        const term = (listFilter.value || '').trim().toLowerCase();
        const chatItems = state.searchMode
            ? state.conversations.filter((item) => {
                if (!term) {
                    return true;
                }
                const name = (item.peer?.name || '').toLowerCase();
                const username = (item.peer?.username || '').toLowerCase();
                return name.includes(term) || username.includes(term);
            })
            : state.conversations;

        const showContacts = shouldShowContacts();
        const contacts = showContacts ? sortedContacts() : [];
        const renderKey = buildListRenderKey(chatItems, contacts);
        if (renderKey === state.listRenderKey) {
            return;
        }

        const scrollTop = conversationList.scrollTop;
        let html = '';

        if (!chatItems.length && !contacts.length && !state.contactsLoading) {
            state.listRenderKey = renderKey;
            conversationList.innerHTML = state.searchMode
                ? '<div class="chat-widget__empty">No chats or contacts found.</div>'
                : '<div class="chat-widget__empty">No conversations yet. Search a contact to start.</div>';
            state.animateContactRows = false;
            return;
        }

        if (chatItems.length) {
            if (state.searchMode) {
                html += '<div class="chat-widget__section-label">Chats</div>';
            }
            html += chatItems.map((item) => {
                const peer = item.peer || {};
                const online = isPeerOnline(peer);
                const unread = Number(item.unread_count || 0);
                const typing = isTypingForKey(typingKeyForConversation(item.id));
                const hasDraft = !!draftPreviewForKey(typingKeyForConversation(item.id));
                const previewClass = typing ? 'is-typing' : (hasDraft ? 'is-draft' : '');
                return `
                    <div class="chat-widget__item ${sameId(item.id, state.activeConversationId) ? 'is-active' : ''}" data-conversation-id="${item.id}">
                        <button type="button" class="chat-widget__profile-trigger" data-profile-peer="conversation" data-conversation-id="${item.id}" title="View profile" aria-label="View profile">
                            <div class="chat-widget__avatar ${online ? 'is-online' : ''}">${escapeHtml(initials(peer.name))}</div>
                        </button>
                        <button type="button" class="chat-widget__item-open" data-open-conversation="${item.id}">
                            <div class="chat-widget__item-body">
                                <div class="chat-widget__item-top">
                                    <span class="chat-widget__item-name">${escapeHtml(peer.name || 'User')}</span>
                                    <span class="chat-widget__item-time">${escapeHtml(formatTime(item.latest_message?.created_at || item.updated_at))}</span>
                                </div>
                                <div class="chat-widget__item-bottom">
                                    <span class="chat-widget__item-preview ${previewClass}">
                                        ${listTicksHtml(item.latest_message, item.id)}${listPreviewHtml(item.latest_message, item.id)}
                                    </span>
                                    ${unread > 0 ? `<span class="chat-widget__unread">${unread > 99 ? '99+' : unread}</span>` : ''}
                                </div>
                            </div>
                        </button>
                    </div>
                `;
            }).join('');
        }

        if (state.searchMode && state.contactsLoading && !contacts.length) {
            html += contactsSkeletonHtml();
        } else if (state.searchMode && contacts.length) {
            const animateRows = state.animateContactRows;
            html += '<div class="chat-widget__contacts-block">';
            html += '<div class="chat-widget__section-label">Contacts</div>';
            html += contacts.map((user, index) => {
                const online = isPeerOnline(user) || !!user.is_online;
                const typing = isTypingForKey(typingKeyForDraft(user.id));
                const draftKey = typingKeyForDraft(user.id);
                const hasDraft = !!draftPreviewForKey(draftKey);
                const previewClass = typing ? 'is-typing' : (hasDraft ? 'is-draft' : '');
                let previewHtml = escapeHtml(`@${user.username} · Start chat`);
                if (typing) {
                    previewHtml = escapeHtml('typing...');
                } else if (hasDraft) {
                    previewHtml = draftPreviewHtml(draftKey);
                } else if (online) {
                    previewHtml = escapeHtml('Online');
                }
                const stagger = Math.min(index, 5);
                const enterClass = animateRows ? ' is-row-enter' : '';
                return `
                    <div class="chat-widget__item chat-widget__item--contact${enterClass}" data-draft-peer-id="${user.id}" style="--chat-stagger:${stagger}">
                        <button type="button" class="chat-widget__profile-trigger" data-profile-peer="contact" data-peer-id="${user.id}" title="View profile" aria-label="View profile">
                            <div class="chat-widget__avatar ${online ? 'is-online' : ''}">${escapeHtml(initials(user.name))}</div>
                        </button>
                        <button type="button" class="chat-widget__item-open" data-open-draft="${user.id}">
                            <div class="chat-widget__item-body">
                                <div class="chat-widget__item-top">
                                    <span class="chat-widget__item-name">${escapeHtml(user.name)}</span>
                                </div>
                                <div class="chat-widget__item-bottom">
                                    <span class="chat-widget__item-preview ${previewClass} ${online && !typing && !hasDraft ? 'is-online-label' : ''}">
                                        ${previewHtml}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                `;
            }).join('');
            html += '</div>';
        }

        state.listRenderKey = renderKey;
        state.animateContactRows = false;
        conversationList.innerHTML = html;
        conversationList.scrollTop = scrollTop;
    }

    function messageBodyHtml(message) {
        let html = '';
        if (message.type === 'image' && message.attachment_url) {
            const messageId = messageDomId(message);
            const loaded = state.loadedImageIds.has(messageId);
            html += `
                <a class="chat-bubble__image-link" href="${escapeHtml(message.attachment_url)}" target="_blank" rel="noopener">
                    <span class="chat-bubble__image-frame ${loaded ? 'is-loaded' : 'is-loading'}">
                        <span class="chat-bubble__image-placeholder" aria-hidden="true">
                            <i class="fa-regular fa-image"></i>
                            <span class="chat-bubble__image-spinner"></span>
                        </span>
                        <img class="chat-bubble__image" src="${escapeHtml(message.attachment_url)}" alt="${escapeHtml(message.attachment_original_name || 'Image')}" decoding="async" draggable="false">
                    </span>
                </a>
            `;
        } else if (message.type === 'file' && message.attachment_url) {
            html += `<a class="chat-bubble__file" href="${escapeHtml(message.attachment_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-file"></i><span>${escapeHtml(message.attachment_original_name || 'File')}</span></a>`;
        }
        if (message.body) {
            html += `<div class="chat-bubble__text">${escapeHtml(message.body)}</div>`;
        }
        return html || '<div></div>';
    }

    function clearImageFrameSize(frame) {
        if (!frame) {
            return;
        }
        frame.style.width = '';
        frame.style.height = '';
        frame.style.aspectRatio = '';
    }

    function isMessagesNearBottom(threshold = 96) {
        if (!messagesEl) {
            return true;
        }
        return messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight <= threshold;
    }

    function scrollMessagesToBottom() {
        if (!messagesEl) {
            return;
        }
        messagesEl.scrollTop = messagesEl.scrollHeight;
        window.requestAnimationFrame(() => {
            if (!messagesEl || !state.stickToBottom) {
                return;
            }
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    }

    function bindMessageMediaScroll() {
        if (!messagesEl) {
            return;
        }

        messagesEl.querySelectorAll('img.chat-bubble__image').forEach((img) => {
            const frame = img.closest('.chat-bubble__image-frame');
            const bubble = img.closest('.chat-bubble[data-message-id]');
            const messageId = bubble ? String(bubble.dataset.messageId || '') : '';

            const markLoaded = () => {
                if (messageId != null && messageId !== '') {
                    state.loadedImageIds.add(String(messageId));
                }
                clearImageFrameSize(frame);
                frame?.classList.remove('is-loading', 'is-error');
                frame?.classList.add('is-loaded');
                if (state.stickToBottom && state.view === 'thread') {
                    scrollMessagesToBottom();
                }
            };

            if (img.dataset.mediaBound === '1') {
                if (img.complete && img.naturalHeight > 0) {
                    clearImageFrameSize(frame);
                    frame?.classList.add('is-loaded');
                    frame?.classList.remove('is-loading', 'is-error');
                }
                return;
            }
            img.dataset.mediaBound = '1';

            if (img.complete && img.naturalHeight > 0) {
                markLoaded();
                return;
            }

            img.addEventListener('load', markLoaded, { once: true });
            img.addEventListener('error', () => {
                clearImageFrameSize(frame);
                frame?.classList.remove('is-loading');
                frame?.classList.add('is-loaded', 'is-error');
            }, { once: true });
        });
    }

    function scheduleBubbleEnter(bubble) {
        if (!bubble) {
            return;
        }
        // Key by client_seq so reconcile (temp → real id) does not lose the timer.
        const id = String(bubble.dataset.clientSeq || bubble.dataset.messageId || '');
        if (!id) {
            return;
        }
        // Never restart enter animation — that looks like a brand-new bubble on status change.
        if (bubble.dataset.enterPlayed === '1') {
            return;
        }
        bubble.dataset.enterPlayed = '1';
        if (!bubble.classList.contains('is-entering')) {
            bubble.classList.add('is-entering');
        }
        const existing = state.enterBubbleTimers.get(id);
        if (existing) {
            clearTimeout(existing);
        }
        const timer = window.setTimeout(() => {
            bubble.classList.remove('is-entering');
            state.enterBubbleTimers.delete(id);
        }, 320);
        state.enterBubbleTimers.set(id, timer);
    }

    function captureBubblePositions() {
        const map = new Map();
        if (!messagesEl) {
            return map;
        }
        messagesEl.querySelectorAll('.chat-bubble[data-message-id]').forEach((el) => {
            map.set(String(el.dataset.messageId), el.getBoundingClientRect());
        });
        return map;
    }

    function playBubbleShiftAnimation(previousRects, newBubbleIds = []) {
        if (!messagesEl || !previousRects?.size) {
            return;
        }
        const newIds = new Set((newBubbleIds || []).map(String));
        messagesEl.querySelectorAll('.chat-bubble[data-message-id]').forEach((el) => {
            const id = String(el.dataset.messageId);
            if (newIds.has(id)) {
                return;
            }
            const first = previousRects.get(id);
            if (!first) {
                return;
            }
            const last = el.getBoundingClientRect();
            const dy = first.top - last.top;
            if (Math.abs(dy) < 0.5) {
                return;
            }
            if (typeof el.animate === 'function') {
                el.animate(
                    [
                        { transform: `translateY(${dy}px)` },
                        { transform: 'translateY(0)' },
                    ],
                    {
                        duration: 300,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                    },
                );
            }
        });
    }

    function bubbleMarkup(message, { animate = false } = {}) {
        const isMine = sameId(message.user_id, authUserId);
        const hasImage = message.type === 'image' && !!message.attachment_url;
        const imageOnly = hasImage && !message.body;
        const classes = [
            'chat-bubble',
            isMine ? 'is-mine' : 'is-theirs',
            hasImage ? 'has-image' : '',
            imageOnly ? 'is-image-only' : '',
            message.status === 'failed' ? 'is-failed' : '',
            animate ? 'is-entering' : '',
        ].filter(Boolean).join(' ');
        const tempAttr = isTempMessageId(message.id)
            ? ` data-temp-id="${escapeHtml(messageDomId(message))}"`
            : '';
        const seqAttr = message.client_seq != null
            ? ` data-client-seq="${escapeHtml(String(message.client_seq))}"`
            : '';

        return `
            <div class="${classes}" data-message-id="${escapeHtml(messageDomId(message))}"${tempAttr}${seqAttr}>
                <div class="chat-bubble__content">${messageBodyHtml(message)}</div>
                <div class="chat-bubble__meta">
                    <span class="chat-bubble__time">${escapeHtml(formatTime(message.created_at))}</span>
                    ${ticksHtml(message, isMine)}
                </div>
            </div>
        `;
    }

    function dayMarkup(iso) {
        return `<div class="chat-widget__day" data-day-key="${escapeHtml(new Date(iso).toDateString())}">${escapeHtml(new Date(iso).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }))}</div>`;
    }

    function patchMessageMetas(sorted) {
        const bubbles = [...(messagesEl?.querySelectorAll('.chat-bubble[data-message-id]') || [])];
        sorted.forEach((message, index) => {
            const bubble = (message.client_seq != null
                ? messagesEl?.querySelector(`.chat-bubble[data-client-seq="${CSS.escape(String(message.client_seq))}"]`)
                : null)
                || bubbles[index]
                || messagesEl?.querySelector(`.chat-bubble[data-message-id="${CSS.escape(messageDomId(message))}"]`);
            if (!bubble) {
                return;
            }
            applyStatusToBubble(bubble, message);
        });
    }

    function existingBubbleNodes() {
        return [...(messagesEl?.querySelectorAll('.chat-bubble[data-message-id]') || [])];
    }

    function existingBubbleIds() {
        return existingBubbleNodes().map((el) => String(el.dataset.messageId));
    }

    function syncBubblesByPosition(messages, { updateContent = false } = {}) {
        const bubbles = existingBubbleNodes();
        if (!bubbles.length || bubbles.length !== messages.length) {
            return false;
        }

        messages.forEach((message, index) => {
            const bubble = bubbles[index];
            bubble.dataset.messageId = messageDomId(message);
            if (updateContent) {
                const hasImage = message.type === 'image' && !!message.attachment_url;
                const imageOnly = hasImage && !message.body;
                bubble.classList.toggle('has-image', hasImage);
                bubble.classList.toggle('is-image-only', imageOnly);

                const content = bubble.querySelector('.chat-bubble__content');
                if (content) {
                    const currentImg = content.querySelector('img.chat-bubble__image');
                    const nextUrl = message.type === 'image' ? message.attachment_url : null;
                    const currentUrl = currentImg?.getAttribute('src');
                    if (nextUrl && currentUrl && currentUrl !== nextUrl && String(currentUrl).startsWith('blob:')) {
                        currentImg.src = nextUrl;
                    } else if (!(nextUrl && currentUrl && currentUrl === nextUrl && !message.body)) {
                        const nextContent = messageBodyHtml(message);
                        if (content.innerHTML !== nextContent) {
                            content.innerHTML = nextContent;
                        }
                    }
                }
            }

            applyStatusToBubble(bubble, message);
        });

        if (updateContent) {
            bindMessageMediaScroll();
        }
        return true;
    }

    function trimOrphanBubbles(expectedCount) {
        const bubbles = existingBubbleNodes();
        if (bubbles.length <= expectedCount) {
            return;
        }
        bubbles.slice(expectedCount).forEach((bubble) => {
            const day = bubble.previousElementSibling;
            bubble.remove();
            if (day?.classList?.contains('chat-widget__day') && !day.nextElementSibling?.classList?.contains('chat-bubble')) {
                // Keep day markers that still precede remaining bubbles; remove trailing empty day.
                if (!day.nextElementSibling) {
                    day.remove();
                }
            }
        });
    }

    function renderMessages({ forceScroll = false, rebuild = false } = {}) {
        const sorted = orderedMessages();
        const shouldStick = forceScroll || state.stickToBottom || isMessagesNearBottom();
        const bubbles = existingBubbleNodes();

        if (!rebuild && bubbles.length > sorted.length && sorted.length > 0) {
            trimOrphanBubbles(sorted.length);
        }

        const currentCount = existingBubbleNodes().length;

        // Same count ⇒ same slots. Never full-rebuild for status / id reconcile.
        if (!rebuild && currentCount > 0 && currentCount === sorted.length) {
            syncBubblesByPosition(sorted, { updateContent: false });
            // Do not scroll on status-only sync — it causes visible bubble jitter.
            if (forceScroll) {
                state.stickToBottom = true;
                scrollMessagesToBottom();
            }
            return;
        }

        const currentIds = existingBubbleIds();
        const nextIds = sorted.map((message) => messageDomId(message));
        const canAppend = !rebuild
            && currentIds.length > 0
            && nextIds.length > currentIds.length
            && currentIds.every((id, index) => {
                const message = sorted[index];
                if (!message) {
                    return false;
                }
                if (id === nextIds[index]) {
                    return true;
                }
                return isTempMessageId(id)
                    && (sameId(message.temp_id, id) || sameId(message.reconciled_from, id) || sameId(message.id, nextIds[index]));
            });

        if (canAppend) {
            syncBubblesByPosition(sorted.slice(0, currentIds.length), { updateContent: false });
            const lastExisting = sorted[currentIds.length - 1];
            let lastDay = lastExisting?.created_at
                ? new Date(lastExisting.created_at).toDateString()
                : '';
            const chunks = [];
            const appended = sorted.slice(currentIds.length);
            const previousRects = captureBubblePositions();
            appended.forEach((message) => {
                const day = message.created_at ? new Date(message.created_at).toDateString() : '';
                if (day && day !== lastDay) {
                    lastDay = day;
                    chunks.push(dayMarkup(message.created_at));
                }
                chunks.push(bubbleMarkup(message, { animate: !!message._animate }));
                message._animate = false;
            });
            messagesEl.insertAdjacentHTML('beforeend', chunks.join(''));
            if (shouldStick || forceScroll) {
                state.stickToBottom = true;
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
            const newIds = appended.map((message) => messageDomId(message));
            playBubbleShiftAnimation(previousRects, newIds);
            window.requestAnimationFrame(() => {
                appended.forEach((message) => {
                    const bubble = messagesEl.querySelector(`.chat-bubble[data-client-seq="${CSS.escape(String(message.client_seq))}"]`)
                        || messagesEl.querySelector(`.chat-bubble[data-message-id="${CSS.escape(messageDomId(message))}"]`);
                    if (bubble) {
                        scheduleBubbleEnter(bubble);
                    }
                });
                if (shouldStick || forceScroll) {
                    scrollMessagesToBottom();
                }
            });
            bindMessageMediaScroll();
            return;
        }

        // Full rebuild only for initial load / hard mismatch.
        let lastDay = '';
        const chunks = [];
        const animatedIds = [];
        sorted.forEach((message) => {
            const day = message.created_at ? new Date(message.created_at).toDateString() : '';
            if (day && day !== lastDay) {
                lastDay = day;
                chunks.push(dayMarkup(message.created_at));
            }
            if (message._animate) {
                animatedIds.push(String(message.client_seq ?? messageDomId(message)));
            }
            chunks.push(bubbleMarkup(message, { animate: !!message._animate }));
            message._animate = false;
        });

        messagesEl.innerHTML = chunks.join('') || '<div class="chat-widget__empty">Say hello</div>';
        if (shouldStick || forceScroll) {
            state.stickToBottom = true;
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
        window.requestAnimationFrame(() => {
            animatedIds.forEach((key) => {
                const bubble = messagesEl.querySelector(`.chat-bubble[data-client-seq="${CSS.escape(key)}"]`)
                    || messagesEl.querySelector(`.chat-bubble[data-message-id="${CSS.escape(key)}"]`);
                if (bubble) {
                    scheduleBubbleEnter(bubble);
                }
            });
            if (shouldStick || forceScroll) {
                scrollMessagesToBottom();
            }
        });
        bindMessageMediaScroll();
    }

    function updateThreadHeader() {
        const peer = state.activePeer || state.draftPeer || {};
        threadName.textContent = peer.name || 'Chat';
        threadAvatar.textContent = initials(peer.name);
        threadAvatar.classList.toggle('is-online', isPeerOnline(peer));
        threadStatus.textContent = peerStatusLabel(peer);
    }

    function setTypingKey(key, isTyping) {
        if (!key) {
            return;
        }

        const existingTimer = state.typingHideTimers.get(key);
        if (existingTimer) {
            clearTimeout(existingTimer);
            state.typingHideTimers.delete(key);
        }

        if (isTyping) {
            state.typingConversationIds.add(key);
            state.typingHideTimers.set(key, setTimeout(() => {
                state.typingConversationIds.delete(key);
                state.typingHideTimers.delete(key);
                renderConversationList();
                if (key === currentTypingKey()) {
                    updateThreadHeader();
                }
            }, 4000));
        } else {
            state.typingConversationIds.delete(key);
        }

        renderConversationList();
        if (key === currentTypingKey()) {
            updateThreadHeader();
        }
    }

    async function refreshUnread() {
        try {
            const payload = await api(root.dataset.unreadUrl);
            updateBadge(Number(payload.count || 0));
        } catch (e) {
            // ignore
        }
    }

    function queueUnreadRefresh() {
        if (state.unreadRefreshTimer) {
            clearTimeout(state.unreadRefreshTimer);
        }
        state.unreadRefreshTimer = setTimeout(() => {
            state.unreadRefreshTimer = null;
            refreshUnread();
        }, 200);
    }

    async function loadConversations() {
        try {
            const payload = await api(root.dataset.conversationsUrl);
            state.conversations = payload.data || [];
            updateBadge(Number(payload.unread_count || 0));
            renderConversationList();
            state.conversations.forEach((item) => subscribeConversation(item.id));
        } catch (e) {
            conversationList.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
        }
    }

    async function searchContacts(query) {
        if (!state.searchMode) {
            state.contactResults = [];
            state.contactsLoading = false;
            state.lastContactQuery = null;
            renderConversationList();
            return;
        }

        const term = (query || '').trim();
        const requestId = ++state.searchRequestId;
        const queryChanged = term !== state.lastContactQuery;
        const needsLoadingUi = state.contactResults.length === 0 || queryChanged;

        if (queryChanged && state.contactResults.length) {
            state.contactResults = [];
        }
        if (needsLoadingUi) {
            state.contactsLoading = true;
            state.animateContactRows = true;
            renderConversationList();
        }

        try {
            const url = new URL(root.dataset.searchUsersUrl, window.location.origin);
            url.searchParams.set('q', term);
            const payload = await api(url.toString());
            if (requestId !== state.searchRequestId || !state.searchMode) {
                return;
            }
            state.contactResults = payload.data || [];
            state.contactsLoading = false;
            state.lastContactQuery = term;
            state.animateContactRows = true;
            renderConversationList();
        } catch (e) {
            if (requestId !== state.searchRequestId || !state.searchMode) {
                return;
            }
            state.contactResults = [];
            state.contactsLoading = false;
            state.lastContactQuery = term;
            renderConversationList();
        }
    }

    function leaveConversationChannel() {
        state.conversationChannel = null;
    }

    function bindConversationListeners(channel, conversationId) {
        channel
            .listen('.chat.message.sent', (event) => {
                handleIncomingMessage(normalizeMessagePayload(event));
            })
            .listen('.chat.message.delivered', (event) => {
                if (sameId(event.user_id, authUserId)) {
                    return;
                }
                updateOutgoingStatus(event.conversation_id, 'delivered', event.delivered_at);
            })
            .listen('.chat.conversation.read', (event) => {
                if (sameId(event.user_id, authUserId)) {
                    return;
                }
                updateOutgoingStatus(event.conversation_id, 'read', event.read_at);
            });
    }

    function subscribeConversation(conversationId) {
        if (!window.Echo || !conversationId || state.subscribedConversations.has(Number(conversationId))) {
            return;
        }
        state.subscribedConversations.add(Number(conversationId));
        bindConversationListeners(window.Echo.private(`conversation.${conversationId}`), conversationId);
    }

    function setupUserChannelFallback() {
        if (!window.Echo || !authUserId || state.userChannelBound) {
            return;
        }
        state.userChannelBound = true;
        window.Echo.private(`App.Models.User.${authUserId}`)
            .listen('.chat.message.sent', (event) => {
                handleIncomingMessage(normalizeMessagePayload(event));
            })
            .listen('.chat.typing', (event) => {
                if (sameId(event.user_id, authUserId)) {
                    return;
                }
                const key = event.conversation_id
                    ? typingKeyForConversation(event.conversation_id)
                    : typingKeyForDraft(event.user_id);
                setTypingKey(key, event.typing !== false);
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
        let changed = false;
        state.messages = state.messages.map((message) => {
            if (isOptimisticMessage(message) || isTempMessageId(message.id)) {
                return message;
            }
            if (!sameId(message.conversation_id || state.activeConversationId, conversationId)) {
                return message;
            }
            if (!sameId(message.user_id, authUserId)) {
                return message;
            }
            if (at && message.created_at && new Date(message.created_at) > new Date(at)) {
                return message;
            }
            const nextStatus = preferStatus(message.status, status);
            const patch = {};
            if (nextStatus !== message.status) {
                changed = true;
                patch.status = nextStatus;
            }
            if ((status === 'delivered' || status === 'read') && at && !message.delivered_at) {
                patch.delivered_at = at;
                changed = true;
            }
            if (status === 'read' && at) {
                patch.read_at = at;
                changed = true;
            }
            if (Object.keys(patch).length) {
                return { ...message, ...patch };
            }
            return message;
        });

        const conversation = state.conversations.find((item) => sameId(item.id, conversationId));
        if (conversation?.latest_message && sameId(conversation.latest_message.user_id, authUserId)) {
            conversation.latest_message.status = preferStatus(conversation.latest_message.status, status);
            if ((status === 'delivered' || status === 'read') && at && !conversation.latest_message.delivered_at) {
                conversation.latest_message.delivered_at = at;
            }
            if (status === 'read' && at) {
                conversation.latest_message.read_at = at;
            }
            renderConversationList();
        }

        if (changed && isActiveThread(conversationId)) {
            patchMessageMetas(orderedMessages());
        }
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
            const item = state.conversations.find((c) => sameId(c.id, conversationId));
            if (item) {
                item.unread_count = 0;
            }
            renderConversationList();
            syncBadgeFromConversations();
            queueUnreadRefresh();
        } catch (e) {
            // ignore
        }
    }

    function markDeliveredForPeer(userId) {
        state.conversations.forEach((conversation) => {
            if (sameId(conversation.peer?.id, userId)) {
                markDelivered(conversation.id);
            }
        });
    }

    function focusComposer() {
        if (!input || !(state.open && state.view === 'thread')) {
            return;
        }
        window.requestAnimationFrame(() => {
            if (state.open && state.view === 'thread') {
                input.focus({ preventScroll: true });
            }
        });
    }

    function showMessagesLoading() {
        messagesEl.innerHTML = `
            <div class="chat-widget__messages-loading" aria-live="polite">
                <div class="chat-widget__messages-loading-spinner" aria-hidden="true"></div>
                <span>Loading messages...</span>
            </div>
        `;
    }

    function clearThreadMessagesUi() {
        state.messages.forEach((message) => revokeOptimisticBlob(message));
        state.messages = [];
        state.loadedImageIds.clear();
        state.pendingPayloads.clear();
        state.messageSeq = 0;
        messagesEl.innerHTML = '';
    }

    async function openConversation(conversationId) {
        const loadToken = ++state.messageLoadToken;
        saveComposerDraft();
        stopOutgoingTyping();
        clearAttachment();
        hideUserProfile();
        const conversation = state.conversations.find((item) => sameId(item.id, conversationId));
        state.activeConversationId = Number(conversationId);
        state.draftPeer = null;
        state.activePeer = normalizePeer(conversation?.peer) || null;
        clearThreadMessagesUi();
        exitSearchMode({ animate: false });
        state.stickToBottom = true;
        restoreComposerDraft();
        updateThreadHeader();
        showMessagesLoading();
        showView('thread');
        focusComposer();
        joinActiveConversationChannel(conversationId);
        await loadMessages(conversationId, { merge: false, loadToken, forceScroll: true });
        if (loadToken !== state.messageLoadToken || !sameId(state.activeConversationId, conversationId)) {
            return;
        }
        await markDelivered(conversationId);
        await markRead(conversationId);
        state.stickToBottom = true;
        scrollMessagesToBottom();
        bindMessageMediaScroll();
        focusComposer();
        syncOutgoingTyping();
    }

    function openDraft(peer) {
        state.messageLoadToken += 1;
        saveComposerDraft();
        stopOutgoingTyping();
        clearAttachment();
        hideUserProfile();
        state.activeConversationId = null;
        state.draftPeer = normalizePeer(peer);
        state.activePeer = state.draftPeer;
        clearThreadMessagesUi();
        leaveConversationChannel();
        exitSearchMode({ animate: false });
        state.stickToBottom = true;
        restoreComposerDraft();
        updateThreadHeader();
        showView('thread');
        renderMessages({ forceScroll: true });
        focusComposer();
        syncOutgoingTyping();
    }

    async function loadMessages(conversationId, { merge = false, loadToken = null, forceScroll = false } = {}) {
        if (!conversationId) {
            return;
        }
        state.loadingMessages = true;
        try {
            const payload = await api(urlTemplate(root.dataset.messagesUrlTemplate, conversationId));
            if (loadToken !== null && loadToken !== state.messageLoadToken) {
                return;
            }
            if (!sameId(state.activeConversationId, conversationId)) {
                return;
            }
            const fetched = (payload.data || []).slice().reverse();

            if (!merge) {
                state.messageSeq = 0;
                state.messages = fetched.map((message) => {
                    const normalized = {
                        ...message,
                        id: Number(message.id),
                        conversation_id: Number(message.conversation_id || conversationId),
                        user_id: Number(message.user_id),
                        client_seq: nextClientSeq(),
                    };
                    rememberProcessedMessage(normalized.id);
                    return normalized;
                });
            } else {
                // Poll must NEVER absorb optimistic slots. Parallel HTTP responses finish
                // out of order; only reconcileOptimistic(tempId, response) may pair them.
                const fetchedById = new Map(fetched.map((message) => [messageDomId(message), message]));
                const nextMessages = [];
                const seenRealIds = new Set();
                const blockingOptimistic = hasOpenOptimisticMessages();

                state.messages.forEach((existing) => {
                    if (isOptimisticMessage(existing)) {
                        nextMessages.push(existing);
                        return;
                    }

                    const key = messageDomId(existing);
                    const incoming = fetchedById.get(key);
                    if (incoming) {
                        nextMessages.push({
                            ...existing,
                            ...incoming,
                            id: Number(incoming.id),
                            conversation_id: Number(incoming.conversation_id || conversationId),
                            user_id: Number(incoming.user_id),
                            client_seq: existing.client_seq,
                            temp_id: existing.temp_id,
                            reconciled_from: existing.reconciled_from,
                            status: preferStatus(existing.status, incoming.status),
                        });
                        seenRealIds.add(key);
                        return;
                    }

                    nextMessages.push(existing);
                    seenRealIds.add(key);
                });

                fetched.forEach((message) => {
                    const key = messageDomId(message);
                    if (seenRealIds.has(key)) {
                        return;
                    }
                    if (nextMessages.some((item) => sameId(item.id, message.id) && !isTempMessageId(item.id))) {
                        return;
                    }
                    // Skip own messages while optimistic bubbles are open — HTTP reconcile owns them.
                    if (sameId(message.user_id, authUserId) && blockingOptimistic) {
                        return;
                    }
                    nextMessages.push({
                        ...message,
                        id: Number(message.id),
                        conversation_id: Number(message.conversation_id || conversationId),
                        user_id: Number(message.user_id),
                        client_seq: nextClientSeq(),
                    });
                    seenRealIds.add(key);
                    rememberProcessedMessage(message.id);
                });

                state.messages = dedupeMessagesByRealId(nextMessages);
            }

            renderMessages({
                forceScroll: forceScroll || (!merge && state.stickToBottom),
                rebuild: !merge,
            });
        } catch (e) {
            if (loadToken !== null && loadToken !== state.messageLoadToken) {
                return;
            }
            if (!merge && sameId(state.activeConversationId, conversationId)) {
                messagesEl.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
            }
        } finally {
            if (loadToken === null || loadToken === state.messageLoadToken) {
                state.loadingMessages = false;
            }
        }
    }

    function startThreadPolling() {
        stopThreadPolling();
        state.threadPollTimer = setInterval(() => {
            if (state.open && state.view === 'thread' && state.activeConversationId && !state.loadingMessages) {
                loadMessages(state.activeConversationId, { merge: true });
            }
        }, 6000);
    }

    function stopThreadPolling() {
        if (state.threadPollTimer) {
            clearInterval(state.threadPollTimer);
            state.threadPollTimer = null;
        }
    }

    function upsertConversationPreview(message) {
        const index = state.conversations.findIndex((item) => sameId(item.id, message.conversation_id));
        if (index >= 0) {
            const item = state.conversations[index];
            item.latest_message = message;
            item.updated_at = message.created_at;
            if (!sameId(message.user_id, authUserId) && !isActiveThread(message.conversation_id)) {
                item.unread_count = Number(item.unread_count || 0) + 1;
            } else if (!sameId(message.user_id, authUserId) && isActiveThread(message.conversation_id)) {
                item.unread_count = 0;
            }
            state.conversations.splice(index, 1);
            state.conversations.unshift(item);
            renderConversationList();
            syncBadgeFromConversations();
            return;
        }
        loadConversations();
    }

    function appendMessage(message, { animate = true } = {}) {
        if (!message || message.id == null || message.id === '') {
            return false;
        }
        if (state.messages.some((item) => sameId(item.id, message.id))) {
            return false;
        }
        if (message.temp_id && state.messages.some((item) => sameId(item.temp_id, message.temp_id))) {
            return false;
        }
        // Own realtime echo while optimistic slots are open must not create a second bubble
        // (that re-plays the enter animation when pending → sent). HTTP reconcile owns pairing.
        if (
            sameId(message.user_id, authUserId)
            && !isTempMessageId(message.id)
            && hasOpenOptimisticMessages()
        ) {
            return false;
        }
        if (message.client_seq == null || !Number.isFinite(Number(message.client_seq))) {
            message.client_seq = nextClientSeq();
        } else {
            state.messageSeq = Math.max(state.messageSeq, Number(message.client_seq));
        }
        if (animate) {
            message._animate = true;
        }
        state.messages.push(message);
        renderMessages({ forceScroll: true });
        return true;
    }

    function buildOptimisticMessage({ tempId, body, file, conversationId }) {
        const isImage = !!(file && String(file.type || '').startsWith('image/'));
        const blobUrl = file && isImage ? URL.createObjectURL(file) : null;
        let type = 'text';
        if (file && isImage) {
            type = 'image';
        } else if (file) {
            type = 'file';
        }

        return {
            id: tempId,
            temp_id: tempId,
            conversation_id: conversationId ? Number(conversationId) : null,
            user_id: authUserId,
            body: body || null,
            type,
            attachment_url: blobUrl || (file && !isImage ? '#' : null),
            attachment_original_name: file?.name || null,
            status: 'pending',
            created_at: new Date().toISOString(),
            local_blob_url: blobUrl,
            client_seq: nextClientSeq(),
        };
    }

    function revokeOptimisticBlob(message) {
        if (message?.local_blob_url) {
            URL.revokeObjectURL(message.local_blob_url);
            message.local_blob_url = null;
        }
    }

    function patchSingleBubble(message) {
        const bubble = messagesEl?.querySelector(`.chat-bubble[data-message-id="${CSS.escape(messageDomId(message))}"]`)
            || (message.temp_id
                ? messagesEl?.querySelector(`.chat-bubble[data-temp-id="${CSS.escape(String(message.temp_id))}"]`)
                : null)
            || (message.client_seq != null
                ? messagesEl?.querySelector(`.chat-bubble[data-client-seq="${CSS.escape(String(message.client_seq))}"]`)
                : null);
        if (!bubble) {
            syncBubblesByPosition(orderedMessages(), { updateContent: false });
            return;
        }
        applyStatusToBubble(bubble, message);
    }

    function reconcileOptimistic(tempId, realMessage) {
        const realId = Number(realMessage.id);

        // Remove any duplicate real-id copies, but keep the optimistic slot for tempId.
        state.messages = state.messages.filter((item) => {
            if (sameId(item.id, tempId) || sameId(item.temp_id, tempId) || sameId(item.reconciled_from, tempId)) {
                return true;
            }
            return !sameId(item.id, realId);
        });

        let index = findMessageIndexById(tempId);
        if (index < 0) {
            // Already reconciled earlier; just ensure no duplicate and patch ticks.
            index = state.messages.findIndex((item) => sameId(item.id, realId));
            if (index < 0) {
                // Should be rare: keep thread stable rather than appending out of order.
                return;
            }
        }

        const previous = state.messages[index];
        if (state.loadedImageIds.has(String(tempId))) {
            state.loadedImageIds.add(String(realId));
            state.loadedImageIds.delete(String(tempId));
        }
        revokeOptimisticBlob(previous);

        let status = realMessage.status || 'sent';
        if (isPeerOnline(state.activePeer)) {
            status = preferStatus(status === 'pending' ? 'sent' : status, 'delivered');
            if (status === 'pending' || status === 'failed') {
                status = 'delivered';
            }
        }
        if (status === 'pending' || status === 'failed') {
            status = 'sent';
        }

        const message = {
            ...previous,
            ...realMessage,
            id: realId,
            conversation_id: Number(realMessage.conversation_id || previous.conversation_id || state.activeConversationId),
            user_id: Number(realMessage.user_id || previous.user_id || authUserId),
            status,
            // Keep local timestamp so the visible time label does not jump/reflow.
            created_at: previous.created_at || realMessage.created_at,
            client_seq: previous.client_seq != null ? previous.client_seq : nextClientSeq(),
            temp_id: previous.temp_id || tempId,
            reconciled_from: tempId,
            local_blob_url: null,
            delivered_at: realMessage.delivered_at
                || previous.delivered_at
                || (status === 'delivered' || status === 'read' ? new Date().toISOString() : null),
        };

        state.messages[index] = message;
        state.messages = dedupeMessagesByRealId(state.messages);
        rememberProcessedMessage(realId);
        state.messageSeq = Math.max(state.messageSeq, Number(message.client_seq) || 0);

        // Prefer stable lookup by client_seq (never moves in the list).
        const bubble = (message.client_seq != null
            ? messagesEl?.querySelector(`.chat-bubble[data-client-seq="${CSS.escape(String(message.client_seq))}"]`)
            : null)
            || messagesEl?.querySelector(`.chat-bubble[data-temp-id="${CSS.escape(String(tempId))}"]`)
            || messagesEl?.querySelector(`.chat-bubble[data-message-id="${CSS.escape(String(tempId))}"]`);

        if (bubble) {
            const content = bubble.querySelector('.chat-bubble__content');
            if (content && message.type === 'image' && previous.local_blob_url && message.attachment_url) {
                const img = content.querySelector('img.chat-bubble__image');
                if (img) {
                    img.src = message.attachment_url;
                }
            } else if (content && previous.type === 'file' && message.attachment_url && message.attachment_url !== '#') {
                content.innerHTML = messageBodyHtml(message);
            }
            // Only swap ticks — do not rewrite meta or kill enter animation mid-flight.
            applyStatusToBubble(bubble, message);
            trimOrphanBubbles(state.messages.length);
            return;
        }

        syncBubblesByPosition(orderedMessages(), { updateContent: false });
        trimOrphanBubbles(state.messages.length);
    }

    function markOptimisticFailed(tempId, errorMessage = '') {
        const index = findMessageIndexById(tempId);
        if (index < 0) {
            return;
        }
        state.messages[index] = {
            ...state.messages[index],
            status: 'failed',
            error_message: errorMessage || 'Failed to send',
        };
        patchSingleBubble(state.messages[index]);
    }

    async function withDraftSendLock(fn) {
        const previous = state.draftSendLock;
        let release = () => {};
        state.draftSendLock = new Promise((resolve) => {
            release = resolve;
        });
        try {
            await previous;
            return await fn();
        } finally {
            release();
        }
    }

    async function dispatchOutgoing(tempId) {
        const payload = state.pendingPayloads.get(tempId);
        if (!payload) {
            return;
        }

        const index = findMessageIndexById(tempId);
        if (index >= 0) {
            state.messages[index].status = 'pending';
            patchSingleBubble(state.messages[index]);
        }

        const formData = new FormData();
        if (payload.body) {
            formData.append('body', payload.body);
        }
        if (payload.file) {
            formData.append('attachment', payload.file);
        }

        const sendRequest = async () => {
            let message;
            let conversationPayload = null;
            const conversationId = state.activeConversationId || payload.conversationId;

            if (conversationId) {
                payload.conversationId = Number(conversationId);
                const response = await api(urlTemplate(root.dataset.storeMessageUrlTemplate, conversationId), {
                    method: 'POST',
                    body: formData,
                });
                message = response.data;
            } else if (payload.draftPeer?.id || state.draftPeer?.id) {
                const peerId = payload.draftPeer?.id || state.draftPeer.id;
                formData.set('user_id', String(peerId));
                const response = await api(root.dataset.directMessageUrl, {
                    method: 'POST',
                    body: formData,
                });
                conversationPayload = response.data.conversation;
                message = response.data.message;
                state.activeConversationId = Number(conversationPayload.id);
                state.draftPeer = null;
                state.activePeer = normalizePeer(conversationPayload.peer);
                joinActiveConversationChannel(state.activeConversationId);

                const existingIndex = state.conversations.findIndex((item) => sameId(item.id, conversationPayload.id));
                if (existingIndex >= 0) {
                    state.conversations[existingIndex] = conversationPayload;
                } else {
                    state.conversations.unshift(conversationPayload);
                }
                payload.conversationId = state.activeConversationId;
            } else {
                throw new Error('No active chat');
            }

            return { message, conversationPayload };
        };

        try {
            const needsDraftLock = !(state.activeConversationId || payload.conversationId)
                && !!(payload.draftPeer?.id || state.draftPeer?.id);
            const result = needsDraftLock
                ? await withDraftSendLock(sendRequest)
                : await sendRequest();

            reconcileOptimistic(tempId, result.message);
            state.pendingPayloads.delete(tempId);
            upsertConversationPreview(result.message);
            updateThreadHeader();
            renderConversationList();
        } catch (e) {
            markOptimisticFailed(tempId, e.message);
            toastError(e.message);
        }
    }

    async function retryOptimisticSend(tempId) {
        const payload = state.pendingPayloads.get(tempId);
        if (!payload) {
            return;
        }
        const index = findMessageIndexById(tempId);
        if (index >= 0 && state.messages[index].status === 'pending') {
            return;
        }
        await dispatchOutgoing(tempId);
    }

    function sendMessage() {
        const body = (input.value || '').trim();
        const file = state.pendingAttachment;
        if (!body && !file) {
            return;
        }

        const draftKey = composerDraftKey();
        const conversationId = state.activeConversationId;
        const draftPeer = state.draftPeer ? { ...state.draftPeer } : null;
        const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

        input.value = '';
        clearAttachment();
        clearComposerDraftForKey(draftKey);
        if (conversationId) {
            clearComposerDraftForKey(typingKeyForConversation(conversationId));
        }
        autoGrow();
        stopOutgoingTyping();
        focusComposer();

        const optimistic = buildOptimisticMessage({ tempId, body, file, conversationId });
        state.pendingPayloads.set(tempId, {
            body,
            file,
            conversationId,
            draftPeer,
        });

        appendMessage(optimistic, { animate: true });
        if (optimistic.conversation_id) {
            upsertConversationPreview(optimistic);
            renderConversationList();
        }

        dispatchOutgoing(tempId);
    }

    function handleIncomingMessage(message) {
        if (!message || !message.conversation_id || !message.id) {
            return;
        }

        message.conversation_id = Number(message.conversation_id);
        message.id = Number(message.id);
        message.user_id = Number(message.user_id);

        if (!rememberProcessedMessage(message.id)) {
            return;
        }

        setTypingKey(typingKeyForConversation(message.conversation_id), false);
        if (!sameId(message.user_id, authUserId)) {
            setTypingKey(typingKeyForDraft(message.user_id), false);
        }

        upsertConversationPreview(message);
        subscribeConversation(message.conversation_id);

        if (isActiveThread(message.conversation_id) || (isDraftThread(message.user_id) && !sameId(message.user_id, authUserId))) {
            if (isDraftThread(message.user_id)) {
                state.activeConversationId = message.conversation_id;
                state.draftPeer = null;
                joinActiveConversationChannel(message.conversation_id);
            }
            // Own echoes: never animate; skip append entirely while optimistic send is in flight.
            if (sameId(message.user_id, authUserId) && hasOpenOptimisticMessages()) {
                return;
            }
            appendMessage(message, { animate: !sameId(message.user_id, authUserId) });
            if (!sameId(message.user_id, authUserId)) {
                markDelivered(message.conversation_id);
                markRead(message.conversation_id);
            }
            return;
        }

        if (!sameId(message.user_id, authUserId)) {
            syncBadgeFromConversations();
            queueUnreadRefresh();
            markDelivered(message.conversation_id);
            if (!state.open) {
                showIncomingToast(message);
            }
        }
    }

    function messageToastPreview(message) {
        if (!message) {
            return 'New message';
        }
        if (message.type === 'image') {
            return 'Sent a photo';
        }
        if (message.type === 'file') {
            return message.attachment_original_name || 'Sent a file';
        }
        return message.body || 'New message';
    }

    function resolveIncomingSender(message) {
        const conversation = state.conversations.find((item) => sameId(item.id, message.conversation_id));
        if (conversation?.peer) {
            return conversation.peer;
        }
        if (message.user) {
            return message.user;
        }
        return {
            id: message.user_id,
            name: 'New message',
            username: '',
        };
    }

    function dismissToast(toastEl) {
        if (!toastEl || toastEl.classList.contains('is-leaving')) {
            return;
        }
        toastEl.classList.add('is-leaving');
        window.setTimeout(() => toastEl.remove(), 220);
    }

    function toastedStorageKey() {
        return `chat.toastedMessageIds.${authUserId}`;
    }

    function loadToastedMessageIds() {
        try {
            const raw = window.localStorage.getItem(toastedStorageKey());
            const ids = raw ? JSON.parse(raw) : [];
            if (Array.isArray(ids)) {
                state.toastedMessageIds = new Set(ids.map(String).slice(-200));
            }
        } catch (e) {
            state.toastedMessageIds = new Set();
        }
    }

    function persistToastedMessageIds() {
        try {
            window.localStorage.setItem(
                toastedStorageKey(),
                JSON.stringify([...state.toastedMessageIds].slice(-200)),
            );
        } catch (e) {
            // ignore quota / private mode
        }
    }

    function rememberToastedMessage(messageId) {
        const id = String(messageId ?? '');
        if (!id) {
            return false;
        }
        if (state.toastedMessageIds.has(id)) {
            return false;
        }
        state.toastedMessageIds.add(id);
        while (state.toastedMessageIds.size > 200) {
            const oldest = state.toastedMessageIds.values().next().value;
            state.toastedMessageIds.delete(oldest);
        }
        persistToastedMessageIds();
        return true;
    }

    function showIncomingToast(message) {
        if (!toastHost || state.open || !message?.id) {
            return;
        }

        const messageId = String(message.id);
        if (state.toastedMessageIds.has(messageId)) {
            return;
        }
        if (toastHost.querySelector(`[data-message-id="${CSS.escape(messageId)}"]`)) {
            rememberToastedMessage(messageId);
            return;
        }

        rememberToastedMessage(messageId);

        const sender = resolveIncomingSender(message);
        const toast = document.createElement('button');
        toast.type = 'button';
        toast.className = 'chat-widget__toast';
        toast.setAttribute('data-message-id', messageId);
        toast.setAttribute('data-conversation-id', String(message.conversation_id));
        toast.innerHTML = `
            <div class="chat-widget__avatar">${escapeHtml(initials(sender.name))}</div>
            <div class="chat-widget__toast-body">
                <div class="chat-widget__toast-top">
                    <p class="chat-widget__toast-name">${escapeHtml(sender.name || 'New message')}</p>
                    <span class="chat-widget__toast-time">${escapeHtml(formatTime(message.created_at))}</span>
                </div>
                <p class="chat-widget__toast-preview">${escapeHtml(messageToastPreview(message))}</p>
            </div>
            <span class="chat-widget__toast-close" data-toast-close aria-label="Dismiss" role="button">
                <i class="fa-solid fa-xmark"></i>
            </span>
        `;

        toast.addEventListener('click', (event) => {
            if (event.target.closest('[data-toast-close]')) {
                event.preventDefault();
                event.stopPropagation();
                dismissToast(toast);
                return;
            }
            dismissToast(toast);
            openChatFromToast(Number(toast.getAttribute('data-conversation-id') || message.conversation_id), message);
        });

        while (toastHost.children.length >= 3) {
            toastHost.firstElementChild?.remove();
        }

        toastHost.appendChild(toast);
    }

    async function catchUpMissedToasts() {
        if (state.open || !toastHost || !root.dataset.unreadMessagesUrl) {
            state.catchUpToastAt = Date.now();
            return;
        }

        try {
            const payload = await api(root.dataset.unreadMessagesUrl);
            if (state.open) {
                state.catchUpToastAt = Date.now();
                return;
            }

            // API returns newest-first; keep up to 3 never-toasted, show oldest→newest.
            const missed = (payload.data || [])
                .filter((message) => message?.id
                    && !sameId(message.user_id, authUserId)
                    && !state.toastedMessageIds.has(String(message.id)))
                .slice(0, 3)
                .reverse();

            missed.forEach((message) => {
                showIncomingToast(message);
            });
        } catch (e) {
            // ignore catch-up transport errors
        }

        state.catchUpToastAt = Date.now();
    }

    async function openChatFromToast(conversationId, message = null) {
        clearIncomingToasts();

        if (!state.open) {
            state.open = true;
            panel.classList.remove('d-none');
            fab.classList.add('is-open');
            fab.setAttribute('aria-expanded', 'true');
            releaseBootstrapFocusTraps();
        }

        await loadConversations();

        let exists = state.conversations.some((item) => sameId(item.id, conversationId));
        if (!exists) {
            await new Promise((resolve) => window.setTimeout(resolve, 250));
            await loadConversations();
            exists = state.conversations.some((item) => sameId(item.id, conversationId));
        }

        if (!exists && message) {
            const sender = resolveIncomingSender(message);
            state.conversations.unshift({
                id: Number(conversationId),
                type: 'direct',
                peer: normalizePeer(sender),
                latest_message: message,
                unread_count: 1,
                updated_at: message.created_at || null,
            });
            exists = true;
        }

        if (exists) {
            await openConversation(conversationId);
            return;
        }

        showView('list');
    }

    async function postTyping(typing) {
        const peerId = state.activePeer?.id || state.draftPeer?.id;
        if (!peerId || !root.dataset.typingUrl) {
            return;
        }

        try {
            await api(root.dataset.typingUrl, {
                method: 'POST',
                body: {
                    user_id: Number(peerId),
                    typing: !!typing,
                    conversation_id: state.activeConversationId || null,
                },
            });
        } catch (e) {
            // ignore typing transport errors
        }
    }

    function stopOutgoingTyping() {
        const wasActive = state.outgoingTypingActive;
        state.outgoingTypingActive = false;
        if (state.typingHeartbeat) {
            clearInterval(state.typingHeartbeat);
            state.typingHeartbeat = null;
        }
        if (wasActive) {
            postTyping(false);
        }
    }

    function syncOutgoingTyping() {
        const hasText = (input.value || '').trim().length > 0;
        const focused = document.activeElement === input;
        const shouldType = state.open && state.view === 'thread' && focused && hasText && !!(state.activePeer?.id || state.draftPeer?.id);

        if (!shouldType) {
            stopOutgoingTyping();
            return;
        }

        state.outgoingTypingActive = true;
        postTyping(true);

        if (!state.typingHeartbeat) {
            state.typingHeartbeat = setInterval(() => {
                const stillFocused = document.activeElement === input;
                const stillHasText = (input.value || '').trim().length > 0;
                if (!(state.open && state.view === 'thread' && stillFocused && stillHasText && state.outgoingTypingActive)) {
                    stopOutgoingTyping();
                    return;
                }
                postTyping(true);
            }, 1500);
        }
    }

    function clearAttachment() {
        state.pendingAttachment = null;
        attachInput.value = '';
        attachPreview.classList.add('d-none');
        attachName.textContent = '';
    }

    function fileExtension(file) {
        const name = String(file?.name || '');
        const idx = name.lastIndexOf('.');
        if (idx < 0) {
            return '';
        }
        return name.slice(idx + 1).toLowerCase();
    }

    function isAllowedAttachment(file) {
        if (!file) {
            return false;
        }
        if (file.size > MAX_ATTACHMENT_BYTES) {
            return false;
        }
        if (String(file.type || '').startsWith('image/')) {
            return true;
        }
        return ALLOWED_EXTENSIONS.has(fileExtension(file));
    }

    function setPendingAttachment(file) {
        if (!file) {
            clearAttachment();
            return false;
        }
        if (file.size > MAX_ATTACHMENT_BYTES) {
            toastError('Attachments may not be greater than 10MB.');
            return false;
        }
        if (!isAllowedAttachment(file)) {
            toastError('This file type is not allowed.');
            return false;
        }
        state.pendingAttachment = file;
        attachName.textContent = file.name;
        attachPreview.classList.remove('d-none');
        return true;
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    }

    function showDropzone() {
        if (!dropzone) {
            return;
        }
        dropzone.classList.add('is-active');
        dropzone.setAttribute('aria-hidden', 'false');
    }

    function hideDropzone() {
        state.dragDepth = 0;
        if (!dropzone) {
            return;
        }
        dropzone.classList.remove('is-active');
        dropzone.setAttribute('aria-hidden', 'true');
    }

    function canAcceptDrop() {
        return state.open && state.view === 'thread' && !!(state.activeConversationId || state.draftPeer?.id);
    }

    function hasFileDrag(event) {
        const types = event.dataTransfer?.types;
        if (!types) {
            return false;
        }
        return Array.from(types).includes('Files');
    }

    function setupDragAndDrop() {
        if (!threadView) {
            return;
        }

        threadView.addEventListener('dragenter', (event) => {
            if (!canAcceptDrop() || !hasFileDrag(event)) {
                return;
            }
            event.preventDefault();
            state.dragDepth += 1;
            showDropzone();
        });

        threadView.addEventListener('dragover', (event) => {
            if (!canAcceptDrop() || !hasFileDrag(event)) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
            showDropzone();
        });

        threadView.addEventListener('dragleave', (event) => {
            if (!canAcceptDrop()) {
                return;
            }
            event.preventDefault();
            state.dragDepth = Math.max(0, state.dragDepth - 1);
            if (state.dragDepth === 0) {
                hideDropzone();
            }
        });

        threadView.addEventListener('drop', (event) => {
            if (!canAcceptDrop()) {
                return;
            }
            event.preventDefault();
            hideDropzone();
            const file = event.dataTransfer?.files?.[0] || null;
            if (!file) {
                return;
            }
            if (setPendingAttachment(file)) {
                input.focus();
                syncOutgoingTyping();
            }
        });
    }

    function resolveProfilePeer(trigger) {
        const source = trigger.getAttribute('data-profile-peer');
        if (source === 'conversation') {
            const conversationId = trigger.getAttribute('data-conversation-id');
            const conversation = state.conversations.find((item) => sameId(item.id, conversationId));
            return conversation?.peer || null;
        }
        if (source === 'contact') {
            const peerId = Number(trigger.getAttribute('data-peer-id'));
            return state.contactResults.find((item) => sameId(item.id, peerId)) || null;
        }
        if (source === 'thread') {
            return state.activePeer || state.draftPeer || null;
        }
        return null;
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
                renderConversationList();
                updateThreadHeader();
            })
            .joining((user) => {
                state.onlineUserIds.add(Number(user.id));
                if (state.activePeer && sameId(state.activePeer.id, user.id)) {
                    state.activePeer.is_online = true;
                    updateThreadHeader();
                    if (state.activeConversationId) {
                        updateOutgoingStatus(state.activeConversationId, 'delivered', new Date().toISOString());
                    }
                }
                markDeliveredForPeer(user.id);
                renderConversationList();
            })
            .leaving((user) => {
                state.onlineUserIds.delete(Number(user.id));
                if (state.activePeer && sameId(state.activePeer.id, user.id)) {
                    state.activePeer.is_online = false;
                    updateThreadHeader();
                }
                renderConversationList();
            });
    }

    fab.addEventListener('click', togglePanel);
    messagesEl.addEventListener('scroll', () => {
        state.stickToBottom = isMessagesNearBottom();
    }, { passive: true });
    messagesEl.addEventListener('click', (event) => {
        const retryBtn = event.target.closest('[data-retry-message]');
        if (!retryBtn || !messagesEl.contains(retryBtn)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const tempId = retryBtn.getAttribute('data-retry-message');
        if (tempId) {
            retryOptimisticSend(tempId);
        }
    });
    document.getElementById('chat-close-btn')?.addEventListener('click', closePanel);
    document.getElementById('chat-close-thread-btn')?.addEventListener('click', closePanel);
    document.getElementById('chat-thread-profile-trigger')?.addEventListener('click', () => {
        showUserProfile(state.activePeer || state.draftPeer);
    });

    profileEl?.querySelectorAll('[data-profile-close]').forEach((el) => {
        el.addEventListener('click', hideUserProfile);
    });

    root.querySelectorAll('[data-chat-back]').forEach((btn) => {
        btn.addEventListener('click', () => {
            state.messageLoadToken += 1;
            saveComposerDraft();
            stopOutgoingTyping();
            clearAttachment();
            hideUserProfile();
            hideDropzone();
            leaveConversationChannel();
            state.activeConversationId = null;
            state.draftPeer = null;
            state.activePeer = null;
            clearThreadMessagesUi();
            input.value = '';
            autoGrow();
            showView('list');
            loadConversations();
        });
    });

    searchBackBtn?.addEventListener('click', () => {
        exitSearchMode({ animate: true });
    });

    searchEl?.addEventListener('click', (event) => {
        if (event.target.closest('#chat-search-back')) {
            return;
        }
        enterSearchMode();
    });

    listFilter.addEventListener('focus', () => {
        enterSearchMode();
    });

    listFilter.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && state.searchMode) {
            event.preventDefault();
            exitSearchMode({ animate: true });
            listFilter.blur();
        }
    });

    listFilter.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            if (!state.searchMode) {
                return;
            }
            searchContacts(listFilter.value);
        }, 250);
    });

    conversationList.addEventListener('click', (event) => {
        const profileTrigger = event.target.closest('[data-profile-peer]');
        if (profileTrigger && conversationList.contains(profileTrigger)) {
            event.preventDefault();
            event.stopPropagation();
            showUserProfile(resolveProfilePeer(profileTrigger));
            return;
        }

        const conversationBtn = event.target.closest('[data-open-conversation]');
        if (conversationBtn) {
            openConversation(conversationBtn.getAttribute('data-open-conversation'));
            return;
        }

        const draftBtn = event.target.closest('[data-open-draft]');
        if (draftBtn) {
            const peerId = Number(draftBtn.getAttribute('data-open-draft'));
            const peer = state.contactResults.find((item) => sameId(item.id, peerId));
            if (peer) {
                openDraft(peer);
            }
        }
    });

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('input', () => {
        autoGrow();
        syncOutgoingTyping();
        const key = composerDraftKey();
        if (key) {
            const text = input.value || '';
            if (text.trim()) {
                state.drafts[key] = { text };
            } else {
                delete state.drafts[key];
            }
            if (state.view === 'list') {
                renderConversationList();
            }
        }
    });
    input.addEventListener('focus', () => {
        syncOutgoingTyping();
    });
    input.addEventListener('blur', () => {
        setTimeout(() => {
            if (document.activeElement === input) {
                syncOutgoingTyping();
                return;
            }
            const composer = root.querySelector('.chat-widget__composer');
            if (composer && composer.contains(document.activeElement) && (input.value || '').trim()) {
                return;
            }
            stopOutgoingTyping();
        }, 0);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    attachInput.addEventListener('change', () => {
        const file = attachInput.files?.[0] || null;
        if (file) {
            setPendingAttachment(file);
        } else {
            clearAttachment();
        }
        input.focus();
        syncOutgoingTyping();
    });
    attachClear.addEventListener('click', () => {
        clearAttachment();
        input.focus();
        syncOutgoingTyping();
    });

    emojiBtn.addEventListener('click', () => {
        emojiPicker.classList.toggle('d-none');
        input.focus();
        syncOutgoingTyping();
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
        syncOutgoingTyping();
    });

    setupEmojiPicker();
    setupPresence();
    setupUserChannelFallback();
    setupDragAndDrop();
    loadToastedMessageIds();
    refreshUnread();
    loadConversations().then(() => catchUpMissedToasts());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        if (Date.now() - state.catchUpToastAt < 2500) {
            return;
        }
        state.catchUpToastAt = Date.now();
        loadConversations().then(() => catchUpMissedToasts());
    });
    state.pollTimer = setInterval(() => {
        refreshUnread();
        if (state.open && state.view === 'list') {
            loadConversations();
        }
    }, 15000);
})();
