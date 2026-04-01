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
    with AutomaticKeepAliveClientMixin {
  late List<Referral> _referrals;
  bool _isLoading = false;
  List<dynamic> _clinics = [];
  List<dynamic> _doctors = [];

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _referrals = List<Referral>.from(widget.encounter.referrals);
    _loadClinicsDoctors();
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
        final appointDate = result['appointment_date'];
        await widget.api.createReferral(
          widget.encounter.id,
          patientId: widget.encounter.patientId,
          toDoctorId: result['to_doctor_id'],
          toClinicId: result['to_clinic_id'],
          reason: result['reason'],
          notes: result['notes'],
          urgency: result['urgency'] ?? 0,
          appointmentDate: appointDate is DateTime
              ? appointDate.toIso8601String().split('T')[0]
              : appointDate?.toString(),
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

  Future<void> _updateStatus(Referral ref) async {
    final statusOptions = {
      0: 'Pending',
      1: 'Accepted',
      2: 'Declined',
      3: 'Completed',
    };

    int? selectedStatus;
    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: const Text('Update Referral Status'),
          content: SingleChildScrollView(
            child: RadioGroup<int>(
              groupValue: selectedStatus ?? ref.status,
              onChanged: (val) {
                setState(() => selectedStatus = val);
              },
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: statusOptions.entries.map((e) {
                  return RadioListTile<int>(
                    title: Text(e.value),
                    value: e.key,
                  );
                }).toList(),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: selectedStatus != null
                  ? () async {
                      Navigator.pop(ctx);
                      try {
                        await widget.api.updateReferral(
                          widget.encounter.id,
                          ref.id,
                          status: selectedStatus!,
                        );
                        if (!mounted) return;
                        await _refresh();
                        if (!mounted) return;
                        showSuccessSnackBar(context, 'Status updated');
                        widget.onSaved();
                      } catch (e) {
                        if (!mounted) return;
                        showErrorSnackBar(context, 'Failed to update: $e');
                      }
                    }
                  : null,
              child: const Text('Update'),
            ),
          ],
        ),
      ),
    );
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
          : RefreshIndicator(
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
                                  onUpdateStatus: () => _updateStatus(ref),
                                  onDelete: () => _deleteReferral(ref),
                                ))
                            .toList(),
                      ),
                    ),
            ),
    );
  }
}

class _ReferralCard extends StatelessWidget {
  final Referral referral;
  final VoidCallback onUpdateStatus;
  final VoidCallback onDelete;

  const _ReferralCard({
    required this.referral,
    required this.onUpdateStatus,
    required this.onDelete,
  });

  String _getUrgencyLabel(int? urgency) {
    switch (urgency) {
      case 2:
        return 'Emergency';
      case 1:
        return 'Urgent';
      default:
        return 'Routine';
    }
  }

  Color _getUrgencyColor(int? urgency) {
    switch (urgency) {
      case 2:
        return Colors.red;
      case 1:
        return Colors.orange;
      default:
        return Colors.blue;
    }
  }

  String _getStatusLabel(int status) {
    switch (status) {
      case 1:
        return 'Accepted';
      case 2:
        return 'Declined';
      case 3:
        return 'Completed';
      default:
        return 'Pending';
    }
  }

