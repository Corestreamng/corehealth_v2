import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 8: Patient history — encounter, lab, imaging, prescription, procedure history.
class HistoryTab extends StatefulWidget {
  final EncounterApiService api;
  final int patientId;

  const HistoryTab({super.key, required this.api, required this.patientId});

  @override
  State<HistoryTab> createState() => _HistoryTabState();
}

class _HistoryTabState extends State<HistoryTab>
    with AutomaticKeepAliveClientMixin {
  int _selectedIndex = 0;

  final _tabs = const [
    'Encounters',
    'Labs',
    'Imaging',
    'Meds',
    'Procedures',
  ];

  @override
  bool get wantKeepAlive => true;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final primary = Theme.of(context).colorScheme.primary;

    return Column(
      children: [
        // ── Horizontal chip selector ──
        SizedBox(
          height: 48,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            itemCount: _tabs.length,
            itemBuilder: (context, index) {
              final selected = index == _selectedIndex;
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(_tabs[index],
                      style: TextStyle(fontSize: 12, color: selected ? Colors.white : primary)),
                  selected: selected,
                  selectedColor: primary,
                  onSelected: (_) => setState(() => _selectedIndex = index),
                ),
              );
            },
          ),
        ),
        Expanded(
          child: IndexedStack(
            index: _selectedIndex,
            children: [
              _HistoryList(
                api: widget.api,
                patientId: widget.patientId,
                type: 'encounter',
              ),
              _HistoryList(
                api: widget.api,
                patientId: widget.patientId,
                type: 'lab',
              ),
              _HistoryList(
                api: widget.api,
                patientId: widget.patientId,
                type: 'imaging',
              ),
              _HistoryList(
                api: widget.api,
                patientId: widget.patientId,
                type: 'prescription',
              ),
              _HistoryList(
                api: widget.api,
                patientId: widget.patientId,
                type: 'procedure',
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _HistoryList extends StatefulWidget {
  final EncounterApiService api;
  final int patientId;
  final String type;

  const _HistoryList({
    required this.api,
    required this.patientId,
    required this.type,
  });

  @override
  State<_HistoryList> createState() => _HistoryListState();
}

class _HistoryListState extends State<_HistoryList>
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  String? _error;
  bool _hasMore = true;
  bool _isLoadingMore = false;
  int _page = 1;
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
            _scrollController.position.maxScrollExtent - 200 &&
        !_isLoadingMore &&
        _hasMore) {
      _loadMore();
    }
  }

  Future<ApiResult> _fetch(int page) {
    switch (widget.type) {
      case 'encounter':
        return widget.api.getEncounterHistory(widget.patientId, page: page);
      case 'lab':
        return widget.api.getLabHistory(widget.patientId, page: page);
      case 'imaging':
        return widget.api.getImagingHistory(widget.patientId, page: page);
      case 'prescription':
        return widget.api.getPrescriptionHistory(widget.patientId, page: page);
      case 'procedure':
        return widget.api.getProcedureHistory(widget.patientId, page: page);
      default:
        return widget.api.getEncounterHistory(widget.patientId, page: page);
    }
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _page = 1;
    });

    final res = await _fetch(1);

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => j as Map<String, dynamic>)
              .toList() ??
          [];
      setState(() {
        _items = list;
        _hasMore = res.data!['next_page_url'] != null;
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message.isNotEmpty ? res.message : 'Failed to load';
        _isLoading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_isLoadingMore || !_hasMore) return;
    setState(() => _isLoadingMore = true);
    _page++;

    final res = await _fetch(_page);

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => j as Map<String, dynamic>)
              .toList() ??
          [];
      setState(() {
        _items.addAll(list);
        _hasMore = res.data!['next_page_url'] != null;
        _isLoadingMore = false;
      });
    } else {
      _page--;
      setState(() => _isLoadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }
    if (_items.isEmpty) {
      return EmptyState(
        icon: Icons.history,
        title: 'No ${widget.type} history',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(12),
        itemCount: _items.length + (_isLoadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == _items.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return _HistoryCard(item: _items[index], type: widget.type);
        },
      ),
    );
  }
}

class _HistoryCard extends StatelessWidget {
  final Map<String, dynamic> item;
  final String type;

  const _HistoryCard({required this.item, required this.type});

  @override
  Widget build(BuildContext context) {
    switch (type) {
      case 'encounter':
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
                        item['date']?.toString() ?? item['created_at']?.toString() ?? '',
                        style: const TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w600),
                      ),
                    ),
                    StatusBadge(
                      label: item['completed'] == true ? 'Completed' : 'Open',
                      color: item['completed'] == true
                          ? Colors.green.shade700
                          : Colors.orange.shade700,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  'Dr. ${item['doctor_name'] ?? '—'} · ${item['clinic_name'] ?? '—'}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                ),
                if ((item['lab_count'] ?? 0) > 0 ||
                    (item['imaging_count'] ?? 0) > 0 ||
                    (item['prescription_count'] ?? 0) > 0) ...[
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 12,
                    children: [
                      if ((item['lab_count'] ?? 0) > 0)
                        Text('🧪 ${item['lab_count']} labs',
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade600)),
                      if ((item['imaging_count'] ?? 0) > 0)
                        Text('📷 ${item['imaging_count']} imaging',
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade600)),
                      if ((item['prescription_count'] ?? 0) > 0)
                        Text('💊 ${item['prescription_count']} meds',
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade600)),
                    ],
                  ),
                ],
              ],
            ),
          ),
        );

      case 'lab':
      case 'imaging':
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
                        item['service_name']?.toString() ?? 'Unknown',
                        style: const TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                    StatusBadge.fromStatus(
                        item['status_label']?.toString() ?? 'Unknown'),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  item['created_at']?.toString() ?? '',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                ),
                if (item['result'] != null &&
                    item['result'].toString().isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text('Result: ${item['result']}',
                        style: TextStyle(
                            fontSize: 12, color: Colors.green.shade800)),
                  ),
                ],
              ],
            ),
          ),
        );

      case 'prescription':
        return Card(
          margin: const EdgeInsets.only(bottom: 8),
          child: ListTile(
            dense: true,
            leading: Icon(Icons.medication,
                color: Colors.orange.shade700, size: 22),
            title: Text(item['product_name']?.toString() ?? 'Unknown',
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
            subtitle: Text(
              '${item['dose'] ?? 'No dose'} · ${item['created_at'] ?? ''}',
              style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
            ),
            trailing: StatusBadge.fromStatus(
                item['status_label']?.toString() ?? 'Unknown'),
          ),
        );

      case 'procedure':
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
                        item['service_name']?.toString() ?? 'Unknown',
                        style: const TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                    StatusBadge.fromStatus(
                        item['procedure_status']?.toString() ?? 'Unknown'),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${item['priority'] ?? ''} · ${item['created_at'] ?? ''}',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                ),
              ],
            ),
          ),
        );

      default:
        return const SizedBox.shrink();
    }
  }
}
