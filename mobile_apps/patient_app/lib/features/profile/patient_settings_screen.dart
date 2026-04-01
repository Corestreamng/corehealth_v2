import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';
import '../server_setup/server_setup_screen.dart';

class PatientSettingsScreen extends StatefulWidget {
  const PatientSettingsScreen({super.key});

  @override
  State<PatientSettingsScreen> createState() => _PatientSettingsScreenState();
}

class _PatientSettingsScreenState extends State<PatientSettingsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  void _showChangePasswordDialog() {
    final currentCtrl = TextEditingController();
    final newCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();
    bool saving = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Change Password'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: currentCtrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Current Password',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: newCtrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'New Password',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: confirmCtrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm New Password',
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (newCtrl.text.length < 8) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                              content: Text(
                                  'Password must be at least 8 characters')),
                        );
                        return;
                      }
                      if (newCtrl.text != confirmCtrl.text) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                              content: Text('Passwords do not match')),
                        );
                        return;
                      }
                      setDialogState(() => saving = true);
                      final result = await _api.changePassword(
                        currentPassword: currentCtrl.text,
                        newPassword: newCtrl.text,
                        confirmPassword: confirmCtrl.text,
                      );
                      setDialogState(() => saving = false);
                      if (!ctx.mounted) return;
                      final msg = result.success
                          ? 'Password updated'
                          : (result.message.isNotEmpty
                              ? result.message
                              : 'Failed');
                      final messenger = ScaffoldMessenger.of(ctx);
                      Navigator.pop(ctx);
                      messenger.showSnackBar(
                        SnackBar(content: Text(msg)),
                      );
                    },
              child: saving
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Update'),
            ),
          ],
        ),
      ),
    ).then((_) {
      currentCtrl.dispose();
      newCtrl.dispose();
      confirmCtrl.dispose();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();
    final server = context.watch<ServerConfigProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Change password
          Card(
            child: ListTile(
              leading: const Icon(Icons.lock_outlined),
              title: const Text('Change Password',
                  style: TextStyle(fontWeight: FontWeight.w500)),
              trailing: Icon(Icons.chevron_right_rounded,
                  color: Colors.grey.shade400),
              onTap: _showChangePasswordDialog,
            ),
          ),
          const SizedBox(height: 8),

          // Change hospital
          Card(
            child: ListTile(
              leading: const Icon(Icons.swap_horiz_rounded),
              title: const Text('Change Hospital',
                  style: TextStyle(fontWeight: FontWeight.w500)),
              trailing: Icon(Icons.chevron_right_rounded,
                  color: Colors.grey.shade400),
              onTap: () async {
                await LocalStorage.clearAll();
                if (!context.mounted) return;
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(
                      builder: (_) => const ServerSetupScreen()),
                  (_) => false,
                );
              },
            ),
          ),
          const SizedBox(height: 20),

          // Server info
          Card(
            color: Colors.grey.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Server Info',
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Colors.grey.shade600)),
                  const SizedBox(height: 8),
                  Text(
                    theme.siteName.isNotEmpty
                        ? theme.siteName
                        : 'Unknown Hospital',
                    style: const TextStyle(fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    server.baseUrl ?? 'N/A',
                    style: TextStyle(
                        fontSize: 12, color: Colors.grey.shade500),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),

          // Sign out
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16)),
                    title: const Text('Sign Out'),
                    content:
                        const Text('Are you sure you want to sign out?'),
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
                if (confirm != true || !context.mounted) return;
                try {
                  final cfg =
                      context.read<ServerConfigProvider>();
                  final client = ApiClient(cfg.baseUrl!);
                  await client.logout();
                } catch (_) {}
                await LocalStorage.clearSession();
                if (!context.mounted) return;
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(
                      builder: (_) => const LoginScreen()),
                  (_) => false,
                );
              },
              icon: Icon(Icons.logout_rounded,
                  color: Colors.red.shade600),
              label: Text('Sign Out',
                  style: TextStyle(color: Colors.red.shade600)),
              style: OutlinedButton.styleFrom(
                side: BorderSide(color: Colors.red.shade300),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
