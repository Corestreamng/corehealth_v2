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
        itemBuilder: (ctx, img) => Card(
          margin: const EdgeInsets.only(bottom: 8),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
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
                if (img.result != null && img.result!.isNotEmpty) ...[
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
                const SizedBox(height: 6),
                Text(
                  _formatDate(img.resultDate ?? img.createdAt),
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                ),
              ],
            ),
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
