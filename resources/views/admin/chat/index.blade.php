@extends('admin.layouts.app')

@section('title', 'Messages')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/chat-styles.css') }}">
<style>
    :root {
        --hospital-color: {{ appsettings()->hos_color ?? '#011b33' }};
    }
    .message-sent {
        background: var(--hospital-color) !important;
        color: white;
    }
    /* Markdown content styles for messenger */
    .message-bubble .markdown-content {
        line-height: 1.4;
    }
    .message-bubble .markdown-content p {
        margin: 0 0 0.5em 0;
    }
    .message-bubble .markdown-content p:last-child {
        margin-bottom: 0;
    }
    .message-bubble .markdown-content strong {
        font-weight: 700;
    }
    .message-bubble .markdown-content em {
        font-style: italic;
    }
    .message-bubble .markdown-content code {
        background: rgba(0,0,0,0.1);
        padding: 1px 4px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 0.9em;
    }
    .message-sent .markdown-content code {
        background: rgba(255,255,255,0.2);
    }
    .message-bubble .markdown-content pre {
        background: rgba(0,0,0,0.1);
        padding: 8px;
        border-radius: 4px;
        overflow-x: auto;
        margin: 0.5em 0;
    }
    .message-sent .markdown-content pre {
        background: rgba(255,255,255,0.15);
    }
    .message-bubble .markdown-content ul,
    .message-bubble .markdown-content ol {
        margin: 0.5em 0;
        padding-left: 1.5em;
    }
    .message-bubble .markdown-content li {
        margin: 0.2em 0;
    }
    .message-bubble .markdown-content blockquote {
        border-left: 3px solid rgba(128,128,128,0.5);
        margin: 0.5em 0;
        padding-left: 10px;
        opacity: 0.9;
    }
    .message-bubble .markdown-content a {
        color: inherit;
        text-decoration: underline;
    }
    .message-bubble .markdown-content h1,
    .message-bubble .markdown-content h2,
    .message-bubble .markdown-content h3 {
        font-size: 1em;
        font-weight: 700;
        margin: 0.5em 0 0.25em 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-modern" style="height: calc(100vh - 150px);">
                <div class="card-body p-0 d-flex h-100">
                    <!-- Sidebar -->
                    <div id="chat-sidebar" class="chat-sidebar border-right" style="width: 380px; display: flex; flex-direction: column;">
                        <!-- Header with New Chat Button -->
                        <div class="p-3 border-bottom bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 font-weight-bold">Messages</h5>
                                <button class="btn btn-sm btn-primary" onclick="fpToggleNewChat()">
                                    <i class="mdi mdi-plus"></i> New
                                </button>
                            </div>

                            <!-- Search Mode Toggle -->
                            <div class="search-mode-toggle mb-2">
                                <button type="button" class="mode-btn active" data-mode="people" onclick="fpSwitchSearchMode('people')">
                                    <i class="mdi mdi-account-search"></i>
                                    <span>People</span>
                                </button>
                                <button type="button" class="mode-btn" data-mode="chats" onclick="fpSwitchSearchMode('chats')">
                                    <i class="mdi mdi-message-text-outline"></i>
                                    <span>Chats</span>
                                </button>
                                <div class="mode-slider"></div>
                            </div>

                            <!-- Search Input -->
                            <div class="position-relative">
                                <input type="text" class="form-control" placeholder="Search people..." id="fp-search-input">
                                <div id="fp-search-results" class="list-group mt-1 position-absolute w-100 shadow-sm" style="z-index: 100; display: none; max-height: 300px; overflow-y: auto; border-radius: 8px;"></div>
                            </div>

                            <!-- Selected Users -->
                            <div id="fp-selected-users" class="mt-2 d-flex flex-wrap" style="gap: 5px; display: none;"></div>
                            <button id="fp-start-chat-btn" class="btn btn-primary btn-block mt-2" style="display: none;" onclick="fpInitiateChat()">Start Conversation</button>
                        </div>

                        <!-- Conversation Filters -->
                        <div class="conversation-filters">
                            <button class="filter-btn active" data-filter="all" onclick="fpSetFilter('all')">
                                All
                            </button>
                            <button class="filter-btn" data-filter="unread" onclick="fpSetFilter('unread')">
                                Unread <span class="count" id="unread-count">0</span>
                            </button>
                            <button class="filter-btn" data-filter="archived" onclick="fpSetFilter('archived')">
                                Archived
                            </button>
                        </div>

                        <!-- Search Results Header -->
                        <div id="fp-search-header" class="px-3 py-2 bg-light border-bottom" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="mdi mdi-magnify text-primary"></i>
                                    <span class="font-weight-bold" id="fp-search-count">0 results</span>
                                    <span class="text-muted" id="fp-search-query"></span>
                                </div>
                                <button class="btn btn-sm btn-light" onclick="fpClearSearch()" title="Clear search">
                                    <i class="mdi mdi-close"></i> Clear
                                </button>
                            </div>
                        </div>

                        <!-- Conversation List -->
                        <div class="flex-grow-1 overflow-auto chat-messages-container" id="fp-conversation-list">
                            <div class="text-center p-4 text-muted">Loading...</div>
                        </div>

                        <!-- Load More -->
                        <div id="fp-load-more-container" class="load-more-btn" style="display: none;" onclick="fpLoadMoreConversations()">
                            <i class="mdi mdi-chevron-down"></i> Load More
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div id="chat-main" class="chat-main flex-grow-1 d-flex flex-column" style="background: #f0f2f5;">
                        <!-- Header -->
                        <div id="fp-chat-header" class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center" style="display: none !important;">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-light mr-2 mobile-back-btn" onclick="fpBackToList()" style="display: none;">
                                    <i class="mdi mdi-arrow-left"></i>
                                </button>
                                <div class="avatar-wrapper mr-3" id="fp-header-img-container">
                                    <!-- Avatar with status indicator injected here -->
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold" id="fp-header-name">Select a conversation</h6>
                                    <div id="fp-header-participants" class="small text-muted mt-1"></div>
                                    <div id="fp-header-status" class="small text-success mt-1" style="display: none;">
                                        <i class="mdi mdi-circle" style="font-size: 8px;"></i> Online
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2" id="fp-header-actions">
                                <button id="fp-archive-btn" class="btn btn-sm btn-light" title="Archive conversation" onclick="fpArchiveConversation()">
                                    <i class="mdi mdi-archive-outline"></i>
                                </button>
                                <button class="btn btn-sm btn-light" title="Search in conversation" onclick="fpToggleMessageSearch()">
                                    <i class="mdi mdi-magnify"></i>
                                </button>
                                <button class="btn btn-sm btn-light" title="Conversation info">
                                    <i class="mdi mdi-information-outline"></i>
                                </button>
                            </div>
                            <!-- Message Search Bar -->
                            <div class="d-none align-items-center w-100" id="fp-message-search-bar">
                                <button class="btn btn-sm btn-light mr-2" onclick="fpCloseMessageSearch()">
                                    <i class="mdi mdi-arrow-left"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm flex-grow-1" placeholder="Search messages..." id="fp-message-search-input">
                                <div class="d-flex align-items-center ml-2" id="fp-search-nav" style="display: none !important;">
                                    <small class="text-muted mr-2" id="fp-search-count">0 of 0</small>
                                    <button class="btn btn-sm btn-light mr-1" onclick="fpPrevSearchMatch()" title="Previous match">
                                        <i class="mdi mdi-chevron-up"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light" onclick="fpNextSearchMatch()" title="Next match">
                                        <i class="mdi mdi-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div id="fp-typing-indicator" class="px-4 pt-2" style="display: none;">
                            <div class="typing-indicator">
                                <div class="typing-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <span id="fp-typing-name">Someone</span> is typing...
                            </div>
                        </div>

                        <!-- Load Previous Messages -->
                        <div id="fp-load-prev-container" class="load-more-btn" style="display: none;" onclick="fpLoadPreviousMessages()">
                            <i class="mdi mdi-chevron-up"></i> Load Previous Messages
                        </div>

                        <!-- Messages -->
                        <div id="fp-messages-area" class="flex-grow-1 p-4 overflow-auto chat-messages-container">
                            <div class="h-100 d-flex align-items-center justify-content-center text-muted flex-column">
                                <i class="mdi mdi-message-text-outline" style="font-size: 64px; opacity: 0.2;"></i>
                                <p class="mt-3" style="font-size: 16px;">Select a conversation to start chatting</p>
                            </div>
                        </div>

                        <!-- Drop Zone -->
                        <div id="fp-drop-zone" class="drop-zone">
                            <div class="drop-zone-icon">
                                <i class="mdi mdi-cloud-upload"></i>
                            </div>
                            <div class="drop-zone-text">Drop files here to send</div>
                        </div>

                        <!-- Input -->
                        <div id="fp-input-area" class="message-composer" style="display: none;">
                            <div id="fp-file-preview" class="mb-2" style="display: none;"></div>
                            <form onsubmit="fpSendMessage(event)" class="composer-form">
                                <button type="button" class="composer-btn" title="Attach file" onclick="document.getElementById('fp-file-input').click()">
                                    <i class="mdi mdi-paperclip"></i>
                                </button>
                                <input type="file" id="fp-file-input" hidden multiple onchange="fpHandleFileSelect(this)">

                                <textarea
                                    class="form-control composer-input"
                                    id="fp-input"
                                    placeholder="Type a message..."
                                    rows="1"
                                    oninput="fpAutoResize(this); fpNotifyTyping();"
                                    onkeydown="fpHandleEnter(event)"
                                ></textarea>

                                <button type="button" class="composer-btn emoji-picker-btn" title="Emoji">
                                    <i class="mdi mdi-emoticon-outline"></i>
                                </button>

                                <button type="button" class="composer-btn" title="Formal Mode" onclick="fpToggleFormalMode(this)" id="fp-formal-mode-btn">
                                    <i class="mdi mdi-format-letter-case"></i>
                                </button>

                                <button type="submit" class="composer-btn send-btn" title="Send">
                                    <i class="mdi mdi-send"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('assets/js/marked.min.js') }}"></script>
