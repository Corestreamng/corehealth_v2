import 'dart:convert';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/api/chat_api_service.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';
import '../chat/conversations_screen.dart';
import '../profile/doctor_profile_screen.dart';
import '../profile/doctor_settings_screen.dart';
import '../queue/queue_screen.dart';
import '../server_setup/server_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  Map<String, dynamic>? _user;
  late EncounterApiService _encounterApi;
  late ChatApiService _chatApi;

  // Dashboard live stats from queueStats endpoint
  Map<String, dynamic> _stats = {};
  bool _statsLoading = true;

  // Recent investigations preview (top 5)
  List<Map<String, dynamic>> _recentInvestigations = [];
  bool _investigationsLoading = true;

  // Chat unread badge
  int _unreadCount = 0;
  Timer? _unreadTimer;

  @override
  void initState() {
    super.initState();
    _loadUser();
    final baseUrl = LocalStorage.baseUrl ?? '';
    _encounterApi = EncounterApiService(baseUrl);
    _chatApi = ChatApiService(baseUrl);
    _loadDashboardData();
    _pollUnread();
    _unreadTimer = Timer.periodic(const Duration(seconds: 10), (_) => _pollUnread());
  }

  @override
  void dispose() {
    _unreadTimer?.cancel();
    super.dispose();
  }

  Future<void> _pollUnread() async {
    final res = await _chatApi.getUnreadCount();
    if (mounted && res.success) {
      final count = (res.data?['unread_count'] ?? res.rawBody?['unread_count'] ?? 0);
      setState(() => _unreadCount = count is int ? count : int.tryParse('$count') ?? 0);
    }
  }

  void _loadUser() {
    final json = LocalStorage.userJson;
    if (json != null) {
      setState(() => _user = jsonDecode(json));
    }
  }

  Future<void> _loadDashboardData() async {
    setState(() {
      _statsLoading = true;
      _investigationsLoading = true;
    });

    // Fetch stats and investigations in parallel
    final results = await Future.wait([
      _encounterApi.getQueueStats(),
      _encounterApi.getMyInvestigations(perPage: 5),
    ]);

    if (!mounted) return;
    setState(() {
      if (results[0].success && results[0].data != null) {
        _stats = results[0].data!;
      }
      _statsLoading = false;

      if (results[1].success) {
        final rawBody = results[1].rawBody;
        if (rawBody is Map<String, dynamic> && rawBody.containsKey('data')) {
          _recentInvestigations = List<Map<String, dynamic>>.from(
              (rawBody['data'] as List?) ?? []);
        } else if (rawBody is List) {
          _recentInvestigations =
              List<Map<String, dynamic>>.from(rawBody);
        }
      }
      _investigationsLoading = false;
    });
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Sign Out'),
        content: const Text('Are you sure you want to sign out?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red.shade600,
            ),
            child: const Text('Sign Out'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    try {
      final serverConfig = context.read<ServerConfigProvider>();
      final client = ApiClient(serverConfig.baseUrl!);
      await client.logout();
    } catch (_) {}

    await LocalStorage.clearSession();

    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  void _openQueue() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => const QueueScreen()))
        .then((_) => _loadDashboardData());
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();
    final primary = theme.primaryColor;
    final userName = _user?['name'] ?? 'Doctor';
    final roles = (_user?['roles'] as List?)?.join(', ') ?? '';

    return Scaffold(
      // ── App Bar ──
      appBar: AppBar(
        title: Text(
          theme.siteName.isNotEmpty ? theme.siteName : 'CoreHealth',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 18),
        ),
        actions: [
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.chat_bubble_outline),
                onPressed: () {
                  Navigator.push(context,
                    MaterialPageRoute(builder: (_) => const ConversationsScreen()),
                  ).then((_) => _pollUnread());
                },
              ),
              if (_unreadCount > 0)
                Positioned(
                  right: 6,
                  top: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                    child: Text(
                      _unreadCount > 99 ? '99+' : '$_unreadCount',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),

      // ── Body ──
      body: IndexedStack(
        index: _currentIndex,
        children: [
          _DashboardTab(
            userName: userName,
            roles: roles,
            primary: primary,
            stats: _stats,
            statsLoading: _statsLoading,
            recentInvestigations: _recentInvestigations,
            investigationsLoading: _investigationsLoading,
            onOpenQueue: _openQueue,
            onRefreshStats: _loadDashboardData,
            onViewInvestigations: () => setState(() => _currentIndex = 2),
          ),
          const QueueScreen(embedded: true),
          _InvestigationsTab(encounterApi: _encounterApi),
          _ProfileTab(
            user: _user,
            primary: primary,
            onLogout: _logout,
          ),
        ],
      ),

      // ── Bottom Nav ──
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (i) => setState(() => _currentIndex = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard_rounded),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.queue_rounded),
            selectedIcon: Icon(Icons.queue_rounded),
            label: 'Queue',
          ),
          NavigationDestination(
            icon: Icon(Icons.biotech_outlined),
            selectedIcon: Icon(Icons.biotech_rounded),
            label: 'Investigations',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 1 — Dashboard
// ═══════════════════════════════════════════════════════════════════════════════

class _DashboardTab extends StatelessWidget {
  final String userName;
  final String roles;
  final Color primary;
  final Map<String, dynamic> stats;
  final bool statsLoading;
  final List<Map<String, dynamic>> recentInvestigations;
  final bool investigationsLoading;
  final VoidCallback onOpenQueue;
  final VoidCallback onRefreshStats;
  final VoidCallback onViewInvestigations;

  const _DashboardTab({
    required this.userName,
    required this.roles,
    required this.primary,
    required this.stats,
    required this.statsLoading,
    required this.recentInvestigations,
    required this.investigationsLoading,
    required this.onOpenQueue,
    required this.onRefreshStats,
    required this.onViewInvestigations,
  });

  @override
  Widget build(BuildContext context) {
    final waiting = stats['waiting'] ?? 0;
    final ready = stats['ready'] ?? 0;
    final inConsult = stats['in_consult'] ?? 0;
    final completed = stats['completed'] ?? 0;
    final total = stats['total'] ?? 0;

    return RefreshIndicator(
      onRefresh: () async => onRefreshStats(),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        physics: const AlwaysScrollableScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Greeting
            Text(
              'Hello, $userName 👋',
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
            ),
            if (roles.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(roles,
                    style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
              ),
            const SizedBox(height: 24),

            // Queue stats — 2x2 grid
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    icon: Icons.person_add_alt_1_rounded,
                    label: 'Waiting',
                    value: statsLoading ? '…' : '${waiting + ready}',
                    color: primary,
                    onTap: onOpenQueue,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    icon: Icons.replay_rounded,
                    label: 'In Consult',
                    value: statsLoading ? '…' : '$inConsult',
                    color: const Color(0xFF00897B),
                    onTap: onOpenQueue,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    icon: Icons.queue_rounded,
                    label: 'Total Queue',
                    value: statsLoading ? '…' : '$total',
                    color: const Color(0xFF5E35B1),
                    onTap: onOpenQueue,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    icon: Icons.check_circle_outline_rounded,
                    label: 'Completed Today',
                    value: statsLoading ? '…' : '$completed',
                    color: const Color(0xFFE65100),
                    onTap: onOpenQueue,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 28),

            // Recent investigations preview
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Recent Investigations',
                    style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey.shade800)),
                if (recentInvestigations.isNotEmpty)
                  TextButton(
                    onPressed: onViewInvestigations,
                    child: const Text('View All'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (investigationsLoading)
              const Center(
                  child: Padding(
                      padding: EdgeInsets.all(20),
                      child: CircularProgressIndicator(strokeWidth: 2)))
            else if (recentInvestigations.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Center(
                    child: Text('No recent investigations',
                        style: TextStyle(color: Colors.grey.shade500)),
                  ),
                ),
              )
            else
              ...recentInvestigations.map((inv) => _InvestigationPreviewCard(
                    item: inv,
                  )),
            const SizedBox(height: 24),

            // Quick actions
            Text('Quick Actions',
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.grey.shade800)),
            const SizedBox(height: 12),
            _ActionTile(
              icon: Icons.queue_rounded,
              title: 'Patient Queue',
              subtitle: 'View and manage your patient queue',
              color: primary,
              onTap: onOpenQueue,
            ),
            _ActionTile(
              icon: Icons.biotech_rounded,
              title: 'All Investigations',
              subtitle: 'Lab & imaging orders overview',
              color: const Color(0xFF5E35B1),
              onTap: onViewInvestigations,
            ),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 3 — Investigations (Lab + Imaging orders by this doctor)
// ═══════════════════════════════════════════════════════════════════════════════

class _InvestigationsTab extends StatefulWidget {
  final EncounterApiService encounterApi;

  const _InvestigationsTab({required this.encounterApi});

  @override
  State<_InvestigationsTab> createState() => _InvestigationsTabState();
}

class _InvestigationsTabState extends State<_InvestigationsTab>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String _type = 'all';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(() {
      if (_tabController.indexIsChanging) return;
      final types = ['all', 'lab', 'imaging'];
      final newType = types[_tabController.index];
      if (newType != _type) {
        _type = newType;
        _load();
      }
    });
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final result =
        await widget.encounterApi.getMyInvestigations(type: _type, perPage: 50);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (result.success) {
        final raw = result.rawBody;
        if (raw is Map && raw.containsKey('data') && raw['data'] is List) {
          _items = List<Map<String, dynamic>>.from(raw['data']);
        } else {
          _items = [];
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'All'),
            Tab(text: 'Lab Orders'),
            Tab(text: 'Imaging'),
          ],
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _items.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 100),
                          Center(
                            child: Column(
                              children: [
                                Icon(Icons.biotech_outlined,
                                    size: 64, color: Colors.grey.shade300),
                                const SizedBox(height: 16),
                                Text('No investigations found',
                                    style: TextStyle(
                                        fontSize: 16,
                                        color: Colors.grey.shade500)),
                              ],
                            ),
                          ),
                        ],
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        itemBuilder: (ctx, i) =>
                            _InvestigationCard(item: _items[i]),
                      ),
          ),
        ),
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 4 — Profile
// ═══════════════════════════════════════════════════════════════════════════════

