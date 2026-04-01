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
        itemBuilder: (ctx, rx) => _PrescriptionCard(rx: rx),
      ),
    );
  }
}

class _PrescriptionCard extends StatelessWidget {
  final PatientPrescription rx;
  const _PrescriptionCard({required this.rx});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child:
                      Icon(Icons.medication, size: 18, color: Colors.orange.shade700),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(rx.productName ?? 'Medication',
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                StatusBadge.fromStatus(rx.statusLabel),
              ],
            ),

            // Dose display line
            if (rx.doseDisplay.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(rx.doseDisplay,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
            ],

            // Detail chips (frequency, duration, route, qty)
            if (_hasDetails) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: [
                  if (rx.route != null && rx.route!.isNotEmpty)
                    _detailChip('Route', rx.route!, Colors.blue),
                  if (rx.frequency != null && rx.frequency!.isNotEmpty)
                    _detailChip('Freq', rx.frequency!, Colors.purple),
                  if (rx.duration != null && rx.duration!.isNotEmpty)
                    _detailChip(
                        'Duration',
                        '${rx.duration} ${rx.durationUnit ?? 'days'}',
                        Colors.teal),
                  if (rx.qty != null)
                    _detailChip('Qty', '${rx.qty}', Colors.indigo),
                ],
              ),
            ],

            // Special instruction
            if (rx.specialInstruction != null &&
                rx.specialInstruction!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.amber.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.amber.shade200),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline,
                        size: 14, color: Colors.amber.shade800),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        rx.specialInstruction!,
                        style: TextStyle(
                            fontSize: 11, color: Colors.amber.shade900),
                      ),
                    ),
                  ],
                ),
              ),
            ],

            // Date
            const SizedBox(height: 6),
            Text(_formatDate(rx.createdAt),
                style: TextStyle(fontSize: 10, color: Colors.grey.shade400)),
          ],
        ),
      ),
    );
  }

  bool get _hasDetails =>
      (rx.route != null && rx.route!.isNotEmpty) ||
      (rx.frequency != null && rx.frequency!.isNotEmpty) ||
      (rx.duration != null && rx.duration!.isNotEmpty) ||
      rx.qty != null;

  Widget _detailChip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text('$label: $value',
          style: TextStyle(
              fontSize: 10, fontWeight: FontWeight.w500, color: color)),
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
