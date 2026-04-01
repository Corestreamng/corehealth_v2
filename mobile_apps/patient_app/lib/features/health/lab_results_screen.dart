import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import '../../core/widgets/shared_widgets.dart';

/// Shows all lab results across all encounters — paginated.
class LabResultsScreen extends StatefulWidget {
  const LabResultsScreen({super.key});

  @override
  State<LabResultsScreen> createState() => _LabResultsScreenState();
}

class _LabResultsScreenState extends State<LabResultsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Lab Results')),
      body: PaginatedList<PatientLabResult>(
        fetcher: (page) => _api.getLabResults(page: page),
        parser: (j) => PatientLabResult.fromJson(j),
        emptyIcon: Icons.science_rounded,
        emptyTitle: 'No lab results',
        emptySubtitle: 'Your lab test results will appear here',
        itemBuilder: (ctx, lab) => _LabResultCard(lab: lab),
      ),
    );
  }
}

class _LabResultCard extends StatelessWidget {
  final PatientLabResult lab;
  const _LabResultCard({required this.lab});

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
                Icon(Icons.science, size: 18, color: Colors.blue.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(lab.serviceName ?? 'Lab Test',
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                StatusBadge.fromStatus(lab.statusLabel),
              ],
            ),

            // Result box
            if (lab.hasResult) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Result',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.green.shade800)),
                    const SizedBox(height: 4),
                    Text(lab.result!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.green.shade900)),
                  ],
                ),
              ),
            ],

            // Rejection box
            if (lab.isRejected &&
                lab.rejectionReason != null &&
                lab.rejectionReason!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Rejection Reason',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.red.shade800)),
                    const SizedBox(height: 2),
                    Text(lab.rejectionReason!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.red.shade900)),
                  ],
                ),
              ),
            ],

            // Notes
            if (lab.note != null && lab.note!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Note: ${lab.note}',
                  style: TextStyle(
                      fontSize: 11,
                      color: Colors.grey.shade600,
                      fontStyle: FontStyle.italic)),
            ],

            // Tracking details
            if (_hasTracking) ...[
              const SizedBox(height: 8),
              _trackingRow(Icons.receipt_long, 'Billed by', lab.billedBy),
              _trackingRow(Icons.colorize, 'Sample by', lab.sampleTakenBy),
              _trackingRow(Icons.edit_note, 'Result by', lab.resultEnteredBy),
              _trackingRow(Icons.verified, 'Approved by', lab.approvedBy),
            ],

            // Attachments
            if (lab.attachments.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: lab.attachments
                    .map((a) => Chip(
                          avatar: Icon(Icons.attach_file,
                              size: 14, color: Colors.blue.shade700),
                          label: Text(
                            a.split('/').last,
                            style: const TextStyle(fontSize: 10),
                            overflow: TextOverflow.ellipsis,
                          ),
                          visualDensity: VisualDensity.compact,
                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,
                        ))
                    .toList(),
              ),
            ],

            // Date
            const SizedBox(height: 6),
            Text(
              _formatDate(lab.resultDate ?? lab.createdAt),
              style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
            ),
          ],
        ),
      ),
    );
  }

  bool get _hasTracking =>
      lab.billedBy != null ||
      lab.sampleTakenBy != null ||
      lab.resultEnteredBy != null ||
      lab.approvedBy != null;

  Widget _trackingRow(IconData icon, String label, String? value) {
    if (value == null || value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 3),
      child: Row(
        children: [
          Icon(icon, size: 13, color: Colors.grey.shade500),
          const SizedBox(width: 6),
          Text('$label: ',
              style: TextStyle(
                  fontSize: 10,
                  color: Colors.grey.shade500,
                  fontWeight: FontWeight.w500)),
          Expanded(
            child: Text(value,
                style: TextStyle(fontSize: 10, color: Colors.grey.shade700)),
          ),
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
