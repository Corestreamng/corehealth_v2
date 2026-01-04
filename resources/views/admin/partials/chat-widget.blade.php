<div id="chat-widget-container">
    @if(!request()->routeIs('chat.index'))
    <!-- Floating Button -->
    <button id="chat-floating-btn" class="chat-floating-btn" onclick="toggleChatWindow()">
        <i class="mdi mdi-message-text-outline"></i>
        <span class="chat-badge" id="chat-total-unread" style="display: none;">0</span>
    </button>
    @endif

    <!-- Chat Window -->
    <div id="chat-window" class="chat-window">
        <!-- Header -->
        <div class="chat-header">
            <div class="d-flex align-items-center overflow-hidden">
                <div id="chat-header-info" class="d-flex flex-column overflow-hidden">
                    <h6 class="mb-0 text-white text-truncate" id="chat-header-title">Messages</h6>
                    <div id="chat-header-participants" class="small text-white-50 text-truncate" style="font-size: 10px; display: none;"></div>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <a href="{{ route('chat.index') }}" target="_blank" class="btn btn-sm text-white p-0 mr-2" title="Open Full Messenger">
                    <i class="mdi mdi-open-in-new"></i>
                </a>
                <button class="btn btn-sm text-white p-0 mr-2" onclick="showConversationList()" id="back-to-list-btn" style="display: none;">
                    <i class="mdi mdi-arrow-left"></i>
                </button>
                <button class="btn btn-sm text-white p-0" onclick="toggleChatWindow()">
                    <i class="mdi mdi-close"></i>
                </button>
            </div>
        </div>

        <!-- Conversation List -->
        <div id="chat-conversation-list" class="chat-body">
            <div class="p-2">
                <!-- Animated Search Mode Toggle -->
                <div class="search-mode-toggle mb-2">
                    <button type="button" class="mode-btn active" data-mode="people" onclick="switchSearchMode('people')">
                        <i class="mdi mdi-account-search"></i>
                        <span>People</span>
                    </button>
                    <button type="button" class="mode-btn" data-mode="chats" onclick="switchSearchMode('chats')">
                        <i class="mdi mdi-message-text-outline"></i>
                        <span>Chats</span>
                    </button>
                    <div class="mode-slider"></div>
                </div>

                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" placeholder="Search people..." id="user-search-input">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>

                <div id="user-search-results" class="list-group mt-1" style="display: none; position: absolute; z-index: 1000; width: 90%; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"></div>

                <!-- Selected Users Area -->
                <div id="selected-users-container" class="mt-2 d-flex flex-wrap" style="gap: 5px; display: none;"></div>
                <button id="start-chat-btn" class="btn btn-primary btn-sm btn-block mt-2" style="display: none;" onclick="initiateChat()">Start Conversation</button>
            </div>
            <div class="list-group list-group-flush" id="conversation-list-container">
                <!-- Conversations will be loaded here -->
                <div class="text-center p-4 text-muted">Loading new data...</div>
            </div>
            <div class="p-2 text-center" id="load-more-conversations-container" style="display: none;">
                <button class="btn btn-sm btn-light btn-block" onclick="loadMoreConversations()">Load More</button>
            </div>
        </div>

        <!-- Message Area -->
        <div id="chat-message-area" class="chat-body" style="display: none;">
            <div class="p-2 text-center" id="load-prev-messages-container" style="display: none;">
                <button class="btn btn-sm btn-light btn-block" onclick="loadPreviousMessages()">Load Previous</button>
            </div>
            <div class="chat-messages" id="messages-container">
                <!-- Messages will be loaded here -->
            </div>
            <div class="chat-input-area">
                <form id="chat-form" onsubmit="sendMessage(event)">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <label class="btn btn-light m-0" for="chat-file-input">
                                <i class="mdi mdi-paperclip"></i>
                            </label>
                            <input type="file" id="chat-file-input" style="display: none;" multiple onchange="handleFileSelect(this)">
                        </div>
                        <input type="text" class="form-control" id="chat-input" placeholder="Type a message...">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="mdi mdi-send"></i>
                            </button>
                        </div>
                    </div>
                    <div id="file-preview" class="small text-muted mt-1" style="display: none;"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notification Sound -->
<audio id="chat-notification-sound" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU" preload="auto"></audio>

