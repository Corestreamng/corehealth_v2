import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';

import '../../../core/widgets/status_badge.dart';

class InjImmTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;

  const InjImmTab({
    super.key,
    required this.api,
    required this.encounter,
  });

  @override
  State<InjImmTab> createState() => _InjImmTabState();
}

class _InjImmTabState extends State<InjImmTab>
    with AutomaticKeepAliveClientMixin {
  late Future<List<dynamic>> _proceduresFuture;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _proceduresFuture = _loadProcedures();
  }

  Future<List<dynamic>> _loadProcedures() async {
    try {
      final result = await widget.api.getProcedureHistory(
        widget.encounter.patientId,
        page: 1,
      );
      if (result.success && result.data != null) {
        final list = result.data!['data'] ?? result.data!['items'] ?? result.rawBody;
        if (list is List && list.isNotEmpty) {
          return list.where((proc) {
            if (proc is! Map) return false;
            final serviceName =
                (proc['service_name'] ?? proc['name'] ?? '')
                    .toString()
                    .toLowerCase();
            return serviceName.contains('injection') ||
                serviceName.contains('immunization') ||
                serviceName.contains('vaccine') ||
                serviceName.contains('vaccination');
          }).toList();
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _proceduresFuture = _loadProcedures();
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<dynamic>>(
        future: _proceduresFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          final procedures = snapshot.data ?? [];

          if (procedures.isEmpty) {
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(32.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.vaccines,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Injection & Immunization History',
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                              color: Colors.grey[700],
                            ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Injection & Immunization history will appear here',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.grey[600],
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Records are managed by nursing staff',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Colors.grey[500],
                          fontSize: 12,
                          fontStyle: FontStyle.italic,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }

          return SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(12.0),
            child: Column(
              children: procedures
                  .map((procedure) => _InjImmCard(procedure: procedure))
                  .toList(),
            ),
          );
        },
      ),
    );
  }
}

class _InjImmCard extends StatelessWidget {
  final dynamic procedure;

  const _InjImmCard({
    required this.procedure,
  });

  String _getFieldValue(String key1, String key2) {
    if (procedure is! Map) return 'N/A';
    final m = procedure as Map;
    final val = m[key1] ?? m[key2];
    return (val ?? 'N/A').toString();
  }

  String _getStatusLabel(String? status) {
    if (status == null) return 'Completed';
    final s = status.toLowerCase();
    if (s.contains('completed')) return 'Completed';
    if (s.contains('pending')) return 'Pending';
    if (s.contains('cancelled')) return 'Cancelled';
    return 'Completed';
  }

  Color _getStatusColor(String? status) {
    if (status == null) return Colors.green;
    final s = status.toLowerCase();
    if (s.contains('completed')) return Colors.green;
    if (s.contains('pending')) return Colors.orange;
    if (s.contains('cancelled')) return Colors.red;
    return Colors.green;
  }

  @override
  Widget build(BuildContext context) {
    final serviceName = _getFieldValue('service_name', 'serviceName');
    final date = _getFieldValue('procedure_date', 'procedureDate');
    final status = _getFieldValue('status', 'status');
    final doctorName = _getFieldValue('doctor_name', 'doctorName');
    final notes = _getFieldValue('notes', 'notes');

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
                  child: Text(
                    serviceName,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                StatusBadge(
                  label: _getStatusLabel(status),
                  color: _getStatusColor(status),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(Icons.event, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 6),
                Text(
                  date.split(' ')[0],
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.grey[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (doctorName != 'N/A')
              Text(
                'Administered by: $doctorName',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
            if (notes != 'N/A' && notes.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(10.0),
                decoration: BoxDecoration(
                  color: Colors.grey[100],
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Notes:',
                      style: TextStyle(
                        fontSize: 11,
                        color: Colors.grey[600],
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      notes,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[700],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
