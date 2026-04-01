import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/storage/local_storage.dart';
import '../../../core/dictation/dictation_button.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 4: Lab service requests — search, add, delete, view results.
class LabTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onSaved;

  const LabTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onSaved,
  });

  @override
  State<LabTab> createState() => _LabTabState();
}

class _LabTabState extends State<LabTab>
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;
  final List<_PendingItem> _pending = [];
  bool _isSaving = false;

  // ── History state ──
  List<Map<String, dynamic>> _history = [];
  bool _loadingHistory = false;
  int _historyPage = 1;
  int _historyLastPage = 1;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _subTabCtrl = TabController(length: 2, vsync: this, initialIndex: 1);
    _loadHistory();
  }

  @override
  void dispose() {
    _subTabCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadHistory({bool append = false}) async {
    if (!append) setState(() => _loadingHistory = true);
    final res = await widget.api.getLabHistory(
      widget.encounter.patientId,
      page: _historyPage,
    );
    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = res.data!['data'];
      final meta = res.data!['meta'] ?? res.data!;
      final items = list is List
          ? List<Map<String, dynamic>>.from(list)
          : <Map<String, dynamic>>[];
      setState(() {
        if (append) {
          _history.addAll(items);
        } else {
          _history = items;
        }
        _historyLastPage = meta['last_page'] ?? 1;
        _loadingHistory = false;
      });
    } else {
      setState(() => _loadingHistory = false);
    }
  }

  void _addPending(Map<String, dynamic> item) {
    final id = item['id'] as int;
    if (_pending.any((p) => p.serviceId == id)) return;
    if (widget.encounter.labs.any((l) => l.serviceId == id)) {
      showErrorSnackBar(context, 'This test is already requested');
      return;
    }
    setState(() {
      _pending.add(_PendingItem(
        serviceId: id,
        name: item['service_name']?.toString() ??
            item['display']?.toString() ??
            'Unknown',
        note: '',
      ));
    });
  }

  Future<void> _saveLabs() async {
    if (_pending.isEmpty) {
      showErrorSnackBar(context, 'Add at least one lab test');
      return;
    }
    setState(() => _isSaving = true);

    final res = await widget.api.saveLabs(
      widget.encounter.id,
      serviceIds: _pending.map((p) => p.serviceId).toList(),
      notes: _pending.map((p) => p.note).toList(),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Labs saved');
      setState(() => _pending.clear());
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  Future<void> _deleteLab(LabRequest lab) async {
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Delete Lab Request',
      message: 'Remove "${lab.serviceName}" from this encounter?',
    );
    if (!confirmed || !mounted) return;

    final res = await widget.api.deleteLab(widget.encounter.id, lab.id);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Lab request deleted');
      widget.onSaved();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Delete failed');
    }
  }

  Future<void> _editNote(LabRequest lab) async {
    final ctrl = TextEditingController(text: lab.note ?? '');
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text('Edit Note — ${lab.serviceName}', style: const TextStyle(fontSize: 15)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text('Clinical Note',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                ),
                DictationButton(
                    controller: ctrl,
                    fieldLabel: 'Lab Note'),
              ],
            ),
            const SizedBox(height: 4),
            TextField(
              controller: ctrl,
              maxLines: 3,
              autofocus: true,
              decoration: const InputDecoration(hintText: 'Clinical note...'),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel')),
          ElevatedButton(
              onPressed: () => Navigator.pop(ctx, ctrl.text),
              child: const Text('Save')),
        ],
      ),
    );
    ctrl.dispose();
    if (result == null || !mounted) return;

    final res = await widget.api.updateLabNote(
      widget.encounter.id, lab.id,
      note: result,
    );
    if (!mounted) return;
    if (res.success) {
      showSuccessSnackBar(context, 'Note updated');
      widget.onSaved();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return Column(
      children: [
        TabBar(
          controller: _subTabCtrl,
          labelColor: Theme.of(context).colorScheme.primary,
          unselectedLabelColor: Colors.grey,
          indicatorSize: TabBarIndicatorSize.tab,
          tabs: const [Tab(text: 'History'), Tab(text: 'Entry')],
        ),
        Expanded(
          child: TabBarView(
            controller: _subTabCtrl,
            children: [
              _buildHistoryTab(),
              _buildEntryTab(),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildHistoryTab() {
    if (_loadingHistory) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_history.isEmpty) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Text('No lab history for this patient.',
              style: TextStyle(color: Colors.grey)),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: () async {
        _historyPage = 1;
        await _loadHistory();
      },
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _history.length + (_historyPage < _historyLastPage ? 1 : 0),
        itemBuilder: (context, i) {
          if (i == _history.length) {
            if (!_loadingHistory && _historyPage < _historyLastPage) {
              _historyPage++;
              _loadHistory(append: true);
            }
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            );
          }
          final h = _history[i];
          return _HistoryLabCard(item: h);
        },
      ),
    );
  }

  Widget _buildEntryTab() {
    final labs = widget.encounter.labs;
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Search & add ──
          if (!readOnly) ...[
            const SectionHeader(title: 'Add Lab Tests', icon: Icons.add_circle_outline),
            ServiceSearchField(
              hintText: 'Search lab services...',
              onSearch: (term) => widget.api.searchServices(term),
              onSelect: _addPending,
            ),
            const SizedBox(height: 8),
          ],

          // ── Pending items ──
          if (_pending.isNotEmpty) ...[
            Card(
              color: Colors.blue.shade50,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Pending (${_pending.length})',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Colors.blue.shade800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._pending.asMap().entries.map((entry) {
                      final i = entry.key;
                      final p = entry.value;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(p.name,
                                      style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w500)),
                                  const SizedBox(height: 4),
                                  TextField(
                                    onChanged: (v) => _pending[i].note = v,
                                    decoration: const InputDecoration(
                                      hintText: 'Clinical note (optional)',
                                      isDense: true,
                                      contentPadding: EdgeInsets.symmetric(
                                          horizontal: 10, vertical: 8),
                                    ),
                                    style: const TextStyle(fontSize: 12),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              icon: Icon(Icons.remove_circle,
                                  color: Colors.red.shade400, size: 20),
                              onPressed: () =>
                                  setState(() => _pending.removeAt(i)),
                            ),
                          ],
                        ),
                      );
                    }),
                    const SizedBox(height: 4),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _isSaving ? null : _saveLabs,
                        icon: _isSaving
                            ? const SizedBox(
                                width: 18, height: 18,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.save, size: 18),
                        label: Text(_isSaving
                            ? 'Saving...'
                            : 'Save ${_pending.length} Lab Request${_pending.length == 1 ? '' : 's'}'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // ── Existing lab requests ──
          SectionHeader(
            title: 'Lab Requests',
            icon: Icons.science_outlined,
            trailing: Text('${labs.length}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          ),
          if (labs.isEmpty)
            const EmptyState(
              icon: Icons.science_outlined,
              title: 'No lab requests',
              subtitle: 'Search and add lab tests above',
            )
          else
            ...labs.map((lab) => _LabCard(
                  lab: lab,
                  readOnly: readOnly,
                  onDelete: () => _deleteLab(lab),
                  onEditNote: () => _editNote(lab),
                )),

          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _LabCard extends StatelessWidget {
  final LabRequest lab;
  final bool readOnly;
  final VoidCallback onDelete;
  final VoidCallback onEditNote;

  const _LabCard({
    required this.lab,
    required this.readOnly,
    required this.onDelete,
    required this.onEditNote,
  });

  Color get _statusColor {
    switch (lab.statusCode) {
      case 1: return const Color(0xFFF57F17); // amber — requested
      case 2: return const Color(0xFF1565C0); // blue — billed
      case 3: return const Color(0xFF6A1B9A); // purple — sample taken
      case 4: return const Color(0xFF2E7D32); // green — result ready
      case 5: return const Color(0xFF0277BD); // light blue — pending approval
      case 6: return const Color(0xFFC62828); // red — rejected
      default: return Colors.grey.shade600;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Header ──
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        lab.serviceName,
                        style: const TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w600),
                      ),
                      if (lab.labNumber != null) ...[
                        const SizedBox(height: 2),
                        Text('Lab #${lab.labNumber}',
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade500)),
                      ],
                    ],
                  ),
                ),
                StatusBadge(
                  label: lab.status,
                  color: _statusColor,
                ),
              ],
            ),

            // ── Clinical note ──
            if (lab.note != null && lab.note!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                'Note: ${lab.note}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              ),
            ],

            // ── Tracking details ──
            const SizedBox(height: 8),
            _TrackingRow(label: 'Requested', value: lab.createdAt, icon: Icons.schedule),
            if (lab.billedByName != null)
              _TrackingRow(label: 'Billed by', value: lab.billedByName!, icon: Icons.receipt_outlined),
            if (lab.sampleTakenByName != null)
              _TrackingRow(label: 'Sample by', value: lab.sampleTakenByName!, icon: Icons.colorize),
            if (lab.approvedByName != null)
              _TrackingRow(label: 'Approved by', value: lab.approvedByName!, icon: Icons.verified_outlined),
            if (lab.doctorName != null)
              _TrackingRow(label: 'Doctor', value: lab.doctorName!, icon: Icons.person_outline),

            // ── Rejection reason ──
            if (lab.statusCode == 6 && lab.rejectionReason != null) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.cancel_outlined, size: 14, color: Colors.red.shade700),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text('Rejected: ${lab.rejectionReason}',
                          style: TextStyle(fontSize: 12, color: Colors.red.shade800)),
                    ),
                  ],
                ),
              ),
            ],

            // ── Result ──
            if (lab.result != null && lab.result!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.green.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.check_circle_outline,
                            size: 14, color: Colors.green.shade700),
                        const SizedBox(width: 4),
                        Text('Result',
                            style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: Colors.green.shade800)),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(lab.result!,
                        style: TextStyle(fontSize: 13, color: Colors.green.shade900)),
                    if (lab.resultDate != null || lab.resultBy != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        [
                          if (lab.resultDate != null) lab.resultDate!,
                          if (lab.resultBy != null) 'by ${lab.resultBy}',
                        ].join(' '),
                        style: TextStyle(
                            fontSize: 10, color: Colors.green.shade600),
                      ),
                    ],
                  ],
                ),
              ),
            ],

            // ── Attachments ──
            if (lab.attachments.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: lab.attachments.map((att) {
                  final name = att['name']?.toString() ?? att['file_name']?.toString() ?? 'File';
                  return ActionChip(
                    avatar: const Icon(Icons.attach_file, size: 14),
                    label: Text(name, style: const TextStyle(fontSize: 11)),
                    onPressed: () {
                      final path = att['file_path']?.toString() ?? att['url']?.toString();
                      if (path != null && path.isNotEmpty) {
                        final base = LocalStorage.baseUrl ?? '';
                        final url = path.startsWith('http') ? path : '$base/storage/$path';
                        launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
                      }
                    },
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  );
                }).toList(),
              ),
            ],

            // ── Actions ──
            if (!readOnly) ...[
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton.icon(
                    onPressed: onEditNote,
                    icon: Icon(Icons.edit_note, size: 16, color: Colors.blue.shade500),
                    label: Text('Edit Note',
                        style: TextStyle(fontSize: 12, color: Colors.blue.shade500)),
                    style: TextButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 8)),
                  ),
                  if (lab.statusCode <= 1)
                    TextButton.icon(
                      onPressed: onDelete,
                      icon: Icon(Icons.delete_outline,
                          size: 16, color: Colors.red.shade400),
                      label: Text('Remove',
                          style: TextStyle(fontSize: 12, color: Colors.red.shade400)),
                      style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 8)),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TrackingRow extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;

  const _TrackingRow({
    required this.label,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    if (value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 3),
      child: Row(
        children: [
          Icon(icon, size: 12, color: Colors.grey.shade400),
          const SizedBox(width: 4),
          Text('$label: ',
              style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
          Expanded(
            child: Text(value,
                style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
          ),
        ],
      ),
    );
  }
}

class _PendingItem {
  final int serviceId;
  final String name;
  String note;

  _PendingItem({
    required this.serviceId,
    required this.name,
    required this.note,
  });
}

// ─────────────────────────────────────────────────────────────
//  History Lab Card
// ─────────────────────────────────────────────────────────────

class _HistoryLabCard extends StatelessWidget {
  final Map<String, dynamic> item;
  const _HistoryLabCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final name = item['service_name']?.toString() ?? 'Unknown';
    final statusLabel = item['status_label']?.toString() ?? '';
    final result = item['result']?.toString() ?? '';
    final doctor = item['doctor_name']?.toString() ?? '';
    final note = item['note']?.toString() ?? '';
    final createdAt = item['created_at']?.toString() ?? '';

    String dateStr = createdAt;
    try {
      final dt = DateTime.parse(createdAt);
      dateStr =
          '${dt.day}/${dt.month}/${dt.year}';
    } catch (_) {}

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
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
                StatusBadge(label: statusLabel, color: Colors.blue.shade600),
              ],
            ),
            const SizedBox(height: 6),
            if (result.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Result:',
                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.green.shade800)),
                    const SizedBox(height: 4),
                    HtmlContent(data: result),
                  ],
                ),
              ),
              const SizedBox(height: 6),
            ],
            if (note.isNotEmpty)
              Text('Note: $note',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.person_outline, size: 12, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Text(doctor,
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                const Spacer(),
                Icon(Icons.access_time, size: 12, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Text(dateStr,
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