class _ProfileTab extends StatelessWidget {
  final Map<String, dynamic>? user;
  final Color primary;
  final VoidCallback onLogout;

  const _ProfileTab({
    required this.user,
    required this.primary,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    final name = user?['name'] ?? 'Doctor';
    final email = user?['email'] ?? '';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const SizedBox(height: 20),
          // Avatar
          CircleAvatar(
            radius: 48,
            backgroundColor: primary.withValues(alpha: 0.15),
            child: Text(
              name.isNotEmpty ? name[0].toUpperCase() : 'D',
              style: TextStyle(
                fontSize: 36,
                fontWeight: FontWeight.w600,
                color: primary,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            name,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (email.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                email,
                style: TextStyle(color: Colors.grey.shade600),
              ),
            ),
          const SizedBox(height: 32),

          // Menu items
          _profileMenuItem(
            icon: Icons.person_outline_rounded,
            label: 'My Profile',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DoctorProfileScreen()),
            ),
          ),
          _profileMenuItem(
            icon: Icons.settings_outlined,
            label: 'Settings',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DoctorSettingsScreen()),
            ),
          ),
          _profileMenuItem(
            icon: Icons.swap_horiz_rounded,
            label: 'Change Server',
            onTap: () async {
              await LocalStorage.clearAll();
              if (!context.mounted) return;
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                  builder: (_) =>
                      const ServerSetupScreen(),
                ),
              );
            },
          ),
          const SizedBox(height: 12),
          _profileMenuItem(
            icon: Icons.logout_rounded,
            label: 'Sign Out',
            color: Colors.red.shade600,
            onTap: onLogout,
          ),
        ],
      ),
    );
  }

  Widget _profileMenuItem({
    required IconData icon,
    required String label,
    Color? color,
    required VoidCallback onTap,
  }) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(icon, color: color ?? Colors.grey.shade700),
        title: Text(
          label,
          style: TextStyle(
            color: color ?? Colors.grey.shade800,
            fontWeight: FontWeight.w500,
          ),
        ),
        trailing: Icon(Icons.chevron_right_rounded,
            color: Colors.grey.shade400),
        onTap: onTap,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  Shared widgets
