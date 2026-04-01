import 'dart:convert';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';
import '../chat/my_doctors_chat_screen.dart';
import '../health/encounters_screen.dart';
import '../health/lab_results_screen.dart';
import '../health/imaging_results_screen.dart';
import '../health/prescriptions_screen.dart';
import '../health/vitals_screen.dart';
import '../health/procedures_screen.dart';
import '../health/admissions_screen.dart';
import '../health/appointments_screen.dart';
import '../health/referrals_screen.dart';
import '../profile/patient_profile_screen.dart';
import '../profile/patient_settings_screen.dart';
import '../server_setup/server_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  Map<String, dynamic>? _patient;
  late PatientApiService _api;

  // Dashboard data
  Map<String, dynamic>? _profileData;
  List<Map<String, dynamic>> _latestVitals = [];
  List<Map<String, dynamic>> _recentPrescriptions = [];
  List<Map<String, dynamic>> _recentEncounters = [];
  List<Map<String, dynamic>> _upcomingAppointments = [];
  bool _dashLoading = true;

  // Chat unread badge
  int _unreadCount = 0;
  Timer? _unreadTimer;

  @override
  void initState() {
    super.initState();
    _loadPatient();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
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
    final res = await _api.getChatUnreadCount();
    if (mounted && res.success) {
      final d = res.data is Map ? res.data : null;
      final count = d?['unread_count'] ?? 0;
      setState(() => _unreadCount = count is int ? count : int.tryParse('$count') ?? 0);
    }
  }

  void _loadPatient() {
    final json = LocalStorage.patientJson;
    if (json != null) {
      setState(() => _patient = jsonDecode(json));
    }
  }

  Future<void> _loadDashboardData() async {
    setState(() => _dashLoading = true);

    final results = await Future.wait([
      _api.getProfile(),
      _api.getVitals(perPage: 3),
      _api.getPrescriptions(perPage: 5),
      _api.getEncounters(perPage: 3),
      _api.getAppointments(perPage: 3, filter: 'upcoming'),
    ]);

    if (!mounted) return;
    setState(() {
      if (results[0].success && results[0].data is Map) {
        _profileData = Map<String, dynamic>.from(results[0].data);
      }
      if (results[1].success && results[1].data is List) {
        _latestVitals = List<Map<String, dynamic>>.from(results[1].data);
      }
      if (results[2].success && results[2].data is List) {
        _recentPrescriptions =
            List<Map<String, dynamic>>.from(results[2].data);
      }
      if (results[3].success && results[3].data is List) {
        _recentEncounters =
            List<Map<String, dynamic>>.from(results[3].data);
      }
      if (results[4].success && results[4].data is List) {
        _upcomingAppointments =
            List<Map<String, dynamic>>.from(results[4].data);
      }
      _dashLoading = false;
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
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.chat_bubble_outline),
                onPressed: () {
                  Navigator.push(context,
                    MaterialPageRoute(builder: (_) => MyDoctorsChatScreen(api: _api)),
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
      body: IndexedStack(
        index: _currentIndex,
        children: [
          _DashboardTab(
            name: fullName,
            cardNo: cardNo,
            patient: _patient,
            primary: primary,
            profileData: _profileData,
            latestVitals: _latestVitals,
            recentPrescriptions: _recentPrescriptions,
            recentEncounters: _recentEncounters,
            upcomingAppointments: _upcomingAppointments,
            dashLoading: _dashLoading,
            onRefresh: _loadDashboardData,
          ),
          const _VisitsTab(),
          const _ResultsTab(),
          _ProfileTab(
            patient: _patient,
            profileData: _profileData,
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
  final Map<String, dynamic>? profileData;
  final List<Map<String, dynamic>> latestVitals;
  final List<Map<String, dynamic>> recentPrescriptions;
  final List<Map<String, dynamic>> recentEncounters;
  final List<Map<String, dynamic>> upcomingAppointments;
  final bool dashLoading;
  final VoidCallback onRefresh;

  const _DashboardTab({
    required this.name,
    required this.cardNo,
    required this.patient,
    required this.primary,
    required this.profileData,
    required this.latestVitals,
    required this.recentPrescriptions,
    required this.recentEncounters,
    required this.upcomingAppointments,
    required this.dashLoading,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final hmo = patient?['hmo'] as Map<String, dynamic>?;
    final bloodGroup = patient?['blood_group'] ?? '';
    final genotype = patient?['genotype'] ?? '';
    final allergies = profileData?['allergies'] as List? ?? [];

    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        physics: const AlwaysScrollableScrollPhysics(),
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
                  Text('Hello, $name 👋',
                      style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: Colors.white)),
                  if (cardNo.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text('Patient ID: $cardNo',
                        style: TextStyle(
                            fontSize: 14,
                            color: Colors.white.withValues(alpha: 0.85))),
                  ],
                  if (hmo != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        '🏥 ${hmo['name'] ?? 'HMO'} — ${hmo['plan'] ?? ''}',
                        style: const TextStyle(
                            fontSize: 12,
                            color: Colors.white,
                            fontWeight: FontWeight.w500),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Allergy banner
            if (allergies.isNotEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                margin: const EdgeInsets.only(bottom: 20),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.warning_amber_rounded,
                            color: Colors.red.shade700, size: 20),
                        const SizedBox(width: 8),
                        Text('Known Allergies',
                            style: TextStyle(
                                fontWeight: FontWeight.w600,
                                color: Colors.red.shade700)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: allergies
                          .map((a) => Chip(
                                label: Text(
                                    a is String ? a : (a['name'] ?? '$a'),
                                    style: TextStyle(
                                        fontSize: 12,
                                        color: Colors.red.shade700)),
                                backgroundColor: Colors.red.shade100,
                                materialTapTargetSize:
                                    MaterialTapTargetSize.shrinkWrap,
                                visualDensity: VisualDensity.compact,
                              ))
                          .toList(),
                    ),
                  ],
                ),
              ),

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
            const SizedBox(height: 20),

            // Latest vitals
            if (latestVitals.isNotEmpty) ...[
              Text('Latest Vitals',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey.shade800)),
              const SizedBox(height: 8),
              _VitalsPreviewCard(vitals: latestVitals.first),
              const SizedBox(height: 20),
            ],

            // Upcoming appointments
            if (upcomingAppointments.isNotEmpty) ...[
              Text('Upcoming Appointments',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey.shade800)),
              const SizedBox(height: 8),
              ...upcomingAppointments.take(3).map((appt) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.blue.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.calendar_today_rounded,
                            color: Colors.blue, size: 20),
                      ),
                      title: Text(
                          appt['doctor_name'] ?? 'Doctor',
                          style: const TextStyle(
                              fontWeight: FontWeight.w500, fontSize: 14)),
                      subtitle: Text(
                          '${appt['appointment_date'] ?? ''}${appt['start_time'] != null ? ' at ${appt['start_time']}' : ''}',
                          style: TextStyle(
                              fontSize: 12, color: Colors.grey.shade600)),
                      trailing: Icon(Icons.chevron_right_rounded,
                          color: Colors.grey.shade400),
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const AppointmentsScreen()),
                      ),
                    ),
                  )),
              const SizedBox(height: 20),
            ],

            // Recent encounter
            if (recentEncounters.isNotEmpty) ...[
              Text('Last Visit',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey.shade800)),
              const SizedBox(height: 8),
              _LastVisitCard(encounter: recentEncounters.first),
              const SizedBox(height: 20),
            ],

            // Active prescriptions
            if (recentPrescriptions.isNotEmpty) ...[
              Text('Recent Prescriptions',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey.shade800)),
              const SizedBox(height: 8),
              ...recentPrescriptions.take(3).map((rx) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: Icon(Icons.medication_rounded,
                          color: Colors.orange.shade700),
                      title: Text(rx['product_name'] ?? 'Unknown',
                          style: const TextStyle(
                              fontWeight: FontWeight.w500, fontSize: 14)),
                      subtitle: Text(
                          'Dose: ${rx['dose'] ?? 'N/A'} • ${rx['status_label'] ?? ''}',
                          style: TextStyle(
                              fontSize: 12, color: Colors.grey.shade600)),
                    ),
                  )),
              const SizedBox(height: 20),
            ],

            // Quick access
            Text('Quick Access',
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.grey.shade800)),
            const SizedBox(height: 12),
            Row(
              children: [
                _QuickButton(
                  icon: Icons.event_note_rounded,
                  label: 'Visits',
                  color: primary,
                  onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const EncountersScreen())),
                ),
                const SizedBox(width: 10),
                _QuickButton(
                  icon: Icons.science_rounded,
                  label: 'Lab',
                  color: Colors.blue.shade700,
                  onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const LabResultsScreen())),
                ),
                const SizedBox(width: 10),
                _QuickButton(
                  icon: Icons.medication_rounded,
                  label: 'Meds',
                  color: Colors.orange.shade700,
                  onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const PrescriptionsScreen())),
                ),
                const SizedBox(width: 10),
                _QuickButton(
                  icon: Icons.monitor_heart_rounded,
                  label: 'Vitals',
                  color: Colors.red.shade700,
                  onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const VitalsScreen())),
                ),
              ],
            ),
          ],
        ),
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
          _HealthMenuTile(
            icon: Icons.hotel_rounded,
            title: 'Admissions',
            subtitle: 'Hospital admission history',
            color: Colors.blueGrey.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const AdmissionsScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.calendar_today_rounded,
            title: 'Appointments',
            subtitle: 'Upcoming & past appointments',
            color: Colors.blue.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const AppointmentsScreen())),
          ),
          _HealthMenuTile(
            icon: Icons.swap_horiz_rounded,
            title: 'Referrals',
            subtitle: 'Specialist referrals',
            color: Colors.indigo.shade700,
            onTap: () => Navigator.push(context,
                MaterialPageRoute(builder: (_) => const ReferralsScreen())),
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
  final Map<String, dynamic>? profileData;
  final Color primary;
  final VoidCallback onLogout;

  const _ProfileTab({
    required this.patient,
    required this.profileData,
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
            icon: Icons.person_outlined,
            label: 'My Profile',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => const PatientProfileScreen()),
            ),
          ),
          _profileMenuItem(
            icon: Icons.settings_outlined,
            label: 'Settings',
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (_) => const PatientSettingsScreen()),
            ),
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

