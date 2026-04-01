import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';

/// Read-only detail of a single encounter with tabbed sub-data.
class EncounterDetailScreen extends StatefulWidget {
  final int encounterId;

  const EncounterDetailScreen({super.key, required this.encounterId});

  @override
  State<EncounterDetailScreen> createState() => _EncounterDetailScreenState();
}

class _EncounterDetailScreenState extends State<EncounterDetailScreen>
    with TickerProviderStateMixin {
  late PatientApiService _api;
  PatientEncounterDetail? _detail;
  bool _isLoading = true;
  String? _error;
  late TabController _tabController;

  final List<_TabDef> _tabs = [];

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
    _tabController = TabController(length: 1, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final res = await _api.getEncounterDetail(widget.encounterId);

    if (!mounted) return;
    if (res.success && res.data != null) {
      final detail = PatientEncounterDetail.fromJson(
          res.data is Map<String, dynamic> ? res.data : {});
      _buildTabs(detail);
      setState(() {
        _detail = detail;
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message;
        _isLoading = false;
      });
    }
  }

  void _buildTabs(PatientEncounterDetail d) {
    _tabs.clear();
    _tabs.add(_TabDef('Overview', Icons.info_outline));
    if (d.vitals.isNotEmpty) {
      _tabs.add(_TabDef('Vitals', Icons.monitor_heart));
    }
    if (d.labs.isNotEmpty) {
      _tabs.add(_TabDef('Labs', Icons.science));
    }
    if (d.imaging.isNotEmpty) {
      _tabs.add(_TabDef('Imaging', Icons.image));
    }
    if (d.prescriptions.isNotEmpty) {
      _tabs.add(_TabDef('Meds', Icons.medication));
    }
    if (d.procedures.isNotEmpty) {
      _tabs.add(_TabDef('Procedures', Icons.medical_services));
    }
    if (d.referrals.isNotEmpty) {
      _tabs.add(_TabDef('Referrals', Icons.swap_horiz));
    }
    if (d.nursingNotes.isNotEmpty ||
        (d.notes != null && d.notes!.isNotEmpty)) {
      _tabs.add(_TabDef('Notes', Icons.description));
    }
    if (d.admission != null) {
      _tabs.add(_TabDef('Admission', Icons.hotel));
    }
    _tabController.dispose();
    _tabController = TabController(length: _tabs.length, vsync: this);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Visit Details'),
        bottom: (!_isLoading && _detail != null && _tabs.length > 1)
            ? TabBar(
                controller: _tabController,
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                tabs: _tabs
                    .map((t) => Tab(icon: Icon(t.icon, size: 18), text: t.label))
                    .toList(),
              )
            : null,
      ),
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
    if (_tabs.length <= 1) {
      return RefreshIndicator(
        onRefresh: _load,
        child: _OverviewTab(detail: d),
      );
    }

    return TabBarView(
      controller: _tabController,
      children: _tabs.map((t) {
        switch (t.label) {
          case 'Overview':
            return RefreshIndicator(
                onRefresh: _load, child: _OverviewTab(detail: d));
          case 'Vitals':
            return _VitalsTab(vitals: d.vitals);
          case 'Labs':
            return _LabsTab(labs: d.labs);
          case 'Imaging':
            return _ImagingTab(imaging: d.imaging);
          case 'Meds':
            return _MedsTab(prescriptions: d.prescriptions);
          case 'Procedures':
            return _ProceduresTab(procedures: d.procedures);
          case 'Referrals':
            return _ReferralsTab(referrals: d.referrals);
          case 'Notes':
            return _NotesTab(
                clinicalNotes: d.notes, nursingNotes: d.nursingNotes);
          case 'Admission':
            return _AdmissionTab(admission: d.admission!);
          default:
            return const SizedBox();
        }
      }).toList(),
    );
  }
}

class _TabDef {
  final String label;
  final IconData icon;
  _TabDef(this.label, this.icon);
}

// ═══════════════════════════════════════════════════════════════
//  Overview Tab
// ═══════════════════════════════════════════════════════════════

class _OverviewTab extends StatelessWidget {
  final PatientEncounterDetail detail;
  const _OverviewTab({required this.detail});

