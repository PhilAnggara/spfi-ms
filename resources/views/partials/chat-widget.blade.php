@auth
<div id="chat-widget"
     class="chat-widget"
     data-auth-user-id="{{ auth()->id() }}"
     data-unread-url="{{ route('chat.unread-count') }}"
     data-conversations-url="{{ route('chat.conversations.index') }}"
     data-store-conversation-url="{{ route('chat.conversations.store') }}"
     data-search-users-url="{{ route('chat.users.search') }}"
     data-messages-url-template="{{ url('/chat/conversations/__ID__/messages') }}"
     data-store-message-url-template="{{ url('/chat/conversations/__ID__/messages') }}"
     data-delivered-url-template="{{ url('/chat/conversations/__ID__/delivered') }}"
     data-read-url-template="{{ url('/chat/conversations/__ID__/read') }}"
     aria-live="polite">
    <button type="button" class="chat-widget__fab" id="chat-widget-fab" aria-label="Open chat" aria-expanded="false">
        <i class="fa-solid fa-comments"></i>
        <span class="chat-widget__badge d-none" id="chat-widget-badge">0</span>
    </button>

    <div class="chat-widget__panel d-none" id="chat-widget-panel" role="dialog" aria-label="Chat">
        <div class="chat-widget__view is-active" id="chat-view-list" data-view="list">
            <div class="chat-widget__header">
                <div>
                    <h6 class="mb-0">Messages</h6>
                    <small class="text-muted">Direct messages</small>
                </div>
                <div class="chat-widget__header-actions">
                    <button type="button" class="chat-widget__icon-btn" id="chat-new-btn" title="New chat" aria-label="New chat">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="chat-widget__icon-btn" id="chat-close-btn" title="Close" aria-label="Close chat">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="chat-widget__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="chat-list-filter" class="form-control form-control-sm" placeholder="Filter conversations...">
            </div>
            <div class="chat-widget__list" id="chat-conversation-list">
                <div class="chat-widget__empty">No conversations yet. Start a new chat.</div>
            </div>
        </div>

        <div class="chat-widget__view" id="chat-view-new" data-view="new">
            <div class="chat-widget__header">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="chat-widget__icon-btn" data-chat-back title="Back" aria-label="Back">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <div>
                        <h6 class="mb-0">New chat</h6>
                        <small class="text-muted">Search people</small>
                    </div>
                </div>
                <button type="button" class="chat-widget__icon-btn" id="chat-close-new-btn" title="Close" aria-label="Close chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-widget__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="chat-user-search" class="form-control form-control-sm" placeholder="Search by name or username..." autocomplete="off">
            </div>
            <div class="chat-widget__list" id="chat-user-results">
                <div class="chat-widget__empty">Type to find a user.</div>
            </div>
        </div>

        <div class="chat-widget__view" id="chat-view-thread" data-view="thread">
            <div class="chat-widget__header chat-widget__thread-header">
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <button type="button" class="chat-widget__icon-btn" data-chat-back title="Back" aria-label="Back">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <div class="chat-widget__avatar" id="chat-thread-avatar">?</div>
                    <div class="min-w-0">
                        <h6 class="mb-0 text-truncate" id="chat-thread-name">Chat</h6>
                        <small class="text-muted" id="chat-thread-status">Offline</small>
                    </div>
                </div>
                <button type="button" class="chat-widget__icon-btn" id="chat-close-thread-btn" title="Close" aria-label="Close chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-widget__messages" id="chat-messages"></div>
            <div class="chat-widget__typing d-none" id="chat-typing">typing...</div>
            <div class="chat-widget__composer">
                <div class="chat-widget__attach-preview d-none" id="chat-attach-preview">
                    <span id="chat-attach-name"></span>
                    <button type="button" class="chat-widget__icon-btn" id="chat-attach-clear" aria-label="Remove attachment">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="chat-widget__composer-row">
                    <button type="button" class="chat-widget__icon-btn" id="chat-emoji-btn" title="Emoji" aria-label="Emoji">
                        <i class="fa-regular fa-face-smile"></i>
                    </button>
                    <label class="chat-widget__icon-btn mb-0" title="Attach file" aria-label="Attach file">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" id="chat-attachment" class="d-none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.rar,.ppt,.pptx">
                    </label>
                    <textarea id="chat-input" class="form-control" rows="1" placeholder="Type a message..."></textarea>
                    <button type="button" class="chat-widget__send-btn" id="chat-send-btn" aria-label="Send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chat-widget__emoji-picker d-none" id="chat-emoji-picker"></div>
            </div>
        </div>
    </div>
</div>
@endauth
