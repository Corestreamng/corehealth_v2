import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';

/// Read-only detail of a single encounter with all sub-data.
class EncounterDetailScreen extends StatefulWidget {
  final int encounterId;

  const EncounterDetailScreen({super.key, required this.encounterId});

  @override
  State<EncounterDetailScreen> createState() => _EncounterDetailScreenState();
}

class _EncounterDetailScreenState extends State<EncounterDetailScreen> {
  late PatientApiService _api;
  PatientEncounterDetail? _detail;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final res = await _api.getEncounterDetail(widget.encounterId);

    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() {
        _detail = PatientEncounterDetail.fromJson(
            res.data is Map<String, dynamic> ? res.data : {});
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Visit Details')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
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

    final d = _detail!;
    return RefreshIndicator(
      onRefresh: _load,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Encounter Header ──
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.medical_services_rounded,
                            color: Theme.of(context).colorScheme.primary),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(d.doctorName ?? 'Doctor',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 16)),
                              if (d.clinicName != null)
                                Text(d.clinicName!,
                                    style: TextStyle(
                                        fontSize: 12,
                                        color: Colors.grey.shade600)),
                            ],
                          ),
                        ),
                        Text(_formatDate(d.createdAt),
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade500)),
                      ],
                    ),
                    if (d.reasons.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 4,
                        runSpacing: 4,
                        children: d.reasons
                            .map((r) => Chip(
                                  label: Text(r,
                                      style: const TextStyle(fontSize: 10)),
                                  materialTapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                  padding: EdgeInsets.zero,
                                  labelPadding:
                                      const EdgeInsets.symmetric(horizontal: 6),
                                ))
                            .toList(),
                      ),
                    ],
                  ],
                ),
              ),
            ),

            // ── Diagnosis ──
            if (d.diagnosis != null && d.diagnosis!.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Diagnosis', icon: Icons.medical_information),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(d.diagnosis!,
                      style: const TextStyle(fontSize: 13, height: 1.5)),
                ),
              ),
            ],

            // ── Presenting Complaints ──
            if (d.comment1 != null && d.comment1!.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Presenting Complaints',
                  icon: Icons.report_problem_outlined),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(d.comment1!,
                      style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade700,
                          height: 1.5)),
                ),
              ),
            ],

            // ── Vitals ──
            if (d.vitals.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Vital Signs', icon: Icons.monitor_heart),
              ...d.vitals.map((v) => _VitalRow(vital: v)),
            ],

            // ── Labs ──
            if (d.labs.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(title: 'Lab Tests', icon: Icons.science),
              ...d.labs.map((l) => _ResultTile(
                    title: l.serviceName ?? 'Lab Test',
                    status: l.statusLabel,
                    result: l.result,
                    date: l.resultDate ?? l.createdAt,
                  )),
            ],

            // ── Imaging ──
            if (d.imaging.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Imaging Studies', icon: Icons.image),
              ...d.imaging.map((i) => _ResultTile(
                    title: i.serviceName ?? 'Imaging',
                    status: i.statusLabel,
                    result: i.result,
                    date: i.resultDate ?? i.createdAt,
                  )),
            ],

            // ── Prescriptions ──
            if (d.prescriptions.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Prescriptions', icon: Icons.medication),
              ...d.prescriptions.map((p) => Card(
                    margin: const EdgeInsets.only(bottom: 4),
                    child: ListTile(
                      dense: true,
                      leading:
                          Icon(Icons.medication, color: Colors.orange.shade700),
                      title: Text(p.productName ?? 'Medication',
                          style: const TextStyle(
                              fontSize: 12, fontWeight: FontWeight.w500)),
                      subtitle: p.dose != null
                          ? Text(p.dose!,
                              style: TextStyle(
                                  fontSize: 11, color: Colors.grey.shade600))
                          : null,
                      trailing: StatusBadge.fromStatus(p.statusLabel),
                    ),
                  )),
            ],

            // ── Procedures ──
            if (d.procedures.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Procedures', icon: Icons.medical_services),
              ...d.procedures.map((p) => Card(
                    margin: const EdgeInsets.only(bottom: 4),
                    child: ListTile(
                      dense: true,
                      leading:
                          Icon(Icons.medical_services, color: Colors.teal.shade700),
                      title: Text(p.serviceName ?? 'Procedure',
                          style: const TextStyle(
                              fontSize: 12, fontWeight: FontWeight.w500)),
                      subtitle: p.scheduledDate != null
                          ? Text('Scheduled: ${p.scheduledDate}',
                              style: TextStyle(
                                  fontSize: 11, color: Colors.grey.shade600))
                          : null,
                      trailing: p.procedureStatus != null
                          ? StatusBadge.fromStatus(p.procedureStatus!)
                          : null,
                    ),
                  )),
            ],

            // ── Clinical Notes ──
            if (d.notes != null && d.notes!.isNotEmpty) ...[
              const SizedBox(height: 16),
              const SectionHeader(
                  title: 'Clinical Notes', icon: Icons.description),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text(d.notes!,
                      style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade700,
                          height: 1.5)),
                ),
              ),
            ],

            const SizedBox(height: 40),
          ],
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

class _VitalRow extends StatelessWidget {
  final PatientVital vital;

  const _VitalRow({required this.vital});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 6),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (vital.createdAt != null)
              Text(vital.createdAt!,
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 12,
              runSpacing: 6,
              children: [
                if (vital.temperature != null)
                  _vitalChip('Temp', '${vital.temperature}°C',
                      VitalCard.vitalColor('temp', vital.temperature)),
                if (vital.systolic != null)
                  _vitalChip('BP', vital.bpDisplay,
                      VitalCard.vitalColor('bp', vital.systolic?.toDouble())),
                if (vital.heartRate != null)
                  _vitalChip('HR', '${vital.heartRate} bpm',
                      VitalCard.vitalColor('hr', vital.heartRate?.toDouble())),
                if (vital.respiratoryRate != null)
                  _vitalChip('RR', '${vital.respiratoryRate}',
                      VitalCard.vitalColor('rr', vital.respiratoryRate?.toDouble())),
                if (vital.spo2 != null)
                  _vitalChip('SpO₂', '${vital.spo2}%',
                      VitalCard.vitalColor('spo2', vital.spo2)),
                if (vital.weight != null)
                  _vitalChip('Weight', '${vital.weight} kg', Colors.blue.shade700),
                if (vital.bloodSugar != null)
                  _vitalChip('Sugar', '${vital.bloodSugar}',
                      VitalCard.vitalColor('sugar', vital.bloodSugar)),
                if (vital.bmi != null)
                  _vitalChip('BMI', vital.bmi!.toStringAsFixed(1),
                      VitalCard.vitalColor('bmi', vital.bmi)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _vitalChip(String label, String value, Color color) {
    return Column(
      children: [
        Text(value,
            style: TextStyle(
                fontSize: 14, fontWeight: FontWeight.w700, color: color)),
        Text(label, style: TextStyle(fontSize: 9, color: Colors.grey.shade600)),
      ],
    );
  }
}

class _ResultTile extends StatelessWidget {
  final String title;
  final String status;
  final String? result;
  final String? date;

  const _ResultTile({
    required this.title,
    required this.status,
    this.result,
    this.date,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 4),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(title,
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w500)),
                ),
                StatusBadge.fromStatus(status),
              ],
            ),
            if (result != null && result!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(result!,
                    style:
                        TextStyle(fontSize: 11, color: Colors.green.shade800)),
              ),
            ],
            if (date != null) ...[
              const SizedBox(height: 4),
              Text(date!,
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
            ],
          ],
        ),
      ),
    );
  }
}
