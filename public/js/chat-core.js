/**
 * CoreHealth Chat System - Shared JavaScript Core
 * Single source of truth for all chat functionality
 */

class ChatCore {
    constructor(config = {}) {
        this.currentUserId = config.currentUserId;
        this.activeConversationId = null;
        this.lastMessageId = 0;
        this.firstMessageId = 0;
        this.allConversations = [];
        this.selectedUsers = [];
        this.pollingInterval = null;
        this.unreadPollingInterval = null;
        this.typingTimeout = null;
        this.currentConversationPage = 1;
        this.lastConversationPage = 1;
        this.conversationFilter = 'all'; // all, unread, archived

        // Store callbacks from config
        const callbacks = config.callbacks || {};
        this.getSearchQuery = callbacks.getSearchQuery || (() => '');
        this.onConversationsLoaded = callbacks.onConversationsLoaded || (() => {});
        this.onConversationOpened = callbacks.onConversationOpened || (() => {});
        this.onMessagesLoaded = callbacks.onMessagesLoaded || (() => {});
        this.onMessageSent = callbacks.onMessageSent || (() => {});
        this.onMessageReceived = callbacks.onMessageReceived || (() => {});
        this.onPreviousMessagesLoaded = callbacks.onPreviousMessagesLoaded || (() => {});
        this.onSelectionChanged = callbacks.onSelectionChanged || (() => {});
        this.onSearchModeChanged = callbacks.onSearchModeChanged || (() => {});
        this.onUnreadCountChanged = callbacks.onUnreadCountChanged || (() => {});
        this.onTypingIndicator = callbacks.onTypingIndicator || (() => {});

        // Routes
        this.routes = config.routes || {};
        this.routes.deleteMessage = this.routes.deleteMessage || '/chat/message';
        this.routes.archive = this.routes.archive || '/chat/archive';
        this.routes.unarchive = this.routes.unarchive || '/chat/unarchive';
    }

    // ============================================
    // CONVERSATION MANAGEMENT
    // ============================================

    async loadConversations(page = 1, append = false, searchQuery = '', searchMode = 'people') {
        if (searchMode === 'people' && searchQuery.length >= 2) return;

        let url = `${this.routes.conversations}?page=${page}`;

        if (searchMode === 'chats' && searchQuery) {
            url += `&q=${encodeURIComponent(searchQuery)}`;
        }

        if (this.conversationFilter !== 'all') {
            url += `&filter=${this.conversationFilter}`;
        }

        try {
            const response = await fetch(url);
            const data = await response.json();

            const conversations = data.data ? data.data : data;
            this.allConversations = append ? [...this.allConversations, ...conversations] : conversations;
            this.currentConversationPage = data.current_page || 1;
            this.lastConversationPage = data.last_page || 1;

            this.onConversationsLoaded(this.allConversations, {
                append,
                hasMore: this.currentConversationPage < this.lastConversationPage,
                isEmpty: this.allConversations.length === 0
            });

            return conversations;
        } catch (error) {
            console.error('Error loading conversations:', error);
            return [];
        }
    }

    loadMoreConversations() {
        if (this.currentConversationPage < this.lastConversationPage) {
            return this.loadConversations(this.currentConversationPage + 1, true);
        }
    }

    setConversationFilter(filter) {
        this.conversationFilter = filter;
        return this.loadConversations(1, false);
    }

    openConversation(id) {
        const conv = this.allConversations.find(c => c.id === id);
        if (!conv) return;

        this.activeConversationId = id;
        this.lastMessageId = 0;
        this.firstMessageId = 0;

        this.onConversationOpened(conv);
        this.loadMessages(id);
        this.startMessagePolling(id);
        this.markAsRead(id);
    }

    closeConversation() {
        this.stopMessagePolling();
        this.activeConversationId = null;
        this.lastMessageId = 0;
        this.firstMessageId = 0;
    }

    // ============================================
    // MESSAGE MANAGEMENT
    // ============================================

