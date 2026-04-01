import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/dictation/dictation_button.dart';
import '../../core/models/encounter_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';
import 'tabs/patient_info_tab.dart';
import 'tabs/vitals_tab.dart';
import 'tabs/clinical_notes_tab.dart';
import 'tabs/nurse_charts_tab.dart';
import 'tabs/inj_imm_tab.dart';
import 'tabs/lab_tab.dart';
import 'tabs/imaging_tab.dart';
import 'tabs/medications_tab.dart';
import 'tabs/procedures_tab.dart';
import 'tabs/history_tab.dart';
import 'tabs/summary_tab.dart';
import 'tabs/admission_history_tab.dart';
import 'tabs/referrals_tab.dart';
import 'tabs/medication_chart_tab.dart';

/// Full Consultation Screen — mirrors the web 13-tab workbench.
class ConsultationScreen extends StatefulWidget {
  final int encounterId;
  final int queueId;
  final String patientName;

  const ConsultationScreen({
    super.key,
    required this.encounterId,
    required this.queueId,
    required this.patientName,
  });

  @override
  State<ConsultationScreen> createState() => _ConsultationScreenState();
}

class _ConsultationScreenState extends State<ConsultationScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  late EncounterApiService _api;

  EncounterData? _encounter;
  bool _isLoading = true;
  String? _error;
  bool _isFinalizing = false;

  // Consultation timer
  Timer? _consultTimer;
  int _elapsedSeconds = 0;
  bool _timerPaused = false;

  // Notes autosave
  Timer? _autosaveTimer;
  String _lastSavedNotes = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 14, vsync: this);
    final baseUrl = LocalStorage.baseUrl ?? '';
    _api = EncounterApiService(baseUrl);
    _loadEncounter();
    _startTimer();
  }

  @override
  void dispose() {
    _consultTimer?.cancel();
    _autosaveTimer?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  void _startTimer() {
    _consultTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!_timerPaused && mounted) {
        setState(() => _elapsedSeconds++);
      }
    });
  }

  String _formatTimer() {
    final m = (_elapsedSeconds ~/ 60).toString().padLeft(2, '0');
    final s = (_elapsedSeconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Future<void> _loadEncounter({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _isLoading = true;
        _error = null;
      });
    }

    try {
      final res = await _api.getEncounterDetail(widget.encounterId);

      if (!mounted) return;
      if (res.success && res.data != null) {
        setState(() {
          _encounter = EncounterData.fromJson(res.data!);
          _lastSavedNotes = _encounter!.notes ?? '';
          _isLoading = false;
          _error = null;
        });
      } else if (!silent) {
        setState(() {
          _error = res.message.isNotEmpty ? res.message : 'Failed to load encounter';
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('[ConsultationScreen] Error loading encounter: $e');
      if (!mounted) return;
      if (!silent) {
        setState(() {
          _error = 'Failed to parse encounter data';
          _isLoading = false;
        });
      }
    }
  }

  /// Silent refresh — keeps existing tabs alive, no loading spinner.
  void _refreshEncounter() => _loadEncounter(silent: true);

  void _onNotesChanged(String notes) {
    _autosaveTimer?.cancel();
    _autosaveTimer = Timer(const Duration(seconds: 30), () {
      if (!mounted) return;
      if (notes != _lastSavedNotes) {
        _api.autosaveNotes(
          encounterId: widget.encounterId,
          notes: notes,
        );
        _lastSavedNotes = notes;
      }
    });
  }

  Future<void> _concludeEncounter() async {
    if (_encounter == null) return;

    final enc = _encounter!;
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _ConcludeSheet(encounter: enc),
    );

    if (result == null || !mounted) return;

    final action = result['action'] as String;
    setState(() => _isFinalizing = true);

    final res = await _api.finalizeEncounter(
      widget.encounterId,
      endConsultation: true,
      admit: action == 'admit',
      admitNote: result['admitNote'] as String?,
      discharge: action == 'discharge',
      dischargeReason: result['dischargeReason'] as String?,
      dischargeNote: result['dischargeNote'] as String?,
      followUpDate: result['followUpDate'] as String?,
      followUpNotes: result['followUpNotes'] as String?,
      closingNotes: result['closingNotes'] as String?,
      queueId: widget.queueId,
    );

    if (!mounted) return;
    setState(() => _isFinalizing = false);

    if (res.success) {
      final msg = switch (action) {
        'admit' => 'Patient admitted successfully',
        'discharge' => 'Patient discharged successfully',
        _ => 'Encounter completed',
      };
      showSuccessSnackBar(context, msg);
      Navigator.of(context).pop(true);
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to finalize');
    }
  }

  void _quickRefer() {
    // Jump to Referrals tab (index 12)
    _tabController.animateTo(12);
  }

  @override
  Widget build(BuildContext context) {
    return LoadingOverlay(
      isLoading: _isFinalizing,
      message: 'Finalizing encounter...',
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(widget.patientName,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
              if (_encounter?.clinic != null)
                Text(
                  _encounter!.clinic!.name,
                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w400),
                ),
            ],
          ),
          actions: [
            // Consultation timer
            if (!_isLoading && _encounter != null && !_encounter!.completed)
              GestureDetector(
                onTap: () => setState(() => _timerPaused = !_timerPaused),
                child: Container(
                  margin: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: _timerPaused
                        ? Colors.orange.withValues(alpha: 0.2)
                        : Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        _timerPaused ? Icons.pause_circle : Icons.timer_outlined,
                        size: 14,
                        color: Colors.white,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        _formatTimer(),
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          fontFeatures: [FontFeature.tabularFigures()],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            // Quick refer
            if (_encounter != null && !_encounter!.completed)
              IconButton(
                icon: const Icon(Icons.swap_horiz, size: 20),
                tooltip: 'Refer Patient',
                onPressed: _quickRefer,
              ),
            IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: 'Reload',
              onPressed: _loadEncounter,
            ),
          ],
          bottom: TabBar(
            controller: _tabController,
            isScrollable: true,
            indicatorColor: Colors.white,
            indicatorWeight: 3,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white60,
            labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            tabAlignment: TabAlignment.start,
            tabs: const [
              Tab(text: 'Patient'),
              Tab(text: 'Vitals'),
              Tab(text: 'Notes'),
              Tab(text: 'Nurse Charts'),
              Tab(text: 'Inj / Imm'),
              Tab(text: 'Labs'),
              Tab(text: 'Imaging'),
              Tab(text: 'Meds'),
              Tab(text: 'Med Chart'),
              Tab(text: 'Procedures'),
              Tab(text: 'History'),
              Tab(text: 'Summary'),
              Tab(text: 'Admissions'),
              Tab(text: 'Referrals'),
            ],
          ),
        ),
        body: _buildBody(),
        floatingActionButton: _encounter != null && !_encounter!.completed
            ? FloatingActionButton.extended(
                onPressed: _concludeEncounter,
                icon: const Icon(Icons.check_circle),
                label: const Text('Conclude'),
                backgroundColor: Colors.green.shade700,
                foregroundColor: Colors.white,
              )
            : null,
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error loading encounter',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _loadEncounter,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }
    if (_encounter == null) {
      return const EmptyState(
        icon: Icons.description_outlined,
        title: 'No encounter data',
      );
    }

    return TabBarView(
      controller: _tabController,
      children: [
        // Tab 0: Patient Info
        PatientInfoTab(encounter: _encounter!, api: _api),
        // Tab 1: Vitals
        VitalsTab(
          api: _api,
          encounter: _encounter!,
          onVitalsRecorded: _refreshEncounter,
        ),
        // Tab 2: Clinical Notes
        ClinicalNotesTab(
          api: _api,
          encounter: _encounter!,
          onNotesChanged: _onNotesChanged,
          onDiagnosisSaved: _refreshEncounter,
        ),
        // Tab 3: Nurse Charts
        NurseChartsTab(api: _api, encounter: _encounter!),
        // Tab 4: Inj / Imm
        InjImmTab(api: _api, encounter: _encounter!),
        // Tab 5: Labs
        LabTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _refreshEncounter,
        ),
        // Tab 6: Imaging
        ImagingTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _refreshEncounter,
        ),
        // Tab 7: Medications
        MedicationsTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _refreshEncounter,
        ),
        // Tab 8: Medication Chart
        MedicationChartTab(
          api: _api,
          encounter: _encounter!,
        ),
        // Tab 9: Procedures
        ProceduresTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _refreshEncounter,
        ),
        // Tab 9: History
        HistoryTab(
          api: _api,
          patientId: _encounter!.patientId,
        ),
        // Tab 10: Summary
        SummaryTab(
          api: _api,
          encounter: _encounter!,
        ),
        // Tab 11: Admission History
        AdmissionHistoryTab(
          api: _api,
          patientId: _encounter!.patientId,
        ),
        // Tab 12: Referrals
        ReferralsTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _refreshEncounter,
        ),
      ],
    );
  }
}

