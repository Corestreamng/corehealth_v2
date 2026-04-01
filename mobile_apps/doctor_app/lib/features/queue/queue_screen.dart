import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/models/queue_item.dart';
import '../../core/widgets/status_badge.dart';
import '../../core/widgets/shared_widgets.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/date_range_filter.dart';
import '../../core/widgets/status_pill_bar.dart';
import '../consultation/consultation_screen.dart';
import 'queue_history_tabs.dart';

class QueueScreen extends StatefulWidget {
  final bool embedded;

  const QueueScreen({super.key, this.embedded = false});

  @override
  State<QueueScreen> createState() => _QueueScreenState();
}

class _QueueScreenState extends State<QueueScreen>
    with SingleTickerProviderStateMixin {
  late EncounterApiService _api;
  late TabController _mainTabController;

  Map<String, dynamic>? _stats;
  bool _statsLoading = false;

  List<QueueItem> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;

  final _scrollController = ScrollController();
  Timer? _refreshTimer;

  // Filters
  int? _selectedStatus; // null means All
  String? _startDate;
  String? _endDate;

  @override
  void initState() {
    super.initState();
    final baseUrl = LocalStorage.baseUrl ?? '';
    _api = EncounterApiService(baseUrl);
    _mainTabController = TabController(length: 2, vsync: this);

    _loadStats();
    _loadQueue();

    _scrollController.addListener(_onScroll);

    // Auto-refresh every 30 seconds
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && !_isLoading && !_isLoadingMore) {
        _loadStats();
        _loadQueue(isRefresh: true);
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _scrollController.dispose();
    _mainTabController.dispose();
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

  Future<void> _loadStats() async {
    setState(() => _statsLoading = true);
    final res = await _api.getQueueStats();
    if (!mounted) return;

    if (res.success && res.data != null) {
      setState(() {
        _stats = res.data;
        _statsLoading = false;
      });
    } else {
      setState(() => _statsLoading = false);
    }
  }

  Future<void> _loadQueue({bool isRefresh = false}) async {
    if (!isRefresh) {
      setState(() {
        _isLoading = true;
        _error = null;
        _page = 1;
      });
    } else {
      _page = 1;
    }

    final res = await _api.getQueues(
      status: _selectedStatus,
      startDate: _startDate,
      endDate: _endDate,
      page: 1,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => QueueItem.fromJson(j as Map<String, dynamic>))
              .toList() ?? [];
      final meta = res.data!['meta'] as Map<String, dynamic>?;

      setState(() {
        _items = list;
        _hasMore = meta != null && (_page < (meta['last_page'] ?? 1));
        _isLoading = false;
      });
    } else {
      if (!isRefresh) {
        setState(() {
          _error = res.message.isNotEmpty ? res.message : 'Failed to load queue';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadMore() async {
    if (_isLoadingMore || !_hasMore) return;
    setState(() => _isLoadingMore = true);
    _page++;

    final res = await _api.getQueues(
      status: _selectedStatus,
      startDate: _startDate,
      endDate: _endDate,
      page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => QueueItem.fromJson(j as Map<String, dynamic>))
              .toList() ?? [];
      final meta = res.data!['meta'] as Map<String, dynamic>?;

      // Deduplicate by queueId when appending pages
      final existingIds = _items.map((e) => e.queueId).toSet();
      final newItems = list.where((e) => !existingIds.contains(e.queueId)).toList();

      setState(() {
        _items.addAll(newItems);
        _hasMore = meta != null && (_page < (meta['last_page'] ?? 1));
        _isLoadingMore = false;
      });
    } else {
      _page--;
      setState(() => _isLoadingMore = false);
    }
  }

  Future<void> _startEncounter(QueueItem item) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    final res = await _api.startEncounter(
      queueId: item.queueId,
      patientId: item.patientId,
      reqEntryId: item.requestEntryId,
    );

    if (!mounted) return;
    Navigator.of(context).pop(); // dismiss loading

    if (res.success && res.data != null) {
      final encounterId = res.data!['encounter']?['id'] ??
          res.data!['encounter_id'] ??
          res.data!['id'];

      if (encounterId != null) {
        final result = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => ConsultationScreen(
              encounterId: encounterId as int,
              queueId: item.queueId,
              patientName: item.patientName,
            ),
          ),
        );
        if (result == true) {
          _loadStats();
          _loadQueue();
        }
      } else {
        showErrorSnackBar(context, 'Encounter created but ID missing');
      }
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to start encounter');
    }
  }

  void _onFilterChanged() {
    _loadStats();
    _loadQueue();
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    final mainTabBar = TabBar(
      controller: _mainTabController,
      indicatorColor: widget.embedded ? primary : Colors.white,
      indicatorWeight: 3,
      labelColor: widget.embedded ? primary : Colors.white,
      unselectedLabelColor: widget.embedded ? Colors.grey.shade600 : Colors.white70,
      tabs: const [
        Tab(icon: Icon(Icons.queue, size: 18), text: "Today's Queue"),
        Tab(icon: Icon(Icons.history, size: 18), text: 'History'),
      ],
    );

    final queueContent = Column(
      children: [
        _QueueStatsBar(stats: _stats, isLoading: _statsLoading),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _onFilterChanged();
          },
        ),
        _buildStatusPillBar(),
        const Divider(height: 1),
        Expanded(
          child: _buildQueueList(primary),
        ),
      ],
    );

    final body = TabBarView(
      controller: _mainTabController,
      children: [
        queueContent,
        QueueHistoryTabs(api: _api),
      ],
    );

    if (widget.embedded) {
      return Column(
        children: [
          Material(color: Theme.of(context).cardColor, child: mainTabBar),
          Expanded(child: body),
        ],
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Patient Queue'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh queue',
            onPressed: () {
              _loadStats();
              _loadQueue();
            },
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: mainTabBar,
        ),
      ),
      body: body,
    );
  }

  Widget _buildStatusPillBar() {
    final s = _stats ?? {};

    int getStat(String key) => s[key] ?? 0;

    final pills = [
      StatusPill(
        label: 'All',
        value: null,
        color: Colors.blueGrey,
        count: getStat('total'),
      ),
      StatusPill(
        label: 'Scheduled',
        value: QueueItem.scheduled,
        color: Colors.purple,
        count: getStat('scheduled'),
      ),
      StatusPill(
        label: 'Waiting',
        value: QueueItem.waiting,
        color: Colors.orange,
        count: getStat('waiting'),
      ),
      StatusPill(
        label: 'Vitals',
        value: QueueItem.vitalsPending,
        color: Colors.pink,
        count: getStat('vitals'),
      ),
      StatusPill(
        label: 'Ready',
        value: QueueItem.ready,
        color: Colors.cyan,
        count: getStat('ready'),
      ),
      StatusPill(
        label: 'In Consult',
        value: QueueItem.inConsultation,
        color: Colors.blue,
        count: getStat('in_consult'),
      ),
      StatusPill(
        label: 'Completed',
        value: QueueItem.completed,
        color: Colors.green,
        count: getStat('completed'),
      ),
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: StatusPillBar(
        pills: pills,
        selectedValue: _selectedStatus,
        onSelected: (val) {
          setState(() {
            _selectedStatus = val;
          });
          _loadQueue();
        },
      ),
    );
  }

  Widget _buildQueueList(Color primary) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: () {
            _loadStats();
            _loadQueue();
          },
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    if (_items.isEmpty) {
      return EmptyState(
        icon: Icons.inbox_outlined,
        title: 'No patients found',
        subtitle: 'Try changing the filters or date range',
        action: ElevatedButton.icon(
          onPressed: () {
            setState(() {
              _selectedStatus = null;
              _startDate = null;
              _endDate = null;
            });
            _onFilterChanged();
          },
          icon: const Icon(Icons.clear_all, size: 18),
          label: const Text('Clear Filters'),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async {
        await _loadStats();
        await _loadQueue(isRefresh: true);
      },
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        itemCount: _items.length + (_isLoadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == _items.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return _QueueCard(
            item: _items[index],
            primary: primary,
            onStart: () => _startEncounter(_items[index]),
          );
        },
      ),
    );
  }
}

