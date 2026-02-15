import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';
import '../health/encounters_screen.dart';
import '../health/lab_results_screen.dart';
import '../health/imaging_results_screen.dart';
import '../health/prescriptions_screen.dart';
import '../health/vitals_screen.dart';
import '../health/procedures_screen.dart';
import '../server_setup/server_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  Map<String, dynamic>? _patient;

  @override
  void initState() {
    super.initState();
    _loadPatient();
  }

  void _loadPatient() {
    final json = LocalStorage.patientJson;
    if (json != null) {
      setState(() => _patient = jsonDecode(json));
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
    final firstName = _patient?['first_name'] ?? 'Patient';
    final lastName = _patient?['last_name'] ?? '';
    final fullName = '$firstName $lastName'.trim();
    final cardNo = _patient?['card_no'] ?? '';

    return Scaffold(
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
      body: IndexedStack(
        index: _currentIndex,
        children: [
          _DashboardTab(
            name: fullName,
            cardNo: cardNo,
            patient: _patient,
            primary: primary,
          ),
          const _VisitsTab(),
          const _ResultsTab(),
          _ProfileTab(
            patient: _patient,
            primary: primary,
            onLogout: _logout,
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (i) => setState(() => _currentIndex = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            selectedIcon: Icon(Icons.calendar_month_rounded),
            label: 'Visits',
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
  final String name;
  final String cardNo;
  final Map<String, dynamic>? patient;
  final Color primary;

  const _DashboardTab({
    required this.name,
    required this.cardNo,
    required this.patient,
    required this.primary,
  });

  @override
  Widget build(BuildContext context) {
    final hmo = patient?['hmo'] as Map<String, dynamic>?;
    final bloodGroup = patient?['blood_group'] ?? '';
    final genotype = patient?['genotype'] ?? '';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Greeting card
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [primary, primary.withValues(alpha: 0.8)],
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Hello, $name 👋',
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
                if (cardNo.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Patient ID: $cardNo',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.white.withValues(alpha: 0.85),
                    ),
                  ),
                ],
                if (hmo != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      '🏥 ${hmo['name'] ?? 'HMO'} — ${hmo['plan'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Colors.white,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Quick info
          if (bloodGroup.isNotEmpty || genotype.isNotEmpty)
            Row(
              children: [
                if (bloodGroup.isNotEmpty)
                  Expanded(
                    child: _InfoChip(
                      icon: Icons.water_drop_rounded,
                      label: 'Blood Group',
                      value: bloodGroup,
                      color: Colors.red.shade600,
                    ),
                  ),
                if (bloodGroup.isNotEmpty && genotype.isNotEmpty)
                  const SizedBox(width: 12),
                if (genotype.isNotEmpty)
                  Expanded(
                    child: _InfoChip(
                      icon: Icons.biotech_rounded,
                      label: 'Genotype',
                      value: genotype,
                      color: Colors.purple.shade600,
                    ),
                  ),
              ],
            ),
          const SizedBox(height: 24),

          // Quick actions
          Text(
            'Quick Access',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade800,
            ),
          ),
          const SizedBox(height: 16),
          _ActionTile(
            icon: Icons.event_note_rounded,
            title: 'My Visits',
            subtitle: 'View your consultation history',
            color: primary,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const EncountersScreen())),
          ),
          _ActionTile(
            icon: Icons.science_rounded,
            title: 'Lab Results',
            subtitle: 'View your latest test results',
            color: const Color(0xFF5E35B1),
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const LabResultsScreen())),
          ),
          _ActionTile(
            icon: Icons.image_rounded,
            title: 'Imaging Results',
            subtitle: 'X-rays, scans & imaging reports',
            color: const Color(0xFF00695C),
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const ImagingResultsScreen())),
          ),
          _ActionTile(
            icon: Icons.medication_rounded,
            title: 'My Prescriptions',
            subtitle: 'Current & past medications',
            color: const Color(0xFFE65100),
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const PrescriptionsScreen())),
          ),
          _ActionTile(
            icon: Icons.monitor_heart_rounded,
            title: 'My Vitals',
            subtitle: 'Blood pressure, temperature & more',
            color: Colors.red.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const VitalsScreen())),
          ),
          _ActionTile(
            icon: Icons.medical_services_rounded,
            title: 'My Procedures',
            subtitle: 'Surgeries & medical procedures',
            color: const Color(0xFF00897B),
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const ProceduresScreen())),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 2 — Visits (Encounters)
// ═══════════════════════════════════════════════════════════════════════════════

