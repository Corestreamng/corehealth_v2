import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 5: Imaging service requests — search, add, delete.
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
    with AutomaticKeepAliveClientMixin {
  final List<_PendingImaging> _pending = [];
  bool _isSaving = false;

  @override
  bool get wantKeepAlive => true;

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
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Delete Imaging Request',
      message: 'Remove "${img.serviceName}" from this encounter?',
    );
    if (!confirmed || !mounted) return;

    final res = await widget.api.deleteImaging(widget.encounter.id, img.id);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Imaging request deleted');
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Delete failed');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
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

  const _ImagingCard({
    required this.imaging,
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
                  child: Text(imaging.serviceName,
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                ),
                StatusBadge.fromStatus(imaging.status),
              ],
            ),
            if (imaging.note != null && imaging.note!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text('Note: ${imaging.note}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],
            if (imaging.result != null && imaging.result!.isNotEmpty) ...[
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
                    Text(imaging.result!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.green.shade900)),
                    if (imaging.resultDate != null)
                      Text(
                          '${imaging.resultDate} by ${imaging.resultBy ?? "—"}',
                          style: TextStyle(
                              fontSize: 10, color: Colors.green.shade600)),
                  ],
                ),
              ),
            ],
            if (!readOnly && imaging.statusCode <= 1) ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: onDelete,
                  icon: Icon(Icons.delete_outline,
                      size: 16, color: Colors.red.shade400),
                  label: Text('Remove',
                      style:
                          TextStyle(fontSize: 12, color: Colors.red.shade400)),
                ),
              ),
            ],
          ],
        ),
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
