import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import '../../core/widgets/shared_widgets.dart';

/// Shows all procedures across all encounters — paginated.
class ProceduresScreen extends StatefulWidget {
  const ProceduresScreen({super.key});

  @override
  State<ProceduresScreen> createState() => _ProceduresScreenState();
}

class _ProceduresScreenState extends State<ProceduresScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  Color _priorityColor(String? priority) {
    switch (priority?.toLowerCase()) {
      case 'emergency':
        return Colors.red.shade700;
      case 'urgent':
        return Colors.orange.shade700;
      default:
        return Colors.teal.shade700;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Procedures')),
      body: PaginatedList<PatientProcedure>(
        fetcher: (page) => _api.getProcedures(page: page),
        parser: (j) => PatientProcedure.fromJson(j),
        emptyIcon: Icons.medical_services_rounded,
        emptyTitle: 'No procedures',
        emptySubtitle: 'Your procedure history will appear here',
        itemBuilder: (ctx, proc) => Card(
          margin: const EdgeInsets.only(bottom: 8),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.medical_services,
                        size: 18, color: _priorityColor(proc.priority)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(proc.serviceName ?? 'Procedure',
                          style: const TextStyle(
                              fontSize: 13, fontWeight: FontWeight.w600)),
                    ),
                    if (proc.procedureStatus != null)
                      StatusBadge.fromStatus(proc.procedureStatus!),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    if (proc.priority != null)
                      _infoChip('Priority', proc.priority!,
                          _priorityColor(proc.priority)),
                    if (proc.scheduledDate != null)
                      _infoChip(
                          'Scheduled', proc.scheduledDate!, Colors.blue.shade700),
                    if (proc.outcome != null && proc.outcome!.isNotEmpty)
                      _infoChip(
                          'Outcome', proc.outcome!, Colors.green.shade700),
                  ],
                ),
                if (proc.createdAt != null) ...[
                  const SizedBox(height: 6),
                  Text(_formatDate(proc.createdAt),
                      style: TextStyle(
                          fontSize: 10, color: Colors.grey.shade500)),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _infoChip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text('$label: $value',
          style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.w500)),
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
