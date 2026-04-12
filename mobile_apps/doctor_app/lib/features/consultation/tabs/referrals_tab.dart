import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/dictation/dictation_button.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

class ReferralsTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onSaved;

  const ReferralsTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onSaved,
  });

  @override
  State<ReferralsTab> createState() => _ReferralsTabState();
}

class _ReferralsTabState extends State<ReferralsTab>
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;
  late List<Referral> _referrals;
  List<Referral> _incoming = [];
  bool _isLoading = false;
  bool _loadingIncoming = false;
  List<dynamic> _clinics = [];
  List<dynamic> _doctors = [];

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _subTabCtrl = TabController(length: 2, vsync: this);
    _referrals = List<Referral>.from(widget.encounter.referrals);
    _loadClinicsDoctors();
    _loadIncoming();
  }

  @override
  void dispose() {
    _subTabCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadClinicsDoctors() async {
    try {
      setState(() => _isLoading = true);
      final clinicsResp = await widget.api.getClinics();
      final doctorsResp = await widget.api.getDoctors();
      if (!mounted) return;
      setState(() {
        _clinics = clinicsResp;
        _doctors = doctorsResp;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      showErrorSnackBar(context, 'Failed to load clinics/doctors: $e');
    }
  }

  Future<void> _loadIncoming() async {
    setState(() => _loadingIncoming = true);
    try {
      final result = await widget.api.getIncomingReferrals(widget.encounter.id);
      if (!mounted) return;
      if (result.success && result.data != null) {
        final list = result.data!['referrals'];
        if (list is List) {
          setState(() {
            _incoming = list
                .map((r) => Referral.fromJson(r as Map<String, dynamic>))
                .toList();
          });
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _loadingIncoming = false);
  }

  Future<void> _refresh() async {
    try {
      final result = await widget.api.getEncounterReferrals(widget.encounter.id);
      if (!mounted) return;
      if (result.success && result.data != null) {
        final list = result.data!['referrals'] ?? result.data!['data'];
        if (list is List) {
          setState(() {
            _referrals = list
                .map((r) => Referral.fromJson(r as Map<String, dynamic>))
                .toList();
          });
        }
      }
    } catch (e) {
      if (!mounted) return;
      showErrorSnackBar(context, 'Failed to refresh referrals: $e');
    }
  }

  Future<void> _createReferral() async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => _ReferralFormDialog(
        clinics: _clinics,
        doctors: _doctors,
      ),
    );

    if (result != null) {
      try {
        await widget.api.createReferral(
          widget.encounter.id,
          patientId: widget.encounter.patientId,
          referralType: result['referral_type'] ?? 'internal',
          targetClinicId: result['target_clinic_id'],
          targetDoctorId: result['target_doctor_id'],
          externalFacilityName: result['external_facility_name'],
          externalDoctorName: result['external_doctor_name'],
          externalFacilityAddress: result['external_facility_address'],
          externalFacilityPhone: result['external_facility_phone'],
          reason: result['reason'],
          clinicalSummary: result['clinical_summary'],
          provisionalDiagnosis: result['provisional_diagnosis'],
          urgency: result['urgency'] ?? 'routine',
        );
        if (!mounted) return;
        showSuccessSnackBar(context, 'Referral created successfully');
        await _refresh();
        widget.onSaved();
      } catch (e) {
        if (!mounted) return;
        showErrorSnackBar(context, 'Failed to create referral: $e');
      }
    }
  }

  Future<void> _deleteReferral(Referral ref) async {
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Delete Referral',
      message: 'Are you sure you want to delete this referral?',
    );

    if (confirmed == true) {
      try {
        await widget.api.deleteReferral(widget.encounter.id, ref.id);
        if (!mounted) return;
        setState(() {
          _referrals.removeWhere((r) => r.id == ref.id);
        });
        showSuccessSnackBar(context, 'Referral deleted');
        widget.onSaved();
      } catch (e) {
        if (!mounted) return;
        showErrorSnackBar(context, 'Failed to delete referral: $e');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createReferral,
        icon: const Icon(Icons.add),
        label: const Text('New Referral'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                TabBar(
                  controller: _subTabCtrl,
                  labelColor: Theme.of(context).colorScheme.primary,
                  unselectedLabelColor: Colors.grey,
                  indicatorSize: TabBarIndicatorSize.tab,
                  tabs: [
                    Tab(text: 'Outgoing (${_referrals.length})'),
                    Tab(text: 'Incoming (${_incoming.length})'),
                  ],
                ),
                Expanded(
                  child: TabBarView(
                    controller: _subTabCtrl,
                    children: [
                      _buildOutgoing(),
                      _buildIncoming(),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildOutgoing() {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: _referrals.isEmpty
          ? const SingleChildScrollView(
              physics: AlwaysScrollableScrollPhysics(),
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(32.0),
                  child: EmptyState(
                    icon: Icons.local_hospital_outlined,
                    title: 'No Referrals',
                    subtitle: 'Create a referral to refer the patient',
                  ),
                ),
              ),
            )
          : SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(12.0),
              child: Column(
                children: _referrals
                    .map((ref) => _ReferralCard(
                          referral: ref,
                          onDelete: () => _deleteReferral(ref),
                        ))
                    .toList(),
              ),
            ),
    );
  }

  Widget _buildIncoming() {
    if (_loadingIncoming) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_incoming.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadIncoming,
        child: const SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(32),
              child: EmptyState(
                icon: Icons.inbox_outlined,
                title: 'No Incoming Referrals',
                subtitle: 'No pending referrals addressed to you',
              ),
            ),
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadIncoming,
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _incoming.length,
        itemBuilder: (context, i) => _IncomingReferralCard(referral: _incoming[i]),
      ),
    );
  }
}

// ─── Outgoing Referral Card ─────────────────────────────────

class _ReferralCard extends StatelessWidget {
  final Referral referral;
  final VoidCallback onDelete;

  const _ReferralCard({
    required this.referral,
    required this.onDelete,
  });

  Color _urgencyColor(String? u) {
    switch (u) {
      case 'emergency': return Colors.red;
      case 'urgent': return Colors.orange;
      default: return Colors.blue;
    }
  }

  Color _statusColor(String? s) {
    switch (s) {
      case 'accepted': return Colors.green;
      case 'declined': return Colors.red;
      case 'completed': return Colors.teal;
      default: return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isExt = referral.isExternal;
    final destination = isExt
        ? referral.externalFacility ?? 'External Facility'
        : referral.toDoctorName ?? referral.toClinicName ?? 'Specialist';

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Type badge + destination
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: isExt ? Colors.purple.shade100 : Colors.blue.shade100,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(isExt ? 'External' : 'Internal',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600,
                          color: isExt ? Colors.purple.shade800 : Colors.blue.shade800)),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(destination,
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            // Urgency + Status badges
            Row(
              children: [
                StatusBadge(
                  label: referral.urgencyLabel,
                  color: _urgencyColor(referral.urgency),
                ),
                const SizedBox(width: 8),
                StatusBadge(
                  label: referral.statusLabel,
                  color: _statusColor(referral.status),
                ),
                const Spacer(),
                Text(referral.createdAt,
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
              ],
            ),
            const SizedBox(height: 10),
            // Reason
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10.0),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(referral.reason ?? 'No reason provided',
                  style: TextStyle(color: Colors.grey[700], fontSize: 14)),
            ),
            // External details
            if (isExt) ...[
              if (referral.externalDoctor != null) ...[
                const SizedBox(height: 6),
                _DetailRow(Icons.person_outline, 'Doctor', referral.externalDoctor!),
              ],
              if (referral.externalFacilityAddress != null) ...[
                const SizedBox(height: 4),
                _DetailRow(Icons.location_on_outlined, 'Address', referral.externalFacilityAddress!),
              ],
              if (referral.externalFacilityPhone != null) ...[
                const SizedBox(height: 4),
                _DetailRow(Icons.phone_outlined, 'Phone', referral.externalFacilityPhone!),
              ],
            ],
            // Provisional diagnosis
            if (referral.provisionalDiagnosis != null && referral.provisionalDiagnosis!.isNotEmpty) ...[
              const SizedBox(height: 8),
              _DetailRow(Icons.medical_services_outlined, 'Diagnosis', referral.provisionalDiagnosis!),
            ],
            // Clinical summary
            if (referral.clinicalSummary != null && referral.clinicalSummary!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Clinical Summary:', style: TextStyle(color: Colors.grey[600], fontSize: 12, fontWeight: FontWeight.w500)),
              const SizedBox(height: 4),
              Text(referral.clinicalSummary!, style: TextStyle(color: Colors.grey[700], fontSize: 13)),
            ],
            // Response notes
            if (referral.responseNotes != null && referral.responseNotes!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10.0),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: Colors.green[200]!),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Response:', style: TextStyle(color: Colors.green[700], fontSize: 12, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    Text(referral.responseNotes!, style: TextStyle(color: Colors.green[700], fontSize: 13)),
                  ],
                ),
              ),
            ],
            // Delete action
            if (referral.canEdit || referral.status == 'pending') ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: OutlinedButton.icon(
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete, size: 18),
                  label: const Text('Delete'),
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ─── Incoming Referral Card ─────────────────────────────────

