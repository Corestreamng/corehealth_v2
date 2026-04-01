import 'dart:convert';
import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/dictation/dictation_button.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 3: Clinical notes — History sub-tab + Entry sub-tab.
/// Entry has: Diagnosis (ICPC-2 + per-diagnosis Status/Course), Template insertion,
/// Presenting Complaints, History of Presenting Illness, Doctor's free-text notes.
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
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;

  // ── Entry tab controllers ──
  late TextEditingController _notesCtrl;
  late TextEditingController _comment1Ctrl;
  late TextEditingController _comment2Ctrl;

  // ── Diagnosis state ──
  final List<DiagnosisEntry> _selectedReasons = [];
  bool _diagnosisApplicable = true;
  bool _isSavingDiagnosis = false;
  bool _isSavingNotes = false;

  // ── Templates ──
  List<Map<String, dynamic>> _templateGroups = [];

  // ── History state ──
  List<Map<String, dynamic>> _historyNotes = [];
  bool _loadingHistory = false;
  int _historyPage = 1;
  bool _hasMoreHistory = true;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _subTabCtrl = TabController(length: 2, vsync: this);

    _notesCtrl = TextEditingController(text: widget.encounter.notes ?? '');
    _comment1Ctrl = TextEditingController(
        text: widget.encounter.reasonsComment1 ?? '');
    _comment2Ctrl = TextEditingController(
        text: widget.encounter.reasonsComment2 ?? '');

    _notesCtrl.addListener(() {
      widget.onNotesChanged(_notesCtrl.text);
    });

    // Load existing diagnosis entries
    _selectedReasons.addAll(widget.encounter.diagnosisEntries);

    // Determine diagnosis_applicable from encounter data
    final da = widget.encounter.diagnosisApplicable;
    _diagnosisApplicable = da == null || da == '1' || da == 'true';

    _loadHistory();
    _loadTemplates();
  }

  @override
  void dispose() {
    _subTabCtrl.dispose();
    _notesCtrl.dispose();
    _comment1Ctrl.dispose();
    _comment2Ctrl.dispose();
    super.dispose();
  }

  Future<void> _loadHistory() async {
    setState(() => _loadingHistory = true);
    try {
      final res = await widget.api.getEncounterHistory(
        widget.encounter.patientId, page: _historyPage,
      );
      if (!mounted) return;
      if (res.success && res.data != null) {
        final items = res.data!['data'] ?? res.data!['items'];
        if (items is List) {
          setState(() {
            _historyNotes = List<Map<String, dynamic>>.from(
              items.whereType<Map>().map((m) => Map<String, dynamic>.from(m)),
            );
            _hasMoreHistory = (_historyPage < (res.data!['last_page'] ?? 1));
          });
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _loadingHistory = false);
  }

  Future<void> _loadTemplates() async {
    try {
      final clinicId = widget.encounter.clinic?.id;
      final res = await widget.api.getClinicNoteTemplates(clinicId: clinicId);
      if (!mounted) return;
      if (res.success) {
        final body = res.rawBody ?? res.data;
        if (body is Map) {
          final groups = body['groups'];
          if (groups is List) {
            setState(() {
              _templateGroups = List<Map<String, dynamic>>.from(
                groups.whereType<Map>().map((m) => Map<String, dynamic>.from(m)),
              );
            });
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _saveDiagnosis() async {
    setState(() => _isSavingDiagnosis = true);

    final perDiag = _selectedReasons
        .map((e) => e.toJson())
        .toList();

    final res = await widget.api.saveDiagnosis(
      widget.encounter.id,
      doctorDiagnosis: _notesCtrl.text,
      diagnosisApplicable: _diagnosisApplicable,
      reasonsForEncounter: _selectedReasons.map((r) => r.display).toList(),
      perDiagnosisComments: jsonEncode(perDiag),
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

  void _insertTemplate(String content) {
    // Strip HTML tags for mobile plain-text notes
    final stripped = content
        .replaceAll(RegExp(r'<br\s*/?>'), '\n')
        .replaceAll(RegExp(r'<p[^>]*>'), '\n')
        .replaceAll(RegExp(r'</p>'), '')
        .replaceAll(RegExp(r'<[^>]+>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .trim();

    // Substitute placeholders
    final substituted = stripped
        .replaceAll('{patient_name}', widget.encounter.patient.name)
        .replaceAll('{date}', DateTime.now().toString().split(' ')[0])
        .replaceAll('{doctor_name}', 'Doctor');

    final sel = _notesCtrl.selection;
    final text = _notesCtrl.text;
    final base = sel.baseOffset.clamp(0, text.length);
    final extent = sel.extentOffset.clamp(0, text.length);
    final before = text.substring(0, base);
    final after = text.substring(extent);
    final sep = before.isEmpty ? '' : '\n';
    _notesCtrl.text = '$before$sep$substituted\n$after';
    _notesCtrl.selection = TextSelection.collapsed(
        offset: before.length + sep.length + substituted.length);
  }

  // ══════════════════════════════════════════════
  //  Build
  // ══════════════════════════════════════════════

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return Column(
      children: [
        Container(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          child: TabBar(
            controller: _subTabCtrl,
            labelColor: Theme.of(context).colorScheme.primary,
            unselectedLabelColor: Colors.grey,
            indicatorSize: TabBarIndicatorSize.tab,
            tabs: const [
              Tab(text: 'History'),
              Tab(text: 'Entry'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _subTabCtrl,
            children: [
              _buildHistory(),
              _buildEntry(),
            ],
          ),
        ),
      ],
    );
  }

  // ──────────────── History Sub-Tab ────────────────

  Widget _buildHistory() {
    if (_loadingHistory) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_historyNotes.isEmpty) {
      return const EmptyState(
        icon: Icons.history,
        title: 'No Previous Notes',
        subtitle: 'Clinical notes from previous encounters will appear here.',
      );
    }

    return RefreshIndicator(
      onRefresh: () async {
        _historyPage = 1;
        await _loadHistory();
      },
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _historyNotes.length + (_hasMoreHistory ? 1 : 0),
        itemBuilder: (ctx, i) {
          if (i == _historyNotes.length) {
            return Center(
              child: TextButton(
                onPressed: () {
                  _historyPage++;
                  _loadHistory();
                },
                child: const Text('Load More'),
              ),
            );
          }
          final note = _historyNotes[i];
          return _HistoryNoteCard(note: note);
        },
      ),
    );
  }

  // ──────────────── Entry Sub-Tab ────────────────

  Widget _buildEntry() {
    final readOnly = widget.encounter.completed;
    final showDiagnosisSection = widget.encounter.requireDiagnosis;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ═══════ Diagnosis applicable toggle ═══════
          if (showDiagnosisSection) ...[
            Row(
              children: [
                const Expanded(
                  child: Text('Diagnosis Applicable',
                      style: TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                ),
                Switch(
                  value: _diagnosisApplicable,
                  onChanged: readOnly
                      ? null
                      : (val) => setState(() => _diagnosisApplicable = val),
                ),
              ],
            ),
            if (!_diagnosisApplicable)
              Container(
                padding: const EdgeInsets.all(10),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.orange.shade200),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline, size: 16, color: Colors.orange),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Diagnosis is marked as not applicable for this encounter. '
                        'Diagnosis fields will be cleared on save.',
                        style: TextStyle(fontSize: 12, color: Colors.orange),
                      ),
                    ),
                  ],
                ),
              ),

            // ═══════ Reasons for Encounter (ICPC-2) ═══════
            if (_diagnosisApplicable) ...[
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
                    if (!_selectedReasons.any((r) =>
                        r.code == code.code && r.name == code.name)) {
                      setState(() {
                        _selectedReasons.add(DiagnosisEntry(
                          code: code.code,
                          name: code.name,
                          display: code.display,
                        ));
                      });
                    }
                  },
                ),
              const SizedBox(height: 8),

              // Per-diagnosis list with Status & Course
              if (_selectedReasons.isNotEmpty)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Selected Diagnoses',
                            style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: Colors.grey)),
                        const SizedBox(height: 6),
                        ..._selectedReasons.asMap().entries.map((entry) {
                          final idx = entry.key;
                          final r = entry.value;
                          return _DiagnosisEntryRow(
                            entry: r,
                            readOnly: readOnly,
                            onRemove: () {
                              setState(() => _selectedReasons.removeAt(idx));
                            },
                            onStatusChanged: (v) {
                              setState(() => r.status = v);
                            },
                            onCourseChanged: (v) {
                              setState(() => r.course = v);
                            },
                          );
                        }),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 12),

              // Comment fields
              Row(
                children: [
                  const Expanded(
                    child: Text('Presenting Complaints',
                        style: TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w500)),
                  ),
                  if (!readOnly)
                    DictationButton(
                        controller: _comment1Ctrl,
                        fieldLabel: 'Presenting Complaints'),
                ],
              ),
              const SizedBox(height: 4),
              TextField(
                controller: _comment1Ctrl,
                readOnly: readOnly,
                maxLines: 2,
                decoration: const InputDecoration(
                  hintText: 'Describe the patient\'s presenting complaints...',
                  alignLabelWithHint: true,
                ),
                style: const TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  const Expanded(
                    child: Text('History of Presenting Illness',
                        style: TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w500)),
                  ),
                  if (!readOnly)
                    DictationButton(
                        controller: _comment2Ctrl,
                        fieldLabel: 'History of Presenting Illness'),
                ],
              ),
              const SizedBox(height: 4),
              TextField(
                controller: _comment2Ctrl,
                readOnly: readOnly,
                maxLines: 2,
                decoration: const InputDecoration(
                  hintText: 'Elaborate on the history...',
                  alignLabelWithHint: true,
                ),
                style: const TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 16),
            ],

            // Save diagnosis button
            if (!readOnly)
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isSavingDiagnosis ? null : _saveDiagnosis,
                  icon: _isSavingDiagnosis
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.save, size: 18),
                  label: Text(
                      _isSavingDiagnosis ? 'Saving...' : 'Save Diagnosis'),
                ),
              ),
            const SizedBox(height: 24),
          ],

          // ═══════ Clinical Notes with Template Insertion ═══════
          SectionHeader(
            title: 'Clinical Notes',
            icon: Icons.description_outlined,
            trailing: readOnly
                ? null
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_templateGroups.isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.note_add_outlined, size: 20),
                          tooltip: 'Insert Template',
                          onPressed: _showTemplateDialog,
                          visualDensity: VisualDensity.compact,
                        ),
                      DictationButton(
                          controller: _notesCtrl,
                          fieldLabel: 'Clinical Notes'),
                    ],
                  ),
          ),
          if (readOnly && _notesCtrl.text.isNotEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: HtmlContent(
                data: _notesCtrl.text,
                style: const TextStyle(fontSize: 13, height: 1.5),
              ),
            )
          else
            TextField(
              controller: _notesCtrl,
              readOnly: readOnly,
              maxLines: 12,
              minLines: 6,
              decoration: InputDecoration(
                hintText:
                    'Enter clinical notes...\n\n(Auto-saves every 30 seconds)',
                alignLabelWithHint: true,
                helperText: readOnly ? null : 'Auto-saves every 30s',
                helperStyle:
                    TextStyle(fontSize: 11, color: Colors.grey.shade400),
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
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.save_outlined, size: 18),
                label:
                    Text(_isSavingNotes ? 'Saving...' : 'Save Notes Now'),
              ),
            ),

          const SizedBox(height: 80),
        ],
      ),
    );
  }

  // ──────────────── Template Dialog ────────────────

  void _showTemplateDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        maxChildSize: 0.85,
        builder: (_, scrollCtrl) {
          return Column(
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.note_add, size: 20),
                    const SizedBox(width: 8),
                    const Expanded(
                      child: Text('Insert Template',
                          style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w600)),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: () => Navigator.pop(ctx),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: _templateGroups.isEmpty
                    ? const Center(
                        child: Text('No templates available',
                            style: TextStyle(color: Colors.grey)),
                      )
                    : ListView(
                        controller: scrollCtrl,
                        padding: const EdgeInsets.all(12),
                        children: _templateGroups.map((group) {
                          final category =
                              group['category']?.toString() ?? 'General';
                          final templates =
                              group['templates'] as List? ?? [];
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Padding(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 8),
                                child: Text(
                                  category,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: Theme.of(ctx)
                                        .colorScheme
                                        .primary,
                                  ),
                                ),
                              ),
                              ...templates.map((t) {
                                final tpl = t is Map
                                    ? Map<String, dynamic>.from(t)
                                    : <String, dynamic>{};
                                return ListTile(
                                  dense: true,
                                  title: Text(
                                      tpl['name']?.toString() ?? 'Template',
                                      style: const TextStyle(fontSize: 13)),
                                  subtitle: tpl['description'] != null
                                      ? Text(tpl['description'].toString(),
                                          style: const TextStyle(
                                              fontSize: 11))
                                      : null,
                                  trailing: tpl['is_global'] == true
                                      ? const Chip(
                                          label: Text('Global',
                                              style: TextStyle(
                                                  fontSize: 10)),
                                          padding: EdgeInsets.zero,
                                          visualDensity:
                                              VisualDensity.compact,
                                        )
                                      : null,
                                  onTap: () {
                                    Navigator.pop(ctx);
                                    final content =
                                        tpl['content']?.toString() ?? '';
                                    _insertTemplate(content);
                                  },
                                );
                              }),
                              const Divider(),
                            ],
                          );
                        }).toList(),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════
