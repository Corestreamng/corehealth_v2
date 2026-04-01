import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import '../../core/api/chat_api_service.dart';
import '../../core/storage/local_storage.dart';
import 'chat_thread_screen.dart';

class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen> {
  late ChatApiService _chatApi;
  List<Map<String, dynamic>> _conversations = [];
  bool _loading = true;
  String _filter = 'all'; // all, unread, archived
  String _search = '';
  final _searchCtrl = TextEditingController();
  Timer? _pollTimer;
  int? _myUserId;

  @override
  void initState() {
    super.initState();
    final base = LocalStorage.baseUrl ?? '';
    _chatApi = ChatApiService(base);
    _loadMyUserId();
    _load();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _load(silent: true));
  }

  void _loadMyUserId() {
    final json = LocalStorage.userJson;
    if (json != null) {
      final user = jsonDecode(json);
      _myUserId = user['id'];
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) setState(() => _loading = true);
    final res = await _chatApi.getConversations(
      filter: _filter,
      search: _search.isEmpty ? null : _search,
    );
    if (!mounted) return;
    if (res.success) {
      final raw = res.rawBody;
      List items = [];
      if (raw is Map && raw['data'] is List) {
        items = raw['data'];
      } else if (raw is List) {
        items = raw;
      }
      setState(() {
        _conversations = items.cast<Map<String, dynamic>>();
        _loading = false;
      });
    } else if (!silent) {
      setState(() => _loading = false);
    }
  }

  void _onFilterChanged(String f) {
    setState(() => _filter = f);
    _load();
  }

  void _openNewChat() async {
    final result = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(builder: (_) => _SearchUsersScreen(chatApi: _chatApi)),
    );
    if (result != null && mounted) {
      _openThread(result);
    }
  }

  void _openThread(Map<String, dynamic> conv) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatThreadScreen(
          chatApi: _chatApi,
          conversation: conv,
          myUserId: _myUserId ?? 0,
        ),
      ),
    );
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: Row(
              children: [
                _FilterChip(label: 'All', selected: _filter == 'all', onTap: () => _onFilterChanged('all')),
                const SizedBox(width: 8),
                _FilterChip(label: 'Unread', selected: _filter == 'unread', onTap: () => _onFilterChanged('unread')),
                const SizedBox(width: 8),
                _FilterChip(label: 'Archived', selected: _filter == 'archived', onTap: () => _onFilterChanged('archived')),
                const Spacer(),
                SizedBox(
                  width: 160,
                  height: 36,
                  child: TextField(
                    controller: _searchCtrl,
                    style: const TextStyle(fontSize: 14),
                    decoration: InputDecoration(
                      hintText: 'Search...',
                      prefixIcon: const Icon(Icons.search, size: 18),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(vertical: 8),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(20),
                        borderSide: BorderSide.none,
                      ),
                      filled: true,
                      fillColor: cs.surfaceContainerHighest,
                    ),
                    onChanged: (v) {
                      _search = v;
                      _load();
                    },
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _openNewChat,
        child: const Icon(Icons.edit_outlined),
      ),
      body: _loading && _conversations.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _conversations.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.chat_bubble_outline, size: 64, color: cs.outline),
                      const SizedBox(height: 12),
                      Text('No conversations', style: TextStyle(color: cs.outline, fontSize: 16)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    itemCount: _conversations.length,
                    separatorBuilder: (_, __) =>
                        Divider(height: 1, indent: 72, color: cs.outlineVariant.withValues(alpha: 0.3)),
                    itemBuilder: (ctx, i) => _ConversationTile(
                      conv: _conversations[i],
                      onTap: () => _openThread(_conversations[i]),
                      onArchive: _filter != 'archived'
                          ? () async {
                              await _chatApi.archiveConversation(_conversations[i]['id']);
                              _load();
                            }
                          : null,
                      onUnarchive: _filter == 'archived'
                          ? () async {
                              await _chatApi.unarchiveConversation(_conversations[i]['id']);
                              _load();
                            }
                          : null,
                    ),
                  ),
                ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Conversation Tile
// ═════════════════════════════════════════════════════════════════

class _ConversationTile extends StatelessWidget {
  final Map<String, dynamic> conv;
  final VoidCallback onTap;
  final VoidCallback? onArchive;
  final VoidCallback? onUnarchive;

  const _ConversationTile({
    required this.conv,
    required this.onTap,
    this.onArchive,
    this.onUnarchive,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = conv['display_name'] ?? 'Unknown';
    final initials = conv['initials'] ?? '??';
    final avatarUrl = conv['avatar_url'];
    final avatarColor = _parseColor(conv['avatar_color']);
    final unread = conv['unread_count'] ?? 0;
    final latest = conv['latest_message'] as Map<String, dynamic>?;

    String subtitle = '';
    String time = '';
    if (latest != null) {
      final body = latest['body'] ?? '';
      final isMine = latest['is_mine'] == true;
      final type = latest['type'] ?? 'text';
      if (type == 'file' && (body == null || body.isEmpty)) {
        subtitle = isMine ? 'You sent an attachment' : 'Sent an attachment';
      } else {
        subtitle = isMine ? 'You: $body' : body;
      }
      final dt = DateTime.tryParse(latest['created_at'] ?? '');
      if (dt != null) {
        final now = DateTime.now();
        if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
          time = '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
        } else {
          time = '${dt.day}/${dt.month}/${dt.year}';
        }
      }
    }

    return Dismissible(
      key: ValueKey(conv['id']),
      direction: onArchive != null
          ? DismissDirection.endToStart
          : (onUnarchive != null ? DismissDirection.startToEnd : DismissDirection.none),
      background: Container(
        color: Colors.green,
        alignment: Alignment.centerLeft,
        padding: const EdgeInsets.only(left: 24),
        child: const Icon(Icons.unarchive, color: Colors.white),
      ),
      secondaryBackground: Container(
        color: Colors.orange,
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 24),
        child: const Icon(Icons.archive, color: Colors.white),
      ),
      confirmDismiss: (dir) async {
        if (dir == DismissDirection.endToStart && onArchive != null) {
          onArchive!();
        } else if (dir == DismissDirection.startToEnd && onUnarchive != null) {
          onUnarchive!();
        }
        return false;
      },
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(
          radius: 24,
          backgroundColor: avatarColor,
          backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
          child: avatarUrl == null
              ? Text(initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14))
              : null,
        ),
        title: Row(
          children: [
            Expanded(
              child: Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontWeight: unread > 0 ? FontWeight.w700 : FontWeight.w500,
                  fontSize: 15,
                ),
              ),
            ),
            if (time.isNotEmpty)
              Text(time,
                  style: TextStyle(
                    fontSize: 12,
                    color: unread > 0 ? cs.primary : cs.outline,
                    fontWeight: unread > 0 ? FontWeight.w600 : FontWeight.normal,
                  )),
          ],
        ),
        subtitle: Row(
          children: [
            Expanded(
              child: Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13,
                  color: unread > 0 ? cs.onSurface : cs.outline,
                  fontWeight: unread > 0 ? FontWeight.w500 : FontWeight.normal,
                ),
              ),
            ),
            if (unread > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: cs.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  unread > 99 ? '99+' : '$unread',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Color _parseColor(String? hex) {
    if (hex == null || hex.isEmpty) return Colors.blueGrey;
    hex = hex.replaceFirst('#', '');
    if (hex.length == 6) hex = 'FF$hex';
    return Color(int.parse(hex, radix: 16));
  }
}