  @override
  Widget build(BuildContext context) {
    final d = detail;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Encounter header card
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
                                    fontWeight: FontWeight.w700, fontSize: 16)),
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
                  if (d.diagnosisEntries.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Table(
                      columnWidths: const {
                        0: FlexColumnWidth(1),
                        1: FlexColumnWidth(2.5),
                        2: FlexColumnWidth(1.2),
                        3: FlexColumnWidth(1.2),
                      },
                      border: TableBorder.all(
                          color: Colors.grey.shade300, width: 0.5),
                      children: [
                        TableRow(
                          decoration: BoxDecoration(
                            color: Theme.of(context)
                                .colorScheme
                                .primary
                                .withValues(alpha: 0.08),
                          ),
                          children: const [
                            _THCell('Code'),
                            _THCell('Diagnosis'),
                            _THCell('Status'),
                            _THCell('Course'),
                          ],
                        ),
                        ...d.diagnosisEntries.map((e) => TableRow(
                              children: [
                                _TDCell(e.code),
                                _TDCell(e.name),
                                _TDCell(e.status,
                                    color: _dxStatusColor(e.status)),
                                _TDCell(e.course,
                                    color: _dxCourseColor(e.course)),
                              ],
                            )),
                      ],
                    ),
                  ] else if (d.reasons.isNotEmpty) ...[
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

          // Doctor's notes (HTML)
          if (d.diagnosis != null && d.diagnosis!.isNotEmpty) ...[
            const SizedBox(height: 16),
            const SectionHeader(
                title: "Doctor's Notes", icon: Icons.description),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: HtmlContent(
                    data: d.diagnosis!,
                    style: const TextStyle(fontSize: 13, height: 1.5)),
              ),
            ),
          ],

          // Presenting Complaints (comment_1)
          if (d.comment1 != null && d.comment1!.isNotEmpty) ...[
            const SizedBox(height: 16),
            const SectionHeader(
                title: 'Presenting Complaints',
                icon: Icons.report_problem_outlined),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: HtmlContent(
                    data: d.comment1!,
                    style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade700,
                        height: 1.5)),
              ),
            ),
          ],
          // History of Presenting Illness (comment_2)
          if (d.comment2 != null && d.comment2!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: HtmlContent(
                    data: d.comment2!,
                    style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade700,
                        height: 1.5)),
              ),
            ),
          ],

          // Summary counts
          const SizedBox(height: 16),
          const SectionHeader(title: 'Summary', icon: Icons.dashboard),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Wrap(
                spacing: 16,
                runSpacing: 12,
                children: [
                  _CountChip('Vitals', d.vitals.length, Icons.monitor_heart,
                      Colors.red),
                  _CountChip(
                      'Labs', d.labs.length, Icons.science, Colors.blue),
                  _CountChip(
                      'Imaging', d.imaging.length, Icons.image, Colors.purple),
                  _CountChip('Meds', d.prescriptions.length,
                      Icons.medication, Colors.orange),
                  _CountChip('Procedures', d.procedures.length,
                      Icons.medical_services, Colors.teal),
                  _CountChip('Referrals', d.referrals.length,
                      Icons.swap_horiz, Colors.indigo),
                ],
              ),
            ),
          ),

          const SizedBox(height: 40),
        ],
      ),
    );
  }

  static String _formatDate(String? date) {
    if (date == null) return '';
    try {
      final d = DateTime.parse(date);
      const months = [
        '', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
      ];
      return '${d.day} ${months[d.month]} ${d.year}';
    } catch (_) {
      return date;
    }
  }
}

class _CountChip extends StatelessWidget {
  final String label;
  final int count;
  final IconData icon;
  final Color color;
  const _CountChip(this.label, this.count, this.icon, this.color);

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 16, color: count > 0 ? color : Colors.grey.shade400),
        const SizedBox(width: 4),
        Text('$count $label',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: count > 0 ? color : Colors.grey.shade400,
            )),
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Vitals Tab
// ═══════════════════════════════════════════════════════════════

class _VitalsTab extends StatelessWidget {
  final List<PatientVital> vitals;
  const _VitalsTab({required this.vitals});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: vitals.length,
      itemBuilder: (_, i) => _VitalCard(vital: vitals[i]),
    );
  }
}