class _IncomingReferralCard extends StatelessWidget {
  final Referral referral;
  const _IncomingReferralCard({required this.referral});

  Color _urgencyColor(String? u) {
    switch (u) {
      case 'emergency': return Colors.red;
      case 'urgent': return Colors.orange;
      default: return Colors.blue;
    }
  }

  @override
  Widget build(BuildContext context) {
    final patientName = referral.fromDoctorName ?? 'Unknown Patient';
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                StatusBadge(label: referral.urgencyLabel, color: _urgencyColor(referral.urgency)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text('From: $patientName',
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                ),
              ],
            ),
            if (referral.referringClinic != null) ...[
              const SizedBox(height: 4),
              Text(referral.referringClinic!, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],
            const SizedBox(height: 8),
            Text(referral.reason ?? '', style: const TextStyle(fontSize: 13)),
            if (referral.provisionalDiagnosis != null && referral.provisionalDiagnosis!.isNotEmpty) ...[
              const SizedBox(height: 6),
              _DetailRow(Icons.medical_services_outlined, 'Dx', referral.provisionalDiagnosis!),
            ],
            const SizedBox(height: 6),
            Text(referral.createdAt, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }
}

// ─── Detail row helper ──────────────────────────────────────

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  const _DetailRow(this.icon, this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 14, color: Colors.grey.shade500),
        const SizedBox(width: 4),
        Text('$label: ', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: Colors.grey.shade600)),
        Expanded(child: Text(value, style: TextStyle(fontSize: 12, color: Colors.grey.shade700))),
      ],
    );
  }
}

