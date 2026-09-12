@auth
@php
    $canOperateSupport = auth()->user()->can('chat-support-operate');
    $systemDisplayName = \App\Services\ChatService::SYSTEM_DISPLAY_NAME;
@endphp
<div id="chat-widget"
     class="chat-widget{{ $canOperateSupport ? ' chat-widget--operator' : '' }}"
     data-auth-user-id="{{ auth()->id() }}"
     data-auth-user-name="{{ auth()->user()->name }}"
     data-can-support-operate="{{ $canOperateSupport ? '1' : '0' }}"
     data-system-display-name="{{ $systemDisplayName }}"
     data-system-avatar-url="{{ asset('assets/images/system_profile.png') }}"
     data-unread-url="{{ route('chat.unread-count') }}"
     data-unread-messages-url="{{ route('chat.unread-messages') }}"
     data-conversations-url="{{ route('chat.conversations.index') }}"
     data-support-conversations-url="{{ route('chat.support.conversations.index') }}"
     data-support-conversations-store-url="{{ route('chat.support.conversations.store') }}"
     data-support-departments-url="{{ route('chat.support.departments.index') }}"
     data-support-broadcast-url="{{ route('chat.support.broadcast') }}"
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
                    <h6 class="mb-0" id="chat-list-title">Messages</h6>
                    <small class="text-muted" id="chat-list-subtitle">Search or start a chat</small>
                </div>
                <div class="chat-widget__header-actions">
                    @if ($canOperateSupport)
                        <button type="button" class="chat-widget__icon-btn d-none" id="chat-broadcast-open" title="Broadcast from {{ $systemDisplayName }}" aria-label="Broadcast from {{ $systemDisplayName }}">
                            <i class="fa-solid fa-bullhorn"></i>
                        </button>
                    @endif
                    <button type="button" class="chat-widget__icon-btn" id="chat-close-btn" title="Close" aria-label="Close chat">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            @if ($canOperateSupport)
                <div class="chat-widget__tabs" id="chat-list-tabs" role="tablist" aria-label="Chat mode">
                    <button type="button" class="chat-widget__tab is-active" role="tab" aria-selected="true" data-chat-tab="personal" id="chat-tab-personal">
                        <span>Personal</span>
                        <span class="chat-widget__tab-badge d-none" id="chat-tab-badge-personal" aria-hidden="true">0</span>
                    </button>
                    <button type="button" class="chat-widget__tab" role="tab" aria-selected="false" data-chat-tab="system" id="chat-tab-system">
                        <span>{{ $systemDisplayName }}</span>
                        <span class="chat-widget__tab-badge d-none" id="chat-tab-badge-system" aria-hidden="true">0</span>
                    </button>
                </div>
            @endif
            <div class="chat-widget__search" id="chat-search-bar">
                <button type="button" class="chat-widget__search-back" id="chat-search-back" title="Back" aria-label="Back to chats" hidden>
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="chat-widget__search-field">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="chat-list-filter" class="form-control form-control-sm" placeholder="Search or start chat..." autocomplete="off">
                </div>
            </div>
            <div class="chat-widget__list-shell">
                <div class="chat-widget__list" id="chat-conversation-list">
                    <div class="chat-widget__empty">No conversations yet. Search a contact to start.</div>
                </div>
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
                            <div class="chat-widget__thread-title-row">
                                <h6 class="mb-0 text-truncate" id="chat-thread-name">Chat</h6>
                                <span class="chat-widget__official-badge d-none" id="chat-thread-official-badge" title="Official SPFI account" aria-label="Official SPFI account">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                </span>
                            </div>
                            <small id="chat-thread-status">Offline</small>
                        </div>
                    </button>
                </div>
                <button type="button" class="chat-widget__icon-btn" id="chat-close-thread-btn" title="Close" aria-label="Close chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="chat-widget__operator-hint d-none" id="chat-operator-hint" aria-live="polite"></div>
            <div class="chat-widget__messages" id="chat-messages"></div>
            <div class="chat-widget__composer">
                <div class="chat-widget__attach-preview d-none" id="chat-attach-preview">
                    <div class="chat-widget__attach-preview-main">
                        <img id="chat-attach-thumb" class="chat-widget__attach-thumb d-none" alt="" decoding="async">
                        <span id="chat-attach-name"></span>
                    </div>
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

        @if ($canOperateSupport)
            <div class="chat-widget__view" id="chat-view-broadcast" data-view="broadcast">
                <div class="chat-widget__header">
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                        <button type="button" class="chat-widget__icon-btn" id="chat-broadcast-back" title="Back" aria-label="Back">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                        <div class="min-w-0">
                            <h6 class="mb-0 text-truncate">Broadcast</h6>
                            <small class="text-muted">From {{ $systemDisplayName }}</small>
                        </div>
                    </div>
                    <button type="button" class="chat-widget__icon-btn" id="chat-close-broadcast-btn" title="Close" aria-label="Close chat">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="chat-widget__broadcast">
                    <span class="chat-widget__broadcast-label">Send to</span>
                    <div class="chat-widget__broadcast-audience" id="chat-broadcast-audience-group" role="group" aria-label="Broadcast audience">
                        <button type="button" class="chat-widget__broadcast-option is-active" data-broadcast-audience="all" aria-pressed="true">
                            <i class="fa-solid fa-globe" aria-hidden="true"></i>
                            <span>
                                <strong>All</strong>
                                <small>Everyone</small>
                            </span>
                        </button>
                        <button type="button" class="chat-widget__broadcast-option" data-broadcast-audience="departments" aria-pressed="false">
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                            <span>
                                <strong>Departments</strong>
                                <small>By dept</small>
                            </span>
                        </button>
                        <button type="button" class="chat-widget__broadcast-option" data-broadcast-audience="users" aria-pressed="false">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <span>
                                <strong>Users</strong>
                                <small>Pick people</small>
                            </span>
                        </button>
                    </div>
                    <input type="hidden" id="chat-broadcast-audience" value="all">

                    <div id="chat-broadcast-targets-departments" class="chat-widget__broadcast-targets d-none">
                        <label class="chat-widget__broadcast-label" for="chat-broadcast-departments">Select departments</label>
                        <div class="chat-widget__broadcast-select-wrap">
                            <select id="chat-broadcast-departments" class="form-select" multiple></select>
                        </div>
                        <p class="chat-widget__broadcast-hint">Search and select one or more departments.</p>
                    </div>

                    <div id="chat-broadcast-targets-users" class="chat-widget__broadcast-targets d-none">
                        <label class="chat-widget__broadcast-label" for="chat-broadcast-users">Select users</label>
                        <div class="chat-widget__broadcast-select-wrap">
                            <select id="chat-broadcast-users" class="form-select" multiple></select>
                        </div>
                        <p class="chat-widget__broadcast-hint">Search by name or username, then pick multiple people.</p>
                    </div>

                    <div id="chat-broadcast-targets-all" class="chat-widget__broadcast-all-note">
                        This broadcast will be sent to all users, including you.
                    </div>

                    <label class="chat-widget__broadcast-label" for="chat-broadcast-body">Message</label>
                    <div class="chat-widget__broadcast-format">
                        <button type="button" class="chat-widget__broadcast-format-btn" data-broadcast-format="*" title="Bold (Ctrl+B)" aria-label="Bold">B</button>
                        <button type="button" class="chat-widget__broadcast-format-btn" data-broadcast-format="_" title="Italic (Ctrl+I)" aria-label="Italic"><em>I</em></button>
                        <button type="button" class="chat-widget__broadcast-format-btn" data-broadcast-format="~" title="Strikethrough (Ctrl+Shift+X)" aria-label="Strikethrough"><s>S</s></button>
                        <p class="chat-widget__broadcast-format-hint">Ctrl+B · Ctrl+I · Ctrl+Shift+X</p>
                    </div>
                    <textarea id="chat-broadcast-body" class="form-control" rows="4" placeholder="Announce something from {{ $systemDisplayName }}..."></textarea>

                    <div class="chat-widget__attach-preview d-none" id="chat-broadcast-attach-preview">
                        <div class="chat-widget__attach-preview-main">
                            <img id="chat-broadcast-attach-thumb" class="chat-widget__attach-thumb d-none" alt="" decoding="async">
                            <span id="chat-broadcast-attach-name"></span>
                        </div>
                        <button type="button" class="chat-widget__icon-btn" id="chat-broadcast-attach-clear" aria-label="Remove attachment">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="chat-widget__broadcast-actions">
                        <label class="chat-widget__icon-btn mb-0" title="Attach file" aria-label="Attach file">
                            <i class="fa-solid fa-paperclip"></i>
                            <input type="file" id="chat-broadcast-attachment" class="d-none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.rar,.ppt,.pptx">
                        </label>
                        <button type="button" class="chat-widget__broadcast-send" id="chat-broadcast-send">
                            Send broadcast
                        </button>
                    </div>
                    <p class="chat-widget__broadcast-hint" id="chat-broadcast-hint">Recipients will see this in their {{ $systemDisplayName }} chat.</p>
                </div>
            </div>
        @endif

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
                <div class="chat-widget__profile-official d-none" id="chat-profile-official">
                    <div class="chat-widget__profile-official-badge">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Official account
                    </div>
                    <p class="chat-widget__profile-official-copy">
                        This is the official {{ $systemDisplayName }} support channel. Messages here come from the system team.
                    </p>
                </div>
                <div class="chat-widget__profile-fields" id="chat-profile-fields">
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