class _VitalCard extends StatelessWidget {
  final PatientVital vital;
  const _VitalCard({required this.vital});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (vital.createdAt != null)
              Text(vital.createdAt!,
                  style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
            const SizedBox(height: 8),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              childAspectRatio: 1.5,
              mainAxisSpacing: 4,
              crossAxisSpacing: 4,
              children: [
                if (vital.systolic != null)
                  _VitalTile('BP', vital.bpDisplay,
                      VitalCard.vitalColor('bp', vital.systolic?.toDouble())),
                if (vital.temperature != null)
                  _VitalTile('Temp', '${vital.temperature}\u00B0C',
                      VitalCard.vitalColor('temp', vital.temperature)),
                if (vital.heartRate != null)
                  _VitalTile('HR', '${vital.heartRate} bpm',
                      VitalCard.vitalColor('hr', vital.heartRate?.toDouble())),
                if (vital.respiratoryRate != null)
                  _VitalTile(
                      'RR',
                      '${vital.respiratoryRate}',
                      VitalCard.vitalColor(
                          'rr', vital.respiratoryRate?.toDouble())),
                if (vital.spo2 != null)
                  _VitalTile('SpO\u2082', '${vital.spo2}%',
                      VitalCard.vitalColor('spo2', vital.spo2)),
                if (vital.weight != null)
                  _VitalTile(
                      'Weight', '${vital.weight} kg', Colors.blue.shade700),
                if (vital.height != null)
                  _VitalTile(
                      'Height', '${vital.height} cm', Colors.green.shade700),
                if (vital.bmi != null)
                  _VitalTile('BMI', vital.bmi!.toStringAsFixed(1),
                      VitalCard.vitalColor('bmi', vital.bmi)),
                if (vital.bloodSugar != null)
                  _VitalTile('Sugar', '${vital.bloodSugar}',
                      VitalCard.vitalColor('sugar', vital.bloodSugar)),
                if (vital.painScore != null)
                  _VitalTile(
                      'Pain', '${vital.painScore}/10', Colors.red.shade700),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _VitalTile extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  const _VitalTile(this.label, this.value, this.color);

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(value,
            style: TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: color),
            textAlign: TextAlign.center),
        const SizedBox(height: 2),
        Text(label,
            style: TextStyle(fontSize: 9, color: Colors.grey.shade600)),
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Labs Tab
// ═══════════════════════════════════════════════════════════════

class _LabsTab extends StatelessWidget {
  final List<PatientLabResult> labs;
  const _LabsTab({required this.labs});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: labs.length,
      itemBuilder: (_, i) => _LabCard(lab: labs[i]),
    );
  }
}

class _LabCard extends StatelessWidget {
  final PatientLabResult lab;
  const _LabCard({required this.lab});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.science, size: 18, color: Colors.blue.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(lab.serviceName ?? 'Lab Test',
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 14)),
                ),
                StatusBadge.fromStatus(lab.statusLabel),
              ],
            ),
            if (lab.note != null && lab.note!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('Note: ${lab.note!}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],
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
            if (lab.isRejected && lab.rejectionReason != null) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text('Rejected: ${lab.rejectionReason!}',
                    style:
                        TextStyle(fontSize: 12, color: Colors.red.shade800)),
              ),
            ],
            const SizedBox(height: 6),
            Text(lab.resultDate ?? lab.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Imaging Tab
// ═══════════════════════════════════════════════════════════════

class _ImagingTab extends StatelessWidget {
  final List<PatientImagingResult> imaging;
  const _ImagingTab({required this.imaging});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: imaging.length,
      itemBuilder: (_, i) => _ImagingCard(img: imaging[i]),
    );
  }
}

class _ImagingCard extends StatelessWidget {
  final PatientImagingResult img;
  const _ImagingCard({required this.img});

  @override
  Widget build(BuildContext context) {
    return Card(
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
                  child: Text(img.serviceName ?? 'Imaging',
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 14)),
                ),
                StatusBadge.fromStatus(img.statusLabel),
              ],
            ),
            if (img.note != null && img.note!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('Note: ${img.note!}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],
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
                    Text('Result',
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
            if (img.isRejected && img.rejectionReason != null) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text('Rejected: ${img.rejectionReason!}',
                    style:
                        TextStyle(fontSize: 12, color: Colors.red.shade800)),
              ),
            ],
            const SizedBox(height: 6),
            Text(img.resultDate ?? img.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Medications Tab
// ═══════════════════════════════════════════════════════════════

class _MedsTab extends StatelessWidget {
  final List<PatientPrescription> prescriptions;
  const _MedsTab({required this.prescriptions});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: prescriptions.length,
      itemBuilder: (_, i) => _MedCard(rx: prescriptions[i]),
    );
  }
}