// ═════════════════════════════════════════════════════════════════
//  Filter Chip
// ═════════════════════════════════════════════════════════════════

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? cs.primary : cs.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? cs.onPrimary : cs.onSurface,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Search Users Screen (for new conversation)
// ═════════════════════════════════════════════════════════════════

class _SearchUsersScreen extends StatefulWidget {
  final ChatApiService chatApi;
  const _SearchUsersScreen({required this.chatApi});

  @override
  State<_SearchUsersScreen> createState() => _SearchUsersScreenState();
}

class _SearchUsersScreenState extends State<_SearchUsersScreen> {
  final _ctrl = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  bool _searching = false;
  Timer? _debounce;

  @override
  void dispose() {
    _ctrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearch(String q) {
    _debounce?.cancel();
    if (q.length < 2) {
      setState(() => _results = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () async {
      setState(() => _searching = true);
      final res = await widget.chatApi.searchUsers(q);
      if (!mounted) return;
      if (res.success && res.rawBody is List) {
        setState(() {
          _results = (res.rawBody as List).cast<Map<String, dynamic>>();
          _searching = false;
        });
      } else {
        setState(() => _searching = false);
      }
    });
  }

  void _selectUser(Map<String, dynamic> user) async {
    final userId = user['id'] as int;
    final res = await widget.chatApi.createConversation([userId]);
    if (!mounted) return;
    if (res.success) {
      final conv = res.rawBody is Map<String, dynamic> ? res.rawBody : res.data;
      if (conv is Map<String, dynamic>) {
        Navigator.pop(context, conv);
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : 'Failed to create conversation')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('New Message'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _ctrl,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'Search staff by name...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onChanged: _onSearch,
            ),
          ),
          if (_searching) const LinearProgressIndicator(),
          Expanded(
            child: _results.isEmpty
                ? Center(
                    child: Text(
                      _ctrl.text.length < 2 ? 'Type at least 2 characters' : 'No results',
                      style: TextStyle(color: cs.outline),
                    ),
                  )
                : ListView.builder(
                    itemCount: _results.length,
                    itemBuilder: (ctx, i) {
                      final u = _results[i];
                      final name = u['name'] ?? '';
                      final cat = u['category'] ?? '';
                      final dept = u['department'] ?? '';
                      final spec = u['specialization'] ?? '';
                      final avatar = u['avatar_url'];
                      final initials = u['initials'] ?? '';
                      final sub = [cat, spec, dept].where((s) => s.isNotEmpty).join(' · ');
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundImage: avatar != null ? NetworkImage(avatar) : null,
                          child: avatar == null ? Text(initials) : null,
                        ),
                        title: Text(name),
                        subtitle: Text(sub, maxLines: 1, overflow: TextOverflow.ellipsis),
                        onTap: () => _selectUser(u),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
