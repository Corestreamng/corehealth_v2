import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import 'patient_chat_thread_screen.dart';

/// Lists doctors the patient has had encounters with.
/// Tapping a doctor opens / creates a 1-on-1 conversation.
class MyDoctorsChatScreen extends StatefulWidget {
  final PatientApiService api;

  const MyDoctorsChatScreen({super.key, required this.api});

  @override
  State<MyDoctorsChatScreen> createState() => _MyDoctorsChatScreenState();
}

class _MyDoctorsChatScreenState extends State<MyDoctorsChatScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  List<Map<String, dynamic>> _doctors = [];
  List<Map<String, dynamic>> _conversations = [];
  bool _doctorsLoading = true;
  bool _convsLoading = true;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
    _loadMyDoctors();
    _loadConversations();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      _loadConversations(silent: true);
    });
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadMyDoctors() async {
    setState(() => _doctorsLoading = true);
    final res = await widget.api.getMyDoctors();
    if (!mounted) return;
    if (res.success) {
      final raw = res.data is List ? res.data : [];
      setState(() {
        _doctors = List<Map<String, dynamic>>.from(raw);
        _doctorsLoading = false;
      });
    } else {
      setState(() => _doctorsLoading = false);
    }
  }

  Future<void> _loadConversations({bool silent = false}) async {
    if (!silent) setState(() => _convsLoading = true);
    final res = await widget.api.getConversations();
    if (!mounted) return;
    if (res.success) {
      List items = [];
      if (res.data is List) {
        items = res.data;
      } else if (res.data is Map && res.data['data'] is List) {
        items = res.data['data'];
      }
      setState(() {
        _conversations = items.whereType<Map<String, dynamic>>().toList();
        _convsLoading = false;
      });
    } else if (!silent) {
      setState(() => _convsLoading = false);
    }
  }

  void _openDoctorChat(Map<String, dynamic> doctor) async {
    final uid = doctor['user_id'];
    final userId = uid is int ? uid : int.parse('$uid');
    // Create or get existing conversation
    final res = await widget.api.createConversation([userId]);
    if (!mounted) return;
    if (res.success) {
      final conv = res.data is Map<String, dynamic> ? res.data : null;
      if (conv is Map<String, dynamic>) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PatientChatThreadScreen(
              api: widget.api,
              conversation: conv,
            ),
          ),
        );
        _loadConversations();
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : 'Failed to open chat')),
      );
    }
  }

  void _openConversation(Map<String, dynamic> conv) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PatientChatThreadScreen(
          api: widget.api,
          conversation: conv,
        ),
      ),
    );
    _loadConversations();
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        bottom: TabBar(
          controller: _tabCtrl,
          tabs: const [
            Tab(text: 'My Doctors'),
            Tab(text: 'Conversations'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabCtrl,
        children: [
          // Tab 1: My Doctors
          _doctorsLoading
              ? const Center(child: CircularProgressIndicator())
              : _doctors.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.medical_services_outlined, size: 64, color: cs.outline),
                          const SizedBox(height: 12),
                          Text('No doctors found', style: TextStyle(color: cs.outline)),
                          const SizedBox(height: 4),
                          Text('You will see doctors after your first visit.',
                              style: TextStyle(color: cs.outline, fontSize: 12)),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _loadMyDoctors,
                      child: ListView.builder(
                        itemCount: _doctors.length,
                        itemBuilder: (ctx, i) {
                          final doc = _doctors[i];
                          return _DoctorTile(
                            doctor: doc,
                            onTap: () => _openDoctorChat(doc),
                          );
                        },
                      ),
                    ),

          // Tab 2: Active Conversations
          _convsLoading && _conversations.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : _conversations.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.chat_bubble_outline, size: 64, color: cs.outline),
                          const SizedBox(height: 12),
                          Text('No conversations yet', style: TextStyle(color: cs.outline)),
                          const SizedBox(height: 4),
                          Text('Tap a doctor to start messaging.',
                              style: TextStyle(color: cs.outline, fontSize: 12)),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _loadConversations,
                      child: ListView.separated(
                        itemCount: _conversations.length,
                        separatorBuilder: (_, __) =>
                            Divider(height: 1, indent: 72, color: cs.outlineVariant.withValues(alpha: 0.3)),
                        itemBuilder: (ctx, i) => _ConvTile(
                          conv: _conversations[i],
                          onTap: () => _openConversation(_conversations[i]),
                        ),
                      ),
                    ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Doctor Tile
// ═════════════════════════════════════════════════════════════════

class _DoctorTile extends StatelessWidget {
  final Map<String, dynamic> doctor;
  final VoidCallback onTap;

  const _DoctorTile({required this.doctor, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = doctor['name'] ?? 'Doctor';
    final initials = doctor['initials'] ?? '';
    final avatar = doctor['avatar_url'];
    final cat = doctor['category'] ?? '';
    final spec = doctor['specialization'] ?? '';
    final dept = doctor['department'] ?? '';
    final visits = doctor['encounter_count'] ?? 0;
    final lastVisit = doctor['last_visit'];

    final subtitle = [cat, spec, dept].where((s) => s.isNotEmpty).join(' · ');

    String lastStr = '';
    if (lastVisit != null) {
      final dt = DateTime.tryParse(lastVisit);
      if (dt != null) lastStr = 'Last visit: ${dt.day}/${dt.month}/${dt.year}';
    }

    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        radius: 24,
        backgroundColor: cs.primaryContainer,
        backgroundImage: avatar != null ? NetworkImage(avatar) : null,
        child: avatar == null
            ? Text(initials, style: TextStyle(color: cs.onPrimaryContainer, fontWeight: FontWeight.w600))
            : null,
      ),
      title: Text(name, style: const TextStyle(fontWeight: FontWeight.w500)),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (subtitle.isNotEmpty)
            Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 12, color: cs.outline)),
          if (lastStr.isNotEmpty)
            Text(lastStr, style: TextStyle(fontSize: 11, color: cs.outline)),
        ],
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.chat_bubble_outline, color: cs.primary, size: 20),
          Text('$visits visits', style: TextStyle(fontSize: 10, color: cs.outline)),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════
//  Conversation Tile (reused pattern)
// ═════════════════════════════════════════════════════════════════

class _ConvTile extends StatelessWidget {
  final Map<String, dynamic> conv;
  final VoidCallback onTap;

  const _ConvTile({required this.conv, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final name = conv['display_name'] ?? 'Unknown';
    final initials = conv['initials'] ?? '??';
    final avatarUrl = conv['avatar_url'];
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

    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        radius: 24,
        backgroundColor: cs.primaryContainer,
        backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
        child: avatarUrl == null
            ? Text(initials, style: TextStyle(color: cs.onPrimaryContainer, fontWeight: FontWeight.w600, fontSize: 13))
            : null,
      ),
      title: Row(
        children: [
          Expanded(
            child: Text(name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontWeight: unread > 0 ? FontWeight.w700 : FontWeight.w500, fontSize: 15)),
          ),
          if (time.isNotEmpty)
            Text(time,
                style: TextStyle(
                  fontSize: 12,
                  color: unread > 0 ? cs.primary : cs.outline,
                )),
        ],
      ),
      subtitle: Row(
        children: [
          Expanded(
            child: Text(subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13,
                  color: unread > 0 ? cs.onSurface : cs.outline,
                  fontWeight: unread > 0 ? FontWeight.w500 : FontWeight.normal,
                )),
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
    );
  }
}