class _MedCard extends StatelessWidget {
  final PatientPrescription rx;
  const _MedCard({required this.rx});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.medication, size: 18, color: Colors.orange.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(rx.productName ?? 'Medication',
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 14)),
                ),
                StatusBadge.fromStatus(rx.statusLabel),
              ],
            ),
            const SizedBox(height: 8),
            if (rx.doseDisplay.isNotEmpty) _infoRow('Dosage', rx.doseDisplay),
            if (rx.qty != null) _infoRow('Quantity', '${rx.qty}'),
            if (rx.specialInstruction != null &&
                rx.specialInstruction!.isNotEmpty)
              _infoRow('Instructions', rx.specialInstruction!),
            const SizedBox(height: 4),
            Text(rx.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(label,
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Procedures Tab
// ═══════════════════════════════════════════════════════════════

class _ProceduresTab extends StatelessWidget {
  final List<PatientProcedure> procedures;
  const _ProceduresTab({required this.procedures});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: procedures.length,
      itemBuilder: (_, i) => _ProcedureCard(proc: procedures[i]),
    );
  }
}

class _ProcedureCard extends StatelessWidget {
  final PatientProcedure proc;
  const _ProcedureCard({required this.proc});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.medical_services,
                    size: 18, color: Colors.teal.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(proc.serviceName ?? 'Procedure',
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 14)),
                ),
                if (proc.procedureStatus != null)
                  StatusBadge.fromStatus(
                      proc.procedureStatus!.replaceAll('_', ' ')),
              ],
            ),
            const SizedBox(height: 8),
            if (proc.priority != null) _infoRow('Priority', proc.priority!),
            if (proc.scheduledDate != null)
              _infoRow('Scheduled',
                  '${proc.scheduledDate}${proc.scheduledTime != null ? ' ${proc.scheduledTime}' : ''}'),
            if (proc.operatingRoom != null &&
                proc.operatingRoom!.isNotEmpty)
              _infoRow('Room', proc.operatingRoom!),
            if (proc.outcome != null) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: proc.outcome == 'successful'
                      ? Colors.green.shade50
                      : Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Outcome: ${proc.outcome}',
                        style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: proc.outcome == 'successful'
                                ? Colors.green.shade800
                                : Colors.orange.shade800)),
                    if (proc.outcomeNotes != null &&
                        proc.outcomeNotes!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(proc.outcomeNotes!,
                          style: const TextStyle(fontSize: 11)),
                    ],
                  ],
                ),
              ),
            ],
            if (proc.preNotes != null && proc.preNotes!.isNotEmpty) ...[
              const SizedBox(height: 6),
              _notesBlock('Pre-op Notes', proc.preNotes!),
            ],
            if (proc.postNotes != null && proc.postNotes!.isNotEmpty) ...[
              const SizedBox(height: 6),
              _notesBlock('Post-op Notes', proc.postNotes!),
            ],
            const SizedBox(height: 4),
            Text(proc.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(label,
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }

  Widget _notesBlock(String title, String text) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade700)),
          const SizedBox(height: 4),
          Text(text, style: const TextStyle(fontSize: 12, height: 1.4)),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Referrals Tab
// ═══════════════════════════════════════════════════════════════

class _ReferralsTab extends StatelessWidget {
  final List<PatientReferral> referrals;
  const _ReferralsTab({required this.referrals});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: referrals.length,
      itemBuilder: (_, i) => _ReferralCard(referral: referrals[i]),
    );
  }
}

class _ReferralCard extends StatelessWidget {
  final PatientReferral referral;
  const _ReferralCard({required this.referral});

