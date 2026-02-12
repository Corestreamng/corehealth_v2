import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/api_client.dart';
import '../../core/config/server_config_provider.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../home/home_screen.dart';
import '../server_setup/server_setup_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _idController = TextEditingController();
  final _phoneController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _idController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final serverConfig = context.read<ServerConfigProvider>();
    final client = ApiClient(serverConfig.baseUrl!);

    final res = await client.patientLogin(
      patientId: _idController.text.trim(),
      phone: _phoneController.text.trim(),
    );

    if (!mounted) return;

    if (res.success && res.data != null) {
      final token = res.data!['token'] as String?;
      if (token != null) {
        await LocalStorage.setAuthToken(token);
      }
      await LocalStorage.setPatientJson(
          jsonEncode(res.data!['patient']));

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
      );
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = res.message.isNotEmpty
            ? res.message
            : 'Login failed. Please check your hospital number and phone.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();
    final primary = theme.primaryColor;
    final logoBase64 = theme.logoBase64;

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 28),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 48),

                // Logo + Hospital name
                Center(
                  child: Column(
                    children: [
                      Container(
                        width: 88,
                        height: 88,
                        decoration: BoxDecoration(
                          color: primary.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(22),
                        ),
                        padding: const EdgeInsets.all(14),
                        child: logoBase64 != null && logoBase64.isNotEmpty
                            ? Image.memory(
                                base64Decode(logoBase64),
                                fit: BoxFit.contain,
                              )
                            : Icon(
                                Icons.favorite_rounded,
                                size: 44,
                                color: primary,
                              ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        theme.siteName.isNotEmpty
                            ? theme.siteName
                            : 'CoreHealth',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: Colors.grey.shade900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Patient Portal',
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.grey.shade500,
                          letterSpacing: 1,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 40),

                // Header
                Text(
                  'Welcome',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                    color: Colors.grey.shade900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Sign in with your hospital number and registered phone number',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade600,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 32),

                // Hospital number
                TextFormField(
                  controller: _idController,
                  textInputAction: TextInputAction.next,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    labelText: 'Hospital Number',
                    hintText: 'e.g. #0001 or PAT-0001',
                    prefixIcon:
                        Icon(Icons.badge_outlined, color: primary),
                  ),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return 'Please enter your hospital number';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 20),

                // Phone number
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.go,
                  onFieldSubmitted: (_) => _login(),
                  decoration: InputDecoration(
                    labelText: 'Phone Number',
                    hintText: 'e.g. 08012345678',
                    prefixIcon:
                        Icon(Icons.phone_outlined, color: primary),
                  ),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return 'Please enter your phone number';
                    }
                    if (v.trim().length < 10) {
                      return 'Please enter a valid phone number';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 28),

                // Error
                if (_errorMessage != null) ...[
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.red.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.error_outline_rounded,
                            color: Colors.red.shade600, size: 20),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _errorMessage!,
                            style: TextStyle(
                              color: Colors.red.shade700,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                ],

                // Login button
                ElevatedButton(
                  onPressed: _isLoading ? null : _login,
                  child: _isLoading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Sign In'),
                ),
                const SizedBox(height: 24),

                // Help text
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.help_outline_rounded,
                          color: Colors.blue.shade600, size: 20),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Your hospital number is on your patient card. '
                          'Use the phone number you registered with.',
                          style: TextStyle(
                            color: Colors.blue.shade700,
                            fontSize: 12,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),

                // Change server
                Center(
                  child: TextButton.icon(
                    onPressed: () {
                      Navigator.of(context).pushReplacement(
                        MaterialPageRoute(
                            builder: (_) => const ServerSetupScreen()),
                      );
                    },
                    icon: Icon(Icons.swap_horiz_rounded,
                        color: Colors.grey.shade600, size: 18),
                    label: Text(
                      'Change Hospital',
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ),
                Center(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      'Connected to: ${context.read<ServerConfigProvider>().baseUrl ?? "—"}',
                      style: TextStyle(
                        fontSize: 11,
                        color: Colors.grey.shade400,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
