import 'dart:async';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/api/chat_api_service.dart';

/// Full-screen chat thread for a single conversation.
class ChatThreadScreen extends StatefulWidget {
  final ChatApiService chatApi;
  final Map<String, dynamic> conversation;
  final int myUserId;

  const ChatThreadScreen({
    super.key,
    required this.chatApi,
    required this.conversation,
    required this.myUserId,
  });

  @override
  State<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends State<ChatThreadScreen> {
  final _msgCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  List<Map<String, dynamic>> _messages = [];
  bool _loading = true;
  bool _sending = false;
  Timer? _pollTimer;
  int? _lastMessageId;

  int get _convId => widget.conversation['id'] as int;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _markAsRead();
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) => _poll());
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    setState(() => _loading = true);
    final res = await widget.chatApi.getMessages(_convId);
    if (!mounted) return;
    if (res.success && res.rawBody is List) {
      final msgs = (res.rawBody as List).cast<Map<String, dynamic>>();
      setState(() {
        _messages = msgs;
        _loading = false;
        if (msgs.isNotEmpty) _lastMessageId = msgs.last['id'];
      });
      _scrollToBottom();
    } else {
      setState(() => _loading = false);
    }
  }

  Future<void> _poll() async {
    if (_lastMessageId == null) return;
    final res = await widget.chatApi.getMessages(_convId, afterId: _lastMessageId);
    if (!mounted) return;
    if (res.success && res.rawBody is List) {
      final newMsgs = (res.rawBody as List).cast<Map<String, dynamic>>();
      if (newMsgs.isNotEmpty) {
        setState(() {
          _messages.addAll(newMsgs);
          _lastMessageId = newMsgs.last['id'];
        });
        _markAsRead();
        _scrollToBottom();
      }
    }
  }

  void _markAsRead() {
    widget.chatApi.markAsRead(_convId);
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    _msgCtrl.clear();
    final res = await widget.chatApi.sendMessage(_convId, text);
    if (!mounted) return;
    setState(() => _sending = false);
    if (res.success) {
      final msg = res.rawBody is Map<String, dynamic> ? res.rawBody : res.data;
      if (msg is Map<String, dynamic>) {
        setState(() {
          _messages.add(msg);
          _lastMessageId = msg['id'];
        });
        _scrollToBottom();
      }
    }
  }

  void _deleteMessage(int msgId, int index) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Message'),
        content: const Text('This message will be removed for everyone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      final res = await widget.chatApi.deleteMessage(msgId);
      if (res.success && mounted) {
        setState(() {
          _messages[index] = {
            ..._messages[index],
            'is_deleted': true,
            'body': null,
          };
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = widget.conversation['display_name'] ?? 'Chat';
    final initials = widget.conversation['initials'] ?? '';
    final avatarUrl = widget.conversation['avatar_url'];

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
              child: avatarUrl == null ? Text(initials, style: const TextStyle(fontSize: 12)) : null,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          // Messages list
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _messages.isEmpty
                    ? Center(
                        child: Text('No messages yet', style: TextStyle(color: cs.outline)),
                      )
                    : ListView.builder(
                        controller: _scrollCtrl,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        itemCount: _messages.length,
                        itemBuilder: (ctx, i) {
                          final msg = _messages[i];
                          final isMine = msg['user_id'] == widget.myUserId;
                          final prevMsg = i > 0 ? _messages[i - 1] : null;
                          final showSender = !isMine &&
                              (prevMsg == null || prevMsg['user_id'] != msg['user_id']);
                          return _MessageBubble(
                            msg: msg,
                            isMine: isMine,
                            showSender: showSender,
                            onDelete: isMine && msg['is_deleted'] != true
                                ? () => _deleteMessage(msg['id'], i)
                                : null,
                          );
                        },
                      ),
          ),

          // Input bar
          Container(
            decoration: BoxDecoration(
              color: cs.surface,
              border: Border(top: BorderSide(color: cs.outlineVariant.withValues(alpha: 0.3))),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: SafeArea(
              top: false,
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _msgCtrl,
                      maxLines: 4,
                      minLines: 1,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: InputDecoration(
                        hintText: 'Type a message...',
                        filled: true,
                        fillColor: cs.surfaceContainerHighest,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24),
                          borderSide: BorderSide.none,
                        ),
                      ),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 6),
                  _sending
                      ? const SizedBox(
                          width: 40,
                          height: 40,
                          child: Padding(
                            padding: EdgeInsets.all(8),
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      : IconButton(
                          icon: Icon(Icons.send_rounded, color: cs.primary),
                          onPressed: _send,
                        ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Message Bubble
// ═════════════════════════════════════════════════════════════════

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> msg;
  final bool isMine;
  final bool showSender;
  final VoidCallback? onDelete;

  const _MessageBubble({
    required this.msg,
    required this.isMine,
    required this.showSender,
    this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDeleted = msg['is_deleted'] == true;
    final body = msg['body'] ?? '';
    final senderName = msg['sender_name'] ?? '';
    final attachments = msg['attachments'] as List? ?? [];
    final createdAt = DateTime.tryParse(msg['created_at'] ?? '');
    final timeStr = createdAt != null
        ? '${createdAt.hour.toString().padLeft(2, '0')}:${createdAt.minute.toString().padLeft(2, '0')}'
        : '';

    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: EdgeInsets.only(
          top: showSender ? 12 : 2,
          bottom: 2,
          left: isMine ? 64 : 0,
          right: isMine ? 0 : 64,
        ),
        child: Column(
          crossAxisAlignment: isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            if (showSender)
              Padding(
                padding: const EdgeInsets.only(left: 12, bottom: 2),
                child: Text(senderName,
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: cs.primary)),
              ),
            GestureDetector(
              onLongPress: onDelete,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: isDeleted
                      ? cs.surfaceContainerHighest
                      : isMine
                          ? cs.primary
                          : cs.surfaceContainerHighest,
                  borderRadius: BorderRadius.only(
                    topLeft: const Radius.circular(16),
                    topRight: const Radius.circular(16),
                    bottomLeft: Radius.circular(isMine ? 16 : 4),
                    bottomRight: Radius.circular(isMine ? 4 : 16),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (isDeleted)
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.block, size: 14, color: cs.outline),
                          const SizedBox(width: 4),
                          Text('This message was deleted',
                              style: TextStyle(fontStyle: FontStyle.italic, color: cs.outline, fontSize: 13)),
                        ],
                      )
                    else ...[
                      if (body.isNotEmpty)
                        Text(
                          body,
                          style: TextStyle(
                            color: isMine ? cs.onPrimary : cs.onSurface,
                            fontSize: 14,
                          ),
                        ),
                      if (attachments.isNotEmpty) ...[
                        if (body.isNotEmpty) const SizedBox(height: 6),
                        ...attachments.map((a) => _AttachmentChip(
                              attachment: a is Map<String, dynamic> ? a : {},
                              isMine: isMine,
                            )),
                      ],
                    ],
                    const SizedBox(height: 4),
                    Text(
                      timeStr,
                      style: TextStyle(
                        fontSize: 10,
                        color: isDeleted
                            ? cs.outline
                            : isMine
                                ? cs.onPrimary.withValues(alpha: 0.7)
                                : cs.outline,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Attachment Chip
// ═════════════════════════════════════════════════════════════════

class _AttachmentChip extends StatelessWidget {
  final Map<String, dynamic> attachment;
  final bool isMine;

  const _AttachmentChip({required this.attachment, required this.isMine});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = attachment['file_name'] ?? 'File';
    final url = attachment['url'] ?? '';
    final size = attachment['file_size'];
    String sizeStr = '';
    if (size is int) {
      if (size > 1048576) {
        sizeStr = '${(size / 1048576).toStringAsFixed(1)} MB';
      } else {
        sizeStr = '${(size / 1024).toStringAsFixed(0)} KB';
      }
    }

    return GestureDetector(
      onTap: () {
        if (url.isNotEmpty) {
          launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        }
      },
      child: Container(
        margin: const EdgeInsets.only(top: 4),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isMine ? cs.onPrimary.withValues(alpha: 0.15) : cs.primary.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.attach_file, size: 16,
                color: isMine ? cs.onPrimary : cs.primary),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12,
                  color: isMine ? cs.onPrimary : cs.primary,
                  decoration: TextDecoration.underline,
                ),
              ),
            ),
            if (sizeStr.isNotEmpty) ...[
              const SizedBox(width: 6),
              Text(sizeStr,
                  style: TextStyle(
                    fontSize: 10,
                    color: isMine ? cs.onPrimary.withValues(alpha: 0.7) : cs.outline,
                  )),
            ],
          ],
        ),
      ),
    );
  }
}
