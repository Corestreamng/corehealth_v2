import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';

/// Shows patient admission history — paginated.
class AdmissionsScreen extends StatefulWidget {
  const AdmissionsScreen({super.key});

  @override
  State<AdmissionsScreen> createState() => _AdmissionsScreenState();
}

class _AdmissionsScreenState extends State<AdmissionsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Admissions')),
      body: PaginatedList<PatientAdmission>(
        fetcher: (page) => _api.getAdmissions(page: page),
        parser: (j) => PatientAdmission.fromJson(j),
        emptyIcon: Icons.hotel_rounded,
        emptyTitle: 'No admissions',
        emptySubtitle: 'Your admission history will appear here',
        itemBuilder: (ctx, admission) => _AdmissionCard(admission: admission),
      ),
    );
  }
}

class _AdmissionCard extends StatelessWidget {
  final PatientAdmission admission;

  const _AdmissionCard({required this.admission});

  @override
  Widget build(BuildContext context) {
    final isActive = admission.isActive;
    final statusColor = isActive ? Colors.green : Colors.grey;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: isActive
            ? BorderSide(color: Colors.green.shade300, width: 1.5)
            : BorderSide.none,
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: status + doctor
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        isActive ? Icons.hotel : Icons.check_circle_outline,
                        size: 14,
                        color: statusColor,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        admission.statusLabel,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: statusColor,
                        ),
                      ),
                    ],
                  ),
                ),
                const Spacer(),
                if (admission.priority != null)
                  _priorityChip(admission.priority!),
              ],
            ),
            const SizedBox(height: 10),

            // Bed & Ward info
            if (admission.bedInfo != null &&
                admission.bedInfo!.isNotEmpty) ...[
              Row(
                children: [
                  Icon(Icons.bed_rounded,
                      size: 16, color: Colors.blue.shade600),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      admission.bedInfo!,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: Colors.grey.shade800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
            ],

            // Doctor
            if (admission.doctorName != null) ...[
              Row(
                children: [
                  Icon(Icons.person_outline,
                      size: 16, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Text(
                    'Dr. ${admission.doctorName}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade700,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
            ],

            // Reason
            if (admission.admissionReason != null &&
                admission.admissionReason!.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Reason',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.blue.shade700)),
                    const SizedBox(height: 2),
                    Text(admission.admissionReason!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.blue.shade900)),
                  ],
                ),
              ),
              const SizedBox(height: 6),
            ],

            // Discharge info
            if (admission.discharged &&
                admission.dischargeReason != null &&
                admission.dischargeReason!.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Discharge Reason',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.orange.shade700)),
                    const SizedBox(height: 2),
                    Text(admission.dischargeReason!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.orange.shade900)),
                  ],
                ),
              ),
              const SizedBox(height: 6),
            ],

            // Footer: dates + duration
            Row(
              children: [
                Icon(Icons.calendar_today, size: 12, color: Colors.grey.shade500),
                const SizedBox(width: 4),
                Text(
                  _formatDate(admission.createdAt),
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                ),
                if (admission.dischargeDate != null) ...[
                  Text(' → ', style: TextStyle(color: Colors.grey.shade400)),
                  Text(
                    _formatDate(admission.dischargeDate),
                    style:
                        TextStyle(fontSize: 10, color: Colors.grey.shade500),
                  ),
                ],
                const Spacer(),
                if (admission.daysAdmitted != null)
                  Text(
                    '${admission.daysAdmitted} day${admission.daysAdmitted == 1 ? '' : 's'}',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey.shade600,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _priorityChip(String priority) {
    final color = switch (priority.toLowerCase()) {
      'emergency' => Colors.red,
      'urgent' => Colors.orange,
      _ => Colors.teal,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        priority,
        style: TextStyle(
            fontSize: 10, fontWeight: FontWeight.w600, color: color),
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