/// Enhanced conclude encounter bottom sheet with summary cards + follow-up.
class _ConcludeSheet extends StatefulWidget {
  final EncounterData encounter;
  const _ConcludeSheet({required this.encounter});

  @override
  State<_ConcludeSheet> createState() => _ConcludeSheetState();
}

class _ConcludeSheetState extends State<_ConcludeSheet> {
  final _closingNotesController = TextEditingController();
  final _followUpNotesController = TextEditingController();
  final _admitNoteController = TextEditingController();
  final _dischargeNoteController = TextEditingController();

  DateTime? _followUpDate;
  String? _dischargeReason;

  @override
  void dispose() {
    _closingNotesController.dispose();
    _followUpNotesController.dispose();
    _admitNoteController.dispose();
    _dischargeNoteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final enc = widget.encounter;
    final diagnosisCount = enc.doctorDiagnosis?.isNotEmpty == true ? 1 : 0;

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return SingleChildScrollView(
          controller: scrollController,
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle
              Center(
                child: Container(
                  width: 40, height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text('Conclude Encounter',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),

              // Summary cards
              Row(
                children: [
                  _SummaryCard('Diagnosis', '$diagnosisCount', Icons.medical_information, Colors.purple),
                  const SizedBox(width: 8),
                  _SummaryCard('Labs', '${enc.labs.length}', Icons.science, Colors.blue),
                  const SizedBox(width: 8),
                  _SummaryCard('Imaging', '${enc.imaging.length}', Icons.image, Colors.teal),
                  const SizedBox(width: 8),
                  _SummaryCard('Meds', '${enc.prescriptions.length}', Icons.medication, Colors.orange),
                ],
              ),
              const SizedBox(height: 20),

              // Follow-up section
              const Text('Follow-up Scheduling',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              InkWell(
                onTap: () async {
                  final date = await showDatePicker(
                    context: context,
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                    initialDate: _followUpDate ?? DateTime.now().add(const Duration(days: 7)),
                  );
                  if (date != null) setState(() => _followUpDate = date);
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade300),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.calendar_today, size: 16, color: Colors.grey.shade600),
                      const SizedBox(width: 8),
                      Text(
                        _followUpDate != null
                            ? '${_followUpDate!.day}/${_followUpDate!.month}/${_followUpDate!.year}'
                            : 'Select follow-up date (optional)',
                        style: TextStyle(
                          color: _followUpDate != null ? Colors.black87 : Colors.grey.shade500,
                        ),
                      ),
                      const Spacer(),
                      if (_followUpDate != null)
                        GestureDetector(
                          onTap: () => setState(() => _followUpDate = null),
                          child: Icon(Icons.clear, size: 16, color: Colors.grey.shade500),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Expanded(
                    child: Text('Follow-up Notes',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                  ),
                  DictationButton(
                      controller: _followUpNotesController,
                      fieldLabel: 'Follow-up Notes'),
                ],
              ),
              const SizedBox(height: 4),
              TextField(
                controller: _followUpNotesController,
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: 'Follow-up notes (optional)',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  contentPadding: const EdgeInsets.all(12),
                ),
              ),
              const SizedBox(height: 16),

              // Closing notes
              Row(
                children: [
                  const Expanded(
                    child: Text('Closing Notes',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                  ),
                  DictationButton(
                      controller: _closingNotesController,
                      fieldLabel: 'Closing Notes'),
                ],
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _closingNotesController,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Final notes for this encounter...',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  contentPadding: const EdgeInsets.all(12),
                ),
              ),
              const SizedBox(height: 24),

              // Action buttons
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _submit('end'),
                  icon: const Icon(Icons.check_circle, size: 18),
                  label: const Text('End Consultation'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green.shade700,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _showAdmitFields,
                      icon: const Icon(Icons.local_hospital, size: 18),
                      label: const Text('Admit'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _showDischargeFields,
                      icon: Icon(Icons.exit_to_app, size: 18, color: Colors.red.shade700),
                      label: Text('Discharge', style: TextStyle(color: Colors.red.shade700)),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        side: BorderSide(color: Colors.red.shade300),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
            ],
          ),
        );
      },
    );
  }