<!-- Unread Message Modal -->
<div id="unread-message-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title">New Messages</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="mdi mdi-email-alert text-primary mb-2" style="font-size: 48px;"></i>
                <p class="mb-0">You have <strong id="unread-modal-count">0</strong> unread messages.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" onclick="snoozeNotifications()">Snooze (5m)</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="acknowledgeMessages()">Open Chat</button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --chat-primary: {{ appsettings()->hos_color ?? '#011b33' }};
    }

    .chat-floating-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--chat-primary);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 24px;
        cursor: pointer;
        transition: transform 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-floating-btn:hover {
        transform: scale(1.1);
    }

    .chat-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #ff3e3e;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
    }

    .chat-window {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 350px;
        height: 500px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        z-index: 9999;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #eee;
    }

    .chat-header {
        background: var(--chat-primary);
        color: white;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-body {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .chat-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f8f9fa;
    }

    .message-bubble {
        max-width: 80%;
        padding: 10px 15px;
        border-radius: 15px;
        margin-bottom: 10px;
        font-size: 14px;
        position: relative;
        word-wrap: break-word;
    }

    .message-sent {
        background: var(--chat-primary);
        color: white;
        align-self: flex-end;
        margin-left: auto;
        border-bottom-right-radius: 2px;
    }

    .message-received {
        background: white;
        border: 1px solid #eee;
        align-self: flex-start;
        border-bottom-left-radius: 2px;
    }

    .chat-input-area {
        padding: 10px;
        background: white;
        border-top: 1px solid #eee;
    }

    .conversation-item {
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid #f0f0f0;
    }

    .conversation-item:hover {
        background: #f8f9fa;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    /* Search Mode Toggle Styles */
    .search-mode-toggle {
        position: relative;
        display: flex;
        background: #f0f2f5;
        border-radius: 8px;
        padding: 4px;
        gap: 4px;
    }

    .mode-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border: none;
        background: transparent;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #65676b;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 1;
    }

    .mode-btn i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .mode-btn:hover:not(.active) {
        color: #333;
        transform: scale(1.02);
    }

    .mode-btn.active {
        color: white;
    }

    .mode-btn.active i {
        transform: scale(1.1);
    }

    .mode-slider {
        position: absolute;
        top: 4px;
        left: 4px;
        width: calc(50% - 4px);
        height: calc(100% - 8px);
        border-radius: 6px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .mode-btn[data-mode="people"].active ~ .mode-slider,
    .mode-slider.mode-people {
        left: 4px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .mode-btn[data-mode="chats"].active ~ .mode-slider,
    .mode-slider.mode-chats {
        left: calc(50% + 2px);
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    /* Search input animation */
    #user-search-input {
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    #user-search-input:focus {
        border-color: var(--chat-primary);
        box-shadow: 0 0 0 0.2rem rgba(1, 27, 51, 0.15);
    }

    .search-mode-people #user-search-input {
        border-color: #667eea;
    }

    .search-mode-people #user-search-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .search-mode-chats #user-search-input {
        border-color: #f5576c;
    }

    .search-mode-chats #user-search-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.25);
    }
</style>

