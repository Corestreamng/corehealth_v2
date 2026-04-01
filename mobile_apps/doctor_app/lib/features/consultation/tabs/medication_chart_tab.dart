import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/shared_widgets.dart';
import '../../../core/widgets/status_badge.dart';

/// Tab: Medication Chart — shows prescribed drugs, dispensing status,
/// and administration tracking (read-only for the doctor).
class MedicationChartTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;

  const MedicationChartTab({
    super.key,
    required this.api,
    required this.encounter,
  });

  @override
  State<MedicationChartTab> createState() => _MedicationChartTabState();
}

class _MedicationChartTabState extends State<MedicationChartTab>
    with AutomaticKeepAliveClientMixin {
  bool _isLoading = true;
  String? _error;
  List<Map<String, dynamic>> _prescriptions = [];
  List<Map<String, dynamic>> _directEntries = [];
  Map<String, dynamic> _summary = {};

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final res = await widget.api.getMedicationChart(widget.encounter.patientId);
    if (!mounted) return;

    if (res.success && res.data != null) {
      final d = res.data!;
      setState(() {
        _prescriptions = List<Map<String, dynamic>>.from(d['prescriptions'] ?? []);
        _directEntries = List<Map<String, dynamic>>.from(d['direct_entries'] ?? []);
        _summary = Map<String, dynamic>.from(d['summary'] ?? {});
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message.isNotEmpty ? res.message : 'Failed to load medication chart';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    if (_prescriptions.isEmpty && _directEntries.isEmpty) {
      return const EmptyState(
        icon: Icons.medication_outlined,
        title: 'No medications charted',
        subtitle: 'Prescribed medications will appear here once ordered.',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          // ── Summary cards ──
          _buildSummaryRow(),
          const SizedBox(height: 12),

          // ── Prescriptions ──
          if (_prescriptions.isNotEmpty) ...[
            const SectionHeader(title: 'Prescriptions', icon: Icons.medication),
            const SizedBox(height: 6),
            ..._prescriptions.map(_buildPrescriptionCard),
          ],

          // ── Direct Administrations ──
          if (_directEntries.isNotEmpty) ...[
            const SizedBox(height: 16),
            const SectionHeader(title: 'Direct Administrations', icon: Icons.vaccines),
            const SizedBox(height: 6),
            ..._directEntries.map(_buildDirectCard),
          ],
        ],
      ),
    );
  }

  Widget _buildSummaryRow() {
    return Row(
      children: [
        _StatChip(
          label: 'Total',
          count: _summary['total'] ?? 0,
          color: Colors.blue.shade700,
        ),
        const SizedBox(width: 6),
        _StatChip(
          label: 'Dispensed',
          count: _summary['dispensed'] ?? 0,
          color: Colors.green.shade700,
        ),
        const SizedBox(width: 6),
        _StatChip(
          label: 'Awaiting',
          count: (_summary['awaiting_payment'] ?? 0) +
              (_summary['awaiting_pharmacy'] ?? 0) +
              (_summary['awaiting_billing'] ?? 0),
          color: Colors.orange.shade700,
        ),
      ],
    );
  }

  Widget _buildPrescriptionCard(Map<String, dynamic> rx) {
    final name = rx['product_name']?.toString() ?? 'Unknown';
    final dose = rx['dose']?.toString() ?? '';
    final qty = rx['qty_prescribed']?.toString() ?? '';
    final statusLabel = rx['status_label']?.toString() ?? '';
    final statusColor = _colorFromStatus(rx['status_color']?.toString());
    final doctor = rx['doctor_name']?.toString() ?? '';
    final remaining = rx['remaining_doses'] ?? 0;
    final administered = rx['times_administered'] ?? 0;
    final scheduled = rx['times_scheduled'] ?? 0;
    final isDispensed = rx['is_dispensed'] == true;
    final isFullyAdmin = rx['is_fully_administered'] == true;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Drug name + status
            Row(
              children: [
                Expanded(
                  child: Text(name,
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                StatusBadge(label: statusLabel, color: statusColor),
              ],
            ),
            if (dose.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text('Dose: $dose  ×  Qty: $qty',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
            ],
            const SizedBox(height: 8),

            // Administration progress
            if (isDispensed) ...[
              Row(
                children: [
                  _InfoChip(
                      icon: Icons.check_circle_outline,
                      label: 'Administered: $administered',
                      color: Colors.green),
                  const SizedBox(width: 8),
                  _InfoChip(
                      icon: Icons.schedule,
                      label: 'Scheduled: $scheduled',
                      color: Colors.blue),
                  const SizedBox(width: 8),
                  _InfoChip(
                      icon: Icons.inventory_2_outlined,
                      label: 'Remaining: $remaining',
                      color: isFullyAdmin ? Colors.grey : Colors.orange),
                ],
              ),
              const SizedBox(height: 6),
            ],

            // Doctor
            if (doctor.isNotEmpty)
              Row(
                children: [
                  Icon(Icons.person_outline,
                      size: 12, color: Colors.grey.shade400),
                  const SizedBox(width: 4),
                  Text('Dr. $doctor',
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey.shade500)),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildDirectCard(Map<String, dynamic> entry) {
    final name = entry['product_name']?.toString() ?? 'Unknown';
    final source = entry['drug_source']?.toString() ?? '';
    final administered = entry['times_administered'] ?? 0;
    final nurse = entry['nurse_name']?.toString() ?? '';

    final sourceLabel = source == 'ward_stock'
        ? 'Ward Stock'
        : source == 'patient_own'
            ? "Patient's Own"
            : source;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      color: Colors.amber.shade50,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(name,
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                StatusBadge(
                    label: sourceLabel, color: Colors.amber.shade800),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                _InfoChip(
                    icon: Icons.check_circle_outline,
                    label: 'Administered: $administered',
                    color: Colors.green),
              ],
            ),
            if (nurse.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text('By: $nurse',
                  style:
                      TextStyle(fontSize: 11, color: Colors.grey.shade500)),
            ],
          ],
        ),
      ),
    );
  }

  Color _colorFromStatus(String? s) {
    return switch (s) {
      'success' => Colors.green.shade700,
      'danger' => Colors.red.shade700,
      'warning' => Colors.orange.shade700,
      'secondary' => Colors.grey.shade600,
      _ => Colors.blue.shade700,
    };
  }
}

class _StatChip extends StatelessWidget {
  final String label;
  final int count;
  final Color color;
  const _StatChip(
      {required this.label, required this.count, required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Column(
          children: [
            Text('$count',
                style: TextStyle(
                    fontSize: 18, fontWeight: FontWeight.w700, color: color)),
            const SizedBox(height: 2),
            Text(label,
                style: TextStyle(fontSize: 10, color: color),
                textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _InfoChip(
      {required this.icon, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: color),
        const SizedBox(width: 3),
        Text(label, style: TextStyle(fontSize: 11, color: color)),
      ],
    );
  }
}
