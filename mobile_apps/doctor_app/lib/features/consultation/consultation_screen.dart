import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/models/encounter_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';
import 'tabs/patient_info_tab.dart';
import 'tabs/vitals_tab.dart';
import 'tabs/clinical_notes_tab.dart';
import 'tabs/lab_tab.dart';
import 'tabs/imaging_tab.dart';
import 'tabs/medications_tab.dart';
import 'tabs/procedures_tab.dart';
import 'tabs/history_tab.dart';
import 'tabs/summary_tab.dart';

/// Full Consultation Screen — mirrors the web 10-tab workbench.
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

  // Notes autosave
  Timer? _autosaveTimer;
  String _lastSavedNotes = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 9, vsync: this);
    final baseUrl = LocalStorage.baseUrl ?? '';
    _api = EncounterApiService(baseUrl);
    _loadEncounter();
  }

  @override
  void dispose() {
    _autosaveTimer?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadEncounter() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    final res = await _api.getEncounterDetail(widget.encounterId);

    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() {
        _encounter = EncounterData.fromJson(res.data!);
        _lastSavedNotes = _encounter!.notes ?? '';
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message.isNotEmpty ? res.message : 'Failed to load encounter';
        _isLoading = false;
      });
    }
  }

  void _onNotesChanged(String notes) {
    _autosaveTimer?.cancel();
    _autosaveTimer = Timer(const Duration(seconds: 30), () {
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

    // Show options dialog
    final action = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Conclude Encounter'),
        content: const Text('Choose how to conclude this encounter:'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          OutlinedButton.icon(
            onPressed: () => Navigator.pop(ctx, 'end'),
            icon: const Icon(Icons.check_circle_outline, size: 18),
            label: const Text('End Consultation'),
          ),
          ElevatedButton.icon(
            onPressed: () => Navigator.pop(ctx, 'admit'),
            icon: const Icon(Icons.local_hospital, size: 18),
            label: const Text('Admit Patient'),
          ),
        ],
      ),
    );

    if (action == null || !mounted) return;

    String? admitNote;
    if (action == 'admit') {
      admitNote = await _showAdmitNoteDialog();
      if (admitNote == null || !mounted) return;
    }

    setState(() => _isFinalizing = true);

    final res = await _api.finalizeEncounter(
      widget.encounterId,
      endConsultation: true,
      admit: action == 'admit',
      admitNote: admitNote,
      queueId: widget.queueId,
    );

    if (!mounted) return;
    setState(() => _isFinalizing = false);

    if (res.success) {
      showSuccessSnackBar(context,
          action == 'admit' ? 'Patient admitted successfully' : 'Encounter completed');
      Navigator.of(context).pop(true);
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to finalize');
    }
  }

  Future<String?> _showAdmitNoteDialog() async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Admission Note'),
        content: TextField(
          controller: controller,
          maxLines: 4,
          decoration: const InputDecoration(
            hintText: 'Enter admission note...',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, controller.text),
            child: const Text('Admit'),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
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
              Tab(text: 'Labs'),
              Tab(text: 'Imaging'),
              Tab(text: 'Meds'),
              Tab(text: 'Procedures'),
              Tab(text: 'History'),
              Tab(text: 'Summary'),
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
        PatientInfoTab(encounter: _encounter!),
        VitalsTab(
          api: _api,
          encounter: _encounter!,
          onVitalsRecorded: _loadEncounter,
        ),
        ClinicalNotesTab(
          api: _api,
          encounter: _encounter!,
          onNotesChanged: _onNotesChanged,
          onDiagnosisSaved: _loadEncounter,
        ),
        LabTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _loadEncounter,
        ),
        ImagingTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _loadEncounter,
        ),
        MedicationsTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _loadEncounter,
        ),
        ProceduresTab(
          api: _api,
          encounter: _encounter!,
          onSaved: _loadEncounter,
        ),
        HistoryTab(
          api: _api,
          patientId: _encounter!.patientId,
        ),
        SummaryTab(
          api: _api,
          encounter: _encounter!,
        ),
      ],
    );
  }
}