<script type="module">
    import { EmojiButton } from "{{ asset('assets/js/emoji-button.min.js') }}";
    window.EmojiButton = EmojiButton;
</script>
<script>
    // Helper function to render markdown
    function renderMarkdown(text) {
        if (!text) return '';
        if (typeof marked !== 'undefined') {
            // Configure marked for inline rendering
            marked.setOptions({
                breaks: true, // Convert \n to <br>
                gfm: true,    // GitHub Flavored Markdown
                sanitize: false
            });
            return marked.parse(text);
        }
        // Fallback: escape HTML and convert newlines
        return text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
    }

    // Initialize ChatCore with callbacks
    const chatCore = new ChatCore({
        currentUserId: {{ Auth::id() }},
        routes: {
            conversations: '{{ route("chat.conversations") }}',
            messages: '{{ url("chat/messages") }}',
            send: '{{ route("chat.send") }}',
            create: '{{ route("chat.create") }}',
            searchUsers: '{{ route("chat.search-users") }}',
            markRead: '{{ url("chat/mark-read") }}',
            checkUnread: '{{ route("chat.check-unread") }}'
        },
        callbacks: {
            getSearchQuery: () => document.getElementById('fp-search-input').value,
            onConversationsLoaded: renderConversations,
            onConversationOpened: openConversationUI,
            onMessagesLoaded: renderMessages,
            onMessageSent: (msg) => {
                appendMessage(msg);
                scrollToBottom();
                clearMessageInput();
            },
            onPreviousMessagesLoaded: (messages, meta) => {
                prependMessages(messages);
                if (!meta.hasMore) {
                    document.getElementById('fp-load-prev-container').style.display = 'none';
                }
            },
            onSelectionChanged: renderSelectedUsers,
            onSearchModeChanged: updateSearchPlaceholder,
            onFilterChanged: updateFilterUI
        }
    });

    // Emoji Picker
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof EmojiButton !== 'undefined') {
            const picker = new EmojiButton({
                position: 'top-start',
                theme: 'auto',
                autoHide: false
            });
            const trigger = document.querySelector('.emoji-picker-btn');
            const input = document.getElementById('fp-input');

            if (trigger && input) {
                trigger.addEventListener('click', () => {
                    picker.togglePicker(trigger);
                });

                picker.on('emoji', selection => {
                    const start = input.selectionStart;
                    const end = input.selectionEnd;
                    const text = input.value;
                    const before = text.substring(0, start);
                    const after = text.substring(end, text.length);

                    input.value = before + selection.emoji + after;
                    input.selectionStart = input.selectionEnd = start + selection.emoji.length;

                    fpAutoResize(input);
                    input.focus();
                });
            }
        }
    });

    let fpTypingTimeout = null;
    let fpSearchMode = 'people';
    let fpActiveFilter = 'all';

    document.addEventListener('DOMContentLoaded', function() {
        chatCore.loadConversations();
        setupSearchListener();
        setupDragAndDrop();
    });

    // ============================================
    // CONVERSATION RENDERING
    // ============================================

    function renderConversations(conversations, meta) {
        const container = document.getElementById('fp-conversation-list');
        const searchQuery = document.getElementById('fp-search-input').value;
        const isSearching = fpSearchMode === 'chats' && searchQuery.length > 0;

        // Update search header
        const searchHeader = document.getElementById('fp-search-header');
        if (isSearching) {
            searchHeader.style.display = 'block';
            document.getElementById('fp-search-count').textContent = `${conversations.length} result${conversations.length !== 1 ? 's' : ''}`;
            document.getElementById('fp-search-query').textContent = `for "${searchQuery}"`;
        } else {
            searchHeader.style.display = 'none';
        }

        container.innerHTML = '';

        if (meta.isEmpty) {
            const emptyMessage = isSearching
                ? `<div class="text-center p-4 text-muted">
                    <i class="mdi mdi-magnify-close" style="font-size: 48px; opacity: 0.3;"></i>
                    <p class="mb-0">No conversations found</p>
                    <small>Try different keywords</small>
                   </div>`
                : `<div class="chat-empty-state">
                    <div class="chat-empty-state-icon">
                        <i class="mdi mdi-message-text-outline"></i>
                    </div>
                    <p>No conversations yet</p>
                    <button class="btn btn-primary btn-sm" onclick="fpToggleNewChat()">
                        Start a conversation
                    </button>
                   </div>`;
            container.innerHTML = emptyMessage;
            return;
        }

        conversations.forEach(conv => {
            const div = document.createElement('div');
            div.className = `chat-conversation-item ${chatCore.activeConversationId === conv.id ? 'active' : ''}`;
            div.onclick = () => chatCore.openConversation(conv.id);

            let preview = 'Start chatting';
            if (conv.latest_message) {
                const isMe = conv.latest_message.user_id === chatCore.currentUserId;
                const prefix = isMe ? 'You: ' : '';
                preview = conv.latest_message.type === 'file'
                    ? prefix + '📎 Attachment'
                    : prefix + conv.latest_message.body;
            }

            // Highlight matching text if searching
            let displayName = conv.display_name;
            let displayPreview = preview;
            if (isSearching && searchQuery) {
                const regex = new RegExp(`(${searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                displayName = conv.display_name.replace(regex, '<mark class="bg-warning">$1</mark>');
                displayPreview = preview.replace(regex, '<mark class="bg-warning">$1</mark>');
            }

            div.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="chat-avatar-container mr-3">
                        ${chatCore.getAvatarHtml(conv, 45, 45)}
                        <div class="chat-status-indicator chat-status-online"></div>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="chat-conversation-name">${displayName}</div>
                            <div class="chat-conversation-time">${chatCore.formatTime(conv.latest_message?.created_at)}</div>
                        </div>
                        <div class="chat-conversation-preview text-truncate">
                            ${displayPreview}
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(div);
        });

        // Show/hide load more button
        const loadMoreBtn = document.getElementById('fp-load-more-container');
        loadMoreBtn.style.display = meta.hasMore ? 'block' : 'none';
    }

    function openConversationUI(conv) {
        // Update header
        document.getElementById('fp-chat-header').style.display = 'flex';
        document.getElementById('fp-input-area').style.display = 'block';
        document.getElementById('fp-header-name').innerText = conv.display_name;

        const imgContainer = document.getElementById('fp-header-img-container');
        imgContainer.innerHTML = `
            <div class="chat-avatar-container">
                ${chatCore.getAvatarHtml(conv, 45, 45)}
                <div class="chat-status-indicator chat-status-online"></div>
            </div>
        `;

        // Show participants
        const participantsDiv = document.getElementById('fp-header-participants');
        if (conv.participants_list && conv.participants_list.length > 0) {
            const others = conv.participants_list.filter(p => p.id !== chatCore.currentUserId);
            participantsDiv.innerText = others.map(p => p.name).join(', ');
            participantsDiv.style.display = 'block';
        } else {
            participantsDiv.style.display = 'none';
        }

        // Clear messages area
        const messagesArea = document.getElementById('fp-messages-area');
        messagesArea.innerHTML = '<div class="chat-loading"><div class="chat-spinner"></div></div>';

        // On mobile, slide to chat view
        if (window.innerWidth <= 768) {
            document.getElementById('chat-sidebar').classList.add('hide');
            document.getElementById('chat-main').classList.add('show');
        }
    }

    // ============================================
    // MOBILE NAVIGATION
    // ============================================

    function fpBackToList() {
        if (window.innerWidth <= 768) {
            document.getElementById('chat-sidebar').classList.remove('hide');
            document.getElementById('chat-main').classList.remove('show');
        }
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Reset mobile classes on desktop
            document.getElementById('chat-sidebar').classList.remove('hide');
            document.getElementById('chat-main').classList.remove('show');
        }
    });

    function renderMessages(messages, meta) {
        const container = document.getElementById('fp-messages-area');

        if (meta.isInitial) {
            container.innerHTML = '';
        }

        if (messages.length === 0 && meta.isInitial) {
            container.innerHTML = `
                <div class="chat-empty-state">
                    <div class="chat-empty-state-icon">
                        <i class="mdi mdi-message-outline"></i>
                    </div>
                    <p>No messages yet. Say hello!</p>
                </div>
            `;
            return;
        }

        messages.forEach(msg => appendMessage(msg));

        // Always scroll to bottom on initial load, otherwise only if user is near bottom
        if (meta.isInitial) {
            scrollToBottom();
        } else if (!meta.isPolling && chatCore.isUserNearBottom(container)) {
            scrollToBottom();
        }

        // Show/hide load previous button
        const loadPrevBtn = document.getElementById('fp-load-prev-container');
        loadPrevBtn.style.display = meta.hasMore ? 'block' : 'none';
    }

    function appendMessage(msg) {
        if (document.getElementById(`fp-msg-${msg.id}`)) return;

        const container = document.getElementById('fp-messages-area');
        const currentUserId = {{ Auth::id() }};
        const isMe = msg.user_id === currentUserId;

        const div = document.createElement('div');
        div.id = `fp-msg-${msg.id}`;
        div.className = `d-flex mb-3 ${isMe ? 'justify-content-end' : 'justify-content-start'} align-items-end`;

        // Get sender info
        const senderName = msg.user?.name || msg.sender_name || 'Unknown';
        const senderAvatar = msg.user?.avatar || msg.sender_avatar || '';

        let avatarHtml = '';
        if (senderAvatar) {
            avatarHtml = `<img src="${senderAvatar}" class="rounded-circle" width="32" height="32" style="object-fit: cover;">`;
        } else {
            const initials = senderName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'];
            const color = colors[msg.user_id % colors.length];
            avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold" style="width: 32px; height: 32px; background-color: ${color}; font-size: 12px;">${initials}</div>`;
        }

        let content = '';

        // Check if message is deleted
        if (msg.is_deleted || msg.body === 'This message was deleted') {
            content = '<div style="font-style: italic; opacity: 0.6;">This message was deleted</div>';
        } else {
            // Attachments
            if (msg.attachments && msg.attachments.length > 0) {
                content += '<div class="mb-2">';
                msg.attachments.forEach(att => {
                    const icon = chatCore.getFileIcon(att.file_name);
                    content += `
                        <a href="{{ url('storage') }}/${att.file_path}" target="_blank" class="file-attachment">
                            <i class="mdi ${icon} file-attachment-icon"></i>
                            <span class="file-attachment-name">${att.file_name}</span>
                        </a>
                    `;
                });
                content += '</div>';
            }

            // Body - render as markdown
            if (msg.body) {
                content += `<div class="markdown-content">${renderMarkdown(msg.body)}</div>`;
            }
        }

        // Add delete button for sent messages (not deleted)
        const deleteBtn = (isMe && !msg.is_deleted && msg.body !== 'This message was deleted')
            ? `<button class="message-delete-btn" onclick="chatCore.deleteMessage(${msg.id})" title="Delete message"><i class="mdi mdi-delete-outline"></i></button>`
            : '';

        div.innerHTML = `
            ${!isMe ? `<div class="mr-2">${avatarHtml}</div>` : ''}
            <div style="max-width: 70%; position: relative;">
                ${!isMe ? `<div class="text-muted mb-1" style="font-size: 11px; padding-left: 12px;">${senderName}</div>` : ''}
                <div class="message-bubble ${isMe ? 'message-sent' : 'message-received'}">
                    ${content}
                    <div class="message-timestamp">
                        ${chatCore.formatTime(msg.created_at)}
                    </div>
                    ${deleteBtn}
                </div>
            </div>
            ${isMe ? `<div class="ml-2">${avatarHtml}</div>` : ''}
        `;

        container.appendChild(div);
    }

    function prependMessages(messages) {
        const container = document.getElementById('fp-messages-area');
        const oldScrollHeight = container.scrollHeight;
        const oldScrollTop = container.scrollTop;

        const fragment = document.createDocumentFragment();
        const currentUserId = {{ Auth::id() }};
        messages.forEach(msg => {
            const isMe = msg.user_id === currentUserId;
            const div = document.createElement('div');
            div.id = `fp-msg-${msg.id}`;
            div.className = `d-flex mb-3 ${isMe ? 'justify-content-end' : 'justify-content-start'} align-items-end`;

            // Get sender info
            const senderName = msg.user?.name || msg.sender_name || 'Unknown';
            const senderAvatar = msg.user?.avatar || msg.sender_avatar || '';

            let avatarHtml = '';
            if (senderAvatar) {
                avatarHtml = `<img src="${senderAvatar}" class="rounded-circle" width="32" height="32" style="object-fit: cover;">`;
            } else {
                const initials = senderName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'];
                const color = colors[msg.user_id % colors.length];
                avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold" style="width: 32px; height: 32px; background-color: ${color}; font-size: 12px;">${initials}</div>`;
            }

            let content = '';

            // Check if message is deleted
            if (msg.is_deleted || msg.body === 'This message was deleted') {
                content = '<div style="font-style: italic; opacity: 0.6;">This message was deleted</div>';
            } else {
                if (msg.attachments && msg.attachments.length > 0) {
                    content += '<div class="mb-2">';
                    msg.attachments.forEach(att => {
                        content += `
                            <a href="{{ url('storage') }}/${att.file_path}" target="_blank" class="file-attachment">
                                <i class="mdi ${chatCore.getFileIcon(att.file_name)} file-attachment-icon"></i>
                                <span class="file-attachment-name">${att.file_name}</span>
                            </a>
                        `;
                    });
                    content += '</div>';
                }
                if (msg.body) content += `<div class="markdown-content">${renderMarkdown(msg.body)}</div>`;
            }

            // Add delete button for sent messages (not deleted)
            const deleteBtn = (isMe && !msg.is_deleted && msg.body !== 'This message was deleted')
                ? `<button class="message-delete-btn" onclick="chatCore.deleteMessage(${msg.id})" title="Delete message"><i class="mdi mdi-delete-outline"></i></button>`
                : '';

            div.innerHTML = `
                ${!isMe ? `<div class="mr-2">${avatarHtml}</div>` : ''}
                <div style="max-width: 70%; position: relative;">
                    ${!isMe ? `<div class="text-muted mb-1" style="font-size: 11px; padding-left: 12px;">${senderName}</div>` : ''}
                    <div class="message-bubble ${isMe ? 'message-sent' : 'message-received'}">
                        ${content}
                        <div class="message-timestamp">${chatCore.formatTime(msg.created_at)}</div>
                        ${deleteBtn}
                    </div>
                </div>
                ${isMe ? `<div class="ml-2">${avatarHtml}</div>` : ''}
            `;
            fragment.appendChild(div);
        });

        container.insertBefore(fragment, container.firstChild);
        // Keep scroll at the top to show oldest fetched message
        container.scrollTop = 0;
    }

    // ============================================
    // UI INTERACTIONS
    // ============================================

    function fpSwitchSearchMode(mode) {
        fpSearchMode = mode;
        const buttons = document.querySelectorAll('.mode-btn');
        const slider = document.querySelector('.mode-slider');

        buttons.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === mode));
        slider.className = 'mode-slider mode-' + mode;

        chatCore.setSearchMode(mode);
        document.getElementById('fp-search-input').value = '';
        document.getElementById('fp-search-results').style.display = 'none';

        // Clear search and reload conversations
        chatCore.loadConversations();
    }

    function fpClearSearch() {
        document.getElementById('fp-search-input').value = '';
        document.getElementById('fp-search-results').style.display = 'none';
        chatCore.loadConversations();
    }

    function fpSetFilter(filter) {
        fpActiveFilter = filter;
        chatCore.setFilter(filter);

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });
    }

    async function fpArchiveConversation() {
        if (!chatCore.activeConversationId) return;

        const result = await chatCore.archiveConversation(chatCore.activeConversationId);
        if (result) {
            // Close the conversation view
            document.getElementById('fp-chat-header').style.display = 'none';
            document.getElementById('fp-input-area').style.display = 'none';
            document.getElementById('fp-messages-area').innerHTML = '';
            chatCore.activeConversationId = null;
        }
    }

    function updateSearchPlaceholder(mode) {
        const input = document.getElementById('fp-search-input');
        input.placeholder = mode === 'people' ? 'Search people...' : 'Search conversations...';
    }

    function updateFilterUI(filter) {
        // Update active state on filter buttons
    }

    function setupSearchListener() {
        const searchInput = document.getElementById('fp-search-input');
        const searchResults = document.getElementById('fp-search-results');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value;

            if (fpSearchMode === 'chats') {
                searchTimeout = setTimeout(() => chatCore.loadConversations(), 400);
                return;
            }

            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(async () => {
                const users = await chatCore.searchUsers(query);
                searchResults.innerHTML = '';
                searchResults.style.display = 'block';

                if (users.length === 0) {
                    searchResults.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
                    return;
                }

                users.forEach(user => {
                    const a = document.createElement('a');
                    a.className = 'list-group-item list-group-item-action border-0 border-bottom';
                    a.href = '#';
                    a.innerHTML = `
                        <div class="d-flex flex-column py-1">
                            <strong class="mb-1" style="font-size: 14px;">${user.firstname} ${user.surname}</strong>
                            <div class="d-flex flex-wrap" style="gap: 4px;">
                                <span class="badge badge-info" style="font-size: 10px;">${user.user_category}</span>
                                <span class="badge badge-secondary" style="font-size: 10px;">${user.specialization}</span>
                            </div>
                        </div>
                    `;
                    a.onclick = (e) => {
                        e.preventDefault();
                        chatCore.addToSelection(user);
                        searchResults.style.display = 'none';
                        searchInput.value = '';
                    };
                    searchResults.appendChild(a);
                });
            }, 300);
        });
    }

    function renderSelectedUsers(users) {
        const container = document.getElementById('fp-selected-users');
        const btn = document.getElementById('fp-start-chat-btn');

        container.innerHTML = '';

        if (users.length > 0) {
            container.style.display = 'flex';
            btn.style.display = 'block';

            users.forEach(user => {
                const chip = document.createElement('div');
                chip.className = 'badge badge-primary p-2';
                chip.innerHTML = `
                    ${user.firstname}
                    <span class="ml-2 cursor-pointer" onclick="chatCore.removeFromSelection(${user.id})">&times;</span>
                `;
                container.appendChild(chip);
            });
        } else {
            container.style.display = 'none';
            btn.style.display = 'none';
        }
    }

    function fpToggleNewChat() {
        fpSwitchSearchMode('people');
        document.getElementById('fp-search-input').focus();
    }

    function fpInitiateChat() {
        chatCore.startConversationWithSelected();
    }

    function fpLoadMoreConversations() {
        chatCore.loadMoreConversations();
    }

    function fpLoadPreviousMessages() {
        chatCore.loadPreviousMessages();
    }

    // ============================================
    // MESSAGE SEARCH
    // ============================================

    let messageSearchResults = [];
    let currentSearchIndex = 0;
    let messageSearchTimeout = null;

    function fpToggleMessageSearch() {
        const searchBar = document.getElementById('fp-message-search-bar');
        const headerActions = document.getElementById('fp-header-actions');

        searchBar.classList.remove('d-none');
        searchBar.classList.add('d-flex');
        headerActions.classList.add('d-none');

        setTimeout(() => document.getElementById('fp-message-search-input').focus(), 100);
    }

    function fpCloseMessageSearch() {
        const searchBar = document.getElementById('fp-message-search-bar');
        const headerActions = document.getElementById('fp-header-actions');

        searchBar.classList.add('d-none');
        searchBar.classList.remove('d-flex');
        headerActions.classList.remove('d-none');

        document.getElementById('fp-message-search-input').value = '';
        document.getElementById('fp-search-nav').style.display = 'none';
        messageSearchResults = [];
        currentSearchIndex = 0;

        // Remove all highlights
        document.querySelectorAll('.message-search-highlight').forEach(el => {
            el.outerHTML = el.textContent;
        });
    }

    async function fpSearchMessages(query) {
        if (!chatCore.activeConversationId || query.length < 2) {
            document.getElementById('fp-search-nav').style.display = 'none';
            messageSearchResults = [];
            return;
        }

        try {
            const response = await fetch(`/chat/search-messages/${chatCore.activeConversationId}?q=${encodeURIComponent(query)}`);
            const results = await response.json();

            messageSearchResults = results;
            currentSearchIndex = 0;

            if (results.length > 0) {
                document.getElementById('fp-search-nav').style.display = 'flex';
                document.getElementById('fp-search-count').textContent = `1 of ${results.length}`;
                fpHighlightSearchResults(query);
                fpScrollToSearchMatch(0);
            } else {
                document.getElementById('fp-search-nav').style.display = 'none';
                document.getElementById('fp-search-count').textContent = '0 of 0';
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    function fpHighlightSearchResults(query) {
        // Remove existing highlights
        document.querySelectorAll('.message-search-highlight').forEach(el => {
            const parent = el.parentNode;
            parent.replaceChild(document.createTextNode(el.textContent), el);
            parent.normalize();
        });

        if (!query || messageSearchResults.length === 0) return;

        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')})`, 'gi');

        messageSearchResults.forEach((result, index) => {
            const msgElement = document.getElementById(`fp-msg-${result.id}`);
            if (!msgElement) return;

            const textNodes = [];
            const walker = document.createTreeWalker(msgElement, NodeFilter.SHOW_TEXT, null);
            let node;
            while (node = walker.nextNode()) {
                if (node.parentElement.classList.contains('message-timestamp')) continue;
                textNodes.push(node);
            }

            textNodes.forEach(textNode => {
                const text = textNode.textContent;
                if (!regex.test(text)) return;

                const fragment = document.createDocumentFragment();
                let lastIndex = 0;
                text.replace(regex, (match, p1, offset) => {
                    fragment.appendChild(document.createTextNode(text.slice(lastIndex, offset)));
                    const mark = document.createElement('mark');
                    mark.className = index === currentSearchIndex ? 'message-search-highlight bg-warning' : 'message-search-highlight bg-warning-light';
                    mark.textContent = match;
                    fragment.appendChild(mark);
                    lastIndex = offset + match.length;
                });
                fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
                textNode.parentNode.replaceChild(fragment, textNode);
            });
        });
    }

    function fpScrollToSearchMatch(index) {
        if (messageSearchResults.length === 0) return;

        const result = messageSearchResults[index];
        const msgElement = document.getElementById(`fp-msg-${result.id}`);

        if (msgElement) {
            msgElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function fpNextSearchMatch() {
        if (messageSearchResults.length === 0) return;

        currentSearchIndex = (currentSearchIndex + 1) % messageSearchResults.length;
        document.getElementById('fp-search-count').textContent = `${currentSearchIndex + 1} of ${messageSearchResults.length}`;

        const query = document.getElementById('fp-message-search-input').value;
        fpHighlightSearchResults(query);
        fpScrollToSearchMatch(currentSearchIndex);
    }

    function fpPrevSearchMatch() {
        if (messageSearchResults.length === 0) return;

        currentSearchIndex = (currentSearchIndex - 1 + messageSearchResults.length) % messageSearchResults.length;
        document.getElementById('fp-search-count').textContent = `${currentSearchIndex + 1} of ${messageSearchResults.length}`;

        const query = document.getElementById('fp-message-search-input').value;
        fpHighlightSearchResults(query);
        fpScrollToSearchMatch(currentSearchIndex);
    }

    // Setup search input listener
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('fp-message-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(messageSearchTimeout);
                const query = this.value;

                if (query.length < 2) {
                    fpCloseMessageSearch();
                    fpToggleMessageSearch();
                    return;
                }

                messageSearchTimeout = setTimeout(() => fpSearchMessages(query), 300);
            });
        }

        // Auto-enable Formal Mode
        const formalBtn = document.getElementById('fp-formal-mode-btn');
        if (formalBtn) {
            const initFormalMode = () => {
                if (typeof ClassicEditor !== 'undefined') {
                    fpToggleFormalMode(formalBtn);
                } else {
                    setTimeout(initFormalMode, 100);
                }
            };
            initFormalMode();
        }
    });

    // ============================================
    // MESSAGE SENDING
    // ============================================

    let formalEditor = null;

    function fpToggleFormalMode(btn) {
        const input = document.getElementById('fp-input');

        if (formalEditor) {
            // Disable Formal Mode
            const data = formalEditor.getData();
            formalEditor.destroy()
                .then(() => {
                    formalEditor = null;
                    input.style.display = 'block';
                    // Strip HTML tags for plain text mode, or keep them if you want to allow HTML in plain mode
                    // For now, we'll keep the text content only to avoid confusion
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    input.value = tempDiv.textContent || tempDiv.innerText || '';

                    btn.classList.remove('active-formal');
                    btn.querySelector('i').className = 'mdi mdi-format-letter-case';
                    fpAutoResize(input);
                });
        } else {
            // Enable Formal Mode
            ClassicEditor
                .create(input, {
                    toolbar: ['bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
                })
                .then(editor => {
                    formalEditor = editor;
                    btn.classList.add('active-formal');
                    btn.querySelector('i').className = 'mdi mdi-format-letter-case text-primary';

                    // Sync data on change
                    editor.model.document.on('change:data', () => {
                        fpNotifyTyping();
                    });
                })
                .catch(error => {
                    console.error(error);
                });
        }
    }

    function fpSendMessage(e) {
        e.preventDefault();
        const input = document.getElementById('fp-input');
        const fileInput = document.getElementById('fp-file-input');

        let body;
        if (formalEditor) {
            body = formalEditor.getData();
        } else {
            body = input.value.trim();
        }

        if (!body && !fileInput.files.length) return;

        chatCore.sendMessage(body, Array.from(fileInput.files));

        if (formalEditor) {
            formalEditor.setData('');
        }
    }

    function clearMessageInput() {
        if (formalEditor) {
            formalEditor.setData('');
        } else {
            document.getElementById('fp-input').value = '';
            fpAutoResize(document.getElementById('fp-input'));
        }
        document.getElementById('fp-file-input').value = '';
        document.getElementById('fp-file-preview').style.display = 'none';
    }

    function fpHandleFileSelect(input) {
        const preview = document.getElementById('fp-file-preview');
        if (input.files && input.files.length > 0) {
            preview.innerHTML = '';
            preview.style.display = 'block';

            Array.from(input.files).forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'chat-file-preview-item';
                div.innerHTML = `
                    <i class="mdi ${chatCore.getFileIcon(file.name)}"></i>
                    <span>${file.name}</span>
                    <span class="chat-file-preview-remove" onclick="fpRemoveFile(${index})">&times;</span>
                `;
                preview.appendChild(div);
            });
        } else {
            preview.style.display = 'none';
        }
    }

    function fpRemoveFile(index) {
        const fileInput = document.getElementById('fp-file-input');
        const dt = new DataTransfer();
        const files = Array.from(fileInput.files);
        files.splice(index, 1);
        files.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
        fpHandleFileSelect(fileInput);
    }

    // ============================================
    // HELPERS
    // ============================================

    function scrollToBottom() {
        const container = document.getElementById('fp-messages-area');
        chatCore.scrollToBottom(container);
    }

    function fpAutoResize(textarea) {
        if (formalEditor) return;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    function fpHandleEnter(e) {
        if (formalEditor) return;
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('fp-input').closest('form').dispatchEvent(new Event('submit'));
        }
    }

    function fpNotifyTyping() {
        // Implement typing notification logic
    }

    function setupDragAndDrop() {
        const messagesArea = document.getElementById('fp-messages-area');
        const dropZone = document.getElementById('fp-drop-zone');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            messagesArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            messagesArea.addEventListener(eventName, () => dropZone.classList.add('active'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            messagesArea.addEventListener(eventName, () => dropZone.classList.remove('active'), false);
        });

        messagesArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('fp-file-input').files = files;
            fpHandleFileSelect(document.getElementById('fp-file-input'));
        }, false);
    }
</script>
@endsection
@endsection

