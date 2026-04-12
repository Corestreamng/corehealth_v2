import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';

/// Nurse Charts Tab — 3 sub-tabs: Nursing Notes, Fluid I/O, Solid I/O.
class NurseChartsTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;

  const NurseChartsTab({
    super.key,
    required this.api,
    required this.encounter,
  });

  @override
  State<NurseChartsTab> createState() => _NurseChartsTabState();
}

class _NurseChartsTabState extends State<NurseChartsTab>
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late TabController _subTabCtrl;

  // Nursing notes state
  List<Map<String, dynamic>> _notes = [];
  List<Map<String, dynamic>> _noteTypes = [];
  int? _selectedTypeId;
  int _page = 1;
  int _lastPage = 1;
  bool _loadingNotes = true;
  bool _loadingMore = false;

  // I/O state
  List<dynamic> _fluidPeriods = [];
  List<dynamic> _solidPeriods = [];
  bool _loadingIO = true;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _subTabCtrl = TabController(length: 3, vsync: this);
    _loadNoteTypes();
    _loadNotes();
    _loadIntakeOutput();
  }

  @override
  void dispose() {
    _subTabCtrl.dispose();
    super.dispose();
  }

  // ── Nursing Notes loading ──

  Future<void> _loadNoteTypes() async {
    final res = await widget.api.getNoteTypes();
    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = res.data!['data'] ?? res.data!;
      if (list is List) {
        setState(() => _noteTypes = List<Map<String, dynamic>>.from(list));
      }
    }
  }

  Future<void> _loadNotes({bool append = false}) async {
    if (!append) setState(() => _loadingNotes = true);
    final res = await widget.api.getNursingNotes(
      widget.encounter.patientId,
      typeId: _selectedTypeId,
      page: _page,
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
          _notes.addAll(items);
        } else {
          _notes = items;
        }
        _lastPage = meta['last_page'] ?? 1;
        _loadingNotes = false;
        _loadingMore = false;
      });
    } else {
      setState(() { _loadingNotes = false; _loadingMore = false; });
    }
  }

  Future<void> _refreshNotes() async {
    _page = 1;
    await _loadNotes();
  }

  void _loadMore() {
    if (_loadingMore || _page >= _lastPage) return;
    _page++;
    _loadingMore = true;
    _loadNotes(append: true);
  }

  void _onTypeFilterChanged(int? typeId) {
    _selectedTypeId = typeId;
    _page = 1;
    _loadNotes();
  }

  // ── I/O loading ──

  Future<void> _loadIntakeOutput() async {
    setState(() => _loadingIO = true);
    final res = await widget.api.getIntakeOutput(widget.encounter.patientId);
    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() {
        _fluidPeriods = res.data!['fluidPeriods'] ?? [];
        _solidPeriods = res.data!['solidPeriods'] ?? [];
        _loadingIO = false;
      });
    } else {
      setState(() => _loadingIO = false);
    }
  }

  static String _stripHtml(String html) {
    return html
        .replaceAll(RegExp(r'<br\s*/?>'), '\n')
        .replaceAll(RegExp(r'</p>\s*<p[^>]*>'), '\n\n')
        .replaceAll(RegExp(r'<[^>]+>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .trim();
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
          labelStyle: const TextStyle(fontSize: 12),
          tabs: const [
            Tab(text: 'Nursing Notes'),
            Tab(icon: Icon(Icons.water_drop_outlined, size: 16), text: 'Fluid I/O'),
            Tab(icon: Icon(Icons.restaurant_outlined, size: 16), text: 'Solid I/O'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: _subTabCtrl,
            children: [
              _buildNotesTab(),
              _buildIOTab(_fluidPeriods, 'Fluid'),
              _buildIOTab(_solidPeriods, 'Solid'),
            ],
          ),
        ),
      ],
    );
  }

  // ── Nursing Notes sub-tab ──

  Widget _buildNotesTab() {
    return Column(
      children: [
        if (_noteTypes.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: DropdownButtonFormField<int?>(
              initialValue: _selectedTypeId,
              decoration: const InputDecoration(
                labelText: 'Filter by Note Type',
                isDense: true,
                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('All Types')),
                ..._noteTypes.map((t) => DropdownMenuItem<int?>(
                  value: t['id'] as int?,
                  child: Text(t['name']?.toString() ?? 'Unknown'),
                )),
              ],
              onChanged: _onTypeFilterChanged,
            ),
          ),
        Expanded(
          child: _loadingNotes
              ? const Center(child: CircularProgressIndicator())
              : _notes.isEmpty
                  ? _buildEmpty('No Nursing Notes', 'Notes will appear here when recorded by nursing staff.')
                  : RefreshIndicator(
                      onRefresh: _refreshNotes,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _notes.length + (_page < _lastPage ? 1 : 0),
                        itemBuilder: (context, i) {
                          if (i == _notes.length) {
                            _loadMore();
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                            );
                          }
                          return _NurseNoteCard(note: _notes[i], stripHtml: _stripHtml);
                        },
                      ),
                    ),
        ),
      ],
    );
  }

  // ── I/O sub-tab (shared for fluid & solid) ──

  Widget _buildIOTab(List<dynamic> periods, String label) {
    if (_loadingIO) return const Center(child: CircularProgressIndicator());
    if (periods.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadIntakeOutput,
        child: _buildEmpty('No $label I/O Records', '$label intake/output will appear here when recorded.'),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadIntakeOutput,
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: periods.length,
        itemBuilder: (context, i) => _IOPeriodCard(period: periods[i], label: label),
      ),
    );
  }

  Widget _buildEmpty(String title, String subtitle) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.assignment_outlined, size: 64, color: Colors.grey[400]),
              const SizedBox(height: 16),
              Text(title, style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: Colors.grey[700])),
              const SizedBox(height: 12),
              Text(subtitle, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], fontSize: 14, height: 1.5)),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Nurse Note Card ────────────────────────────────────────