  @override
  Widget build(BuildContext context) {
    final r = referral;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.swap_horiz,
                    size: 18, color: Colors.indigo.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    r.referralType == 'external'
                        ? 'External Referral'
                        : 'Internal Referral',
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                ),
                if (r.isUrgent)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.red.shade100,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(r.urgency!.toUpperCase(),
                        style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: Colors.red.shade800)),
                  ),
                if (!r.isUrgent && r.urgency != null)
                  StatusBadge.fromStatus(r.urgency!),
              ],
            ),
            const SizedBox(height: 10),
            if (r.fromDoctor != null)
              _infoRow('From',
                  '${r.fromDoctor}${r.fromClinic != null && r.fromClinic!.isNotEmpty ? ' (${r.fromClinic})' : ''}'),
            if (r.toDoctor != null && r.toDoctor!.isNotEmpty)
              _infoRow('To Doctor', r.toDoctor!),
            if (r.toClinic != null && r.toClinic!.isNotEmpty)
              _infoRow('To Clinic', r.toClinic!),
            if (r.reason != null && r.reason!.isNotEmpty)
              _infoRow('Reason', r.reason!),
            if (r.provisionalDiagnosis != null &&
                r.provisionalDiagnosis!.isNotEmpty)
              _infoRow('Diagnosis', r.provisionalDiagnosis!),
            if (r.status != null) ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  Text('Status: ',
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey.shade600)),
                  StatusBadge.fromStatus(r.status!),
                ],
              ),
            ],
            if (r.clinicalSummary != null &&
                r.clinicalSummary!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Clinical Summary',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.blue.shade800)),
                    const SizedBox(height: 4),
                    Text(r.clinicalSummary!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.blue.shade900)),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 4),
            Text(r.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(label,
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Notes Tab (Clinical + Nursing)
// ═══════════════════════════════════════════════════════════════

class _NotesTab extends StatelessWidget {
  final String? clinicalNotes;
  final List<PatientNursingNote> nursingNotes;
  const _NotesTab({this.clinicalNotes, required this.nursingNotes});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (clinicalNotes != null && clinicalNotes!.isNotEmpty) ...[
            const SectionHeader(
                title: "Doctor's Notes", icon: Icons.description),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: HtmlContent(
                    data: clinicalNotes!,
                    style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade700,
                        height: 1.5)),
              ),
            ),
            const SizedBox(height: 16),
          ],
          if (nursingNotes.isNotEmpty) ...[
            const SectionHeader(
                title: 'Nursing Notes', icon: Icons.note_alt),
            ...nursingNotes.map((n) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: Colors.teal.shade50,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(n.type ?? 'General',
                                  style: TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w600,
                                      color: Colors.teal.shade800)),
                            ),
                            const Spacer(),
                            Text(n.createdBy ?? '',
                                style: TextStyle(
                                    fontSize: 10,
                                    color: Colors.grey.shade500)),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(n.note ?? '',
                            style:
                                const TextStyle(fontSize: 12, height: 1.4)),
                        const SizedBox(height: 4),
                        Text(n.createdAt ?? '',
                            style: TextStyle(
                                fontSize: 10,
                                color: Colors.grey.shade500)),
                      ],
                    ),
                  ),
                )),
          ],
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Admission Tab
// ═══════════════════════════════════════════════════════════════

class _AdmissionTab extends StatelessWidget {
  final PatientAdmission admission;
  const _AdmissionTab({required this.admission});

  @override
  Widget build(BuildContext context) {
    final a = admission;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.hotel,
                      size: 20, color: Colors.blueGrey.shade700),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text('Admission Details',
                        style: TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 16)),
                  ),
                  StatusBadge.fromStatus(a.statusLabel),
                ],
              ),
              const Divider(height: 20),
              _infoRow('Doctor', a.doctorName ?? 'Unknown'),
              _infoRow('Bed', a.bedInfo ?? 'N/A'),
              if (a.priority != null) _infoRow('Priority', a.priority!),
              if (a.admissionReason != null &&
                  a.admissionReason!.isNotEmpty)
                _infoRow('Reason', a.admissionReason!),
              if (a.daysAdmitted != null)
                _infoRow('Days', '${a.daysAdmitted}'),
              if (a.discharged) ...[
                const Divider(height: 16),
                if (a.dischargeDate != null)
                  _infoRow('Discharged', a.dischargeDate!),
                if (a.dischargeReason != null &&
                    a.dischargeReason!.isNotEmpty)
                  _infoRow('Discharge Reason', a.dischargeReason!),
              ],
              const SizedBox(height: 8),
              Text(a.createdAt ?? '',
                  style:
                      TextStyle(fontSize: 10, color: Colors.grey.shade500)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(label,
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 13)),
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Diagnosis table helpers
// ═══════════════════════════════════════════════════════════════

Color _dxStatusColor(String s) => switch (s) {
      'Confirmed' => Colors.green.shade700,
      'Query' => Colors.orange.shade700,
      'Differential' => Colors.blue.shade700,
      _ => Colors.grey.shade600,
    };

Color _dxCourseColor(String c) => switch (c) {
      'Acute' => Colors.red.shade600,
      'Chronic' => Colors.purple.shade600,
      'Recurrent' => Colors.orange.shade600,
      _ => Colors.grey.shade600,
    };

class _THCell extends StatelessWidget {
  final String text;
  const _THCell(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 6),
      child: Text(text,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
    );
  }
}

class _TDCell extends StatelessWidget {
  final String text;
  final Color? color;
  const _TDCell(this.text, {this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 6),
      child: Text(text,
          style: TextStyle(
              fontSize: 11, color: color ?? Colors.grey.shade800)),
    );
  }
}
