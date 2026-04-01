import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/storage/local_storage.dart';

class PatientProfileScreen extends StatefulWidget {
  const PatientProfileScreen({super.key});

  @override
  State<PatientProfileScreen> createState() => _PatientProfileScreenState();
}

class _PatientProfileScreenState extends State<PatientProfileScreen> {
  late PatientApiService _api;
  Map<String, dynamic>? _profile;
  bool _loading = true;
  bool _editing = false;
  bool _saving = false;

  // Editable fields
  final _phoneCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _nokNameCtrl = TextEditingController();
  final _nokPhoneCtrl = TextEditingController();
  final _nokAddressCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
    _loadProfile();
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _nokNameCtrl.dispose();
    _nokPhoneCtrl.dispose();
    _nokAddressCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProfile() async {
    setState(() => _loading = true);
    final result = await _api.getProfile();
    if (!mounted) return;
    setState(() {
      if (result.success && result.data is Map) {
        _profile = Map<String, dynamic>.from(result.data);
        _populateControllers();
      }
      _loading = false;
    });
  }

  void _populateControllers() {
    _phoneCtrl.text = _profile?['phone']?.toString() ?? '';
    _addressCtrl.text = _profile?['address']?.toString() ?? '';
    _nokNameCtrl.text = _profile?['next_of_kin_name']?.toString() ?? '';
    _nokPhoneCtrl.text = _profile?['next_of_kin_phone']?.toString() ?? '';
    _nokAddressCtrl.text = _profile?['next_of_kin_address']?.toString() ?? '';
  }

  Future<void> _saveProfile() async {
    setState(() => _saving = true);
    final result = await _api.updateProfile({
      'phone_no': _phoneCtrl.text.trim(),
      'address': _addressCtrl.text.trim(),
      'next_of_kin_name': _nokNameCtrl.text.trim(),
      'next_of_kin_phone': _nokPhoneCtrl.text.trim(),
      'next_of_kin_address': _nokAddressCtrl.text.trim(),
    });
    if (!mounted) return;
    setState(() => _saving = false);

    if (result.success) {
      setState(() => _editing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profile updated')),
      );
      _loadProfile();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result.message.isNotEmpty ? result.message : 'Update failed')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
        actions: [
          if (!_loading && _profile != null)
            TextButton(
              onPressed: () {
                if (_editing) {
                  _populateControllers();
                }
                setState(() => _editing = !_editing);
              },
              child: Text(_editing ? 'Cancel' : 'Edit'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _profile == null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('Failed to load profile'),
                      const SizedBox(height: 12),
                      ElevatedButton(
                          onPressed: _loadProfile,
                          child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadProfile,
                  child: _editing ? _buildEditView() : _buildReadView(),
                ),
    );
  }

  Widget _buildReadView() {
    final name = _profile?['name']?.toString() ?? 'Patient';
    final email = _profile?['email']?.toString() ?? '';
    final fileNo = _profile?['file_no']?.toString() ?? '';
    final allergies = _profile?['allergies'] as List? ?? [];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        children: [
          CircleAvatar(
            radius: 44,
            backgroundColor:
                Theme.of(context).colorScheme.primary.withValues(alpha: 0.15),
            child: Text(
              name.isNotEmpty ? name[0].toUpperCase() : 'P',
              style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w600,
                  color: Theme.of(context).colorScheme.primary),
            ),
          ),
          const SizedBox(height: 12),
          Text(name,
              style:
                  const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          if (fileNo.isNotEmpty)
            Text('ID: $fileNo',
                style: TextStyle(color: Colors.grey.shade600)),
          if (email.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(email,
                  style: TextStyle(color: Colors.grey.shade500, fontSize: 13)),
            ),
          const SizedBox(height: 24),

          _section('Personal Info', [
            _infoRow('Gender', _profile?['gender']),
            _infoRow('Date of Birth', _profile?['dob']),
            _infoRow('Blood Group', _profile?['blood_group']),
            _infoRow('Genotype', _profile?['genotype']),
            _infoRow('Nationality', _profile?['nationality']),
            _infoRow('Ethnicity', _profile?['ethnicity']),
          ]),

          _section('Contact', [
            _infoRow('Phone', _profile?['phone']),
            _infoRow('Address', _profile?['address']),
          ]),

          _section('HMO / Insurance', [
            _infoRow('HMO', _profile?['hmo_name']),
            _infoRow('HMO No.', _profile?['hmo_no']),
          ]),

          _section('Next of Kin', [
            _infoRow('Name', _profile?['next_of_kin_name']),
            _infoRow('Phone', _profile?['next_of_kin_phone']),
            _infoRow('Address', _profile?['next_of_kin_address']),
          ]),

          if (allergies.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
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
          ],
        ],
      ),
    );
  }

  Widget _buildEditView() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Contact',
              style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade800)),
          const SizedBox(height: 8),
          TextField(
            controller: _phoneCtrl,
            decoration: const InputDecoration(
              labelText: 'Phone Number',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.phone,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _addressCtrl,
            decoration: const InputDecoration(
              labelText: 'Address',
              border: OutlineInputBorder(),
            ),
            maxLines: 2,
          ),
          const SizedBox(height: 24),

          Text('Next of Kin',
              style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade800)),
          const SizedBox(height: 8),
          TextField(
            controller: _nokNameCtrl,
            decoration: const InputDecoration(
              labelText: 'Name',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nokPhoneCtrl,
            decoration: const InputDecoration(
              labelText: 'Phone',
              border: OutlineInputBorder(),
            ),
            keyboardType: TextInputType.phone,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nokAddressCtrl,
            decoration: const InputDecoration(
              labelText: 'Address',
              border: OutlineInputBorder(),
            ),
            maxLines: 2,
          ),
          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _saving ? null : _saveProfile,
              child: _saving
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save Changes'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _section(String title, List<Widget> rows) {
    final filtered = rows.whereType<Widget>().toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title,
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade600)),
        const SizedBox(height: 4),
        Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: Column(children: filtered),
        ),
      ],
    );
  }

  Widget _infoRow(String label, dynamic value) {
    final v = value?.toString() ?? '';
    if (v.isEmpty || v == 'N/A') {
      return const SizedBox.shrink();
    }
    return ListTile(
      dense: true,
      title: Text(label,
          style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
      subtitle: Text(v, style: const TextStyle(fontWeight: FontWeight.w500)),
    );
  }
}