//  Per-Diagnosis Row with Status & Course
// ══════════════════════════════════════════════════

class _DiagnosisEntryRow extends StatelessWidget {
  final DiagnosisEntry entry;
  final bool readOnly;
  final VoidCallback onRemove;
  final ValueChanged<String> onStatusChanged;
  final ValueChanged<String> onCourseChanged;

  const _DiagnosisEntryRow({
    required this.entry,
    required this.readOnly,
    required this.onRemove,
    required this.onStatusChanged,
    required this.onCourseChanged,
  });

  static const _statuses = ['N/A', 'Query', 'Differential', 'Confirmed'];
  static const _courses = ['N/A', 'Acute', 'Chronic', 'Recurrent'];

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      color: Colors.grey.shade50,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(entry.code,
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: Colors.blue.shade700)),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(entry.name,
                      style: const TextStyle(fontSize: 13)),
                ),
                if (!readOnly)
                  InkWell(
                    onTap: onRemove,
                    borderRadius: BorderRadius.circular(12),
                    child: const Padding(
                      padding: EdgeInsets.all(4),
                      child: Icon(Icons.close, size: 16, color: Colors.red),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _buildDropdown(
                    label: 'Status',
                    value: entry.status,
                    items: _statuses,
                    onChanged: readOnly ? null : onStatusChanged,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _buildDropdown(
                    label: 'Course',
                    value: entry.course,
                    items: _courses,
                    onChanged: readOnly ? null : onCourseChanged,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDropdown({
    required String label,
    required String value,
    required List<String> items,
    ValueChanged<String>? onChanged,
  }) {
    final safeValue = items.contains(value) ? value : items.first;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(fontSize: 10, color: Colors.grey)),
        const SizedBox(height: 2),
        DropdownButtonFormField<String>(
          initialValue: safeValue,
          isDense: true,
          isExpanded: true,
          decoration: const InputDecoration(
            contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            isDense: true,
          ),
          style: const TextStyle(fontSize: 12, color: Colors.black87),
          items: items
              .map((s) => DropdownMenuItem(value: s, child: Text(s)))
              .toList(),
          onChanged: onChanged != null
              ? (v) {
                  if (v != null) onChanged(v);
                }
              : null,
        ),
      ],
    );
  }
}

