import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import '../../core/widgets/shared_widgets.dart';

/// Shows all imaging results across all encounters — paginated.
class ImagingResultsScreen extends StatefulWidget {
  const ImagingResultsScreen({super.key});

  @override
  State<ImagingResultsScreen> createState() => _ImagingResultsScreenState();
}

class _ImagingResultsScreenState extends State<ImagingResultsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Imaging Results')),
      body: PaginatedList<PatientImagingResult>(
        fetcher: (page) => _api.getImagingResults(page: page),
        parser: (j) => PatientImagingResult.fromJson(j),
        emptyIcon: Icons.image_rounded,
        emptyTitle: 'No imaging results',
        emptySubtitle: 'Your imaging studies will appear here',
        itemBuilder: (ctx, img) => _ImagingResultCard(img: img),
      ),
    );
  }
}

class _ImagingResultCard extends StatelessWidget {
  final PatientImagingResult img;
  const _ImagingResultCard({required this.img});

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
                Icon(Icons.image, size: 18, color: Colors.purple.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(img.serviceName ?? 'Imaging Study',
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                StatusBadge.fromStatus(img.statusLabel),
              ],
            ),

            // Clinical indication
            if (img.clinicalIndication != null &&
                img.clinicalIndication!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline,
                      size: 14, color: Colors.amber.shade700),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Indication: ${img.clinicalIndication}',
                      style: TextStyle(
                          fontSize: 11, color: Colors.amber.shade900),
                    ),
                  ),
                ],
              ),
            ],

            // Report box
            if (img.hasResult) ...[
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
                    Text('Report',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.green.shade800)),
                    const SizedBox(height: 4),
                    Text(img.result!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.green.shade900)),
                  ],
                ),
              ),
            ],

            // Rejection box
            if (img.isRejected &&
                img.rejectionReason != null &&
                img.rejectionReason!.isNotEmpty) ...[
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
                    Text(img.rejectionReason!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.red.shade900)),
                  ],
                ),
              ),
            ],

            // Tracking details
            if (_hasTracking) ...[
              const SizedBox(height: 8),
              _trackingRow(Icons.receipt_long, 'Billed by', img.billedBy),
              _trackingRow(Icons.edit_note, 'Result by', img.resultEnteredBy),
              _trackingRow(Icons.verified, 'Approved by', img.approvedBy),
            ],

            // Attachments
            if (img.attachments.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: img.attachments
                    .map((a) => Chip(
                          avatar: Icon(Icons.image_outlined,
                              size: 14, color: Colors.purple.shade700),
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
              _formatDate(img.resultDate ?? img.createdAt),
              style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
            ),
          ],
        ),
      ),
    );
  }

  bool get _hasTracking =>
      img.billedBy != null ||
      img.resultEnteredBy != null ||
      img.approvedBy != null;

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