  void _submit(String action, {String? admitNote, String? dischargeReason, String? dischargeNote}) {
    Navigator.of(context).pop({
      'action': action,
      'closingNotes': _closingNotesController.text.isNotEmpty ? _closingNotesController.text : null,
      'followUpDate': _followUpDate?.toIso8601String().split('T').first,
      'followUpNotes': _followUpNotesController.text.isNotEmpty ? _followUpNotesController.text : null,
      'admitNote': admitNote,
      'dischargeReason': dischargeReason,
      'dischargeNote': dischargeNote,
    });
  }

  void _showAdmitFields() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Admission Note'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text('Admission Note',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                ),
                DictationButton(
                    controller: _admitNoteController,
                    fieldLabel: 'Admission Note'),
              ],
            ),
            const SizedBox(height: 4),
            TextField(
              controller: _admitNoteController,
              maxLines: 4,
              decoration: const InputDecoration(hintText: 'Enter admission note...'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              _submit('admit', admitNote: _admitNoteController.text);
            },
            child: const Text('Admit'),
          ),
        ],
      ),
    );
  }

  void _showDischargeFields() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Discharge Patient'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _dischargeReason,
              decoration: const InputDecoration(labelText: 'Reason'),
              items: const [
                DropdownMenuItem(value: 'recovered', child: Text('Recovered')),
                DropdownMenuItem(value: 'transferred', child: Text('Transferred')),
                DropdownMenuItem(value: 'against_advice', child: Text('Against Medical Advice')),
                DropdownMenuItem(value: 'observation_complete', child: Text('Observation Complete')),
                DropdownMenuItem(value: 'other', child: Text('Other')),
              ],
              onChanged: (v) => _dischargeReason = v,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Expanded(
                  child: Text('Discharge Notes',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                ),
                DictationButton(
                    controller: _dischargeNoteController,
                    fieldLabel: 'Discharge Notes'),
              ],
            ),
            const SizedBox(height: 4),
            TextField(
              controller: _dischargeNoteController,
              maxLines: 3,
              decoration: const InputDecoration(hintText: 'Discharge notes...'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              _submit('discharge',
                  dischargeReason: _dischargeReason,
                  dischargeNote: _dischargeNoteController.text);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red.shade700),
            child: const Text('Discharge'),
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String label;
  final String count;
  final IconData icon;
  final Color color;

  const _SummaryCard(this.label, this.count, this.icon, this.color);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(height: 4),
            Text(count,
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: color)),
            Text(label,
                style: TextStyle(fontSize: 10, color: color.withValues(alpha: 0.8)),
                textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