class _VisitsTab extends StatelessWidget {
  const _VisitsTab();

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('My Health Records',
              style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: Colors.grey.shade800)),
          const SizedBox(height: 8),
          Text('Access all your medical records in one place',
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
          const SizedBox(height: 24),
          _HealthMenuTile(
            icon: Icons.event_note_rounded,
            title: 'Visit History',
            subtitle: 'All doctor consultations',
            color: primary,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const EncountersScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.monitor_heart_rounded,
            title: 'Vital Signs',
            subtitle: 'BP, temperature, heart rate',
            color: Colors.red.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const VitalsScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.medical_services_rounded,
            title: 'Procedures',
            subtitle: 'Surgeries & medical procedures',
            color: Colors.teal.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const ProceduresScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.medication_rounded,
            title: 'Prescriptions',
            subtitle: 'Medication history',
            color: Colors.orange.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const PrescriptionsScreen())),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
//  TAB 3 — Results (Lab + Imaging)
// ═══════════════════════════════════════════════════════════════════════════════

class _ResultsTab extends StatelessWidget {
  const _ResultsTab();

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Test Results',
              style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: Colors.grey.shade800)),
          const SizedBox(height: 8),
          Text('View your lab and imaging results',
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
          const SizedBox(height: 24),
          _HealthMenuTile(
            icon: Icons.science_rounded,
            title: 'Lab Results',
            subtitle: 'Blood tests, urinalysis & more',
            color: Colors.blue.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const LabResultsScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.image_rounded,
            title: 'Imaging Results',
            subtitle: 'X-rays, CT scans, MRI & ultrasound',
            color: Colors.purple.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const ImagingResultsScreen())),
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
  final Map<String, dynamic>? patient;
  final Color primary;
  final VoidCallback onLogout;

  const _ProfileTab({
    required this.patient,
    required this.primary,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    final firstName = patient?['first_name'] ?? '';
    final lastName = patient?['last_name'] ?? '';
    final fullName = '$firstName $lastName'.trim();
    final cardNo = patient?['card_no'] ?? '';
    final phone = patient?['phone'] ?? '';
    final email = patient?['email'] ?? '';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const SizedBox(height: 20),
          CircleAvatar(
            radius: 48,
            backgroundColor: primary.withValues(alpha: 0.15),
            child: Text(
              firstName.isNotEmpty ? firstName[0].toUpperCase() : 'P',
              style: TextStyle(
                fontSize: 36,
                fontWeight: FontWeight.w600,
                color: primary,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            fullName.isNotEmpty ? fullName : 'Patient',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
          ),
          if (cardNo.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'ID: $cardNo',
                style: TextStyle(color: Colors.grey.shade600),
              ),
            ),
          const SizedBox(height: 24),

          // Info cards
          if (phone.isNotEmpty)
            _profileInfoRow(Icons.phone_outlined, 'Phone', phone),
          if (email.isNotEmpty)
            _profileInfoRow(Icons.email_outlined, 'Email', email),
          const SizedBox(height: 16),

          // Menu items
          _profileMenuItem(
            icon: Icons.medical_information_outlined,
            label: 'Medical Records',
            onTap: () {},
          ),
          _profileMenuItem(
            icon: Icons.settings_outlined,
            label: 'Settings',
            onTap: () {},
          ),
          _profileMenuItem(
            icon: Icons.swap_horiz_rounded,
            label: 'Change Hospital',
            onTap: () async {
              await LocalStorage.clearAll();
              if (!context.mounted) return;
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                    builder: (_) => const ServerSetupScreen()),
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

  Widget _profileInfoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Card(
        child: ListTile(
          leading: Icon(icon, color: Colors.grey.shade600),
          title: Text(label,
              style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          subtitle: Text(value,
              style: const TextStyle(fontWeight: FontWeight.w500)),
        ),
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
        trailing:
            Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400),
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

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _InfoChip({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style:
                        TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: color,
                  ),
                ),
              ],
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
        title: Text(title,
            style: const TextStyle(fontWeight: FontWeight.w600)),
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

class _HealthMenuTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _HealthMenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: color, size: 28),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: const TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(subtitle,
                        style: TextStyle(
                            fontSize: 12, color: Colors.grey.shade600)),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400),
            ],
          ),
        ),
      ),
    );
  }
}
