import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 9: Encounter summary — overview of everything in one glance.
class SummaryTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;

  const SummaryTab({super.key, required this.api, required this.encounter});

  @override
  State<SummaryTab> createState() => _SummaryTabState();
}

class _SummaryTabState extends State<SummaryTab>
    with AutomaticKeepAliveClientMixin {
  Map<String, dynamic>? _summary;
  bool _isLoading = true;
  String? _error;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _loadSummary();
  }

  Future<void> _loadSummary() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final res = await widget.api.getEncounterSummary(widget.encounter.id);

    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() {
        _summary = res.data;
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message.isNotEmpty ? res.message : 'Failed to load summary';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _loadSummary,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    final enc = widget.encounter;
    final diagnosis = _summary?['diagnosis'] ?? enc.doctorDiagnosis ?? '';
    final labs = _summary?['labs'] as List? ?? [];
    final imaging = _summary?['imaging'] as List? ?? [];
    final prescriptions = _summary?['prescriptions'] as List? ?? [];
    final primary = Theme.of(context).colorScheme.primary;

    return RefreshIndicator(
      onRefresh: _loadSummary,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Encounter Status ──
            Card(
              color: enc.completed
                  ? Colors.green.shade50
                  : primary.withValues(alpha: 0.05),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(
                      enc.completed
                          ? Icons.check_circle
                          : Icons.play_circle,
                      color:
                          enc.completed ? Colors.green.shade700 : primary,
                      size: 28,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            enc.completed ? 'Completed' : 'In Progress',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: enc.completed
                                  ? Colors.green.shade800
                                  : primary,
                            ),
                          ),
                          Text(
                            'Encounter #${enc.id} · ${enc.createdAt}',
                            style: TextStyle(
                                fontSize: 12, color: Colors.grey.shade600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // ── Diagnosis ──
            const SectionHeader(
                title: 'Diagnosis', icon: Icons.medical_information),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (diagnosis.toString().isNotEmpty)
                      Text(diagnosis.toString(),
                          style: const TextStyle(fontSize: 13, height: 1.5))
                    else
                      Text('No diagnosis recorded',
                          style: TextStyle(
                              fontSize: 13,
                              color: Colors.grey.shade500,
                              fontStyle: FontStyle.italic)),
                    if (enc.reasonsForEncounter.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: enc.reasonsForEncounter
                            .map((r) => Chip(
                                  label: Text(r,
                                      style: const TextStyle(fontSize: 10)),
                                  materialTapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                  padding: EdgeInsets.zero,
                                  labelPadding: const EdgeInsets.symmetric(
                                      horizontal: 6),
                                ))
                            .toList(),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // ── Stats Row ──
            Row(
              children: [
                _SummaryStatCard(
                  icon: Icons.science,
                  label: 'Labs',
                  count: labs.length,
                  color: Colors.blue.shade700,
                ),
                const SizedBox(width: 8),
                _SummaryStatCard(
                  icon: Icons.image,
                  label: 'Imaging',
                  count: imaging.length,
                  color: Colors.purple.shade700,
                ),
                const SizedBox(width: 8),
                _SummaryStatCard(
                  icon: Icons.medication,
                  label: 'Meds',
                  count: prescriptions.length,
                  color: Colors.orange.shade700,
                ),
                const SizedBox(width: 8),
                _SummaryStatCard(
                  icon: Icons.medical_services,
                  label: 'Procs',
                  count: enc.procedures.length,
                  color: Colors.teal.shade700,
                ),
              ],
            ),
            const SizedBox(height: 16),

            // ── Labs summary ──
            if (labs.isNotEmpty) ...[
              const SectionHeader(title: 'Lab Tests', icon: Icons.science),
              ...labs.map((l) => _SummaryItemTile(
                    title: l['service_name']?.toString() ?? 'Unknown',
                    status: l['status_label']?.toString() ?? '',
                    result: l['result']?.toString(),
                  )),
              const SizedBox(height: 12),
            ],

            // ── Imaging summary ──
            if (imaging.isNotEmpty) ...[
              const SectionHeader(
                  title: 'Imaging Studies', icon: Icons.image),
              ...imaging.map((i) => _SummaryItemTile(
                    title: i['service_name']?.toString() ?? 'Unknown',
                    status: i['status_label']?.toString() ?? '',
                    result: i['result']?.toString(),
                  )),
              const SizedBox(height: 12),
            ],

            // ── Prescriptions summary ──
            if (prescriptions.isNotEmpty) ...[
              const SectionHeader(
                  title: 'Prescriptions', icon: Icons.medication),
              ...prescriptions.map((p) => _SummaryItemTile(
                    title: p['product_name']?.toString() ?? 'Unknown',
                    status: p['status_label']?.toString() ?? '',
                    subtitle: p['dose']?.toString(),
                  )),
              const SizedBox(height: 12),
            ],

            // ── Notes excerpt ──
            if (enc.notes != null && enc.notes!.isNotEmpty) ...[
              const SectionHeader(
                  title: 'Clinical Notes', icon: Icons.description),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(
                    enc.notes!.length > 500
                        ? '${enc.notes!.substring(0, 500)}...'
                        : enc.notes!,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade700,
                      height: 1.5,
                    ),
                  ),
                ),
              ),
            ],

            const SizedBox(height: 80),
          ],
        ),
      ),
    );
  }
}

class _SummaryStatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final int count;
  final Color color;

  const _SummaryStatCard({
    required this.icon,
    required this.label,
    required this.count,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          child: Column(
            children: [
              Icon(icon, color: color, size: 20),
              const SizedBox(height: 4),
              Text(
                '$count',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
              Text(
                label,
                style: TextStyle(fontSize: 10, color: Colors.grey.shade600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryItemTile extends StatelessWidget {
  final String title;
  final String status;
  final String? result;
  final String? subtitle;

  const _SummaryItemTile({
    required this.title,
    required this.status,
    this.result,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        dense: true,
        title: Text(title,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500)),
        subtitle: subtitle != null
            ? Text(subtitle!,
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600))
            : result != null && result!.isNotEmpty
                ? Text('Result: $result',
                    style: TextStyle(
                        fontSize: 11, color: Colors.green.shade700))
                : null,
        trailing:
            StatusBadge.fromStatus(status),
      ),
    );
  }
}
