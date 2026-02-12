import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/theme/theme_provider.dart';
import '../auth/login_screen.dart';

class ServerSetupScreen extends StatefulWidget {
  const ServerSetupScreen({super.key});

  @override
  State<ServerSetupScreen> createState() => _ServerSetupScreenState();
}

class _ServerSetupScreenState extends State<ServerSetupScreen> {
  final _urlController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final rawUrl = _urlController.text.trim();
    var url = rawUrl;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'http://$url';
    }
    url = url.replaceAll(RegExp(r'/+$'), '');

    try {
      final client = ApiClient(url);
      final isValid = await client.verifyServer();

      if (!isValid) {
        setState(() {
          _isLoading = false;
          _errorMessage =
              'Could not connect to a CoreHealth instance at this address. '
              'Please check the URL and try again.';
        });
        return;
      }

      // Fetch instance branding
      final infoRes = await client.getInstanceInfo();
      if (infoRes.success && infoRes.data != null) {
        if (!mounted) return;
        await context.read<ThemeProvider>().updateFromInstance(
              hosColor: infoRes.data!['hos_color'] ?? '#0066cc',
              siteName: infoRes.data!['site_name'] ?? 'CoreHealth',
              logoBase64: infoRes.data!['logo'],
            );
      }

      // Save server URL
      if (!mounted) return;
      await context.read<ServerConfigProvider>().setServerUrl(url);

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Connection failed: ${e.toString()}';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();
    final primary = theme.primaryColor;

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 28),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 60),

                // Header icon
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Icon(
                    Icons.dns_rounded,
                    size: 40,
                    color: primary,
                  ),
                ),
                const SizedBox(height: 32),

                Text(
                  'Connect to Your Hospital',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                    color: Colors.grey.shade900,
                    height: 1.2,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Enter the base URL or IP address of your CoreHealth '
                  'instance to get started.',
                  style: TextStyle(
                    fontSize: 15,
                    color: Colors.grey.shade600,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 40),

                // URL input
                TextFormField(
                  controller: _urlController,
                  keyboardType: TextInputType.url,
                  textInputAction: TextInputAction.go,
                  onFieldSubmitted: (_) => _connect(),
                  decoration: InputDecoration(
                    labelText: 'Server Address',
                    hintText: 'e.g. 192.168.1.100:8000 or hospital.com',
                    prefixIcon:
                        Icon(Icons.link_rounded, color: primary),
                    suffixIcon: _isLoading
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                              ),
                            ),
                          )
                        : null,
                  ),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return 'Please enter a server address';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 12),

                // Hint text
                Row(
                  children: [
                    Icon(Icons.info_outline_rounded,
                        size: 14, color: Colors.grey.shade500),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Ask your hospital IT admin for the CoreHealth server address.',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade500,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Error message
                if (_errorMessage != null) ...[
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.error_outline_rounded,
                            color: Colors.red.shade600, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _errorMessage!,
                            style: TextStyle(
                              color: Colors.red.shade700,
                              fontSize: 13,
                              height: 1.4,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                ],

                // Connect button
                ElevatedButton.icon(
                  onPressed: _isLoading ? null : _connect,
                  icon: _isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.wifi_tethering_rounded),
                  label: Text(_isLoading ? 'Connecting...' : 'Connect'),
                ),
                const SizedBox(height: 48),

                // Example formats
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.grey.shade200),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Example Formats',
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: Colors.grey.shade700,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 12),
                      _exampleRow('Local network:', '192.168.1.100:8000'),
                      const SizedBox(height: 6),
                      _exampleRow('Domain:', 'hospital.corehealth.ng'),
                      const SizedBox(height: 6),
                      _exampleRow('With HTTPS:', 'https://emr.myhospital.com'),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _exampleRow(String label, String value) {
    return Row(
      children: [
        SizedBox(
          width: 100,
          child: Text(
            label,
            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: Colors.grey.shade300),
          ),
          child: Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontFamily: 'monospace',
              color: Colors.grey.shade800,
            ),
          ),
        ),
      ],
    );
  }
}