  Color _getStatusColor(int status) {
    switch (status) {
      case 1:
        return Colors.green;
      case 2:
        return Colors.red;
      case 3:
        return Colors.teal;
      default:
        return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    final destination = referral.toDoctorName ??
        referral.toClinicName ??
        'Specialist';

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    destination,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                StatusBadge(
                  label: _getUrgencyLabel(referral.urgency),
                  color: _getUrgencyColor(referral.urgency),
                ),
                const SizedBox(width: 8),
                StatusBadge(
                  label: _getStatusLabel(referral.status),
                  color: _getStatusColor(referral.status),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10.0),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                referral.reason ?? 'No reason provided',
                style: TextStyle(
                  color: Colors.grey[700],
                  fontSize: 14,
                ),
              ),
            ),
            if (referral.notes != null && referral.notes!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                'Notes:',
                style: TextStyle(
                  color: Colors.grey[600],
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                referral.notes!,
                style: TextStyle(
                  color: Colors.grey[700],
                  fontSize: 13,
                ),
              ),
            ],
            if (referral.appointmentDate != null) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  Icon(Icons.event, size: 18, color: Colors.grey[600]),
                  const SizedBox(width: 6),
                  Text(
                    referral.appointmentDate.toString().split(' ')[0],
                    style: TextStyle(
                      color: Colors.grey[700],
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ],
            if (referral.responseNotes != null &&
                referral.responseNotes!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(10.0),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: Colors.green[200]!),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Response:',
                      style: TextStyle(
                        color: Colors.green[700],
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      referral.responseNotes!,
                      style: TextStyle(
                        color: Colors.green[700],
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                OutlinedButton.icon(
                  onPressed: onUpdateStatus,
                  icon: const Icon(Icons.edit, size: 18),
                  label: const Text('Status'),
                ),
                const SizedBox(width: 8),
                if (referral.status == 0)
                  OutlinedButton.icon(
                    onPressed: onDelete,
                    icon: const Icon(Icons.delete, size: 18),
                    label: const Text('Delete'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
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
  late TextEditingController _reasonController;
  late TextEditingController _notesController;
  DateTime? _selectedDate;
  int? _selectedDoctorId;
  int? _selectedClinicId;
  int _selectedUrgency = 0;

  @override
  void initState() {
    super.initState();
    _reasonController = TextEditingController();
    _notesController = TextEditingController();
    _selectedDate = DateTime.now().add(const Duration(days: 1));
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Create Referral'),
      content: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                hint: const Text('Select Doctor'),
                initialValue: _selectedDoctorId,
                items: widget.doctors
                    .map<DropdownMenuItem<int>>((doc) {
                      final docId = doc['id'] ?? doc.id;
                      final docName = doc['name'] ?? doc.name ?? 'Unknown';
                      return DropdownMenuItem<int>(
                        value: docId,
                        child: Text(docName.toString()),
                      );
                    })
                    .toList(),
                onChanged: (val) => setState(() => _selectedDoctorId = val),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                hint: const Text('Select Clinic'),
                initialValue: _selectedClinicId,
                items: widget.clinics
                    .map<DropdownMenuItem<int>>((clinic) {
                      final clinicId = clinic['id'] ?? clinic.id;
                      final clinicName = clinic['name'] ?? clinic.name ?? 'Unknown';
                      return DropdownMenuItem<int>(
                        value: clinicId,
                        child: Text(clinicName.toString()),
                      );
                    })
                    .toList(),
                onChanged: (val) => setState(() => _selectedClinicId = val),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Expanded(
                    child: Text('Reason *',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                  ),
                  DictationButton(
                      controller: _reasonController,
                      fieldLabel: 'Referral Reason'),
                ],
              ),
              const SizedBox(height: 4),
              TextFormField(
                controller: _reasonController,
                decoration: const InputDecoration(
                  hintText: 'Enter reason...',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
                validator: (val) =>
                    val?.isEmpty ?? true ? 'Reason is required' : null,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Expanded(
                    child: Text('Notes',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                  ),
                  DictationButton(
                      controller: _notesController,
                      fieldLabel: 'Referral Notes'),
                ],
              ),
              const SizedBox(height: 4),
              TextFormField(
                controller: _notesController,
                decoration: const InputDecoration(
                  hintText: 'Additional notes...',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 12),
              SegmentedButton<int>(
                segments: const [
                  ButtonSegment(label: Text('Routine'), value: 0),
                  ButtonSegment(label: Text('Urgent'), value: 1),
                  ButtonSegment(label: Text('Emergency'), value: 2),
                ],
                selected: {_selectedUrgency},
                onSelectionChanged: (val) =>
                    setState(() => _selectedUrgency = val.first),
              ),
              const SizedBox(height: 12),
              ElevatedButton.icon(
                onPressed: () async {
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: _selectedDate ?? DateTime.now(),
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                  );
                  if (picked != null) {
                    setState(() => _selectedDate = picked);
                  }
                },
                icon: const Icon(Icons.calendar_today),
                label: Text(
                  _selectedDate != null
                      ? _selectedDate.toString().split(' ')[0]
                      : 'Select Date',
                ),
              ),
            ],
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
            if (_formKey.currentState!.validate() &&
                (_selectedDoctorId != null || _selectedClinicId != null)) {
              Navigator.pop(context, {
                'to_doctor_id': _selectedDoctorId,
                'to_clinic_id': _selectedClinicId,
                'reason': _reasonController.text,
                'notes': _notesController.text.isNotEmpty
                    ? _notesController.text
                    : null,
                'urgency': _selectedUrgency,
                'appointment_date': _selectedDate,
              });
            }
          },
          child: const Text('Create'),
        ),
      ],
    );
  }
}