    async loadMessages(id, isPolling = false) {
        if (!id) return;

        try {
            // Only send after_id for polling (when we already have messages)
            let url = `${this.routes.messages}/${id}`;
            if (isPolling && this.lastMessageId > 0) {
                url += `?after_id=${this.lastMessageId}`;
            }

            const response = await fetch(url);
            const messages = await response.json();

            if (messages.length > 0) {
                messages.forEach(msg => {
                    if (msg.id > this.lastMessageId) this.lastMessageId = msg.id;
                    if (this.firstMessageId === 0 || msg.id < this.firstMessageId) {
                        this.firstMessageId = msg.id;
                    }
                });

                const meta = {
                    isPolling,
                    isInitial: !isPolling,
                    hasMore: messages.length >= 20
                };
                this.onMessagesLoaded(messages, meta);
            }

            return messages;
        } catch (error) {
            console.error('Error loading messages:', error);
            return [];
        }
    }

    async loadPreviousMessages() {
        if (!this.activeConversationId || this.firstMessageId === 0) return null;

        try {
            const response = await fetch(
                `${this.routes.messages}/${this.activeConversationId}?before_id=${this.firstMessageId}`
            );
            const messages = await response.json();

            if (messages.length > 0) {
                messages.forEach(msg => {
                    if (msg.id < this.firstMessageId) this.firstMessageId = msg.id;
                });
            }

            const meta = { hasMore: messages.length >= 20 };
            this.onPreviousMessagesLoaded(messages, meta);

            return { messages, hasMore: messages.length >= 20 };
        } catch (error) {
            console.error('Error loading previous messages:', error);
            return null;
        }
    }

