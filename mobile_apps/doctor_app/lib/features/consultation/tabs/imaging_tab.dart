import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/storage/local_storage.dart';
import '../../../core/dictation/dictation_button.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 5: Imaging service requests — search, add, delete, view results.
class ImagingTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onSaved;

  const ImagingTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onSaved,
  });

  @override
  State<ImagingTab> createState() => _ImagingTabState();
}

class _ImagingTabState extends State<ImagingTab>
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;
  final List<_PendingImaging> _pending = [];
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
    final res = await widget.api.getImagingHistory(
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
    if (widget.encounter.imaging.any((i) => i.serviceId == id)) {
      showErrorSnackBar(context, 'This imaging is already requested');
      return;
    }
    setState(() {
      _pending.add(_PendingImaging(
        serviceId: id,
        name: item['service_name']?.toString() ??
            item['display']?.toString() ??
            'Unknown',
        note: '',
      ));
    });
  }

  Future<void> _saveImaging() async {
    if (_pending.isEmpty) {
      showErrorSnackBar(context, 'Add at least one imaging request');
      return;
    }
    setState(() => _isSaving = true);

    final res = await widget.api.saveImaging(
      widget.encounter.id,
      serviceIds: _pending.map((p) => p.serviceId).toList(),
      notes: _pending.map((p) => p.note).toList(),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Imaging saved');
      setState(() => _pending.clear());
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  Future<void> _deleteImaging(ImagingRequest img) async {
    final reason = await showDeleteWithReasonDialog(
      context,
      title: 'Delete Imaging Request',
      message: 'Remove "${img.serviceName}" from this encounter?',
    );
    if (reason == null || !mounted) return;

    final res = await widget.api.deleteImaging(widget.encounter.id, img.id, reason: reason);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Imaging request deleted');
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Delete failed');
    }
  }

  Future<void> _editNote(ImagingRequest img) async {
    final ctrl = TextEditingController(text: img.note ?? '');
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text('Edit Note — ${img.serviceName}',
            style: const TextStyle(fontSize: 15)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text('Clinical Indication',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                ),
                DictationButton(
                    controller: ctrl,
                    fieldLabel: 'Imaging Note'),
              ],
            ),
            const SizedBox(height: 4),
            TextField(
              controller: ctrl,
              maxLines: 3,
              autofocus: true,
              decoration: const InputDecoration(hintText: 'Clinical indication...'),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
              onPressed: () => Navigator.pop(ctx, ctrl.text),
              child: const Text('Save')),
        ],
      ),
    );
    ctrl.dispose();
    if (result == null || !mounted) return;

    final res = await widget.api.updateImagingNote(
      widget.encounter.id, img.id,
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
          child: Text('No imaging history for this patient.',
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
          return _HistoryImagingCard(item: h);
        },
      ),
    );
  }

  Widget _buildEntryTab() {
    final imaging = widget.encounter.imaging;
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Search & add ──
          if (!readOnly) ...[
            const SectionHeader(
                title: 'Add Imaging', icon: Icons.add_circle_outline),
            ServiceSearchField(
              hintText: 'Search imaging services...',
              onSearch: (term) => widget.api.searchServices(term),
              onSelect: _addPending,
            ),
            const SizedBox(height: 8),
          ],

          // ── Pending items ──
          if (_pending.isNotEmpty) ...[
            Card(
              color: Colors.purple.shade50,
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
                        color: Colors.purple.shade800,
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
                                      hintText: 'Clinical indication (optional)',
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
                        onPressed: _isSaving ? null : _saveImaging,
                        icon: _isSaving
                            ? const SizedBox(
                                width: 18, height: 18,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.save, size: 18),
                        label: Text(_isSaving ? 'Saving...' : 'Save Imaging'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // ── Existing imaging requests ──
          SectionHeader(
            title: 'Imaging Requests',
            icon: Icons.image_outlined,
            trailing: Text('${imaging.length}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          ),
          if (imaging.isEmpty)
            const EmptyState(
              icon: Icons.image_outlined,
              title: 'No imaging requests',
              subtitle: 'Search and add imaging studies above',
            )
          else
            ...imaging.map((img) => _ImagingCard(
                  imaging: img,
                  readOnly: readOnly,
                  onDelete: () => _deleteImaging(img),
                  onEditNote: () => _editNote(img),
                )),

          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _ImagingCard extends StatelessWidget {
  final ImagingRequest imaging;
  final bool readOnly;
  final VoidCallback onDelete;
  final VoidCallback onEditNote;

  const _ImagingCard({
    required this.imaging,
    required this.readOnly,
    required this.onDelete,
    required this.onEditNote,
  });

  Color get _statusColor {
    switch (imaging.statusCode) {
      case 1: return const Color(0xFFF57F17);
      case 2: return const Color(0xFF1565C0);
      case 3: return const Color(0xFF2E7D32);
      case 5: return const Color(0xFF0277BD);
      case 6: return const Color(0xFFC62828);
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
                  child: Text(imaging.serviceName,
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                ),
                StatusBadge(label: imaging.status, color: _statusColor),
              ],
            ),

            if (imaging.note != null && imaging.note!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Note: ${imaging.note}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],

            // ── Tracking ──
            const SizedBox(height: 8),
            _TrackingRow(label: 'Requested', value: imaging.createdAt, icon: Icons.schedule),
            if (imaging.billedByName != null)
              _TrackingRow(label: 'Billed by', value: imaging.billedByName!, icon: Icons.receipt_outlined),
            if (imaging.approvedByName != null)
              _TrackingRow(label: 'Approved by', value: imaging.approvedByName!, icon: Icons.verified_outlined),
            if (imaging.doctorName != null)
              _TrackingRow(label: 'Doctor', value: imaging.doctorName!, icon: Icons.person_outline),

            // ── Rejection reason ──
            if (imaging.statusCode == 6 && imaging.rejectionReason != null) ...[
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
                      child: Text('Rejected: ${imaging.rejectionReason}',
                          style: TextStyle(fontSize: 12, color: Colors.red.shade800)),
                    ),
                  ],
                ),
              ),
            ],

            // ── Result ──
            if (imaging.result != null && imaging.result!.isNotEmpty) ...[
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
                    Text(imaging.result!,
                        style: TextStyle(
                            fontSize: 13, color: Colors.green.shade900)),
                    if (imaging.resultDate != null || imaging.resultBy != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        [
                          if (imaging.resultDate != null) imaging.resultDate!,
                          if (imaging.resultBy != null) 'by ${imaging.resultBy}',
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
            if (imaging.attachments.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: imaging.attachments.map((att) {
                  final name = att['name']?.toString() ??
                      att['file_name']?.toString() ??
                      'File';
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
                    icon: Icon(Icons.edit_note,
                        size: 16, color: Colors.blue.shade500),
                    label: Text('Edit Note',
                        style: TextStyle(
                            fontSize: 12, color: Colors.blue.shade500)),
                    style: TextButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 8)),
                  ),
                  if (imaging.statusCode <= 1)
                    TextButton.icon(
                      onPressed: onDelete,
                      icon: Icon(Icons.delete_outline,
                          size: 16, color: Colors.red.shade400),
                      label: Text('Remove',
                          style: TextStyle(
                              fontSize: 12, color: Colors.red.shade400)),
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

class _PendingImaging {
  final int serviceId;
  final String name;
  String note;

  _PendingImaging({
    required this.serviceId,
    required this.name,
    required this.note,
  });
}

class _HistoryImagingCard extends StatelessWidget {
  final Map<String, dynamic> item;
  const _HistoryImagingCard({required this.item});

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
      dateStr = '${dt.day}/${dt.month}/${dt.year}';
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
                StatusBadge(label: statusLabel, color: Colors.purple.shade600),
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
