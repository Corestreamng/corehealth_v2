import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 4: Lab service requests — search, add, delete.
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

class _LabTabState extends State<LabTab> with AutomaticKeepAliveClientMixin {
  // Pending items waiting to be saved (before hitting Save)
  final List<_PendingItem> _pending = [];
  bool _isSaving = false;

  @override
  bool get wantKeepAlive => true;

  void _addPending(Map<String, dynamic> item) {
    final id = item['id'] as int;
    // Don't add duplicates
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

  @override
  Widget build(BuildContext context) {
    super.build(context);
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

  const _LabCard({
    required this.lab,
    required this.readOnly,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
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
                  child: Text(
                    lab.serviceName,
                    style: const TextStyle(
                        fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                ),
                StatusBadge.fromStatus(lab.status),
              ],
            ),
            if (lab.note != null && lab.note!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                'Note: ${lab.note}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              ),
            ],
            if (lab.result != null && lab.result!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Result:',
                        style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Colors.green.shade800)),
                    const SizedBox(height: 2),
                    Text(lab.result!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.green.shade900)),
                    if (lab.resultDate != null)
                      Text('${lab.resultDate} by ${lab.resultBy ?? "—"}',
                          style: TextStyle(
                              fontSize: 10, color: Colors.green.shade600)),
                  ],
                ),
              ),
            ],
            if (!readOnly && lab.statusCode <= 1) ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: onDelete,
                  icon: Icon(Icons.delete_outline,
                      size: 16, color: Colors.red.shade400),
                  label: Text('Remove',
                      style: TextStyle(
                          fontSize: 12, color: Colors.red.shade400)),
                ),
              ),
            ],
          ],
        ),
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