    async sendMessage(body, files = []) {
        if (!this.activeConversationId || (!body.trim() && files.length === 0)) {
            return null;
        }

        const formData = new FormData();
        formData.append('conversation_id', this.activeConversationId);
        formData.append('body', body);

        for (let i = 0; i < files.length; i++) {
            formData.append('attachments[]', files[i]);
        }

        try {
            const response = await fetch(this.routes.send, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const message = await response.json();

            if (message.id) {
                if (message.id > this.lastMessageId) this.lastMessageId = message.id;
                this.onMessageSent(message);
            }

            return message;
        } catch (error) {
            console.error('Error sending message:', error);
            return null;
        }
    }

    async markAsRead(conversationId) {
        try {
            await fetch(`${this.routes.markRead}/${conversationId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
        } catch (error) {
            console.error('Error marking as read:', error);
        }
    }

    async deleteMessage(messageId) {
        if (!confirm('Delete this message? This cannot be undone.')) {
            return false;
        }

        try {
            const response = await fetch(`${this.routes.deleteMessage}/${messageId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (response.ok) {
                // Reload messages to show deleted state
                if (this.activeConversationId) {
                    await this.loadMessages(this.activeConversationId);
                }
                return true;
            } else {
                alert(result.error || 'Failed to delete message');
                return false;
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            alert('Failed to delete message');
            return false;
        }
    }

    async archiveConversation(conversationId) {
        try {
            const response = await fetch(`${this.routes.archive}/${conversationId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (response.ok) {
                // Reload conversations
                await this.loadConversations();
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error archiving conversation:', error);
            return false;
        }
    }

    async unarchiveConversation(conversationId) {
        try {
            const response = await fetch(`${this.routes.unarchive}/${conversationId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const result = await response.json();

            if (response.ok) {
                // Reload conversations
                await this.loadConversations();
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error unarchiving conversation:', error);
            return false;
        }
    }

    // ============================================
    // POLLING
    // ============================================

    startMessagePolling(id) {
        if (this.pollingInterval) clearInterval(this.pollingInterval);
        this.pollingInterval = setInterval(() => {
            if (this.activeConversationId === id) {
                this.loadMessages(id, true);
            }
        }, 3000);
    }

    stopMessagePolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    startUnreadPolling() {
        if (this.unreadPollingInterval) clearInterval(this.unreadPollingInterval);

        // Check immediately
        this.checkUnreadCount();

        // Then poll every 5 seconds
        this.unreadPollingInterval = setInterval(() => {
            this.checkUnreadCount();
        }, 5000);
    }

    stopUnreadPolling() {
        if (this.unreadPollingInterval) {
            clearInterval(this.unreadPollingInterval);
            this.unreadPollingInterval = null;
        }
    }

    async checkUnreadCount() {
        if (!this.routes.checkUnread) return;

        try {
            const response = await fetch(this.routes.checkUnread);
            const data = await response.json();
            const count = data.unread_count || 0;

            this.onUnreadCountChanged(count);
        } catch (error) {
            console.error('Error checking unread count:', error);
        }
    }

    // ============================================
    // USER SEARCH & SELECTION
    // ============================================

    async searchUsers(query) {
        if (query.length < 2) return [];

        try {
            const response = await fetch(`${this.routes.searchUsers}?q=${encodeURIComponent(query)}`);
            return await response.json();
        } catch (error) {
            console.error('Error searching users:', error);
            return [];
        }
    }

    addToSelection(user) {
        if (this.selectedUsers.some(u => u.id === user.id)) return;
        this.selectedUsers.push(user);
        this.onSelectionChanged(this.selectedUsers);
        return this.selectedUsers;
    }

    removeFromSelection(userId) {
        this.selectedUsers = this.selectedUsers.filter(u => u.id !== userId);
        this.onSelectionChanged(this.selectedUsers);
        return this.selectedUsers;
    }

    clearSelection() {
        this.selectedUsers = [];
        return this.selectedUsers;
    }

    async createConversation(userIds) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken || !csrfToken.content) {
                throw new Error('CSRF token not found');
            }

            const response = await fetch(this.routes.create, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken.content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ user_ids: userIds })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server response:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const conversation = await response.json();
            await this.loadConversations();
            return conversation;
        } catch (error) {
            console.error('Error creating conversation:', error);
            alert('Failed to create conversation. Please try again.');
            return null;
        }
    }

    async startConversationWithSelected() {
        if (this.selectedUsers.length === 0) return;

        const userIds = this.selectedUsers.map(u => u.id);

        const conversation = await this.createConversation(userIds);

        if (conversation && conversation.id) {
            this.selectedUsers = [];
            this.onSelectionChanged(this.selectedUsers);
            this.openConversation(conversation.id);
        }

        return conversation;
    }

    setSearchMode(mode) {
        this.searchMode = mode;
        this.onSearchModeChanged(mode);
    }

    // ============================================
    // TYPING INDICATOR
    // ============================================

    notifyTyping() {
        if (!this.activeConversationId) return;

        // Clear previous timeout
        if (this.typingTimeout) clearTimeout(this.typingTimeout);

        // Send typing indicator
        fetch(`${this.routes.typing}/${this.activeConversationId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).catch(() => {});

        // Auto-clear after 3 seconds
        this.typingTimeout = setTimeout(() => {
            this.typingTimeout = null;
        }, 3000);
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    getAvatarHtml(data, width, height) {
        if (data.avatar_type === 'image') {
            return `<img src="${data.avatar_src}" class="rounded-circle" width="${width}" height="${height}" style="object-fit: cover;">`;
        } else {
            const fontSize = parseInt(width) * 0.4;
            return `<div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold"
                 style="width: ${width}px; height: ${height}px; background-color: ${data.avatar_color}; font-size: ${fontSize}px;">
                ${data.avatar_initials}
            </div>`;
        }
    }

    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'mdi-file-image';
        if (['pdf'].includes(ext)) return 'mdi-file-pdf';
        if (['doc', 'docx'].includes(ext)) return 'mdi-file-word';
        if (['xls', 'xlsx'].includes(ext)) return 'mdi-file-excel';
        if (['zip', 'rar', '7z'].includes(ext)) return 'mdi-folder-zip';
        return 'mdi-file-document';
    }

    formatTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (days === 0) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (days === 1) {
            return 'Yesterday';
        } else if (days < 7) {
            return date.toLocaleDateString([], { weekday: 'short' });
        } else {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
    }

    isUserNearBottom(container) {
        const threshold = 100;
        return container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
    }

    scrollToBottom(container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatCore;
}
