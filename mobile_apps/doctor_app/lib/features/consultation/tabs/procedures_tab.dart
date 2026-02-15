import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 7: Procedures — search, add with priority/date/notes, manage team & notes.
class ProceduresTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onSaved;

  const ProceduresTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onSaved,
  });

  @override
  State<ProceduresTab> createState() => _ProceduresTabState();
}

class _ProceduresTabState extends State<ProceduresTab>
    with AutomaticKeepAliveClientMixin {
  final List<_PendingProcedure> _pending = [];
  bool _isSaving = false;

  @override
  bool get wantKeepAlive => true;

  void _addPending(Map<String, dynamic> item) {
    final id = item['id'] as int;
    if (_pending.any((p) => p.serviceId == id)) return;
    setState(() {
      _pending.add(_PendingProcedure(
        serviceId: id,
        name: item['service_name']?.toString() ??
            item['display']?.toString() ??
            'Unknown',
      ));
    });
  }

  Future<void> _saveProcedures() async {
    if (_pending.isEmpty) {
      showErrorSnackBar(context, 'Add at least one procedure');
      return;
    }
    setState(() => _isSaving = true);

    final res = await widget.api.saveProcedures(
      widget.encounter.id,
      procedures: _pending
          .map((p) => {
                'service_id': p.serviceId,
                'priority': p.priority,
                if (p.scheduledDate != null)
                  'scheduled_date': p.scheduledDate!.toIso8601String().split('T')[0],
                if (p.preNotes.isNotEmpty) 'pre_notes': p.preNotes,
              })
          .toList(),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Procedures saved');
      setState(() => _pending.clear());
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  Future<void> _deleteProcedure(ProcedureData proc) async {
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Delete Procedure',
      message: 'Remove "${proc.serviceName}" from this encounter?',
    );
    if (!confirmed || !mounted) return;

    final res = await widget.api.deleteProcedure(widget.encounter.id, proc.id);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Procedure deleted');
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Delete failed');
    }
  }

  Future<void> _cancelProcedure(ProcedureData proc) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Cancel Procedure'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Reason for cancellation...',
          ),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('Back')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, controller.text),
            style:
                ElevatedButton.styleFrom(backgroundColor: Colors.red.shade600),
            child: const Text('Cancel Procedure'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (reason == null || !mounted) return;

    final res = await widget.api.cancelProcedure(proc.id, reason: reason);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Procedure cancelled');
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Cancel failed');
    }
  }

  void _showProcedureDetail(ProcedureData proc) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _ProcedureDetailPage(
          api: widget.api,
          procedure: proc,
          readOnly: widget.encounter.completed,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final procedures = widget.encounter.procedures;
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Search & add ──
          if (!readOnly) ...[
            const SectionHeader(
                title: 'Request Procedure', icon: Icons.add_circle_outline),
            ServiceSearchField(
              hintText: 'Search procedures...',
              onSearch: (term) => widget.api.searchServices(term),
              onSelect: _addPending,
            ),
            const SizedBox(height: 8),
          ],

          // ── Pending items ──
          if (_pending.isNotEmpty) ...[
            Card(
              color: Colors.teal.shade50,
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
                        color: Colors.teal.shade800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._pending.asMap().entries.map((entry) {
                      final i = entry.key;
                      final p = entry.value;
                      return _PendingProcedureCard(
                        item: p,
                        onRemove: () =>
                            setState(() => _pending.removeAt(i)),
                        onPriorityChanged: (v) =>
                            setState(() => _pending[i].priority = v),
                        onDateChanged: (d) =>
                            setState(() => _pending[i].scheduledDate = d),
                        onNotesChanged: (v) =>
                            setState(() => _pending[i].preNotes = v),
                      );
                    }),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _isSaving ? null : _saveProcedures,
                        icon: _isSaving
                            ? const SizedBox(
                                width: 18, height: 18,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.save, size: 18),
                        label: Text(
                            _isSaving ? 'Saving...' : 'Save Procedures'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // ── Existing procedures ──
          SectionHeader(
            title: 'Procedures',
            icon: Icons.medical_services_outlined,
            trailing: Text('${procedures.length}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          ),
          if (procedures.isEmpty)
            const EmptyState(
              icon: Icons.medical_services_outlined,
              title: 'No procedures',
              subtitle: 'Search and add procedures above',
            )
          else
            ...procedures.map((proc) => _ProcedureCard(
                  procedure: proc,
                  readOnly: readOnly,
                  onTap: () => _showProcedureDetail(proc),
                  onDelete: () => _deleteProcedure(proc),
                  onCancel: () => _cancelProcedure(proc),
                )),

          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
// Pending procedure form card
// ═══════════════════════════════════════════════════════════════

class _PendingProcedureCard extends StatelessWidget {
  final _PendingProcedure item;
  final VoidCallback onRemove;
  final void Function(String) onPriorityChanged;
  final void Function(DateTime?) onDateChanged;
  final void Function(String) onNotesChanged;

  const _PendingProcedureCard({
    required this.item,
    required this.onRemove,
    required this.onPriorityChanged,
    required this.onDateChanged,
    required this.onNotesChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(item.name,
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w600)),
              ),
              IconButton(
                icon: Icon(Icons.remove_circle,
                    color: Colors.red.shade400, size: 20),
                onPressed: onRemove,
              ),
            ],
          ),
          Row(
            children: [
              // Priority dropdown
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: item.priority,
                  items: const [
                    DropdownMenuItem(value: 'routine', child: Text('Routine')),
                    DropdownMenuItem(value: 'urgent', child: Text('Urgent')),
                    DropdownMenuItem(
                        value: 'emergency', child: Text('Emergency')),
                  ],
                  onChanged: (v) {
                    if (v != null) onPriorityChanged(v);
                  },
                  decoration: const InputDecoration(
                    labelText: 'Priority',
                    isDense: true,
                    contentPadding:
                        EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  ),
                  style: const TextStyle(fontSize: 12, color: Colors.black87),
                ),
              ),
              const SizedBox(width: 10),
              // Scheduled date
              Expanded(
                child: InkWell(
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: context,
                      initialDate: item.scheduledDate ?? DateTime.now(),
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    onDateChanged(picked);
                  },
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Date',
                      isDense: true,
                      contentPadding:
                          EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                    ),
                    child: Text(
                      item.scheduledDate != null
                          ? '${item.scheduledDate!.year}-${item.scheduledDate!.month.toString().padLeft(2, '0')}-${item.scheduledDate!.day.toString().padLeft(2, '0')}'
                          : 'Not set',
                      style: TextStyle(
                        fontSize: 12,
                        color: item.scheduledDate != null
                            ? Colors.black87
                            : Colors.grey.shade500,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          TextField(
            onChanged: onNotesChanged,
            decoration: const InputDecoration(
              hintText: 'Pre-operative notes (optional)',
              isDense: true,
              contentPadding:
                  EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            ),
            style: const TextStyle(fontSize: 12),
          ),
          Divider(color: Colors.teal.shade100),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
// Procedure card (existing)
// ═══════════════════════════════════════════════════════════════

class _ProcedureCard extends StatelessWidget {
  final ProcedureData procedure;
  final bool readOnly;
  final VoidCallback onTap;
  final VoidCallback onDelete;
  final VoidCallback onCancel;

  const _ProcedureCard({
    required this.procedure,
    required this.readOnly,
    required this.onTap,
    required this.onDelete,
    required this.onCancel,
  });

  Color _priorityColor() {
    switch (procedure.priority) {
      case 'emergency': return Colors.red.shade700;
      case 'urgent': return Colors.orange.shade700;
      default: return Colors.green.shade700;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(procedure.serviceName,
                        style: const TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w600)),
                  ),
                  StatusBadge.fromStatus(procedure.procedureStatus),
                ],
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 10,
                runSpacing: 4,
                children: [
                  _ProcChip(
                    icon: Icons.flag,
                    label: procedure.priority.toUpperCase(),
                    color: _priorityColor(),
                  ),
                  if (procedure.scheduledDate != null)
                    _ProcChip(
                      icon: Icons.calendar_today,
                      label: procedure.scheduledDate!,
                      color: Colors.grey.shade700,
                    ),
                  if (procedure.operatingRoom != null)
                    _ProcChip(
                      icon: Icons.room,
                      label: procedure.operatingRoom!,
                      color: Colors.grey.shade700,
                    ),
                ],
              ),
              if (procedure.preNotes != null &&
                  procedure.preNotes!.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text('Pre-op: ${procedure.preNotes}',
                    style: TextStyle(
                        fontSize: 12, color: Colors.grey.shade600)),
              ],
              if (procedure.outcome != null &&
                  procedure.outcome!.isNotEmpty) ...[
                const SizedBox(height: 6),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text('Outcome: ${procedure.outcome}',
                      style: TextStyle(
                          fontSize: 12, color: Colors.green.shade800)),
                ),
              ],
              if (!readOnly &&
                  procedure.procedureStatus != 'completed' &&
                  procedure.procedureStatus != 'cancelled') ...[
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton.icon(
                      onPressed: onCancel,
                      icon: Icon(Icons.cancel_outlined,
                          size: 16, color: Colors.orange.shade600),
                      label: Text('Cancel',
                          style: TextStyle(
                              fontSize: 12, color: Colors.orange.shade600)),
                    ),
                    if (procedure.procedureStatus == 'requested')
                      TextButton.icon(
                        onPressed: onDelete,
                        icon: Icon(Icons.delete_outline,
                            size: 16, color: Colors.red.shade400),
                        label: Text('Delete',
                            style: TextStyle(
                                fontSize: 12, color: Colors.red.shade400)),
                      ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ProcChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;

  const _ProcChip({
    required this.icon,
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 12, color: color),
        const SizedBox(width: 3),
        Text(label, style: TextStyle(fontSize: 11, color: color)),
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════
// Procedure Detail Page (team + notes)
// ═══════════════════════════════════════════════════════════════

class _ProcedureDetailPage extends StatefulWidget {
  final EncounterApiService api;
  final ProcedureData procedure;
  final bool readOnly;

  const _ProcedureDetailPage({
    required this.api,
    required this.procedure,
    required this.readOnly,
  });

  @override
  State<_ProcedureDetailPage> createState() => _ProcedureDetailPageState();
}

class _ProcedureDetailPageState extends State<_ProcedureDetailPage> {
  List<dynamic> _team = [];
  List<dynamic> _notes = [];
  bool _isLoadingTeam = true;
  bool _isLoadingNotes = true;

  @override
  void initState() {
    super.initState();
    _loadTeam();
    _loadNotes();
  }

  Future<void> _loadTeam() async {
    final res = await widget.api.getProcedureTeam(widget.procedure.id);
    if (!mounted) return;
    setState(() {
      _team = res.data?['team'] is List ? res.data!['team'] as List : [];
      _isLoadingTeam = false;
    });
  }

  Future<void> _loadNotes() async {
    final res = await widget.api.getProcedureNotes(widget.procedure.id);
    if (!mounted) return;
    setState(() {
      _notes = res.data?['notes'] is List ? res.data!['notes'] as List : [];
      _isLoadingNotes = false;
    });
  }

  Future<void> _addNote() async {
    final controller = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Add Note'),
        content: TextField(
          controller: controller,
          maxLines: 4,
          decoration: const InputDecoration(hintText: 'Enter note...'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
              onPressed: () => Navigator.pop(ctx, controller.text),
              child: const Text('Add')),
        ],
      ),
    );
    controller.dispose();
    if (note == null || note.trim().isEmpty || !mounted) return;

    final res =
        await widget.api.addProcedureNote(widget.procedure.id, note: note);
    if (!mounted) return;
    if (res.success) {
      showSuccessSnackBar(context, 'Note added');
      _loadNotes();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to add note');
    }
  }

  @override
  Widget build(BuildContext context) {
    final proc = widget.procedure;

    return Scaffold(
      appBar: AppBar(
        title: Text(proc.serviceName,
            style: const TextStyle(fontSize: 16)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Details ──
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    _DetailRow('Status', proc.procedureStatus),
                    _DetailRow('Priority', proc.priority),
                    _DetailRow('Scheduled', proc.scheduledDate ?? 'Not set'),
                    _DetailRow('Requested By', proc.requestedBy ?? '—'),
                    _DetailRow('Requested On', proc.requestedOn ?? '—'),
                    if (proc.operatingRoom != null)
                      _DetailRow('Operating Room', proc.operatingRoom!),
                    if (proc.outcome != null)
                      _DetailRow('Outcome', proc.outcome!),
                    if (proc.cancellationReason != null)
                      _DetailRow('Cancellation', proc.cancellationReason!),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // ── Team ──
            SectionHeader(
              title: 'Procedure Team',
              icon: Icons.people_outline,
              trailing: Text('${_team.length}'),
            ),
            if (_isLoadingTeam)
              const Center(child: CircularProgressIndicator())
            else if (_team.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('No team members assigned',
                      style: TextStyle(color: Colors.grey.shade500)),
                ),
              )
            else
              ..._team.map((m) => Card(
                    margin: const EdgeInsets.only(bottom: 6),
                    child: ListTile(
                      dense: true,
                      leading: CircleAvatar(
                        radius: 16,
                        child: Text(
                          (m['name']?.toString() ?? '?')[0].toUpperCase(),
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                      title: Text(m['name']?.toString() ?? 'Unknown',
                          style: const TextStyle(fontSize: 13)),
                      subtitle: Text(m['role']?.toString() ?? '',
                          style: const TextStyle(fontSize: 11)),
                    ),
                  )),
            const SizedBox(height: 16),

            // ── Notes ──
            SectionHeader(
              title: 'Procedure Notes',
              icon: Icons.note_alt_outlined,
              trailing: !widget.readOnly
                  ? IconButton(
                      icon: const Icon(Icons.add_circle_outline, size: 20),
                      onPressed: _addNote,
                    )
                  : null,
            ),
            if (_isLoadingNotes)
              const Center(child: CircularProgressIndicator())
            else if (_notes.isEmpty)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('No notes yet',
                      style: TextStyle(color: Colors.grey.shade500)),
                ),
              )
            else
              ..._notes.map((n) => Card(
                    margin: const EdgeInsets.only(bottom: 6),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(n['note']?.toString() ?? '',
                              style: const TextStyle(fontSize: 13)),
                          const SizedBox(height: 4),
                          Text(
                            '${n['created_by_name'] ?? ''} · ${n['created_at'] ?? ''}',
                            style: TextStyle(
                                fontSize: 10, color: Colors.grey.shade500),
                          ),
                        ],
                      ),
                    ),
                  )),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(label,
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(
                    fontSize: 13, fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }
}

class _PendingProcedure {
  final int serviceId;
  final String name;
  String priority = 'routine';
  DateTime? scheduledDate;
  String preNotes = '';

  _PendingProcedure({
    required this.serviceId,
    required this.name,
  });
}
