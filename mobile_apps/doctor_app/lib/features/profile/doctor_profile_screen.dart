import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';

class DoctorProfileScreen extends StatefulWidget {
  const DoctorProfileScreen({super.key});

  @override
  State<DoctorProfileScreen> createState() => _DoctorProfileScreenState();
}

class _DoctorProfileScreenState extends State<DoctorProfileScreen> {
  late EncounterApiService _api;
  bool _loading = true;
  bool _saving = false;
  bool _editing = false;

  // Profile data
  Map<String, dynamic> _userData = {};
  Map<String, dynamic> _staffData = {};

  // Editable controllers
  final _surnameCtrl = TextEditingController();
  final _firstnameCtrl = TextEditingController();
  final _othernameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _emergNameCtrl = TextEditingController();
  final _emergPhoneCtrl = TextEditingController();
  final _emergRelCtrl = TextEditingController();
  String? _gender;

  @override
  void initState() {
    super.initState();
    _api = EncounterApiService(LocalStorage.baseUrl ?? '');
    _loadProfile();
  }

  @override
  void dispose() {
    _surnameCtrl.dispose();
    _firstnameCtrl.dispose();
    _othernameCtrl.dispose();
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _emergNameCtrl.dispose();
    _emergPhoneCtrl.dispose();
    _emergRelCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProfile() async {
    setState(() => _loading = true);
    final result = await _api.getDoctorProfile();
    if (!mounted) return;

    if (result.success && result.data != null) {
      setState(() {
        _userData = Map<String, dynamic>.from(result.data!['user'] ?? {});
        _staffData = Map<String, dynamic>.from(result.data!['staff'] ?? {});
        _populateControllers();
        _loading = false;
      });
    } else {
      setState(() => _loading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result.message.isNotEmpty
              ? result.message
              : 'Failed to load profile')),
        );
      }
    }
  }

  void _populateControllers() {
    _surnameCtrl.text = (_userData['surname'] ?? '').toString();
    _firstnameCtrl.text = (_userData['firstname'] ?? '').toString();
    _othernameCtrl.text = (_userData['othername'] ?? '').toString();
    _phoneCtrl.text = (_staffData['phone_number'] ?? '').toString();
    _addressCtrl.text = (_staffData['home_address'] ?? '').toString();
    _emergNameCtrl.text = (_staffData['emergency_contact_name'] ?? '').toString();
    _emergPhoneCtrl.text = (_staffData['emergency_contact_phone'] ?? '').toString();
    _emergRelCtrl.text = (_staffData['emergency_contact_relationship'] ?? '').toString();
    _gender = _staffData['gender']?.toString();
  }

  Future<void> _saveProfile() async {
    setState(() => _saving = true);

    final result = await _api.updateDoctorProfile({
      'surname': _surnameCtrl.text.trim(),
      'firstname': _firstnameCtrl.text.trim(),
      'othername': _othernameCtrl.text.trim(),
      'gender': _gender ?? '',
      'phone_number': _phoneCtrl.text.trim(),
      'home_address': _addressCtrl.text.trim(),
      'emergency_contact_name': _emergNameCtrl.text.trim(),
      'emergency_contact_phone': _emergPhoneCtrl.text.trim(),
      'emergency_contact_relationship': _emergRelCtrl.text.trim(),
    });

    if (!mounted) return;
    setState(() {
      _saving = false;
      if (result.success) _editing = false;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result.success
            ? 'Profile updated successfully'
            : result.message.isNotEmpty
                ? result.message
                : 'Failed to update profile'),
        backgroundColor: result.success ? Colors.green : Colors.red,
      ),
    );

    if (result.success) _loadProfile();
  }

  @override
  Widget build(BuildContext context) {
    final primary = context.watch<ThemeProvider>().primaryColor;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
        actions: [
          if (!_loading)
            IconButton(
              icon: Icon(_editing ? Icons.close : Icons.edit_outlined),
              onPressed: () {
                if (_editing) {
                  _populateControllers();
                }
                setState(() => _editing = !_editing);
              },
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadProfile,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    // Avatar + name
                    CircleAvatar(
                      radius: 48,
                      backgroundColor: primary.withValues(alpha: 0.15),
                      child: Text(
                        (_userData['firstname'] ?? 'D')
                            .toString()
                            .characters
                            .first
                            .toUpperCase(),
                        style: TextStyle(
                          fontSize: 36,
                          fontWeight: FontWeight.w600,
                          color: primary,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (!_editing)
                      Text(
                        (_userData['name'] ?? '').toString(),
                        style: const TextStyle(
                            fontSize: 22, fontWeight: FontWeight.w700),
                      ),
                    if (!_editing && (_userData['email'] ?? '').toString().isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text((_userData['email'] ?? '').toString(),
                            style: TextStyle(color: Colors.grey.shade600)),
                      ),
                    const SizedBox(height: 24),

                    // Read-only info
                    if (!_editing) ...[
                      _infoCard('Department',
                          (_staffData['department'] ?? 'N/A').toString(), Icons.business),
                      _infoCard(
                          'Designation',
                          (_staffData['designation'] ?? 'N/A').toString(),
                          Icons.badge_outlined),
                      _infoCard(
                          'Specialization',
                          (_staffData['specialization_name'] ?? 'N/A').toString(),
                          Icons.local_hospital_outlined),
                      _infoCard('Clinic', (_staffData['clinic_name'] ?? 'N/A').toString(),
                          Icons.location_on_outlined),
                      _infoCard('Gender', (_staffData['gender'] ?? 'N/A').toString(),
                          Icons.person_outline),
                      _infoCard('Phone', (_staffData['phone_number'] ?? 'N/A').toString(),
                          Icons.phone_outlined),
                      _infoCard('Address',
                          (_staffData['home_address'] ?? 'N/A').toString(), Icons.home),
                      const SizedBox(height: 16),
                      _sectionHeader('Emergency Contact'),
                      _infoCard(
                          'Name',
                          (_staffData['emergency_contact_name'] ?? 'N/A').toString(),
                          Icons.person),
                      _infoCard(
                          'Phone',
                          (_staffData['emergency_contact_phone'] ?? 'N/A').toString(),
                          Icons.phone),
                      _infoCard(
                          'Relationship',
                          (_staffData['emergency_contact_relationship'] ?? 'N/A').toString(),
                          Icons.family_restroom),
                    ],

                    // Edit mode
                    if (_editing) ...[
                      _editField('Surname', _surnameCtrl),
                      _editField('First Name', _firstnameCtrl),
                      _editField('Other Name', _othernameCtrl),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: _gender, // ignore: deprecated_member_use
                        decoration: const InputDecoration(
                          labelText: 'Gender',
                          border: OutlineInputBorder(),
                        ),
                        items: ['Male', 'Female', 'Others']
                            .map((g) => DropdownMenuItem(
                                value: g, child: Text(g)))
                            .toList(),
                        onChanged: (v) => setState(() => _gender = v),
                      ),
                      const SizedBox(height: 12),
                      _editField('Phone Number', _phoneCtrl,
                          keyboard: TextInputType.phone),
                      _editField('Home Address', _addressCtrl, maxLines: 2),
                      const SizedBox(height: 16),
                      _sectionHeader('Emergency Contact'),
                      _editField('Contact Name', _emergNameCtrl),
                      _editField('Contact Phone', _emergPhoneCtrl,
                          keyboard: TextInputType.phone),
                      _editField('Relationship', _emergRelCtrl),
                      const SizedBox(height: 24),
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: ElevatedButton(
                          onPressed: _saving ? null : _saveProfile,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: primary,
                            foregroundColor: Colors.white,
                          ),
                          child: _saving
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2, color: Colors.white))
                              : const Text('Save Changes',
                                  style: TextStyle(
                                      fontWeight: FontWeight.w600)),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  Widget _infoCard(String label, String value, IconData icon) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(icon, color: Colors.grey.shade600, size: 22),
        title:
            Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
        subtitle: Text(value, style: const TextStyle(fontWeight: FontWeight.w500)),
      ),
    );
  }

  Widget _editField(String label, TextEditingController ctrl,
      {TextInputType? keyboard, int maxLines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: ctrl,
        keyboardType: keyboard,
        maxLines: maxLines,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(title,
            style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade800)),
      ),
    );
  }
}