// ═══════════════════════════════════════════════════════════════════════════════

Color _statusColor(int status, String type) {
  if (type == 'lab') {
    return switch (status) {
      1 => Colors.orange,
      2 => Colors.blue,
      3 => Colors.indigo,
      4 => Colors.green,
      5 => Colors.amber.shade700,
      6 => Colors.red,
      _ => Colors.grey,
    };
  }
  // imaging
  return switch (status) {
    1 => Colors.orange,
    2 => Colors.blue,
    3 => Colors.green,
    5 => Colors.amber.shade700,
    6 => Colors.red,
    _ => Colors.grey,
  };
}

class _InvestigationCard extends StatelessWidget {
  final Map<String, dynamic> item;
  const _InvestigationCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final type = item['type'] ?? 'lab';
    final isLab = type == 'lab';
    final status = item['status'] ?? 0;
    final statusLabel = item['status_label'] ?? 'Unknown';
    final color = _statusColor(status as int, type as String);
    final hasResult =
        item['result'] != null && (item['result'] as String).isNotEmpty;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  isLab ? Icons.science_rounded : Icons.image_rounded,
                  size: 18,
                  color: isLab ? Colors.blue.shade700 : Colors.purple.shade700,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    item['service_name'] ?? 'Unknown',
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    statusLabel as String,
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: color),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Patient: ${item['patient_name'] ?? 'Unknown'}',
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
            ),
            if (item['note'] != null &&
                (item['note'] as String).isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                'Note: ${item['note']}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
            if (hasResult) ...[
              const Divider(height: 16),
              Text(
                'Result: ${item['result']}',
                style: const TextStyle(fontSize: 13),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _InvestigationPreviewCard extends StatelessWidget {
  final Map<String, dynamic> item;
  const _InvestigationPreviewCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final type = item['type'] ?? 'lab';
    final isLab = type == 'lab';
    final status = item['status'] ?? 0;
    final statusLabel = item['status_label'] ?? 'Unknown';
    final color = _statusColor(status as int, type as String);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(
          isLab ? Icons.science_rounded : Icons.image_rounded,
          color: isLab ? Colors.blue.shade700 : Colors.purple.shade700,
        ),
        title: Text(
          item['service_name'] ?? 'Unknown',
          style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Text(
          item['patient_name'] ?? '',
          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
        ),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            statusLabel as String,
            style: TextStyle(
                fontSize: 11, fontWeight: FontWeight.w600, color: color),
          ),
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final VoidCallback? onTap;

  const _StatCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(height: 16),
              Text(
                value,
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  color: Colors.grey.shade900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey.shade600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback? onTap;

  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
        leading: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: color),
        ),
        title: Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        subtitle: Text(subtitle),
        trailing:
            Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        onTap: onTap ??
            () {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('$title — coming soon')),
              );
            },
      ),
    );
  }
}
