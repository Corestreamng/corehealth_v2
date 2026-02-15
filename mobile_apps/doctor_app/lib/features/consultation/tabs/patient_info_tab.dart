import 'package:flutter/material.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 1: Patient demographic & clinical info.
class PatientInfoTab extends StatelessWidget {
  final EncounterData encounter;

  const PatientInfoTab({super.key, required this.encounter});

  @override
  Widget build(BuildContext context) {
    final p = encounter.patient;
    final primary = Theme.of(context).colorScheme.primary;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Patient header ──
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
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
                  ),
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
                _InfoRow('Date of Birth', p.dob ?? 'N/A'),
                _InfoRow('Gender', p.gender.isNotEmpty ? p.gender : 'N/A'),
                _InfoRow('Blood Group', p.bloodGroup ?? 'N/A'),
                _InfoRow('Genotype', p.genotype ?? 'N/A'),
                _InfoRow('Phone', p.phone ?? 'N/A'),
                _InfoRow('Address', p.address ?? 'N/A', isLast: true),
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
          const SectionHeader(title: 'Allergies', icon: Icons.warning_amber_rounded),
          if (p.allergies.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
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
                  children: p.allergies
                      .map((a) => Chip(
                            label: Text(a, style: const TextStyle(fontSize: 12)),
                            backgroundColor: Colors.red.shade50,
                            side: BorderSide(color: Colors.red.shade200),
                            avatar: Icon(Icons.warning_amber,
                                size: 16, color: Colors.red.shade600),
                            materialTapTargetSize:
                                MaterialTapTargetSize.shrinkWrap,
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
          if (encounter.clinic != null) ...[
            const SectionHeader(title: 'Clinic', icon: Icons.local_hospital_outlined),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.local_hospital, color: primary, size: 20),
                    const SizedBox(width: 8),
                    Text(encounter.clinic!.name,
                        style: const TextStyle(fontWeight: FontWeight.w500)),
                  ],
                ),
              ),
            ),
          ],

          const SizedBox(height: 80), // FAB clearance
        ],
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
                width: 110,
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