class _NurseNoteCard extends StatelessWidget {
  final Map<String, dynamic> note;
  final String Function(String) stripHtml;

  const _NurseNoteCard({required this.note, required this.stripHtml});

  @override
  Widget build(BuildContext context) {
    final typeName = note['type_name']?.toString() ?? 'General';
    final createdBy = note['created_by']?.toString() ?? 'Unknown';
    final createdAt = note['created_at']?.toString() ?? '';
    final rawNote = note['note']?.toString() ?? '';
    final noteText = stripHtml(rawNote);

    String dateStr = createdAt;
    try {
      final dt = DateTime.parse(createdAt);
      dateStr = '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {}

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.teal.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.teal.shade200),
                ),
                child: Text(typeName, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.teal.shade700)),
              ),
              const Spacer(),
            ]),
            const SizedBox(height: 8),
            if (noteText.isNotEmpty)
              Text(noteText, style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
            const SizedBox(height: 8),
            Row(children: [
              Icon(Icons.person_outline, size: 14, color: Colors.grey.shade400),
              const SizedBox(width: 4),
              Expanded(child: Text(createdBy, style: TextStyle(fontSize: 11, color: Colors.grey.shade600))),
              Icon(Icons.access_time, size: 14, color: Colors.grey.shade400),
              const SizedBox(width: 4),
              Text(dateStr, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
            ]),
          ],
        ),
      ),
    );
  }
}

// ─── I/O Period Card ────────────────────────────────────────

class _IOPeriodCard extends StatelessWidget {
  final dynamic period;
  final String label;

  const _IOPeriodCard({required this.period, required this.label});

  @override
  Widget build(BuildContext context) {
    final startedAt = period['started_at']?.toString() ?? '';
    final endedAt = period['ended_at']?.toString();
    final nurseName = period['nurse_name']?.toString() ?? 'Unknown';
    final totalIntake = period['total_intake'] ?? 0;
    final totalOutput = period['total_output'] ?? 0;
    final records = period['records'] as List<dynamic>? ?? [];
    final isActive = endedAt == null;

    String formatDate(String raw) {
      try {
        final dt = DateTime.parse(raw);
        return '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
      } catch (_) {
        return raw;
      }
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(children: [
              if (isActive)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(color: Colors.green.shade100, borderRadius: BorderRadius.circular(8)),
                  child: Text('Active', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.green.shade800)),
                ),
              if (!isActive)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(color: Colors.grey.shade200, borderRadius: BorderRadius.circular(8)),
                  child: Text('Ended', style: TextStyle(fontSize: 10, color: Colors.grey.shade600)),
                ),
              const SizedBox(width: 8),
              Expanded(child: Text(formatDate(startedAt), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500))),
              Text(nurseName, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
            ]),
            const SizedBox(height: 8),
            // Totals
            Row(children: [
              _IOSummary('Intake', totalIntake, Colors.blue),
              const SizedBox(width: 16),
              _IOSummary('Output', totalOutput, Colors.orange),
              const SizedBox(width: 16),
              _IOSummary('Balance', (totalIntake as num) - (totalOutput as num), Colors.purple),
            ]),
            // Records
            if (records.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Divider(height: 1),
              const SizedBox(height: 6),
              ...records.map((r) {
                final type = r['type']?.toString() ?? 'intake';
                final amount = r['amount']?.toString() ?? '0';
                final unit = r['unit']?.toString() ?? 'ml';
                final description = r['description']?.toString() ?? '';
                final recTime = r['recorded_at']?.toString() ?? '';

                return Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(children: [
                    Icon(
                      type == 'intake' ? Icons.arrow_downward : Icons.arrow_upward,
                      size: 14,
                      color: type == 'intake' ? Colors.blue : Colors.orange,
                    ),
                    const SizedBox(width: 4),
                    Text('$amount $unit', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: type == 'intake' ? Colors.blue.shade700 : Colors.orange.shade700)),
                    if (description.isNotEmpty) ...[
                      const SizedBox(width: 6),
                      Expanded(child: Text(description, style: TextStyle(fontSize: 11, color: Colors.grey.shade600), overflow: TextOverflow.ellipsis)),
                    ] else
                      const Spacer(),
                    Text(formatDate(recTime), style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
                  ]),
                );
              }),
            ],
          ],
        ),
      ),
    );
  }
}

class _IOSummary extends StatelessWidget {
  final String label;
  final num value;
  final Color color;
  const _IOSummary(this.label, this.value, this.color);

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: TextStyle(fontSize: 10, color: color.withValues(alpha: 0.7))),
        Text('${value}ml', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: color)),
      ],
    );
  }
}
