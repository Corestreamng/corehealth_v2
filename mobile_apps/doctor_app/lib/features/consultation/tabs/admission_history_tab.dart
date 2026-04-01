import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

class AdmissionHistoryTab extends StatefulWidget {
  final EncounterApiService api;
  final int patientId;

  const AdmissionHistoryTab({
    super.key,
    required this.api,
    required this.patientId,
  });

  @override
  State<AdmissionHistoryTab> createState() => _AdmissionHistoryTabState();
}

class _AdmissionHistoryTabState extends State<AdmissionHistoryTab>
    with AutomaticKeepAliveClientMixin {
  late Future<List<dynamic>> _admissionsFuture;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _admissionsFuture = _fetchAdmissions();
  }

  Future<List<dynamic>> _fetchAdmissions() async {
    try {
      final result = await widget.api.getPatientAdmissions(widget.patientId);
      if (result.success && result.data != null) {
        final list = result.data!['admissions'] ?? result.data!['data'];
        if (list is List) return list;
      }
      return [];
    } catch (e) {
      if (mounted) showErrorSnackBar(context, 'Failed to load admissions: $e');
      return [];
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _admissionsFuture = _fetchAdmissions();
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<dynamic>>(
        future: _admissionsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return Center(
              child: Text('Error: ${snapshot.error}'),
            );
          }

          final admissions = snapshot.data ?? [];

          if (admissions.isEmpty) {
            return const SingleChildScrollView(
              physics: AlwaysScrollableScrollPhysics(),
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(32.0),
                  child: EmptyState(
                    icon: Icons.hotel_outlined,
                    title: 'No Admissions',
                    subtitle: 'Patient admission history will appear here',
                  ),
                ),
              ),
            );
          }

          // Separate active and past admissions
          final activeAdmissions = admissions
              .where((a) => (a['status'] ?? a.status ?? '').toString().toLowerCase() == 'admitted')
              .toList();
          final pastAdmissions = admissions
              .where((a) => (a['status'] ?? a.status ?? '').toString().toLowerCase() != 'admitted')
              .toList();

          return SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(12.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (activeAdmissions.isNotEmpty) ...[
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12.0),
                    child: SectionHeader(title: 'Current Admission'),
                  ),
                  ...activeAdmissions.map((admission) =>
                      _AdmissionCard(admission: admission, isActive: true)),
                  const SizedBox(height: 20),
                ],
                if (pastAdmissions.isNotEmpty) ...[
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12.0),
                    child: SectionHeader(title: 'Past Admissions'),
                  ),
                  ...pastAdmissions.map((admission) =>
                      _AdmissionCard(admission: admission, isActive: false)),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _AdmissionCard extends StatelessWidget {
  final dynamic admission;
  final bool isActive;

  const _AdmissionCard({
    required this.admission,
    required this.isActive,
  });

  String _getFieldValue(String key1, String key2) {
    final val1 = admission[key1];
    final val2 = admission[key2] ?? val1;
    return (val2 ?? 'N/A').toString();
  }

  @override
  Widget build(BuildContext context) {
    final bedName = _getFieldValue('bed_name', 'bedName');
    final wardName = _getFieldValue('ward_name', 'wardName');
    final clinicName = _getFieldValue('clinic_name', 'clinicName');
    final admittedAt = _getFieldValue('admitted_at', 'admittedAt');
    final dischargedAt = _getFieldValue('discharged_at', 'dischargedAt');
    final admittedByName = _getFieldValue('admitted_by_name', 'admittedByName');
    final dischargedByName = _getFieldValue('discharged_by_name', 'dischargedByName');
    final statusColor = isActive ? Colors.green : Colors.grey;
    final statusLabel = isActive ? 'Admitted' : 'Discharged';

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8.0),
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        wardName,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        bedName,
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                StatusBadge(
                  label: isActive ? 'Current Admission' : statusLabel,
                  color: statusColor,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'Clinic: $clinicName',
              style: TextStyle(
                fontSize: 13,
                color: Colors.grey[700],
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.event, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Admitted: ${admittedAt.split(' ')[0]}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[700],
                    ),
                  ),
                ),
              ],
            ),
            if (dischargedAt != 'N/A') ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  Icon(Icons.event, size: 16, color: Colors.grey[600]),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Discharged: ${dischargedAt.split(' ')[0]}',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[700],
                      ),
                    ),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 8),
            if (admittedByName != 'N/A')
              Text(
                'Admitted by: $admittedByName',
                style: TextStyle(
                  fontSize: 11,
                  color: Colors.grey[600],
                  fontStyle: FontStyle.italic,
                ),
              ),
            if (dischargedByName != 'N/A')
              Padding(
                padding: const EdgeInsets.only(top: 4.0),
                child: Text(
                  'Discharged by: $dischargedByName',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey[600],
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