class _QueueStatsBar extends StatelessWidget {
  final Map<String, dynamic>? stats;
  final bool isLoading;

  const _QueueStatsBar({required this.stats, required this.isLoading});

  @override
  Widget build(BuildContext context) {
    if (isLoading && stats == null) {
      return const SizedBox(
        height: 70,
        child: Center(
          child: SizedBox(width: 20, height: 20,
              child: CircularProgressIndicator(strokeWidth: 2)),
        ),
      );
    }
    if (stats == null) return const SizedBox.shrink();

    final s = stats!;

    // 7 Stats matching backend
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.05),
        border: Border(
          bottom: BorderSide(color: Colors.grey.shade200),
        ),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            _StatCell('Waiting', s['waiting'] ?? 0, Colors.orange),
            _StatDivider(),
            _StatCell('Vitals', s['vitals'] ?? 0, Colors.pink),
            _StatDivider(),
            _StatCell('Ready', s['ready'] ?? 0, Colors.cyan),
            _StatDivider(),
            _StatCell('In Consult', s['in_consult'] ?? 0, Colors.blue),
            _StatDivider(),
            _StatCell('Scheduled', s['scheduled'] ?? 0, Colors.purple),
            _StatDivider(),
            _StatCell('Completed', s['completed'] ?? 0, Colors.green),
            _StatDivider(),
            _StatCell('Total', s['total'] ?? 0, Colors.grey.shade800),
          ],
        ),
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  final String label;
  final num value;
  final Color color;

  const _StatCell(this.label, this.value, this.color);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$value',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              color: Colors.grey.shade700,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 28,
      child: VerticalDivider(
        width: 1,
        color: Colors.grey.shade300,
      ),
    );
  }
}

