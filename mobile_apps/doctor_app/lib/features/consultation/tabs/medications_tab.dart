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
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;
  final List<_PendingRx> _pending = [];
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
    final res = await widget.api.getPrescriptionHistory(
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

    return Column(
      children: [
        TabBar(
          controller: _subTabCtrl,
          labelColor: Theme.of(context).colorScheme.primary,
          unselectedLabelColor: Colors.grey,
          indicatorSize: TabBarIndicatorSize.tab,
          tabs: const [Tab(text: 'Drug History'), Tab(text: 'Prescribe')],
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
          child: Text('No prescription history for this patient.',
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
          return _HistoryRxCard(item: h);
        },
      ),
    );
  }

  Widget _buildEntryTab() {
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
            // ── Extended fields ──
            if (rx.frequency != null && rx.frequency!.isNotEmpty) ...[
              const SizedBox(height: 4),
              _RxDetailRow(Icons.timer_outlined, 'Frequency', rx.frequency!),
            ],
            if (rx.duration != null && rx.duration!.isNotEmpty) ...[
              _RxDetailRow(
                Icons.calendar_today_outlined,
                'Duration',
                '${rx.duration}${rx.durationUnit != null ? ' ${rx.durationUnit}' : ''}',
              ),
            ],
            if (rx.route != null && rx.route!.isNotEmpty)
              _RxDetailRow(Icons.route_outlined, 'Route', rx.route!),
            if (rx.specialInstruction != null &&
                rx.specialInstruction!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.amber.shade50,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: Colors.amber.shade200),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline,
                        size: 14, color: Colors.amber.shade700),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        rx.specialInstruction!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.amber.shade900),
                      ),
                    ),
                  ],
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

class _HistoryRxCard extends StatelessWidget {
  final Map<String, dynamic> item;
  const _HistoryRxCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final name = item['product_name']?.toString() ?? 'Unknown';
    final category = item['category']?.toString() ?? '';
    final dose = item['dose']?.toString() ?? '';
    final qty = item['qty']?.toString() ?? '';
    final statusLabel = item['status_label']?.toString() ?? '';
    final doctor = item['doctor_name']?.toString() ?? '';
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
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name,
                          style: const TextStyle(
                              fontSize: 13, fontWeight: FontWeight.w600)),
                      if (category.isNotEmpty)
                        Text(category,
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade500)),
                    ],
                  ),
                ),
                StatusBadge(label: statusLabel, color: Colors.orange.shade700),
              ],
            ),
            const SizedBox(height: 6),
            if (dose.isNotEmpty)
              _RxDetailRow(Icons.medication_outlined, 'Dose', dose),
            if (qty.isNotEmpty)
              _RxDetailRow(Icons.inventory_2_outlined, 'Qty', qty),
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

class _RxDetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _RxDetailRow(this.icon, this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 3),
      child: Row(
        children: [
          Icon(icon, size: 13, color: Colors.grey.shade400),
          const SizedBox(width: 4),
          Text('$label: ',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
          Expanded(
            child: Text(value,
                style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
          ),
        ],
      ),
    );
  }
}
