@auth
<div id="chat-widget"
     class="chat-widget"
     data-auth-user-id="{{ auth()->id() }}"
     data-unread-url="{{ route('chat.unread-count') }}"
     data-conversations-url="{{ route('chat.conversations.index') }}"
     data-direct-message-url="{{ route('chat.direct-messages.store') }}"
     data-typing-url="{{ route('chat.typing') }}"
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
                    <small class="text-muted">Search or start a chat</small>
                </div>
                <div class="chat-widget__header-actions">
                    <button type="button" class="chat-widget__icon-btn" id="chat-close-btn" title="Close" aria-label="Close chat">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="chat-widget__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="chat-list-filter" class="form-control form-control-sm" placeholder="Search or start chat..." autocomplete="off">
            </div>
            <div class="chat-widget__list" id="chat-conversation-list">
                <div class="chat-widget__empty">No conversations yet. Search a contact to start.</div>
            </div>
        </div>

        <div class="chat-widget__view" id="chat-view-thread" data-view="thread">
            <div class="chat-widget__header chat-widget__thread-header">
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <button type="button" class="chat-widget__icon-btn" data-chat-back title="Back" aria-label="Back">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <button type="button" class="chat-widget__profile-trigger chat-widget__profile-trigger--thread" id="chat-thread-profile-trigger" title="View profile" aria-label="View profile">
                        <div class="chat-widget__avatar" id="chat-thread-avatar">?</div>
                        <div class="min-w-0 text-start">
                            <h6 class="mb-0 text-truncate" id="chat-thread-name">Chat</h6>
                            <small id="chat-thread-status">Offline</small>
                        </div>
                    </button>
                </div>
                <button type="button" class="chat-widget__icon-btn" id="chat-close-thread-btn" title="Close" aria-label="Close chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-widget__messages" id="chat-messages"></div>
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
                        <svg class="chat-widget__send-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M3.4 20.4 20.85 12.92a1 1 0 0 0 0-1.84L3.4 3.6a.993.993 0 0 0-1.39.91L2 9.12a1 1 0 0 0 .83.98L17 12 2.83 13.9a1 1 0 0 0-.83.98l.01 4.61c.01.72.78 1.17 1.39.91z"/>
                        </svg>
                    </button>
                </div>
                <div class="chat-widget__emoji-picker d-none" id="chat-emoji-picker"></div>
            </div>
            <div class="chat-widget__dropzone" id="chat-dropzone" aria-hidden="true">
                <div class="chat-widget__dropzone-inner">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Drop file to attach</p>
                </div>
            </div>
        </div>

        <div class="chat-widget__profile d-none" id="chat-user-profile" aria-hidden="true">
            <div class="chat-widget__profile-backdrop" data-profile-close></div>
            <div class="chat-widget__profile-sheet" role="dialog" aria-label="User profile">
                <div class="chat-widget__profile-top">
                    <span class="chat-widget__profile-title">Profile</span>
                    <button type="button" class="chat-widget__icon-btn" id="chat-profile-close" data-profile-close title="Close" aria-label="Close profile">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="chat-widget__profile-hero">
                    <div class="chat-widget__avatar chat-widget__avatar--lg" id="chat-profile-avatar">?</div>
                    <h6 class="chat-widget__profile-name" id="chat-profile-name">User</h6>
                    <p class="chat-widget__profile-username" id="chat-profile-username">@username</p>
                    <span class="chat-widget__profile-status" id="chat-profile-status">Offline</span>
                </div>
                <div class="chat-widget__profile-fields">
                    <div class="chat-widget__profile-field">
                        <span class="chat-widget__profile-field-icon" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
                        <div>
                            <span class="chat-widget__profile-field-label">Email</span>
                            <span class="chat-widget__profile-field-value" id="chat-profile-email">—</span>
                        </div>
                    </div>
                    <div class="chat-widget__profile-field">
                        <span class="chat-widget__profile-field-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                        <div>
                            <span class="chat-widget__profile-field-label">Department</span>
                            <span class="chat-widget__profile-field-value" id="chat-profile-department">—</span>
                        </div>
                    </div>
                    <div class="chat-widget__profile-field">
                        <span class="chat-widget__profile-field-icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                        <div>
                            <span class="chat-widget__profile-field-label">Role</span>
                            <span class="chat-widget__profile-field-value" id="chat-profile-role">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="chat-widget__toast-host" id="chat-toast-host" aria-live="polite" aria-relevant="additions"></div>
</div>
@endauth
