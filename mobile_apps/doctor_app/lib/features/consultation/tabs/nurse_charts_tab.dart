import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';

/// Nurse Charts Tab — displays nursing notes recorded by nursing staff.
/// Shows note type badge, creator name, timestamp, and note content.
/// Supports filtering by note type and pagination.
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
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _notes = [];
  List<Map<String, dynamic>> _noteTypes = [];
  int? _selectedTypeId;
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _loadNoteTypes();
    _loadNotes();
  }

  Future<void> _loadNoteTypes() async {
    final res = await widget.api.getNoteTypes();
    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = res.data!['data'] ?? res.data!;
      if (list is List) {
        setState(() {
          _noteTypes = List<Map<String, dynamic>>.from(list);
        });
      }
    }
  }

  Future<void> _loadNotes({bool append = false}) async {
    if (!append) setState(() => _loading = true);
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
        _loading = false;
        _loadingMore = false;
      });
    } else {
      setState(() {
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  Future<void> _refresh() async {
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
        // ── Type filter ──
        if (_noteTypes.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: DropdownButtonFormField<int?>(
              initialValue: _selectedTypeId,
              decoration: const InputDecoration(
                labelText: 'Filter by Note Type',
                isDense: true,
                contentPadding:
                    EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('All Types'),
                ),
                ..._noteTypes.map((t) => DropdownMenuItem<int?>(
                      value: t['id'] as int?,
                      child: Text(t['name']?.toString() ?? 'Unknown'),
                    )),
              ],
              onChanged: _onTypeFilterChanged,
            ),
          ),

        // ── Notes list ──
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _notes.isEmpty
                  ? _buildEmpty()
                  : RefreshIndicator(
                      onRefresh: _refresh,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _notes.length + (_page < _lastPage ? 1 : 0),
                        itemBuilder: (context, i) {
                          if (i == _notes.length) {
                            _loadMore();
                            return const Padding(
                              padding: EdgeInsets.all(16),
                              child: Center(
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2)),
                            );
                          }
                          return _NurseNoteCard(
                            note: _notes[i],
                            stripHtml: _stripHtml,
                          );
                        },
                      ),
                    ),
        ),
      ],
    );
  }

  Widget _buildEmpty() {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.assignment_outlined,
                  size: 64, color: Colors.grey[400]),
              const SizedBox(height: 16),
              Text(
                'No Nursing Notes',
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(color: Colors.grey[700]),
              ),
              const SizedBox(height: 12),
              Text(
                'Nursing notes are recorded by nursing staff.\nNotes will appear here when added.',
                textAlign: TextAlign.center,
                style: TextStyle(
                    color: Colors.grey[600], fontSize: 14, height: 1.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────
//  Nurse Note Card
// ─────────────────────────────────────────────────────────────

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
    final canEdit = note['can_edit'] == true;

    // Format date
    String dateStr = createdAt;
    try {
      final dt = DateTime.parse(createdAt);
      dateStr =
          '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {}

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Header: type badge + edit indicator ──
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.teal.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.teal.shade200),
                  ),
                  child: Text(
                    typeName,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Colors.teal.shade700,
                    ),
                  ),
                ),
                const Spacer(),
                if (canEdit)
                  Icon(Icons.edit_outlined,
                      size: 14, color: Colors.grey.shade400),
              ],
            ),
            const SizedBox(height: 8),

            // ── Note content ──
            if (noteText.isNotEmpty)
              Text(
                noteText,
                style: TextStyle(fontSize: 13, color: Colors.grey.shade800),
              ),

            const SizedBox(height: 8),

            // ── Footer: creator + timestamp ──
            Row(
              children: [
                Icon(Icons.person_outline,
                    size: 14, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    createdBy,
                    style:
                        TextStyle(fontSize: 11, color: Colors.grey.shade600),
                  ),
                ),
                Icon(Icons.access_time, size: 14, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Text(
                  dateStr,
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