class _QuickButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 16),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            children: [
              Icon(icon, color: color, size: 26),
              const SizedBox(height: 6),
              Text(label,
                  style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: color)),
            ],
          ),
        ),
      ),
    );
  }
}

class _VitalsPreviewCard extends StatelessWidget {
  final Map<String, dynamic> vitals;
  const _VitalsPreviewCard({required this.vitals});

  @override
  Widget build(BuildContext context) {
    final bp = vitals['blood_pressure'] ?? '';
    final temp = vitals['temperature'] ?? '';
    final pulse = vitals['pulse'] ?? '';
    final weight = vitals['weight'] ?? '';
    final date = vitals['created_at'] ?? vitals['date'] ?? '';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (date.toString().isNotEmpty)
              Text(date.toString(),
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
            const SizedBox(height: 8),
            Wrap(
              spacing: 16,
              runSpacing: 8,
              children: [
                if (bp.toString().isNotEmpty)
                  _vitalItem('BP', bp.toString(), Colors.red),
                if (temp.toString().isNotEmpty)
                  _vitalItem('Temp', '$temp°C', Colors.orange),
                if (pulse.toString().isNotEmpty)
                  _vitalItem('Pulse', '$pulse bpm', Colors.blue),
                if (weight.toString().isNotEmpty)
                  _vitalItem('Wt', '$weight kg', Colors.green),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _vitalItem(String label, String value, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
        Text(value,
            style: TextStyle(
                fontSize: 14, fontWeight: FontWeight.w600, color: color)),
      ],
    );
  }
}

class _LastVisitCard extends StatelessWidget {
  final Map<String, dynamic> encounter;
  const _LastVisitCard({required this.encounter});

  @override
  Widget build(BuildContext context) {
    final doctor = encounter['doctor_name'] ?? encounter['doctor'] ?? '';
    final clinic = encounter['clinic_name'] ?? encounter['clinic'] ?? '';
    final date = encounter['encounter_date'] ?? encounter['date'] ?? '';
    final status = encounter['status'] ?? '';

    return Card(
      child: ListTile(
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: Colors.blue.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Icon(Icons.event_note_rounded, color: Colors.blue),
        ),
        title: Text(doctor.toString().isNotEmpty ? doctor.toString() : 'Doctor',
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        subtitle: Text(
            '${clinic.toString().isNotEmpty ? '$clinic • ' : ''}$date',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
        trailing: status.toString().isNotEmpty
            ? Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.green.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(status.toString(),
                    style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                        color: Colors.green)),
              )
            : null,
      ),
    );
  }
}

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