// ─── Referral Form Dialog ───────────────────────────────────

class _ReferralFormDialog extends StatefulWidget {
  final List<dynamic> clinics;
  final List<dynamic> doctors;

  const _ReferralFormDialog({
    required this.clinics,
    required this.doctors,
  });

  @override
  State<_ReferralFormDialog> createState() => _ReferralFormDialogState();
}

class _ReferralFormDialogState extends State<_ReferralFormDialog> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _reasonCtrl;
  late TextEditingController _summaryCtrl;
  late TextEditingController _diagnosisCtrl;
  // External-only
  late TextEditingController _extFacilityCtrl;
  late TextEditingController _extDoctorCtrl;
  late TextEditingController _extAddressCtrl;
  late TextEditingController _extPhoneCtrl;

  String _referralType = 'internal';
  String _urgency = 'routine';
  int? _selectedDoctorId;
  int? _selectedClinicId;

  @override
  void initState() {
    super.initState();
    _reasonCtrl = TextEditingController();
    _summaryCtrl = TextEditingController();
    _diagnosisCtrl = TextEditingController();
    _extFacilityCtrl = TextEditingController();
    _extDoctorCtrl = TextEditingController();
    _extAddressCtrl = TextEditingController();
    _extPhoneCtrl = TextEditingController();
  }

  @override
  void dispose() {
    _reasonCtrl.dispose();
    _summaryCtrl.dispose();
    _diagnosisCtrl.dispose();
    _extFacilityCtrl.dispose();
    _extDoctorCtrl.dispose();
    _extAddressCtrl.dispose();
    _extPhoneCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isExternal = _referralType == 'external';

    return AlertDialog(
      title: const Text('Create Referral'),
      content: SizedBox(
        width: double.maxFinite,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Type toggle
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(label: Text('Internal'), value: 'internal',
                        icon: Icon(Icons.business, size: 16)),
                    ButtonSegment(label: Text('External'), value: 'external',
                        icon: Icon(Icons.open_in_new, size: 16)),
                  ],
                  selected: {_referralType},
                  onSelectionChanged: (v) => setState(() => _referralType = v.first),
                ),
                const SizedBox(height: 16),

                // Internal fields
                if (!isExternal) ...[
                  DropdownButtonFormField<int>(
                    decoration: const InputDecoration(
                      labelText: 'Target Clinic *',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    initialValue: _selectedClinicId,
                    items: widget.clinics.map<DropdownMenuItem<int>>((c) {
                      final id = c['id'] ?? c.id;
                      final name = c['name'] ?? c.name ?? 'Unknown';
                      return DropdownMenuItem(value: id as int, child: Text(name.toString(), style: const TextStyle(fontSize: 13)));
                    }).toList(),
                    onChanged: (v) => setState(() => _selectedClinicId = v),
                    validator: (_) => !isExternal && _selectedClinicId == null ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    decoration: const InputDecoration(
                      labelText: 'Target Doctor (optional)',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    initialValue: _selectedDoctorId,
                    items: [
                      const DropdownMenuItem<int>(value: null, child: Text('Any Available Doctor', style: TextStyle(fontSize: 13, fontStyle: FontStyle.italic))),
                      ...widget.doctors.map<DropdownMenuItem<int>>((d) {
                        final id = d['id'] ?? d.id;
                        final name = d['name'] ?? d.name ?? 'Unknown';
                        return DropdownMenuItem(value: id as int, child: Text(name.toString(), style: const TextStyle(fontSize: 13)));
                      }),
                    ],
                    onChanged: (v) => setState(() => _selectedDoctorId = v),
                  ),
                ],

                // External fields
                if (isExternal) ...[
                  TextFormField(
                    controller: _extFacilityCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Facility Name *',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    validator: (v) => isExternal && (v?.isEmpty ?? true) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _extDoctorCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Doctor Name',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _extAddressCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Facility Address',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _extPhoneCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Facility Phone',
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    keyboardType: TextInputType.phone,
                  ),
                ],
                const SizedBox(height: 16),

                // Urgency
                const Text('Urgency', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                const SizedBox(height: 6),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(label: Text('Routine'), value: 'routine'),
                    ButtonSegment(label: Text('Urgent'), value: 'urgent'),
                    ButtonSegment(label: Text('Emergency'), value: 'emergency'),
                  ],
                  selected: {_urgency},
                  onSelectionChanged: (v) => setState(() => _urgency = v.first),
                ),
                const SizedBox(height: 16),

                // Provisional Diagnosis
                TextFormField(
                  controller: _diagnosisCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Provisional Diagnosis',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 12),

                // Reason
                Row(
                  children: [
                    const Expanded(
                      child: Text('Reason *', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                    ),
                    DictationButton(controller: _reasonCtrl, fieldLabel: 'Referral Reason'),
                  ],
                ),
                const SizedBox(height: 4),
                TextFormField(
                  controller: _reasonCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Reason for referral...',
                    border: OutlineInputBorder(),
                  ),
                  maxLines: 2,
                  validator: (v) => v?.isEmpty ?? true ? 'Required' : null,
                ),
                const SizedBox(height: 12),

                // Clinical Summary
                Row(
                  children: [
                    const Expanded(
                      child: Text('Clinical Summary', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                    ),
                    DictationButton(controller: _summaryCtrl, fieldLabel: 'Clinical Summary'),
                  ],
                ),
                const SizedBox(height: 4),
                TextFormField(
                  controller: _summaryCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Brief clinical summary...',
                    border: OutlineInputBorder(),
                  ),
                  maxLines: 3,
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () {
            if (_formKey.currentState!.validate()) {
              Navigator.pop(context, {
                'referral_type': _referralType,
                if (!isExternal) 'target_clinic_id': _selectedClinicId,
                if (!isExternal) 'target_doctor_id': _selectedDoctorId,
                if (isExternal) 'external_facility_name': _extFacilityCtrl.text,
                if (isExternal && _extDoctorCtrl.text.isNotEmpty) 'external_doctor_name': _extDoctorCtrl.text,
                if (isExternal && _extAddressCtrl.text.isNotEmpty) 'external_facility_address': _extAddressCtrl.text,
                if (isExternal && _extPhoneCtrl.text.isNotEmpty) 'external_facility_phone': _extPhoneCtrl.text,
                'reason': _reasonCtrl.text,
                if (_summaryCtrl.text.isNotEmpty) 'clinical_summary': _summaryCtrl.text,
                if (_diagnosisCtrl.text.isNotEmpty) 'provisional_diagnosis': _diagnosisCtrl.text,
                'urgency': _urgency,
              });
            }
          },
          child: const Text('Create'),
        ),
      ],
    );
  }
}
