import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import '../../core/widgets/shared_widgets.dart';

/// Shows all prescriptions across all encounters — paginated.
class PrescriptionsScreen extends StatefulWidget {
  const PrescriptionsScreen({super.key});

  @override
  State<PrescriptionsScreen> createState() => _PrescriptionsScreenState();
}

class _PrescriptionsScreenState extends State<PrescriptionsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Prescriptions')),
      body: PaginatedList<PatientPrescription>(
        fetcher: (page) => _api.getPrescriptions(page: page),
        parser: (j) => PatientPrescription.fromJson(j),
        emptyIcon: Icons.medication_rounded,
        emptyTitle: 'No prescriptions',
        emptySubtitle: 'Your medication history will appear here',
        itemBuilder: (ctx, rx) => Card(
          margin: const EdgeInsets.only(bottom: 8),
          child: ListTile(
            leading: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.orange.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(Icons.medication, color: Colors.orange.shade700),
            ),
            title: Text(rx.productName ?? 'Medication',
                style:
                    const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (rx.dose != null && rx.dose!.isNotEmpty)
                  Text(rx.dose!,
                      style: TextStyle(
                          fontSize: 12, color: Colors.grey.shade600)),
                if (rx.qty != null)
                  Text('Qty: ${rx.qty}',
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey.shade500)),
                Text(_formatDate(rx.createdAt),
                    style: TextStyle(
                        fontSize: 10, color: Colors.grey.shade400)),
              ],
            ),
            trailing: StatusBadge.fromStatus(rx.statusLabel),
            isThreeLine: true,
          ),
        ),
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
