import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 3: Clinical notes (ICPC-2 diagnosis search) + free-text notes.
class ClinicalNotesTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final void Function(String notes) onNotesChanged;
  final VoidCallback onDiagnosisSaved;

  const ClinicalNotesTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onNotesChanged,
    required this.onDiagnosisSaved,
  });

  @override
  State<ClinicalNotesTab> createState() => _ClinicalNotesTabState();
}

class _ClinicalNotesTabState extends State<ClinicalNotesTab>
    with AutomaticKeepAliveClientMixin {
  late TextEditingController _notesCtrl;
  late TextEditingController _diagnosisCtrl;
  late TextEditingController _comment1Ctrl;
  late TextEditingController _comment2Ctrl;

  final List<DiagnosisCode> _selectedReasons = [];
  bool _isSavingDiagnosis = false;
  bool _isSavingNotes = false;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _notesCtrl = TextEditingController(text: widget.encounter.notes ?? '');
    _diagnosisCtrl = TextEditingController(
        text: widget.encounter.doctorDiagnosis ?? '');
    _comment1Ctrl = TextEditingController(
        text: widget.encounter.reasonsComment1 ?? '');
    _comment2Ctrl = TextEditingController(
        text: widget.encounter.reasonsComment2 ?? '');

    _notesCtrl.addListener(() {
      widget.onNotesChanged(_notesCtrl.text);
    });
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _diagnosisCtrl.dispose();
    _comment1Ctrl.dispose();
    _comment2Ctrl.dispose();
    super.dispose();
  }

  Future<void> _saveDiagnosis() async {
    setState(() => _isSavingDiagnosis = true);

    final res = await widget.api.saveDiagnosis(
      widget.encounter.id,
      doctorDiagnosis: _diagnosisCtrl.text,
      reasonsForEncounter: _selectedReasons.map((r) => r.display).toList(),
      comment1: _comment1Ctrl.text,
      comment2: _comment2Ctrl.text,
    );

    if (!mounted) return;
    setState(() => _isSavingDiagnosis = false);

    if (res.success) {
      showSuccessSnackBar(context, 'Diagnosis saved');
      widget.onDiagnosisSaved();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  Future<void> _saveNotes() async {
    setState(() => _isSavingNotes = true);

    final res = await widget.api.updateNotes(
      widget.encounter.id,
      notes: _notesCtrl.text,
    );

    if (!mounted) return;
    setState(() => _isSavingNotes = false);

    if (res.success) {
      showSuccessSnackBar(context, 'Notes saved');
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ═══════ Reasons for Encounter (ICPC-2) ═══════
          const SectionHeader(
            title: 'Reasons for Encounter',
            icon: Icons.medical_information_outlined,
          ),
          if (!readOnly)
            ServiceSearchField(
              hintText: 'Search ICPC-2 codes...',
              onSearch: (term) => widget.api.searchDiagnosis(term),
              onSelect: (item) {
                final code = DiagnosisCode.fromJson(item);
                if (!_selectedReasons.any((r) => r.id == code.id)) {
                  setState(() => _selectedReasons.add(code));
                }
              },
            ),
          const SizedBox(height: 8),

          // Selected reasons
          if (_selectedReasons.isNotEmpty || widget.encounter.reasonsForEncounter.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Previously saved reasons
                    ...widget.encounter.reasonsForEncounter.map(
                      (r) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Row(
                          children: [
                            Icon(Icons.check, size: 14,
                                color: Colors.green.shade600),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(r,
                                  style: const TextStyle(fontSize: 13)),
                            ),
                          ],
                        ),
                      ),
                    ),
                    // Newly added reasons
                    ..._selectedReasons.map(
                      (r) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Row(
                          children: [
                            Icon(Icons.add_circle, size: 14,
                                color: Colors.blue.shade600),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(r.display,
                                  style: const TextStyle(fontSize: 13)),
                            ),
                            if (!readOnly)
                              IconButton(
                                icon: Icon(Icons.close, size: 16,
                                    color: Colors.grey.shade400),
                                padding: EdgeInsets.zero,
                                constraints: const BoxConstraints(),
                                onPressed: () {
                                  setState(() => _selectedReasons.remove(r));
                                },
                              ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 12),

          // Comment fields
          TextField(
            controller: _comment1Ctrl,
            readOnly: readOnly,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Presenting Complaints',
              hintText: 'Describe the patient\'s presenting complaints...',
              alignLabelWithHint: true,
            ),
            style: const TextStyle(fontSize: 13),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _comment2Ctrl,
            readOnly: readOnly,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'History of Presenting Illness',
              hintText: 'Elaborate on the history...',
              alignLabelWithHint: true,
            ),
            style: const TextStyle(fontSize: 13),
          ),
          const SizedBox(height: 16),

          // ═══════ Doctor's Diagnosis ═══════
          const SectionHeader(
            title: 'Doctor\'s Diagnosis',
            icon: Icons.edit_note_rounded,
          ),
          TextField(
            controller: _diagnosisCtrl,
            readOnly: readOnly,
            maxLines: 4,
            decoration: const InputDecoration(
              hintText: 'Enter your diagnosis / clinical impression...',
              alignLabelWithHint: true,
            ),
            style: const TextStyle(fontSize: 13),
          ),
          const SizedBox(height: 12),

          if (!readOnly)
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _isSavingDiagnosis ? null : _saveDiagnosis,
                icon: _isSavingDiagnosis
                    ? const SizedBox(
                        width: 18, height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.save, size: 18),
                label: Text(
                    _isSavingDiagnosis ? 'Saving...' : 'Save Diagnosis'),
              ),
            ),
          const SizedBox(height: 24),

          // ═══════ Clinical Notes ═══════
          const SectionHeader(
            title: 'Clinical Notes',
            icon: Icons.description_outlined,
          ),
          TextField(
            controller: _notesCtrl,
            readOnly: readOnly,
            maxLines: 12,
            minLines: 6,
            decoration: InputDecoration(
              hintText: 'Enter clinical notes...\n\n(Auto-saves every 30 seconds)',
              alignLabelWithHint: true,
              helperText: readOnly ? null : 'Auto-saves every 30s',
              helperStyle: TextStyle(
                  fontSize: 11, color: Colors.grey.shade400),
            ),
            style: const TextStyle(fontSize: 13, height: 1.5),
          ),
          const SizedBox(height: 10),

          if (!readOnly)
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _isSavingNotes ? null : _saveNotes,
                icon: _isSavingNotes
                    ? const SizedBox(
                        width: 18, height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.save_outlined, size: 18),
                label: Text(_isSavingNotes ? 'Saving...' : 'Save Notes Now'),
              ),
            ),

          const SizedBox(height: 80),
        ],
      ),
    );
  }
}
