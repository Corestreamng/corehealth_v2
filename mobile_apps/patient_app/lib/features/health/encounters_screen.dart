import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import 'encounter_detail_screen.dart';

/// Shows the patient's encounter/visit history.
class EncountersScreen extends StatefulWidget {
  const EncountersScreen({super.key});

  @override
  State<EncountersScreen> createState() => _EncountersScreenState();
}

class _EncountersScreenState extends State<EncountersScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(title: const Text('My Visits')),
      body: PaginatedList<PatientEncounter>(
        fetcher: (page) => _api.getEncounters(page: page),
        parser: (j) => PatientEncounter.fromJson(j),
        emptyIcon: Icons.event_note_rounded,
        emptyTitle: 'No visits yet',
        emptySubtitle: 'Your consultation history will appear here',
        itemBuilder: (ctx, enc) => _EncounterCard(
          encounter: enc,
          primary: primary,
          onTap: () {
            Navigator.push(
              ctx,
              MaterialPageRoute(
                builder: (_) => EncounterDetailScreen(encounterId: enc.id),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _EncounterCard extends StatelessWidget {
  final PatientEncounter encounter;
  final Color primary;
  final VoidCallback onTap;

  const _EncounterCard({
    required this.encounter,
    required this.primary,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(Icons.medical_services_rounded,
                        size: 20, color: primary),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          encounter.doctorName ?? 'Doctor',
                          style: const TextStyle(
                              fontWeight: FontWeight.w600, fontSize: 14),
                        ),
                        if (encounter.clinicName != null)
                          Text(
                            encounter.clinicName!,
                            style: TextStyle(
                                fontSize: 12, color: Colors.grey.shade600),
                          ),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right, color: Colors.grey.shade400),
                ],
              ),

              // Diagnosis
              if (encounter.diagnosis != null &&
                  encounter.diagnosis!.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  encounter.diagnosis!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                ),
              ],

              // Stats chips
              const SizedBox(height: 10),
              Row(
                children: [
                  if (encounter.labCount > 0)
                    _countChip(Icons.science, '${encounter.labCount} Labs',
                        Colors.blue),
                  if (encounter.imagingCount > 0)
                    _countChip(Icons.image, '${encounter.imagingCount} Imaging',
                        Colors.purple),
                  if (encounter.prescriptionCount > 0)
                    _countChip(
                        Icons.medication,
                        '${encounter.prescriptionCount} Meds',
                        Colors.orange),
                  const Spacer(),
                  Text(
                    _formatDate(encounter.createdAt),
                    style:
                        TextStyle(fontSize: 11, color: Colors.grey.shade500),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _countChip(IconData icon, String label, MaterialColor color) {
    return Container(
      margin: const EdgeInsets.only(right: 6),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: color.shade50,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 11, color: color.shade700),
          const SizedBox(width: 3),
          Text(label,
              style: TextStyle(fontSize: 10, color: color.shade700)),
        ],
      ),
    );
  }

  String _formatDate(String? date) {
    if (date == null) return '';
    try {
      final d = DateTime.parse(date);
      return '${d.day}/${d.month}/${d.year}';
    } catch (_) {
      return date;
    }
  }
}