// ══════════════════════════════════════════════════
//  History Note Card
// ══════════════════════════════════════════════════

class _HistoryNoteCard extends StatelessWidget {
  final Map<String, dynamic> note;

  const _HistoryNoteCard({required this.note});

  List<DiagnosisEntry> get _diagnosisEntries {
    final raw = note['reasons_for_encounter'];
    if (raw == null) return [];
    List list;
    if (raw is List) {
      list = raw;
    } else if (raw is String) {
      try {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          list = decoded;
        } else {
          return [];
        }
      } catch (_) {
        return [];
      }
    } else {
      return [];
    }
    return list
        .whereType<Map>()
        .map((m) => DiagnosisEntry.fromJson(Map<String, dynamic>.from(m)))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final doctorName = note['doctor_name']?.toString() ?? 'Doctor';
    final createdAt = note['created_at']?.toString() ?? '';
    final notes = note['notes']?.toString() ?? '';
    final comment1 = note['comment_1']?.toString() ?? '';
    final comment2 = note['comment_2']?.toString() ?? '';
    final entries = _diagnosisEntries;

    String formattedDate = createdAt;
    try {
      final dt = DateTime.parse(createdAt);
      formattedDate =
          '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {}

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.person_outline, size: 14, color: Colors.grey[600]),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(doctorName,
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w600)),
                ),
                Text(formattedDate,
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ],
            ),
            if (entries.isNotEmpty) ...[
              const SizedBox(height: 8),
              Table(
                columnWidths: const {
                  0: FlexColumnWidth(1),
                  1: FlexColumnWidth(2.5),
                  2: FlexColumnWidth(1.2),
                  3: FlexColumnWidth(1.2),
                },
                border: TableBorder.all(color: Colors.grey.shade300, width: 0.5),
                children: [
                  TableRow(
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.08),
                    ),
                    children: const [
                      _THCell('Code'),
                      _THCell('Diagnosis'),
                      _THCell('Status'),
                      _THCell('Course'),
                    ],
                  ),
                  ...entries.map((e) => TableRow(children: [
                    _TDCell(e.code),
                    _TDCell(e.name),
                    _TDCell(e.status, color: _dxStatusColor(e.status)),
                    _TDCell(e.course, color: _dxCourseColor(e.course)),
                  ])),
                ],
              ),
            ],
            if (comment1.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Status: $comment1',
                  style: TextStyle(fontSize: 12, color: Colors.grey[700])),
            ],
            if (comment2.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text('Course: $comment2',
                  style: TextStyle(fontSize: 12, color: Colors.grey[700])),
            ],
            if (notes.isNotEmpty) ...[
              const SizedBox(height: 6),
              HtmlContent(
                data: notes,
                style: const TextStyle(fontSize: 12, height: 1.4),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

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
          style: TextStyle(fontSize: 11, color: color ?? Colors.grey.shade800)),
    );
  }
}
