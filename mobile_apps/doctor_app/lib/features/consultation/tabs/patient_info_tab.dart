import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 1: Patient demographic & clinical info with allergy management.
class PatientInfoTab extends StatefulWidget {
  final EncounterData encounter;
  final EncounterApiService api;

  const PatientInfoTab({
    super.key,
    required this.encounter,
    required this.api,
  });

  @override
  State<PatientInfoTab> createState() => _PatientInfoTabState();
}

class _PatientInfoTabState extends State<PatientInfoTab>
    with AutomaticKeepAliveClientMixin {
  late List<String> _allergies;
  bool _addingAllergy = false;
  final _allergyCtrl = TextEditingController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _allergies = List<String>.from(widget.encounter.patient.allergies);
  }

  @override
  void dispose() {
    _allergyCtrl.dispose();
    super.dispose();
  }

  Future<void> _addAllergy() async {
    final val = _allergyCtrl.text.trim();
    if (val.isEmpty) return;

    final res = await widget.api.addPatientAllergy(
      widget.encounter.patient.id,
      allergy: val,
    );
    if (!mounted) return;

    if (res.success) {
      setState(() {
        _allergies.add(val);
        _allergyCtrl.clear();
        _addingAllergy = false;
      });
      showSuccessSnackBar(context, 'Allergy added');
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to add allergy');
    }
  }

  Future<void> _removeAllergy(String allergy) async {
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Remove Allergy',
      message: 'Remove "$allergy" from patient allergies?',
      confirmText: 'Remove',
    );
    if (!confirmed || !mounted) return;

    final res = await widget.api.deletePatientAllergy(
      widget.encounter.patient.id,
      allergy,
    );
    if (!mounted) return;

    if (res.success) {
      setState(() => _allergies.remove(allergy));
      showSuccessSnackBar(context, 'Allergy removed');
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to remove');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final p = widget.encounter.patient;
    final primary = Theme.of(context).colorScheme.primary;
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Patient header with photo ──
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  _buildAvatar(p, primary),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          p.name,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          [
                            if (p.fileNo.isNotEmpty) 'File: ${p.fileNo}',
                            if (p.gender.isNotEmpty) p.gender,
                            if (p.age.isNotEmpty) p.age,
                          ].join(' · '),
                          style: TextStyle(
                            fontSize: 13,
                            color: Colors.grey.shade600,
                          ),
                        ),
                        if (p.dob != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            'DOB: ${p.dob}',
                            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),

          // ── Demographics ──
          const SectionHeader(title: 'Demographics', icon: Icons.person_outline),
          Card(
            child: Column(
              children: [
                _InfoRow('Blood Group', p.bloodGroup ?? 'N/A'),
                _InfoRow('Genotype', p.genotype ?? 'N/A'),
                _InfoRow('Phone', p.phone ?? 'N/A'),
                _InfoRow('Email', p.email ?? 'N/A'),
                _InfoRow('Occupation', p.occupation ?? 'N/A'),
                _InfoRow('Address', p.address ?? 'N/A'),
                _InfoRow('Nationality', p.nationality ?? 'N/A'),
                _InfoRow('Ethnicity', p.ethnicity ?? 'N/A'),
                _InfoRow('Disability', p.disability ?? 'None', isLast: true),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // ── Next of Kin ──
          const SectionHeader(title: 'Next of Kin', icon: Icons.people_outline),
          Card(
            child: Column(
              children: [
                _InfoRow('Name', p.nokName ?? 'N/A'),
                _InfoRow('Relationship', p.nokRelationship ?? 'N/A'),
                _InfoRow('Phone', p.nokPhone ?? 'N/A'),
                _InfoRow('Address', p.nokAddress ?? 'N/A', isLast: true),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // ── Insurance ──
          const SectionHeader(title: 'Insurance', icon: Icons.shield_outlined),
          Card(
            child: Column(
              children: [
                _InfoRow('HMO', p.hmoName ?? 'N/A'),
                _InfoRow('HMO Number', p.hmoNo ?? 'N/A'),
                _InfoRow('Scheme', p.insuranceScheme ?? 'N/A', isLast: true),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // ── Allergies ──
          SectionHeader(
            title: 'Allergies',
            icon: Icons.warning_amber_rounded,
            trailing: readOnly
                ? null
                : IconButton(
                    icon: Icon(
                      _addingAllergy ? Icons.close : Icons.add_circle_outline,
                      size: 20,
                      color: primary,
                    ),
                    tooltip: _addingAllergy ? 'Cancel' : 'Add allergy',
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    onPressed: () => setState(() {
                      _addingAllergy = !_addingAllergy;
                      if (!_addingAllergy) _allergyCtrl.clear();
                    }),
                  ),
          ),

          if (_addingAllergy) ...[
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _allergyCtrl,
                    autofocus: true,
                    decoration: const InputDecoration(
                      hintText: 'Enter allergy...',
                      isDense: true,
                      contentPadding:
                          EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                    onSubmitted: (_) => _addAllergy(),
                  ),
                ),
                const SizedBox(width: 8),
                ElevatedButton(
                  onPressed: _addAllergy,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  child: const Text('Add'),
                ),
              ],
            ),
            const SizedBox(height: 8),
          ],

          if (_allergies.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(Icons.check_circle_outline,
                        color: Colors.green.shade600, size: 20),
                    const SizedBox(width: 8),
                    Text(
                      'No known allergies',
                      style: TextStyle(color: Colors.green.shade700),
                    ),
                  ],
                ),
              ),
            )
          else
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: _allergies
                      .map((a) => Chip(
                            label: Text(a, style: const TextStyle(fontSize: 12)),
                            backgroundColor: Colors.red.shade50,
                            side: BorderSide(color: Colors.red.shade200),
                            avatar: Icon(Icons.warning_amber,
                                size: 16, color: Colors.red.shade600),
                            materialTapTargetSize:
                                MaterialTapTargetSize.shrinkWrap,
                            onDeleted: readOnly ? null : () => _removeAllergy(a),
                            deleteIconColor: Colors.red.shade400,
                          ))
                      .toList(),
                ),
              ),
            ),
          const SizedBox(height: 12),

          // ── Medical History ──
          if (p.medicalHistory != null && p.medicalHistory!.isNotEmpty) ...[
            const SectionHeader(title: 'Medical History', icon: Icons.history_edu),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  p.medicalHistory!,
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.grey.shade800,
                    height: 1.5,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
          ],

          // ── Clinic ──
          if (widget.encounter.clinic != null) ...[
            const SectionHeader(title: 'Clinic', icon: Icons.local_hospital_outlined),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(Icons.local_hospital, color: primary, size: 20),
                    const SizedBox(width: 8),
                    Text(widget.encounter.clinic!.name,
                        style: const TextStyle(fontWeight: FontWeight.w500)),
                  ],
                ),
              ),
            ),
          ],

          const SizedBox(height: 80),
        ],
      ),
    );
  }

  Widget _buildAvatar(PatientInfo p, Color primary) {
    if (p.photoUrl != null && p.photoUrl!.isNotEmpty) {
      return CircleAvatar(
        radius: 32,
        backgroundImage: NetworkImage(p.photoUrl!),
        onBackgroundImageError: (_, __) {},
        backgroundColor: primary.withValues(alpha: 0.12),
        child: null,
      );
    }
    return CircleAvatar(
      radius: 32,
      backgroundColor: primary.withValues(alpha: 0.12),
      child: Text(
        p.name.isNotEmpty ? p.name[0].toUpperCase() : '?',
        style: TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: primary,
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool isLast;

  const _InfoRow(this.label, this.value, {this.isLast = false});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 120,
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.grey.shade500,
                  ),
                ),
              ),
              Expanded(
                child: Text(
                  value,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade800,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (!isLast) Divider(height: 1, color: Colors.grey.shade100),
      ],
    );
  }
}