class _QueueCard extends StatelessWidget {
  final QueueItem item;
  final Color primary;
  final VoidCallback onStart;

  const _QueueCard({
    required this.item,
    required this.primary,
    required this.onStart,
  });

  @override
  Widget build(BuildContext context) {
    final canDeliver = item.canDeliver;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      color: Colors.white,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onStart,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CircleAvatar(
                      radius: 20,
                      backgroundColor: primary.withValues(alpha: 0.1),
                      child: Text(
                        item.patientName.isNotEmpty
                            ? item.patientName[0].toUpperCase()
                            : '?',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: primary,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  item.patientName,
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              StatusBadge.fromStatus(item.statusLabel),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            [
                              if (item.fileNo.isNotEmpty) item.fileNo,
                              if (item.gender.isNotEmpty)
                                '${item.genderIcon} ${item.gender}',
                              if (item.age.isNotEmpty) item.age,
                            ].join(' · '),
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.grey.shade600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Chips
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _InfoChip(
                      icon: Icons.medical_services_outlined,
                      label: item.clinicName,
                    ),
                    _InfoChip(
                      icon: Icons.shield_outlined,
                      label: item.hmoName,
                    ),
                    if (item.vitalsTaken)
                      _InfoChip(
                        icon: Icons.monitor_heart_outlined,
                        label: 'Vitals ✓',
                        color: Colors.green.shade700,
                      ),
                    if (item.priority != 'normal')
                      _InfoChip(
                        icon: item.priority == 'emergency'
                            ? Icons.emergency_outlined
                            : Icons.priority_high,
                        label: item.priority[0].toUpperCase() +
                            item.priority.substring(1),
                        color: item.priority == 'emergency'
                            ? Colors.red.shade700
                            : Colors.amber.shade800,
                      ),
                    if (item.source != 'walk-in')
                      _InfoChip(
                        icon: item.source == 'appointment'
                            ? Icons.calendar_today_outlined
                            : Icons.swap_horiz,
                        label: item.source == 'appointment'
                            ? 'Appointment'
                            : item.source[0].toUpperCase() +
                                item.source.substring(1),
                        color: Colors.indigo.shade600,
                      ),
                  ],
                ),

                if (!canDeliver) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.orange.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.warning_amber_rounded,
                            size: 16, color: Colors.orange.shade800),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            item.deliveryReason.isNotEmpty ? item.deliveryReason : 'HMO Service Delivery Warning',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.orange.shade900,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color? color;

  const _InfoChip({
    required this.icon,
    required this.label,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    if (label.isEmpty) return const SizedBox.shrink();

    final displayColor = color ?? Colors.grey.shade700;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: displayColor.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: displayColor.withValues(alpha: 0.2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: displayColor),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: displayColor,
            ),
          ),
        ],
      ),
    );
  }
}
