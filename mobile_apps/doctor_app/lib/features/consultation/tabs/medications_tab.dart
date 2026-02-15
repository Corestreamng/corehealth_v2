import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/service_search_field.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 6: Medications / Prescriptions — search products, add dose, save.
class MedicationsTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onSaved;

  const MedicationsTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onSaved,
  });

  @override
  State<MedicationsTab> createState() => _MedicationsTabState();
}

class _MedicationsTabState extends State<MedicationsTab>
    with AutomaticKeepAliveClientMixin {
  final List<_PendingRx> _pending = [];
  bool _isSaving = false;

  @override
  bool get wantKeepAlive => true;

  void _addPending(Map<String, dynamic> item) {
    final id = item['id'] as int;
    if (_pending.any((p) => p.productId == id)) return;
    if (widget.encounter.prescriptions.any((rx) => rx.productId == id)) {
      showErrorSnackBar(context, 'This medication is already prescribed');
      return;
    }
    setState(() {
      _pending.add(_PendingRx(
        productId: id,
        name: item['product_name']?.toString() ??
            item['display']?.toString() ??
            'Unknown',
        dose: '',
        stock: item['stock'],
      ));
    });
  }

  Future<void> _savePrescriptions() async {
    if (_pending.isEmpty) {
      showErrorSnackBar(context, 'Add at least one medication');
      return;
    }
    // Warn about empty doses
    final emptyDoses = _pending.where((p) => p.dose.trim().isEmpty).length;
    if (emptyDoses > 0) {
      final proceed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Missing Doses'),
          content: Text(
              '$emptyDoses medication${emptyDoses == 1 ? '' : 's'} ha${emptyDoses == 1 ? 's' : 've'} no dose specified. Continue anyway?'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Go Back')),
            ElevatedButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Save Anyway')),
          ],
        ),
      );
      if (proceed != true || !mounted) return;
    }

    setState(() => _isSaving = true);

    final res = await widget.api.savePrescriptions(
      widget.encounter.id,
      productIds: _pending.map((p) => p.productId).toList(),
      doses: _pending.map((p) => p.dose).toList(),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Prescriptions saved');
      setState(() => _pending.clear());
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to save');
    }
  }

  Future<void> _deleteRx(Prescription rx) async {
    final confirmed = await showDeleteConfirmation(
      context,
      title: 'Delete Prescription',
      message: 'Remove "${rx.productName}" from this encounter?',
    );
    if (!confirmed || !mounted) return;

    final res =
        await widget.api.deletePrescription(widget.encounter.id, rx.id);
    if (!mounted) return;

    if (res.success) {
      showSuccessSnackBar(context, 'Prescription deleted');
      widget.onSaved();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Delete failed');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final prescriptions = widget.encounter.prescriptions;
    final readOnly = widget.encounter.completed;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Search & add ──
          if (!readOnly) ...[
            const SectionHeader(
                title: 'Prescribe Medication', icon: Icons.add_circle_outline),
            ServiceSearchField(
              hintText: 'Search medications...',
              onSearch: (term) => widget.api.searchProducts(term),
              onSelect: _addPending,
            ),
            const SizedBox(height: 8),
          ],

          // ── Pending items ──
          if (_pending.isNotEmpty) ...[
            Card(
              color: Colors.orange.shade50,
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
                        color: Colors.orange.shade800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._pending.asMap().entries.map((entry) {
                      final i = entry.key;
                      final p = entry.value;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(p.name,
                                            style: const TextStyle(
                                                fontSize: 13,
                                                fontWeight: FontWeight.w500)),
                                      ),
                                      if (p.stock != null)
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: (p.stock as num) > 0
                                                ? Colors.green.shade100
                                                : Colors.red.shade100,
                                            borderRadius:
                                                BorderRadius.circular(8),
                                          ),
                                          child: Text(
                                            'Stock: ${p.stock}',
                                            style: TextStyle(
                                              fontSize: 10,
                                              color: (p.stock as num) > 0
                                                  ? Colors.green.shade800
                                                  : Colors.red.shade800,
                                            ),
                                          ),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  TextField(
                                    onChanged: (v) => _pending[i].dose = v,
                                    decoration: const InputDecoration(
                                      hintText:
                                          'Dose & frequency (e.g. 500mg TDS x 5 days)',
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
                        onPressed: _isSaving ? null : _savePrescriptions,
                        icon: _isSaving
                            ? const SizedBox(
                                width: 18, height: 18,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.save, size: 18),
                        label: Text(
                            _isSaving ? 'Saving...' : 'Save Prescriptions'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // ── Existing prescriptions ──
          SectionHeader(
            title: 'Prescriptions',
            icon: Icons.medication_outlined,
            trailing: Text('${prescriptions.length}',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          ),
          if (prescriptions.isEmpty)
            const EmptyState(
              icon: Icons.medication_outlined,
              title: 'No prescriptions',
              subtitle: 'Search and add medications above',
            )
          else
            ...prescriptions.map((rx) => _RxCard(
                  rx: rx,
                  readOnly: readOnly,
                  onDelete: () => _deleteRx(rx),
                )),

          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _RxCard extends StatelessWidget {
  final Prescription rx;
  final bool readOnly;
  final VoidCallback onDelete;

  const _RxCard({
    required this.rx,
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
                Icon(Icons.medication, size: 18, color: Colors.orange.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(rx.productName,
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                ),
                StatusBadge.fromStatus(rx.status),
              ],
            ),
            if (rx.dose != null && rx.dose!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  'Dose: ${rx.dose}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                ),
              ),
            ],
            if (rx.qty != null) ...[
              const SizedBox(height: 4),
              Text('Qty: ${rx.qty}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ],
            if (!readOnly && rx.statusCode <= 1) ...[
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

class _PendingRx {
  final int productId;
  final String name;
  String dose;
  final dynamic stock;

  _PendingRx({
    required this.productId,
    required this.name,
    required this.dose,
    this.stock,
  });
}