<!-- Polling Logic (No External Dependencies) -->
<script>
    // Wait for ChatCore to be available
    if (typeof ChatCore === 'undefined') {
        console.error('ChatCore is not loaded! Make sure chat-core.js is included before this script.');
    }

    // Initialize ChatCore for widget
    const widgetChatCore = new ChatCore({
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
            getSearchQuery: () => document.getElementById('user-search-input').value,
            onConversationsLoaded: renderWidgetConversations,
            onConversationOpened: openWidgetConversation,
            onMessagesLoaded: renderWidgetMessages,
            onMessageSent: (msg) => {
                appendWidgetMessage(msg);
                scrollWidgetToBottom();
                clearWidgetInput();
            },
            onPreviousMessagesLoaded: (messages, meta) => {
                prependWidgetMessages(messages);
                if (!meta.hasMore) {
                    document.getElementById('load-prev-messages-container').style.display = 'none';
                }
            },
            onSelectionChanged: renderWidgetSelectedUsers,
            onSearchModeChanged: updateWidgetSearchPlaceholder,
            onUnreadCountChanged: updateUnreadBadge
        }
    });

    let lastUnreadCount = 0;
    let snoozeUntil = localStorage.getItem('chat_snooze_until') ? new Date(localStorage.getItem('chat_snooze_until')) : null;

    // Start polling for unread counts immediately
    widgetChatCore.startUnreadPolling();

    // ============================================
    // WIDGET WINDOW MANAGEMENT
    // ============================================

    function toggleChatWindow() {
        const window = document.getElementById('chat-window');
        if (window.style.display === 'none' || window.style.display === '') {
            window.style.display = 'flex';
            widgetChatCore.loadConversations();
        } else {
            window.style.display = 'none';
            widgetChatCore.stopMessagePolling();
        }
    }

    function showConversationList() {
        widgetChatCore.stopMessagePolling();
        widgetChatCore.activeConversationId = null;
        document.getElementById('chat-conversation-list').style.display = 'flex';
        document.getElementById('chat-message-area').style.display = 'none';
        document.getElementById('back-to-list-btn').style.display = 'none';
        document.getElementById('chat-header-title').innerText = 'Messages';
        document.getElementById('chat-header-participants').style.display = 'none';
        widgetChatCore.loadConversations();
    }

    // ============================================
    // UNREAD NOTIFICATIONS
    // ============================================

    function updateUnreadBadge(count) {
        const badge = document.getElementById('chat-total-unread');
        if (!badge) return;

        if (count > 0) {
            badge.innerText = count;
            badge.style.display = 'flex';

            // Play sound if count increased
            if (count > lastUnreadCount) {
                playNotificationSound();
                showUnreadPopup(count);
            }
            lastUnreadCount = count;
        } else {
            badge.style.display = 'none';
            lastUnreadCount = 0;
        }
    }

    function playNotificationSound() {
        const audio = document.getElementById('chat-notification-sound');
        const soundEnabled = {{ (appsettings()->notification_sound ?? 1) ? 'true' : 'false' }};
        if (audio && soundEnabled) {
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
    }

    function showUnreadPopup(count) {
        const window = document.getElementById('chat-window');
        // Only show if chat window is closed AND not snoozed
        if ((window.style.display === 'none' || window.style.display === '') && (!snoozeUntil || new Date() > snoozeUntil)) {
            document.getElementById('unread-modal-count').innerText = count;
            if (typeof $ !== 'undefined') {
                $('#unread-message-modal').modal('show');
            }
        }
    }

    function acknowledgeMessages() {
        if (typeof $ !== 'undefined') {
            $('#unread-message-modal').modal('hide');
        }
        toggleChatWindow();
    }

    function snoozeNotifications() {
        const snoozeTime = new Date(new Date().getTime() + 5 * 60000); // 5 minutes
        snoozeUntil = snoozeTime;
        localStorage.setItem('chat_snooze_until', snoozeTime.toISOString());

        if (typeof $ !== 'undefined') {
            $('#unread-message-modal').modal('hide');
        }
    }

    // ============================================
    // CONVERSATION RENDERING
    // ============================================

    function renderWidgetConversations(conversations, meta) {
        const container = document.getElementById('conversation-list-container');
        container.innerHTML = '';

        if (meta.isEmpty) {
            container.innerHTML = '<div class="text-center p-4 text-muted">No conversations yet</div>';
            document.getElementById('load-more-conversations-container').style.display = 'none';
            return;
        }

        conversations.forEach(conv => {
            const div = document.createElement('div');
            div.className = 'list-group-item conversation-item d-flex align-items-center p-3';
            div.onclick = () => widgetChatCore.openConversation(conv.id);

            let preview = 'Start chatting';
            if (conv.latest_message) {
                const isMe = conv.latest_message.user_id === widgetChatCore.currentUserId;
                const prefix = isMe ? 'You: ' : '';
                preview = conv.latest_message.type === 'file'
                    ? prefix + '📎 Attachment'
                    : prefix + conv.latest_message.body;
            }

            div.innerHTML = `
                <div class="mr-3">${widgetChatCore.getAvatarHtml(conv, 40, 40)}</div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-truncate">${conv.display_name}</h6>
                        <small class="text-muted">${widgetChatCore.formatTime(conv.latest_message?.created_at)}</small>
                    </div>
                    <small class="text-muted text-truncate d-block">
                        ${preview}
                    </small>
                </div>
            `;
            container.appendChild(div);
        });

        // Show/Hide Load More
        const loadMoreBtn = document.getElementById('load-more-conversations-container');
        loadMoreBtn.style.display = meta.hasMore ? 'block' : 'none';
    }

    function openWidgetConversation(conv) {
        document.getElementById('chat-conversation-list').style.display = 'none';
        document.getElementById('chat-message-area').style.display = 'flex';
        document.getElementById('back-to-list-btn').style.display = 'block';

        // Update Header
        document.getElementById('chat-header-title').innerText = conv.display_name;

        // Show Participants
        const participantsDiv = document.getElementById('chat-header-participants');
        if (conv.participants_list && conv.participants_list.length > 0) {
            const others = conv.participants_list.filter(p => p.id !== widgetChatCore.currentUserId);
            const names = others.map(p => p.name).join(', ');
            participantsDiv.innerText = names;
            participantsDiv.style.display = 'block';
            participantsDiv.title = names;
        } else {
            participantsDiv.style.display = 'none';
        }

        // Clear messages area
        document.getElementById('messages-container').innerHTML = '<div class="text-center p-2"><small>Loading messages...</small></div>';
    }

    function loadMoreConversations() {
        widgetChatCore.loadMoreConversations();
    }

    // ============================================
    // MESSAGE RENDERING
    // ============================================

    function renderWidgetMessages(messages, meta) {
        const container = document.getElementById('messages-container');

        if (meta.isInitial) {
            container.innerHTML = '';
        }

        if (messages.length === 0 && meta.isInitial) {
            container.innerHTML = '<div class="text-center text-muted mt-3">No messages yet. Say hello!</div>';
            return;
        }

        messages.forEach(msg => appendWidgetMessage(msg));

        // Always scroll to bottom on initial load, otherwise only if user is near bottom
        if (meta.isInitial) {
            scrollWidgetToBottom();
        } else if (!meta.isPolling && widgetChatCore.isUserNearBottom(container)) {
            scrollWidgetToBottom();
        }

        // Show/hide load previous button
        const loadPrevBtn = document.getElementById('load-prev-messages-container');
        loadPrevBtn.style.display = meta.hasMore ? 'block' : 'none';
    }

    function appendWidgetMessage(msg) {
        if (document.getElementById(`msg-${msg.id}`)) return;

        const container = document.getElementById('messages-container');
        const isMe = msg.user_id === widgetChatCore.currentUserId;

        const div = document.createElement('div');
        div.id = `msg-${msg.id}`;
        div.className = `message-bubble ${isMe ? 'message-sent' : 'message-received'}`;
        div.style.position = 'relative';

        let content = '';

        // Check if message is deleted
        if (msg.is_deleted || msg.body === 'This message was deleted') {
            content = '<div style="font-style: italic; opacity: 0.6;">This message was deleted</div>';
        } else {
            // Attachments
            if (msg.attachments && msg.attachments.length > 0) {
                content += '<div class="mb-2">';
                msg.attachments.forEach(att => {
                    const icon = widgetChatCore.getFileIcon(att.file_name);
                    content += `
                        <a href="{{ url('storage') }}/${att.file_path}" target="_blank" class="d-block text-decoration-none mb-1 ${isMe ? 'text-white' : 'text-dark'}" style="background: rgba(0,0,0,0.1); padding: 5px; border-radius: 5px;">
                            <i class="mdi ${icon}"></i> <span style="font-weight: 500;">${att.file_name}</span>
                        </a>
                    `;
                });
                content += '</div>';
            }

            // Body (Caption)
            if (msg.body) {
                content += `<div>${msg.body}</div>`;
            }
        }

        // Add delete button for sent messages (not deleted)
        const deleteBtn = (isMe && !msg.is_deleted && msg.body !== 'This message was deleted')
            ? `<button class="message-delete-btn" onclick="widgetChatCore.deleteMessage(${msg.id})" title="Delete message"><i class="mdi mdi-delete-outline"></i></button>`
            : '';

        div.innerHTML = `
            ${content}
            <div class="text-right mt-1" style="font-size: 10px; opacity: 0.7;">
                ${widgetChatCore.formatTime(msg.created_at)}
            </div>
            ${deleteBtn}
        `;

        container.appendChild(div);
    }

    function prependWidgetMessages(messages) {
        const container = document.getElementById('messages-container');
        const oldScrollHeight = container.scrollHeight;
        const oldScrollTop = container.scrollTop;

        const fragment = document.createDocumentFragment();
        messages.forEach(msg => {
            const isMe = msg.user_id === widgetChatCore.currentUserId;
            const div = document.createElement('div');
            div.id = `msg-${msg.id}`;
            div.className = `message-bubble ${isMe ? 'message-sent' : 'message-received'}`;

            let content = '';
            if (msg.attachments && msg.attachments.length > 0) {
                content += '<div class="mb-2">';
                msg.attachments.forEach(att => {
                    content += `
                        <a href="{{ url('storage') }}/${att.file_path}" target="_blank" class="d-block text-decoration-none mb-1" style="background: rgba(0,0,0,0.1); padding: 5px; border-radius: 5px;">
                            <i class="mdi ${widgetChatCore.getFileIcon(att.file_name)}"></i>
                            <span>${att.file_name}</span>
                        </a>
                    `;
                });
                content += '</div>';
            }
            if (msg.body) content += `<div>${msg.body}</div>`;

            div.innerHTML = `
                ${content}
                <div class="text-right mt-1" style="font-size: 10px; opacity: 0.7;">
                    ${widgetChatCore.formatTime(msg.created_at)}
                </div>
            `;
            fragment.appendChild(div);
        });

        container.insertBefore(fragment, container.firstChild);
        // Keep scroll at the top to show oldest fetched message
        container.scrollTop = 0;
    }

    function loadPreviousMessages() {
        widgetChatCore.loadPreviousMessages();
    }

    function scrollWidgetToBottom() {
        const container = document.getElementById('messages-container');
        widgetChatCore.scrollToBottom(container);
    }

    // ============================================
    // MESSAGE SENDING
    // ============================================

    function sendMessage(e) {
        e.preventDefault();
        const input = document.getElementById('chat-input');
        const fileInput = document.getElementById('chat-file-input');
        const body = input.value.trim();

        if (!body && !fileInput.files.length) return;

        widgetChatCore.sendMessage(body, Array.from(fileInput.files));
    }

    function clearWidgetInput() {
        document.getElementById('chat-input').value = '';
        document.getElementById('chat-file-input').value = '';
        document.getElementById('file-preview').style.display = 'none';
    }

    function handleFileSelect(input) {
        const preview = document.getElementById('file-preview');
        if (input.files && input.files.length > 0) {
            preview.innerText = `Selected: ${input.files.length} file(s)`;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    // ============================================
    // SEARCH & SELECTION
    // ============================================

    function switchSearchMode(mode) {
        const buttons = document.querySelectorAll('.mode-btn');
        const slider = document.querySelector('.mode-slider');
        const searchInput = document.getElementById('user-search-input');

        buttons.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === mode));
        slider.className = 'mode-slider mode-' + mode;

        widgetChatCore.setSearchMode(mode);
        searchInput.value = '';
        document.getElementById('user-search-results').style.display = 'none';
    }

    function clearSearch() {
        document.getElementById('user-search-input').value = '';
        document.getElementById('user-search-results').style.display = 'none';
        const mode = document.querySelector('.mode-btn.active').dataset.mode;
        if (mode === 'chats') {
            widgetChatCore.loadConversations();
        }
    }

    function updateWidgetSearchPlaceholder(mode) {
        const input = document.getElementById('user-search-input');
        input.placeholder = mode === 'people' ? 'Search people...' : 'Search conversations...';
    }

    // Search Logic
    const searchInput = document.getElementById('user-search-input');
    const searchResults = document.getElementById('user-search-results');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value;
        const mode = document.querySelector('.mode-btn.active').dataset.mode;

        if (mode === 'chats') {
            searchTimeout = setTimeout(() => widgetChatCore.loadConversations(), 400);
            return;
        }

        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(async () => {
            const users = await widgetChatCore.searchUsers(query);
            searchResults.innerHTML = '';
            searchResults.style.display = 'block';

            if (users.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
                return;
            }

            users.forEach(user => {
                const a = document.createElement('a');
                a.className = 'list-group-item list-group-item-action';
                a.href = '#';
                a.innerHTML = `
                    <div class="d-flex flex-column py-1">
                        <strong class="mb-1" style="font-size: 14px;">${user.firstname} ${user.surname}</strong>
                        <div class="d-flex flex-wrap" style="gap: 4px;">
                            <span class="badge badge-info" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">${user.user_category}</span>
                            <span class="badge badge-secondary" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">${user.specialization}</span>
                            <span class="badge badge-light border" style="font-size: 10px; font-weight: normal; padding: 3px 6px;">${user.department}</span>
                        </div>
                    </div>
                `;
                a.onclick = (e) => {
                    e.preventDefault();
                    widgetChatCore.addToSelection(user);
                    searchResults.style.display = 'none';
                    searchInput.value = '';
                };
                searchResults.appendChild(a);
            });
        }, 300);
    });

    function renderWidgetSelectedUsers(users) {
        const container = document.getElementById('selected-users-container');
        const btn = document.getElementById('start-chat-btn');

        container.innerHTML = '';

        if (users.length > 0) {
            container.style.display = 'flex';
            btn.style.display = 'block';

            users.forEach(user => {
                const chip = document.createElement('div');
                chip.className = 'badge badge-primary p-2 d-flex align-items-center';
                chip.innerHTML = `
                    ${user.firstname}
                    <span class="ml-2 cursor-pointer" onclick="widgetChatCore.removeFromSelection(${user.id})">&times;</span>
                `;
                container.appendChild(chip);
            });
        } else {
            container.style.display = 'none';
            btn.style.display = 'none';
        }
    }

    function initiateChat() {
        widgetChatCore.startConversationWithSelected();
    }
</script>
