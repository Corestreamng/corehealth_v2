import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';
import '../server_setup/server_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  void _loadUser() {
    final json = LocalStorage.userJson;
    if (json != null) {
      setState(() => _user = jsonDecode(json));
    }
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
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Notifications coming soon')),
              );
            },
          ),
        ],
      ),

      // ── Body ──
      body: IndexedStack(
        index: _currentIndex,
        children: [
          _DashboardTab(userName: userName, roles: roles, primary: primary),
          const _PatientsTab(),
          const _ResultsTab(),
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
            icon: Icon(Icons.people_outline_rounded),
            selectedIcon: Icon(Icons.people_rounded),
            label: 'Patients',
          ),
          NavigationDestination(
            icon: Icon(Icons.science_outlined),
            selectedIcon: Icon(Icons.science_rounded),
            label: 'Results',
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

  const _DashboardTab({
    required this.userName,
    required this.roles,
    required this.primary,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Greeting
          Text(
            'Hello, $userName 👋',
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (roles.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                roles,
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey.shade600,
                ),
              ),
            ),
          const SizedBox(height: 24),

          // Quick stats cards
          Row(
            children: [
              Expanded(
                child: _StatCard(
                  icon: Icons.people_alt_rounded,
                  label: 'Queue',
                  value: '—',
                  color: primary,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: _StatCard(
                  icon: Icons.edit_note_rounded,
                  label: 'Consultations',
                  value: '—',
                  color: Color(0xFF00897B),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Row(
            children: [
              Expanded(
                child: _StatCard(
                  icon: Icons.science_rounded,
                  label: 'Lab Pending',
                  value: '—',
                  color: Color(0xFF5E35B1),
                ),
              ),
              SizedBox(width: 12),
              Expanded(
                child: _StatCard(
                  icon: Icons.medication_rounded,
                  label: 'Prescriptions',
                  value: '—',
                  color: Color(0xFFE65100),
                ),
              ),
            ],
          ),
          const SizedBox(height: 32),

          // Quick actions
          Text(
            'Quick Actions',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade800,
            ),
          ),
          const SizedBox(height: 16),
          _ActionTile(
            icon: Icons.add_circle_outline_rounded,
            title: 'New Consultation',
            subtitle: 'Start a new patient encounter',
            color: primary,
          ),
          const _ActionTile(
            icon: Icons.search_rounded,
            title: 'Search Patient',
            subtitle: 'Find patient by name or ID',
            color: Color(0xFF00897B),
          ),
          const _ActionTile(
            icon: Icons.assignment_turned_in_outlined,
            title: 'Review Results',
            subtitle: 'Lab & imaging results pending review',
            color: Color(0xFF5E35B1),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 2 — Patients (placeholder)
// ═══════════════════════════════════════════════════════════════════════════════

class _PatientsTab extends StatelessWidget {
  const _PatientsTab();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.people_outlined, size: 64, color: Colors.grey.shade300),
          const SizedBox(height: 16),
          Text(
            'Patient Queue',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Patient management features coming soon',
            style: TextStyle(color: Colors.grey.shade500),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 3 — Results (placeholder)
// ═══════════════════════════════════════════════════════════════════════════════

class _ResultsTab extends StatelessWidget {
  const _ResultsTab();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.science_outlined, size: 64, color: Colors.grey.shade300),
          const SizedBox(height: 16),
          Text(
            'Lab & Imaging Results',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade700,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Results viewer coming soon',
            style: TextStyle(color: Colors.grey.shade500),
          ),
        ],
      ),
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
            onTap: () {},
          ),
          _profileMenuItem(
            icon: Icons.settings_outlined,
            label: 'Settings',
            onTap: () {},
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

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _StatCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
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
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;

  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
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
        onTap: () {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$title — coming soon')),
          );
        },
      ),
    );
  }
}
