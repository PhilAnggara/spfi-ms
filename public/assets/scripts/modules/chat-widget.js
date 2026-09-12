(function () {
    const root = document.getElementById('chat-widget');
    if (!root) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const authUserId = Number(root.dataset.authUserId || document.querySelector('meta[name="auth-user-id"]')?.content || 0);
    const authUserName = String(root.dataset.authUserName || '').trim();
    const canOperateSupport = root.dataset.canSupportOperate === '1';
    const systemAvatarUrl = String(root.dataset.systemAvatarUrl || '/assets/images/system_profile.png');
    const systemDisplayName = String(root.dataset.systemDisplayName || 'SPFI-MS');

    const fab = document.getElementById('chat-widget-fab');
    const panel = document.getElementById('chat-widget-panel');
    const badge = document.getElementById('chat-widget-badge');
    const conversationList = document.getElementById('chat-conversation-list');
    const listFilter = document.getElementById('chat-list-filter');
    const listTitle = document.getElementById('chat-list-title');
    const listSubtitle = document.getElementById('chat-list-subtitle');
    const listTabs = document.getElementById('chat-list-tabs');
    const broadcastOpenBtn = document.getElementById('chat-broadcast-open');
    const broadcastBackBtn = document.getElementById('chat-broadcast-back');
    const broadcastCloseBtn = document.getElementById('chat-close-broadcast-btn');
    const broadcastAudience = document.getElementById('chat-broadcast-audience');
    const broadcastAudienceGroup = document.getElementById('chat-broadcast-audience-group');
    const broadcastTargetsDepartments = document.getElementById('chat-broadcast-targets-departments');
    const broadcastTargetsUsers = document.getElementById('chat-broadcast-targets-users');
    const broadcastTargetsAll = document.getElementById('chat-broadcast-targets-all');
    const broadcastDepartmentsSelect = document.getElementById('chat-broadcast-departments');
    const broadcastUsersSelect = document.getElementById('chat-broadcast-users');
    const broadcastBody = document.getElementById('chat-broadcast-body');
    const broadcastSendBtn = document.getElementById('chat-broadcast-send');
    const broadcastHint = document.getElementById('chat-broadcast-hint');
    const broadcastAttachInput = document.getElementById('chat-broadcast-attachment');
    const broadcastAttachPreview = document.getElementById('chat-broadcast-attach-preview');
    const broadcastAttachName = document.getElementById('chat-broadcast-attach-name');
    const broadcastAttachThumb = document.getElementById('chat-broadcast-attach-thumb');
    const broadcastAttachClear = document.getElementById('chat-broadcast-attach-clear');
    let broadcastAttachObjectUrl = null;
    let broadcastDeptChoices = null;
    let broadcastUserChoices = null;
    let choicesLoading = null;
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
    const attachThumb = document.getElementById('chat-attach-thumb');
    const attachClear = document.getElementById('chat-attach-clear');
    let attachPreviewObjectUrl = null;
    const emojiBtn = document.getElementById('chat-emoji-btn');
    const emojiPicker = document.getElementById('chat-emoji-picker');
    const threadName = document.getElementById('chat-thread-name');
    const threadStatus = document.getElementById('chat-thread-status');
    const threadAvatar = document.getElementById('chat-thread-avatar');
    const threadOfficialBadge = document.getElementById('chat-thread-official-badge');
    const operatorHint = document.getElementById('chat-operator-hint');
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
    const profileFields = document.getElementById('chat-profile-fields');
    const profileOfficial = document.getElementById('chat-profile-official');
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
        listTab: 'personal',
        switchingTab: false,
        conversations: [],
        supportConversations: [],
        broadcastDepartments: [],
        broadcastUsers: [],
        broadcastAttachment: null,
        broadcastSending: false,
        contactResults: [],
        messages: [],
        drafts: {},
        activeConversationId: null,
        activeConversationType: null,
        activeSupportUserId: null,
        draftPeer: null,
        activePeer: null,
        onlineUserIds: new Set(),
        subscribedConversations: new Set(),
        presenceChannel: null,
        conversationChannel: null,
        userChannelBound: false,
        supportChannelBound: false,
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
        unreadSeparatorBeforeId: null,
        unreadSeparatorCount: 0,
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

    function stripWhatsAppMarkup(value) {
        return String(value ?? '')
            .replace(/\*(?!\s)([^*]+?)(?<!\s)\*/g, '$1')
            .replace(/_(?!\s)([^_]+?)(?<!\s)_/g, '$1')
            .replace(/~(?!\s)([^~]+?)(?<!\s)~/g, '$1');
    }

    function formatWhatsAppMarkup(escapedText) {
        return String(escapedText ?? '')
            .replace(/\*(?!\s)([^*]+?)(?<!\s)\*/g, '<strong>$1</strong>')
            .replace(/_(?!\s)([^_]+?)(?<!\s)_/g, '<em>$1</em>')
            .replace(/~(?!\s)([^~]+?)(?<!\s)~/g, '<del>$1</del>');
    }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '?';
        }
        return ((parts[0][0] || '') + (parts[1]?.[0] || '')).toUpperCase();
    }

    function avatarColorForRole(role) {
        switch (String(role || '').trim()) {
            case 'General Manager':
                return '#c2410c';
            case 'Manager':
                return '#4338ca';
            case 'Supervisor':
                return '#0e7490';
            case 'Programmer':
                return '#0284c7';
            default:
                return '#475569';
        }
    }

    function avatarStyleAttr(peer) {
        if (peer?.avatar_url || peer?.is_official) {
            return '';
        }
        return ` style="background:${avatarColorForRole(peer?.role)}"`;
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

    function isSystemTab() {
        return canOperateSupport && state.listTab === 'system';
    }

    function activeConversationList() {
        return isSystemTab() ? state.supportConversations : state.conversations;
    }

    function findConversation(conversationId) {
        return state.conversations.find((item) => sameId(item.id, conversationId))
            || state.supportConversations.find((item) => sameId(item.id, conversationId))
            || null;
    }

    function isActingAsSystemInThread() {
        return canOperateSupport
            && isSystemTab()
            && state.activeConversationType === 'support';
    }

    function isMineMessage(message, { forceSystemInbox = null } = {}) {
        if (!message) {
            return false;
        }
        const systemInbox = forceSystemInbox ?? (isSystemTab() && canOperateSupport);
        if (systemInbox) {
            return message.persona === 'system';
        }
        if (message.persona === 'system') {
            return false;
        }
        return sameId(message.user_id, authUserId);
    }

    function operatorModeQuery(url) {
        if (!isActingAsSystemInThread()) {
            return url;
        }
        const next = new URL(url, window.location.origin);
        next.searchParams.set('as_operator', '1');
        return next.toString();
    }

    function officialBadgeHtml(peer) {
        if (!peer?.is_official) {
            return '';
        }
        return '<span class="chat-widget__official-badge" title="Official SPFI account" aria-label="Official SPFI account"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>';
    }

    function peerAvatarHtml(peer, extraClass = '') {
        const classes = ['chat-widget__avatar', extraClass].filter(Boolean).join(' ');
        const online = isPeerOnline(peer) ? ' is-online' : '';
        const avatarUrl = peer?.avatar_url || (peer?.is_official ? systemAvatarUrl : null);
        if (avatarUrl) {
            return `<div class="${classes}${online} has-image"><img src="${escapeHtml(avatarUrl)}" alt="" decoding="async"></div>`;
        }
        return `<div class="${classes}${online}"${avatarStyleAttr(peer)}>${escapeHtml(initials(peer?.name))}</div>`;
    }

    function setPeerAvatarElement(el, peer) {
        if (!el) {
            return;
        }
        const online = isPeerOnline(peer);
        el.classList.toggle('is-online', online);
        const avatarUrl = peer?.avatar_url || (peer?.is_official ? systemAvatarUrl : null);
        if (avatarUrl) {
            el.classList.add('has-image');
            el.removeAttribute('style');
            el.innerHTML = `<img src="${escapeHtml(avatarUrl)}" alt="" decoding="async">`;
        } else {
            el.classList.remove('has-image');
            el.style.background = avatarColorForRole(peer?.role);
            el.textContent = initials(peer?.name);
        }
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
        const personal = state.conversations.reduce(
            (sum, item) => sum + Math.max(0, Number(item.unread_count) || 0),
            0,
        );
        const support = canOperateSupport
            ? state.supportConversations.reduce(
                (sum, item) => sum + Math.max(0, Number(item.unread_count) || 0),
                0,
            )
            : 0;
        updateBadge(personal + support);
        syncTabBadges(personal, support);
    }

    function syncTabBadges(personalCount = null, supportCount = null) {
        if (!canOperateSupport) {
            return;
        }
        const personal = personalCount == null
            ? state.conversations.reduce((sum, item) => sum + Math.max(0, Number(item.unread_count) || 0), 0)
            : personalCount;
        const support = supportCount == null
            ? state.supportConversations.reduce((sum, item) => sum + Math.max(0, Number(item.unread_count) || 0), 0)
            : supportCount;

        const personalBadge = document.getElementById('chat-tab-badge-personal');
        const systemBadge = document.getElementById('chat-tab-badge-system');
        updateTabBadge(personalBadge, personal);
        updateTabBadge(systemBadge, support);
    }

    function updateTabBadge(el, count) {
        if (!el) {
            return;
        }
        const safeCount = Math.max(0, Number(count) || 0);
        if (safeCount > 0) {
            el.classList.remove('d-none');
            el.textContent = safeCount > 99 ? '99+' : String(safeCount);
            el.setAttribute('aria-hidden', 'false');
        } else {
            el.classList.add('d-none');
            el.textContent = '0';
            el.setAttribute('aria-hidden', 'true');
        }
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
        if (view !== 'thread') {
            clearUnreadSeparator();
        }
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
        syncBroadcastOpenVisibility();
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
        return stripWhatsAppMarkup(message.body || '');
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

        const isMine = isMineMessage(message);
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
        const forceSystemInbox = isSystemTab() && canOperateSupport;
        if (!message || !isMineMessage(message, { forceSystemInbox })) {
            return '';
        }
        return `<span class="chat-widget__list-ticks">${ticksHtml(message, true)}</span>`;
    }

    function normalizePeer(peer) {
        if (!peer) {
            return null;
        }
        return {
            id: peer.id == null ? null : Number(peer.id),
            name: peer.name || '',
            username: peer.username || '',
            email: peer.email || null,
            role: peer.role || null,
            department: peer.department || null,
            is_online: !!peer.is_online,
            last_seen_at: peer.last_seen_at || null,
            is_official: !!peer.is_official,
            avatar_url: peer.avatar_url || (peer.is_official ? systemAvatarUrl : null),
        };
    }

    function showUserProfile(peer) {
        const data = normalizePeer(peer);
        if (!data || !profileEl) {
            return;
        }
        const online = isPeerOnline(data);
        setPeerAvatarElement(profileAvatar, data);
        profileName.textContent = data.name || 'User';
        if (profileUsername) {
            if (data.is_official) {
                profileUsername.textContent = '';
                profileUsername.classList.add('d-none');
            } else {
                profileUsername.textContent = data.username ? `@${data.username}` : '—';
                profileUsername.classList.remove('d-none');
            }
        }

        if (profileStatus) {
            profileStatus.classList.remove('is-online', 'is-typing', 'is-official');
            if (data.is_official) {
                profileStatus.textContent = 'Verified';
                profileStatus.classList.add('is-official');
            } else if (online) {
                profileStatus.textContent = 'Online';
                profileStatus.classList.add('is-online');
            } else if (data.last_seen_at) {
                profileStatus.textContent = `Last seen ${formatTime(data.last_seen_at)}`;
            } else {
                profileStatus.textContent = 'Offline';
            }
        }

        profileOfficial?.classList.toggle('d-none', !data.is_official);
        profileFields?.classList.toggle('d-none', !!data.is_official);
        profileEl.classList.toggle('is-official-profile', !!data.is_official);

        if (!data.is_official) {
            profileEmail.textContent = data.email || '—';
            profileDepartment.textContent = data.department || '—';
            profileRole.textContent = data.role || '—';
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
        const excludeIds = new Set();
        if (isSystemTab()) {
            state.supportConversations.forEach((item) => {
                if (item.peer?.id != null) {
                    excludeIds.add(Number(item.peer.id));
                }
            });
        }

        return [...state.contactResults]
            .filter((user) => !excludeIds.has(Number(user.id)))
            .sort((a, b) => {
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

    function waitMs(ms) {
        return new Promise((resolve) => {
            window.setTimeout(resolve, ms);
        });
    }

    function animateListPage(direction) {
        const target = listShell || conversationList;
        target.classList.remove('is-page-enter', 'is-page-enter-back', 'is-mode-leave', 'is-mode-leave-back');
        void target.offsetWidth;
        target.classList.add(direction === 'back' ? 'is-page-enter-back' : 'is-page-enter');
        if (state.listPageTimer) {
            clearTimeout(state.listPageTimer);
        }
        state.listPageTimer = setTimeout(() => {
            target.classList.remove('is-page-enter', 'is-page-enter-back');
            state.listPageTimer = null;
        }, 280);
    }

    async function animateModeSwitch(direction, swapContent) {
        const target = listShell || conversationList;
        if (state.listPageTimer) {
            clearTimeout(state.listPageTimer);
            state.listPageTimer = null;
        }
        target.classList.remove('is-page-enter', 'is-page-enter-back', 'is-mode-leave', 'is-mode-leave-back');
        void target.offsetWidth;
        target.classList.add(direction === 'back' ? 'is-mode-leave-back' : 'is-mode-leave');
        await waitMs(150);
        await swapContent();
        target.classList.remove('is-mode-leave', 'is-mode-leave-back');
        void target.offsetWidth;
        target.classList.add(direction === 'back' ? 'is-page-enter-back' : 'is-page-enter');
        state.listPageTimer = setTimeout(() => {
            target.classList.remove('is-page-enter', 'is-page-enter-back');
            state.listPageTimer = null;
        }, 280);
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
        state.listRenderKey = '';
        syncBroadcastOpenVisibility();

        if (isSystemTab()) {
            state.contactsLoading = true;
            state.animateContactRows = true;
            renderConversationList();
            animateListPage('forward');
            searchContacts(listFilter.value);
            listFilter.focus();
            return;
        }

        state.contactsLoading = true;
        state.animateContactRows = true;
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
            syncBroadcastOpenVisibility();
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
        syncBroadcastOpenVisibility();
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
        const sourceList = activeConversationList();
        const chatItems = state.searchMode
            ? sourceList.filter((item) => {
                if (!term) {
                    return true;
                }
                const name = (item.peer?.name || '').toLowerCase();
                const username = (item.peer?.username || '').toLowerCase();
                return name.includes(term) || username.includes(term);
            })
            : sourceList;

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
                : (isSystemTab()
                    ? `<div class="chat-widget__empty">No ${escapeHtml(systemDisplayName)} chats yet.</div>`
                    : '<div class="chat-widget__empty">No conversations yet. Search a contact to start.</div>');
            state.animateContactRows = false;
            return;
        }

        if (chatItems.length) {
            if (state.searchMode) {
                html += `<div class="chat-widget__section-label">${isSystemTab() ? escapeHtml(systemDisplayName) : 'Chats'}</div>`;
            }
            html += chatItems.map((item) => {
                const peer = item.peer || {};
                const unread = Number(item.unread_count || 0);
                const typing = isTypingForKey(typingKeyForConversation(item.id));
                const hasDraft = !!draftPreviewForKey(typingKeyForConversation(item.id));
                const previewClass = typing ? 'is-typing' : (hasDraft ? 'is-draft' : '');
                return `
                    <div class="chat-widget__item ${sameId(item.id, state.activeConversationId) ? 'is-active' : ''} ${peer.is_official ? 'is-official' : ''}" data-conversation-id="${item.id}">
                        <button type="button" class="chat-widget__profile-trigger" data-profile-peer="conversation" data-conversation-id="${item.id}" title="View profile" aria-label="View profile">
                            ${peerAvatarHtml(peer)}
                        </button>
                        <button type="button" class="chat-widget__item-open" data-open-conversation="${item.id}">
                            <div class="chat-widget__item-body">
                                <div class="chat-widget__item-top">
                                    <span class="chat-widget__item-name">${escapeHtml(peer.name || 'User')}${officialBadgeHtml(peer)}</span>
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
                let previewHtml = escapeHtml(
                    isSystemTab()
                        ? `@${user.username || ''} · Open ${systemDisplayName} chat`
                        : `@${user.username || ''} · Start chat`,
                );
                if (typing) {
                    previewHtml = escapeHtml('typing...');
                } else if (hasDraft && !isSystemTab()) {
                    previewHtml = draftPreviewHtml(draftKey);
                } else if (online) {
                    previewHtml = escapeHtml('Online');
                }
                const stagger = Math.min(index, 5);
                const enterClass = animateRows ? ' is-row-enter' : '';
                return `
                    <div class="chat-widget__item chat-widget__item--contact${enterClass}" data-draft-peer-id="${user.id}" style="--chat-stagger:${stagger}">
                        <button type="button" class="chat-widget__profile-trigger" data-profile-peer="contact" data-peer-id="${user.id}" title="View profile" aria-label="View profile">
                            ${peerAvatarHtml(user)}
                        </button>
                        <button type="button" class="chat-widget__item-open" data-open-draft="${user.id}">
                            <div class="chat-widget__item-body">
                                <div class="chat-widget__item-top">
                                    <span class="chat-widget__item-name">${escapeHtml(user.name)}</span>
                                </div>
                                <div class="chat-widget__item-bottom">
                                    <span class="chat-widget__item-preview ${previewClass} ${online && !typing && !(hasDraft && !isSystemTab()) ? 'is-online-label' : ''}">
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
            html += `<div class="chat-bubble__text">${formatWhatsAppMarkup(escapeHtml(message.body))}</div>`;
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
        const isMine = isMineMessage(message);
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

    function unreadSeparatorMarkup(count) {
        const safeCount = Math.max(1, Number(count) || 1);
        const label = safeCount === 1 ? '1 unread message' : `${safeCount} unread messages`;
        return `<div class="chat-widget__unread-sep" role="separator">${escapeHtml(label)}</div>`;
    }

    function clearUnreadSeparator() {
        state.unreadSeparatorBeforeId = null;
        state.unreadSeparatorCount = 0;
        messagesEl?.querySelectorAll('.chat-widget__unread-sep').forEach((el) => el.remove());
    }

    function snapshotUnreadSeparator(messages, lastReadAt = null) {
        const unread = (messages || []).filter((message) => {
            if (!message || isMineMessage(message) || isTempMessageId(message.id)) {
                return false;
            }
            if (!message.created_at) {
                return false;
            }
            if (lastReadAt) {
                return new Date(message.created_at) > new Date(lastReadAt);
            }
            return true;
        });

        if (!unread.length) {
            clearUnreadSeparator();
            return;
        }

        state.unreadSeparatorBeforeId = messageDomId(unread[0]);
        state.unreadSeparatorCount = unread.length;
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
            if (
                state.unreadSeparatorBeforeId
                && String(messageDomId(message)) === String(state.unreadSeparatorBeforeId)
            ) {
                chunks.push(unreadSeparatorMarkup(state.unreadSeparatorCount));
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
        setPeerAvatarElement(threadAvatar, peer);
        if (threadOfficialBadge) {
            threadOfficialBadge.classList.toggle('d-none', !peer.is_official);
        }
        threadStatus.textContent = peer.is_official && !isTypingForKey(currentTypingKey())
            ? 'Official SPFI account'
            : peerStatusLabel(peer);

        if (operatorHint) {
            if (isActingAsSystemInThread()) {
                operatorHint.textContent = `Membalas sebagai ${systemDisplayName}`;
                operatorHint.classList.remove('d-none');
            } else {
                operatorHint.textContent = '';
                operatorHint.classList.add('d-none');
            }
        }
    }

    function syncBroadcastOpenVisibility() {
        if (!broadcastOpenBtn) {
            return;
        }
        const show = isSystemTab() && state.view === 'list' && !state.searchMode;
        broadcastOpenBtn.classList.toggle('d-none', !show);
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
            const url = isSystemTab()
                ? root.dataset.supportConversationsUrl
                : root.dataset.conversationsUrl;
            const payload = await api(url);
            if (isSystemTab()) {
                state.supportConversations = payload.data || [];
                state.supportConversations.forEach((item) => subscribeConversation(item.id));
            } else {
                state.conversations = payload.data || [];
                state.conversations.forEach((item) => subscribeConversation(item.id));
            }
            updateBadge(Number(payload.unread_count || 0));
            state.listRenderKey = '';
            renderConversationList();
        } catch (e) {
            conversationList.innerHTML = `<div class="chat-widget__empty">${escapeHtml(e.message)}</div>`;
        }
    }

    async function switchListTab(tab) {
        if (!canOperateSupport || (tab !== 'personal' && tab !== 'system')) {
            return;
        }
        if (state.listTab === tab || state.switchingTab) {
            return;
        }

        const leavingSystem = state.listTab === 'system';
        const direction = tab === 'system' ? 'forward' : 'back';
        state.switchingTab = true;
        state.listTab = tab;

        listTabs?.querySelectorAll('[data-chat-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-chat-tab') === tab;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        listView?.classList.toggle('is-system-mode', tab === 'system');
        root.classList.toggle('is-system-mode', tab === 'system');

        if (listTitle) {
            listTitle.textContent = tab === 'system' ? systemDisplayName : 'Messages';
        }
        if (listSubtitle) {
            listSubtitle.textContent = tab === 'system'
                ? `Reply as ${systemDisplayName}`
                : 'Search or start a chat';
        }
        if (listFilter) {
            listFilter.placeholder = tab === 'system'
                ? `Search ${systemDisplayName} chats...`
                : 'Search or start chat...';
            listFilter.value = '';
        }
        if (state.searchMode && (tab === 'system' || leavingSystem)) {
            exitSearchMode({ animate: false });
        }

        syncBroadcastOpenVisibility();

        try {
            await animateModeSwitch(direction, async () => {
                state.listRenderKey = '';
                conversationList.innerHTML = `
                    <div class="chat-widget__empty" aria-live="polite">
                        <div class="chat-widget__messages-loading-spinner" aria-hidden="true"></div>
                    </div>
                `;
                await loadConversations();
            });
        } finally {
            state.switchingTab = false;
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
            if (isSystemTab()) {
                url.searchParams.set('for_support_search', '1');
            }
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

    function setupSupportChannel() {
        if (!canOperateSupport || !window.Echo || state.supportChannelBound) {
            return;
        }
        state.supportChannelBound = true;
        window.Echo.private('chat.support')
            .listen('.chat.message.sent', (event) => {
                handleIncomingMessage(normalizeMessagePayload(event));
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
            if (!isMineMessage(message)) {
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

        const patchLatest = (item, forceSystemInbox) => {
            if (!item?.latest_message || !isMineMessage(item.latest_message, { forceSystemInbox })) {
                return false;
            }
            item.latest_message.status = preferStatus(item.latest_message.status, status);
            if ((status === 'delivered' || status === 'read') && at && !item.latest_message.delivered_at) {
                item.latest_message.delivered_at = at;
            }
            if (status === 'read' && at) {
                item.latest_message.read_at = at;
            }
            return true;
        };

        const personalItem = state.conversations.find((item) => sameId(item.id, conversationId));
        const supportItem = state.supportConversations.find((item) => sameId(item.id, conversationId));
        const personalChanged = patchLatest(personalItem, false);
        const supportChanged = patchLatest(supportItem, true);
        if (personalChanged || supportChanged) {
            renderConversationList();
        }

        if (changed && isActiveThread(conversationId)) {
            patchMessageMetas(orderedMessages());
        }
    }

    async function markDelivered(conversationId) {
        try {
            await api(operatorModeQuery(urlTemplate(root.dataset.deliveredUrlTemplate, conversationId)), { method: 'POST', body: {} });
        } catch (e) {
            // ignore
        }
    }

    async function markRead(conversationId) {
        try {
            await api(operatorModeQuery(urlTemplate(root.dataset.readUrlTemplate, conversationId)), { method: 'POST', body: {} });
            const item = findConversation(conversationId);
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
        clearUnreadSeparator();
        messagesEl.innerHTML = '';
    }

    async function openConversation(conversationId) {
        const loadToken = ++state.messageLoadToken;
        saveComposerDraft();
        stopOutgoingTyping();
        clearAttachment();
        hideUserProfile();
        const conversation = findConversation(conversationId);
        state.activeConversationId = Number(conversationId);
        state.activeConversationType = conversation?.type || null;
        state.activeSupportUserId = conversation?.support_user_id ?? null;
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
        snapshotUnreadSeparator(state.messages, conversation?.viewer_last_read_at || null);
        if (state.unreadSeparatorBeforeId) {
            renderMessages({ rebuild: true, forceScroll: true });
        }
        await markDelivered(conversationId);
        await markRead(conversationId);
        if (conversation) {
            conversation.viewer_last_read_at = new Date().toISOString();
            conversation.unread_count = 0;
        }
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
        state.activeConversationType = null;
        state.activeSupportUserId = null;
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

    async function openSupportForUser(peer) {
        if (!canOperateSupport || !peer?.id) {
            return;
        }

        const existing = state.supportConversations.find((item) => sameId(item.peer?.id, peer.id));
        if (existing) {
            await openConversation(existing.id);
            return;
        }

        try {
            const payload = await api(root.dataset.supportConversationsStoreUrl, {
                method: 'POST',
                body: { user_id: peer.id },
            });
            const conversation = payload.data;
            if (!conversation?.id) {
                throw new Error('Unable to open support chat.');
            }
            const index = state.supportConversations.findIndex((item) => sameId(item.id, conversation.id));
            if (index >= 0) {
                state.supportConversations[index] = conversation;
            } else if (conversation.latest_message) {
                state.supportConversations.unshift(conversation);
            } else {
                state.supportConversations.unshift(conversation);
            }
            state.listRenderKey = '';
            renderConversationList();
            await openConversation(conversation.id);
        } catch (e) {
            toastError(e.message);
        }
    }

    async function loadMessages(conversationId, { merge = false, loadToken = null, forceScroll = false } = {}) {
        if (!conversationId) {
            return;
        }
        state.loadingMessages = true;
        try {
            const payload = await api(operatorModeQuery(urlTemplate(root.dataset.messagesUrlTemplate, conversationId)));
            if (loadToken !== null && loadToken !== state.messageLoadToken) {
                return;
            }
            if (!sameId(state.activeConversationId, conversationId)) {
                return;
            }
            if (payload.conversation) {
                state.activeConversationType = payload.conversation.type || state.activeConversationType;
                state.activeSupportUserId = payload.conversation.support_user_id ?? state.activeSupportUserId;
                if (payload.conversation.peer) {
                    state.activePeer = normalizePeer(payload.conversation.peer);
                    updateThreadHeader();
                }
                const existing = findConversation(conversationId);
                if (existing) {
                    Object.assign(existing, payload.conversation);
                } else if (payload.conversation.type === 'support' && isSystemTab()) {
                    state.supportConversations.unshift(payload.conversation);
                } else if (payload.conversation.type === 'support') {
                    state.conversations.unshift(payload.conversation);
                }
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
        const bump = (list, asOperatorInbox = false) => {
            const index = list.findIndex((item) => sameId(item.id, message.conversation_id));
            if (index < 0) {
                return false;
            }
            const item = list[index];
            item.latest_message = message;
            item.updated_at = message.created_at;

            let isIncoming = !sameId(message.user_id, authUserId);
            if (asOperatorInbox) {
                isIncoming = message.persona === 'user'
                    || (message.persona == null && !sameId(message.user_id, authUserId));
            } else if (item.type === 'support') {
                isIncoming = message.persona === 'system'
                    || (message.persona == null && !sameId(message.user_id, authUserId));
            }

            if (isIncoming && !isActiveThread(message.conversation_id)) {
                item.unread_count = Number(item.unread_count || 0) + 1;
            } else if (isActiveThread(message.conversation_id)) {
                item.unread_count = 0;
            }

            list.splice(index, 1);
            list.unshift(item);
            return true;
        };

        const updatedPersonal = bump(state.conversations, false);
        const updatedSupport = canOperateSupport ? bump(state.supportConversations, true) : false;
        if (updatedPersonal || updatedSupport) {
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
            persona: isActingAsSystemInThread() ? 'system' : 'user',
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
        if (isActingAsSystemInThread()) {
            formData.append('as_system', '1');
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
            const reconciled = state.messages.find((item) => sameId(item.id, result.message.id)) || result.message;
            // Keep list ticks in sync with the reconciled bubble (at least delivered for SPFI-MS).
            if (
                reconciled
                && isMineMessage(reconciled, { forceSystemInbox: false })
                && (reconciled.status === 'sent' || !reconciled.status)
                && (result.message?.status === 'delivered' || result.message?.status === 'read' || isPeerOnline(state.activePeer))
            ) {
                reconciled.status = preferStatus(reconciled.status || 'sent', result.message.status || 'delivered');
            }
            upsertConversationPreview(reconciled);
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
        clearUnreadSeparator();

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
            if (isMineMessage(message) && hasOpenOptimisticMessages()) {
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
        return stripWhatsAppMarkup(message.body || '') || 'New message';
    }

    function resolveIncomingSender(message) {
        if (message?.persona === 'system') {
            return {
                id: null,
                name: systemDisplayName,
                username: '',
                is_official: true,
                avatar_url: systemAvatarUrl,
            };
        }

        const conversation = findConversation(message?.conversation_id);
        if (conversation?.peer) {
            return normalizePeer(conversation.peer);
        }
        if (message?.user) {
            return normalizePeer({
                ...message.user,
                role: message.user.role || null,
            });
        }
        return {
            id: message?.user_id ?? null,
            name: 'New message',
            username: '',
            role: null,
        };
    }

    function toastChannelMeta(message) {
        // Only operators get a distinct toast for SPFI-MS inbox traffic.
        // End users keep a normal toast — avatar/name already identify the sender.
        if (!canOperateSupport) {
            return {
                kind: 'personal',
                label: 'Personal',
            };
        }

        const inSupportInbox = state.supportConversations.some(
            (item) => sameId(item.id, message?.conversation_id),
        );
        if (inSupportInbox && message?.persona !== 'system') {
            return {
                kind: 'support',
                label: `${systemDisplayName} inbox`,
            };
        }

        return {
            kind: 'personal',
            label: 'Personal',
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
        const channel = toastChannelMeta(message);
        const toast = document.createElement('button');
        toast.type = 'button';
        toast.className = channel.kind === 'support'
            ? 'chat-widget__toast chat-widget__toast--support'
            : 'chat-widget__toast';
        toast.setAttribute('data-message-id', messageId);
        toast.setAttribute('data-conversation-id', String(message.conversation_id));
        toast.setAttribute('data-toast-channel', channel.kind);
        toast.innerHTML = `
            ${channel.kind === 'support' ? `<span class="chat-widget__toast-channel">${escapeHtml(channel.label)}</span>` : ''}
            <div class="chat-widget__toast-main">
                ${peerAvatarHtml(sender)}
                <div class="chat-widget__toast-body">
                    <div class="chat-widget__toast-top">
                        <p class="chat-widget__toast-name">${escapeHtml(sender.name || 'New message')}${officialBadgeHtml(sender)}</p>
                        <span class="chat-widget__toast-time">${escapeHtml(formatTime(message.created_at))}</span>
                    </div>
                    <p class="chat-widget__toast-preview">${escapeHtml(messageToastPreview(message))}</p>
                </div>
                <span class="chat-widget__toast-close" data-toast-close aria-label="Dismiss" role="button">
                    <i class="fa-solid fa-xmark"></i>
                </span>
            </div>
        `;

        toast.addEventListener('click', (event) => {
            if (event.target.closest('[data-toast-close]')) {
                event.preventDefault();
                event.stopPropagation();
                dismissToast(toast);
                return;
            }
            dismissToast(toast);
            openChatFromToast(
                Number(toast.getAttribute('data-conversation-id') || message.conversation_id),
                message,
                toast.getAttribute('data-toast-channel'),
            );
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

    async function openChatFromToast(conversationId, message = null, channelKind = null) {
        clearIncomingToasts();

        if (!state.open) {
            state.open = true;
            panel.classList.remove('d-none');
            fab.classList.add('is-open');
            fab.setAttribute('aria-expanded', 'true');
            releaseBootstrapFocusTraps();
        }

        const preferSystem = channelKind === 'support'
            || (canOperateSupport && message?.persona === 'user' && state.supportConversations.some((item) => sameId(item.id, conversationId)));

        if (preferSystem && state.listTab !== 'system') {
            await switchListTab('system');
        } else {
            await loadConversations();
        }

        let exists = findConversation(conversationId);
        if (!exists) {
            await new Promise((resolve) => window.setTimeout(resolve, 250));
            await loadConversations();
            exists = findConversation(conversationId);
        }

        if (!exists && message) {
            const sender = resolveIncomingSender(message);
            const bucket = preferSystem ? state.supportConversations : state.conversations;
            bucket.unshift({
                id: Number(conversationId),
                type: preferSystem ? 'support' : 'direct',
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

    function revokeAttachPreviewUrl() {
        if (attachPreviewObjectUrl) {
            URL.revokeObjectURL(attachPreviewObjectUrl);
            attachPreviewObjectUrl = null;
        }
    }

    function clearBroadcastAttachment() {
        state.broadcastAttachment = null;
        if (broadcastAttachInput) {
            broadcastAttachInput.value = '';
        }
        if (broadcastAttachObjectUrl) {
            URL.revokeObjectURL(broadcastAttachObjectUrl);
            broadcastAttachObjectUrl = null;
        }
        if (broadcastAttachThumb) {
            broadcastAttachThumb.removeAttribute('src');
            broadcastAttachThumb.classList.add('d-none');
        }
        broadcastAttachPreview?.classList.remove('has-image');
        broadcastAttachPreview?.classList.add('d-none');
        if (broadcastAttachName) {
            broadcastAttachName.textContent = '';
        }
    }

    function setBroadcastAttachment(file) {
        if (!isAllowedAttachment(file)) {
            if (file?.size > MAX_ATTACHMENT_BYTES) {
                toastError('Attachments may not be greater than 10MB.');
            } else {
                toastError('This file type is not allowed.');
            }
            return false;
        }
        clearBroadcastAttachment();
        state.broadcastAttachment = file;
        if (broadcastAttachName) {
            broadcastAttachName.textContent = file.name;
        }
        broadcastAttachPreview?.classList.remove('d-none');
        if (String(file.type || '').startsWith('image/') && broadcastAttachThumb) {
            broadcastAttachObjectUrl = URL.createObjectURL(file);
            broadcastAttachThumb.src = broadcastAttachObjectUrl;
            broadcastAttachThumb.classList.remove('d-none');
            broadcastAttachPreview?.classList.add('has-image');
        }
        return true;
    }

    function selectedBroadcastAudience() {
        return broadcastAudience?.value || 'all';
    }

    function loadScriptOnce(src) {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${src}"]`);
            if (existing) {
                if (existing.dataset.loaded === '1' || typeof window.Choices !== 'undefined') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('Failed to load Choices.js')), { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.addEventListener('load', () => {
                script.dataset.loaded = '1';
                resolve();
            }, { once: true });
            script.addEventListener('error', () => reject(new Error('Failed to load Choices.js')), { once: true });
            document.head.appendChild(script);
        });
    }

    function loadStylesheetOnce(href) {
        if (document.querySelector(`link[href="${href}"]`)) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    async function ensureChoicesLoaded() {
        if (typeof window.Choices !== 'undefined') {
            return;
        }
        const base = `${window.location.origin}/assets/extensions/choices.js/public/assets`;
        loadStylesheetOnce(`${base}/styles/choices.css`);
        await loadScriptOnce(`${base}/scripts/choices.js`);
    }

    function destroyBroadcastChoices(instanceKey) {
        if (instanceKey === 'departments' || !instanceKey) {
            if (broadcastDeptChoices) {
                broadcastDeptChoices.destroy();
                broadcastDeptChoices = null;
            }
            if (broadcastDepartmentsSelect) {
                broadcastDepartmentsSelect.innerHTML = '';
            }
            broadcastTargetsDepartments?.querySelector('.chat-widget__broadcast-select-wrap')
                ?.classList.remove('is-choices-ready');
        }
        if (instanceKey === 'users' || !instanceKey) {
            if (broadcastUserChoices) {
                broadcastUserChoices.destroy();
                broadcastUserChoices = null;
            }
            if (broadcastUsersSelect) {
                broadcastUsersSelect.innerHTML = '';
            }
            broadcastTargetsUsers?.querySelector('.chat-widget__broadcast-select-wrap')
                ?.classList.remove('is-choices-ready');
        }
    }

    function initBroadcastChoices(select, placeholder) {
        if (!select || typeof window.Choices === 'undefined') {
            return null;
        }
        return new window.Choices(select, {
            removeItemButton: true,
            searchEnabled: true,
            searchPlaceholderValue: 'Type to search…',
            placeholder: true,
            placeholderValue: placeholder,
            shouldSort: false,
            itemSelectText: '',
            allowHTML: false,
            position: 'bottom',
        });
    }

    function selectedBroadcastTargetIds(select) {
        if (!select) {
            return [];
        }
        return Array.from(select.selectedOptions || [])
            .map((option) => Number(option.value))
            .filter((id) => Number.isFinite(id) && id > 0);
    }

    function setBroadcastAudience(audience) {
        if (broadcastAudience) {
            broadcastAudience.value = audience;
        }
        broadcastAudienceGroup?.querySelectorAll('[data-broadcast-audience]').forEach((btn) => {
            const active = btn.getAttribute('data-broadcast-audience') === audience;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    async function populateBroadcastDepartments() {
        await ensureChoicesLoaded();
        destroyBroadcastChoices('departments');
        if (!state.broadcastDepartments.length) {
            const payload = await api(root.dataset.supportDepartmentsUrl);
            state.broadcastDepartments = payload.data || [];
        }
        if (!broadcastDepartmentsSelect) {
            return;
        }
        broadcastDepartmentsSelect.innerHTML = state.broadcastDepartments.map((item) => {
            const name = String(item.name || '').trim();
            const code = String(item.code || '').trim();
            const label = code ? `${name} (${code})` : name;
            return `<option value="${item.id}">${escapeHtml(label)}</option>`;
        }).join('');
        broadcastDeptChoices = initBroadcastChoices(broadcastDepartmentsSelect, 'Select departments…');
        broadcastDepartmentsSelect?.closest('.chat-widget__broadcast-select-wrap')
            ?.classList.add('is-choices-ready');
    }

    async function populateBroadcastUsers(query = '') {
        await ensureChoicesLoaded();
        const selected = new Set(selectedBroadcastTargetIds(broadcastUsersSelect).map(String));
        if (broadcastUserChoices) {
            try {
                broadcastUserChoices.getValue(true).forEach((value) => selected.add(String(value)));
            } catch (e) {
                // ignore
            }
        }

        const url = new URL(root.dataset.searchUsersUrl, window.location.origin);
        url.searchParams.set('q', query || '');
        url.searchParams.set('for_broadcast', '1');
        const payload = await api(url.toString());
        const fetched = payload.data || [];
        const byId = new Map();
        [...state.broadcastUsers, ...fetched].forEach((item) => {
            if (item?.id != null) {
                byId.set(String(item.id), item);
            }
        });
        state.broadcastUsers = [...byId.values()];

        destroyBroadcastChoices('users');
        if (!broadcastUsersSelect) {
            return;
        }
        broadcastUsersSelect.innerHTML = state.broadcastUsers.map((item) => {
            const label = item.username ? `${item.name} (@${item.username})` : String(item.name || '');
            const isSelected = selected.has(String(item.id)) ? ' selected' : '';
            return `<option value="${item.id}"${isSelected}>${escapeHtml(label)}</option>`;
        }).join('');
        broadcastUserChoices = initBroadcastChoices(broadcastUsersSelect, 'Search and select users…');
        broadcastUsersSelect?.closest('.chat-widget__broadcast-select-wrap')
            ?.classList.add('is-choices-ready');
    }

    async function syncBroadcastAudienceUi() {
        const audience = selectedBroadcastAudience();
        broadcastTargetsAll?.classList.toggle('d-none', audience !== 'all');

        if (broadcastHint) {
            broadcastHint.textContent = `Recipients will see this in their ${systemDisplayName} chat.`;
        }

        if (audience === 'departments') {
            broadcastTargetsUsers?.classList.add('d-none');
            destroyBroadcastChoices('users');
            broadcastTargetsDepartments?.classList.remove('d-none');
            broadcastTargetsDepartments?.querySelector('.chat-widget__broadcast-select-wrap')
                ?.classList.remove('is-choices-ready');
            await populateBroadcastDepartments();
        } else if (audience === 'users') {
            broadcastTargetsDepartments?.classList.add('d-none');
            destroyBroadcastChoices('departments');
            broadcastTargetsUsers?.classList.remove('d-none');
            broadcastTargetsUsers?.querySelector('.chat-widget__broadcast-select-wrap')
                ?.classList.remove('is-choices-ready');
            await populateBroadcastUsers('');
        } else {
            broadcastTargetsDepartments?.classList.add('d-none');
            broadcastTargetsUsers?.classList.add('d-none');
            destroyBroadcastChoices();
        }
    }

    async function openBroadcastView() {
        if (!canOperateSupport) {
            return;
        }
        clearBroadcastAttachment();
        if (broadcastBody) {
            broadcastBody.value = '';
        }
        setBroadcastAudience('all');
        try {
            await ensureChoicesLoaded();
            await syncBroadcastAudienceUi();
        } catch (e) {
            toastError(e.message);
        }
        showView('broadcast');
    }

    function closeBroadcastView() {
        clearBroadcastAttachment();
        destroyBroadcastChoices();
        showView('list');
    }

    async function sendBroadcast() {
        if (!canOperateSupport || state.broadcastSending) {
            return;
        }
        const body = String(broadcastBody?.value || '').trim();
        const file = state.broadcastAttachment;
        if (!body && !file) {
            toastError('Please enter a message or attach a file.');
            return;
        }

        const audience = selectedBroadcastAudience();
        const targetIds = audience === 'departments'
            ? selectedBroadcastTargetIds(broadcastDepartmentsSelect)
            : (audience === 'users' ? selectedBroadcastTargetIds(broadcastUsersSelect) : []);
        if ((audience === 'departments' || audience === 'users') && !targetIds.length) {
            toastError('Select at least one target.');
            return;
        }

        const form = new FormData();
        form.append('audience', audience);
        targetIds.forEach((id) => form.append('target_ids[]', String(id)));
        if (body) {
            form.append('body', body);
        }
        if (file) {
            form.append('attachment', file);
        }

        state.broadcastSending = true;
        if (broadcastSendBtn) {
            broadcastSendBtn.disabled = true;
            broadcastSendBtn.textContent = 'Sending...';
        }

        try {
            const payload = await api(root.dataset.supportBroadcastUrl, {
                method: 'POST',
                body: form,
            });
            const sent = Number(payload?.data?.sent_count || 0);
            if (window.Swal) {
                window.Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `Broadcast sent to ${sent} user${sent === 1 ? '' : 's'}`,
                    showConfirmButton: false,
                    timer: 2500,
                });
            }
            clearBroadcastAttachment();
            if (broadcastBody) {
                broadcastBody.value = '';
            }
            setBroadcastAudience('all');
            destroyBroadcastChoices();
            await loadConversations();
            closeBroadcastView();
        } catch (e) {
            toastError(e.message);
        } finally {
            state.broadcastSending = false;
            if (broadcastSendBtn) {
                broadcastSendBtn.disabled = false;
                broadcastSendBtn.textContent = 'Send broadcast';
            }
        }
    }

    function clearAttachment() {
        state.pendingAttachment = null;
        attachInput.value = '';
        revokeAttachPreviewUrl();
        if (attachThumb) {
            attachThumb.removeAttribute('src');
            attachThumb.classList.add('d-none');
        }
        attachPreview?.classList.remove('has-image');
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
        attachName.textContent = file.name || 'Pasted image';
        revokeAttachPreviewUrl();
        const isImage = String(file.type || '').startsWith('image/');
        if (isImage && attachThumb) {
            attachPreviewObjectUrl = URL.createObjectURL(file);
            attachThumb.src = attachPreviewObjectUrl;
            attachThumb.alt = file.name || 'Image preview';
            attachThumb.classList.remove('d-none');
            attachPreview.classList.add('has-image');
        } else if (attachThumb) {
            attachThumb.removeAttribute('src');
            attachThumb.classList.add('d-none');
            attachPreview.classList.remove('has-image');
        }
        attachPreview.classList.remove('d-none');
        return true;
    }

    function wrapTextareaSelection(textarea, marker) {
        if (!textarea) {
            return;
        }
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const value = textarea.value || '';
        const selected = value.slice(start, end);
        if (selected.length) {
            const wrapped = `${marker}${selected}${marker}`;
            textarea.value = value.slice(0, start) + wrapped + value.slice(end);
            textarea.focus();
            textarea.selectionStart = start;
            textarea.selectionEnd = start + wrapped.length;
        } else {
            const insert = `${marker}${marker}`;
            textarea.value = value.slice(0, start) + insert + value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + marker.length;
        }
    }

    function wrapComposerSelection(marker) {
        wrapTextareaSelection(input, marker);
        autoGrow();
        syncOutgoingTyping();
    }

    function handleRichTextShortcut(event, textarea) {
        const mod = event.ctrlKey || event.metaKey;
        if (!mod || event.altKey || !textarea) {
            return false;
        }
        const key = event.key.toLowerCase();
        if (key === 'b') {
            event.preventDefault();
            wrapTextareaSelection(textarea, '*');
            return true;
        }
        if (key === 'i') {
            event.preventDefault();
            wrapTextareaSelection(textarea, '_');
            return true;
        }
        if (event.shiftKey && key === 'x') {
            event.preventDefault();
            wrapTextareaSelection(textarea, '~');
            return true;
        }
        return false;
    }

    function handleComposerPaste(event) {
        const items = event.clipboardData?.items;
        if (!items?.length) {
            return;
        }
        for (const item of items) {
            if (!String(item.type || '').startsWith('image/')) {
                continue;
            }
            const blob = item.getAsFile();
            if (!blob) {
                continue;
            }
            event.preventDefault();
            const ext = (blob.type.split('/')[1] || 'png').replace(/[^a-z0-9]/gi, '') || 'png';
            const file = blob.name
                ? blob
                : new File([blob], `pasted-image-${Date.now()}.${ext}`, { type: blob.type || 'image/png' });
            setPendingAttachment(file);
            focusComposer();
            syncOutgoingTyping();
            return;
        }
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 110)}px`;
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
            const conversation = findConversation(conversationId);
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
            state.activeConversationType = null;
            state.activeSupportUserId = null;
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

    listTabs?.addEventListener('click', (event) => {
        const tabBtn = event.target.closest('[data-chat-tab]');
        if (!tabBtn || !listTabs.contains(tabBtn)) {
            return;
        }
        switchListTab(tabBtn.getAttribute('data-chat-tab'));
    });

    broadcastOpenBtn?.addEventListener('click', () => {
        openBroadcastView();
    });
    broadcastBackBtn?.addEventListener('click', () => {
        closeBroadcastView();
    });
    broadcastCloseBtn?.addEventListener('click', closePanel);
    broadcastAudienceGroup?.addEventListener('click', (event) => {
        const option = event.target.closest('[data-broadcast-audience]');
        if (!option || !broadcastAudienceGroup.contains(option)) {
            return;
        }
        const audience = option.getAttribute('data-broadcast-audience');
        if (!audience || audience === selectedBroadcastAudience()) {
            return;
        }
        setBroadcastAudience(audience);
        syncBroadcastAudienceUi().catch((e) => toastError(e.message));
    });
    broadcastAttachInput?.addEventListener('change', () => {
        const file = broadcastAttachInput.files?.[0] || null;
        if (file) {
            setBroadcastAttachment(file);
        }
        broadcastAttachInput.value = '';
    });
    broadcastAttachClear?.addEventListener('click', clearBroadcastAttachment);
    broadcastSendBtn?.addEventListener('click', () => {
        sendBroadcast();
    });
    broadcastBody?.addEventListener('keydown', (event) => {
        handleRichTextShortcut(event, broadcastBody);
    });
    root.querySelectorAll('[data-broadcast-format]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const marker = btn.getAttribute('data-broadcast-format');
            if (!marker) {
                return;
            }
            wrapTextareaSelection(broadcastBody, marker);
        });
    });

    if (canOperateSupport) {
        ensureChoicesLoaded().catch(() => {});
    }

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
            if (!peer) {
                return;
            }
            if (isSystemTab()) {
                openSupportForUser(peer);
                return;
            }
            openDraft(peer);
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
        if (handleRichTextShortcut(event, input)) {
            autoGrow();
            syncOutgoingTyping();
            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
    input.addEventListener('paste', handleComposerPaste);

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
    setupSupportChannel();
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
