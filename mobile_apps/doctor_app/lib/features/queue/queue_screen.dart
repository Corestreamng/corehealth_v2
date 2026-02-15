import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/models/queue_item.dart';
import '../../core/widgets/status_badge.dart';
import '../../core/widgets/shared_widgets.dart';
import '../../core/storage/local_storage.dart';
import '../consultation/consultation_screen.dart';

/// Doctor Queue — 3-tab screen showing New, Continuing, and Previous patients.
class QueueScreen extends StatefulWidget {
  /// When true, omits the AppBar (used when embedded inside HomeScreen's
  /// bottom-nav IndexedStack).
  final bool embedded;

  const QueueScreen({super.key, this.embedded = false});

  @override
  State<QueueScreen> createState() => _QueueScreenState();
}

class _QueueScreenState extends State<QueueScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  late EncounterApiService _api;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    final baseUrl = LocalStorage.baseUrl ?? '';
    _api = EncounterApiService(baseUrl);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    final tabBar = TabBar(
      controller: _tabController,
      indicatorColor: widget.embedded ? primary : Colors.white,
      indicatorWeight: 3,
      labelColor: widget.embedded ? primary : Colors.white,
      unselectedLabelColor:
          widget.embedded ? Colors.grey.shade600 : Colors.white70,
      tabs: const [
        Tab(icon: Icon(Icons.person_add_alt_1, size: 18), text: 'New'),
        Tab(icon: Icon(Icons.loop, size: 18), text: 'Continuing'),
        Tab(icon: Icon(Icons.history, size: 18), text: 'Previous'),
      ],
    );

    final body = TabBarView(
      controller: _tabController,
      children: [
        _QueueTab(api: _api, status: 1, primary: primary),
        _QueueTab(api: _api, status: 2, primary: primary),
        _QueueTab(api: _api, status: 3, primary: primary),
      ],
    );

    if (widget.embedded) {
      return Column(
        children: [
          Material(color: Theme.of(context).cardColor, child: tabBar),
          Expanded(child: body),
        ],
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Patient Queue'),
        bottom: tabBar,
      ),
      body: body,
    );
  }
}

/// Individual queue tab with pull-to-refresh and pagination.
class _QueueTab extends StatefulWidget {
  final EncounterApiService api;
  final int status;
  final Color primary;

  const _QueueTab({
    required this.api,
    required this.status,
    required this.primary,
  });

  @override
  State<_QueueTab> createState() => _QueueTabState();
}

class _QueueTabState extends State<_QueueTab>
    with AutomaticKeepAliveClientMixin {
  List<QueueItem> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _loadQueue();
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

  Future<void> _loadQueue() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _page = 1;
    });

    final res = await widget.api.getQueues(status: widget.status, page: 1);

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => QueueItem.fromJson(j as Map<String, dynamic>))
              .toList() ??
          [];
      setState(() {
        _items = list;
        _hasMore = res.data!['next_page_url'] != null;
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message.isNotEmpty
            ? res.message
            : 'Failed to load queue';
        _isLoading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_isLoadingMore || !_hasMore) return;
    setState(() => _isLoadingMore = true);
    _page++;

    final res =
        await widget.api.getQueues(status: widget.status, page: _page);

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
              ?.map((j) => QueueItem.fromJson(j as Map<String, dynamic>))
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

  Future<void> _startEncounter(QueueItem item) async {
    // Show loading
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    final res = await widget.api.startEncounter(queueId: item.queueId);

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
        // Refresh queue when returning from consultation
        if (result == true) _loadQueue();
      } else {
        showErrorSnackBar(context, 'Encounter created but ID missing');
      }
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to start encounter');
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
          onPressed: _loadQueue,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    if (_items.isEmpty) {
      final labels = {1: 'new', 2: 'continuing', 3: 'previous'};
      return EmptyState(
        icon: Icons.inbox_outlined,
        title: 'No ${labels[widget.status]} patients',
        subtitle: 'Pull down to refresh',
        action: ElevatedButton.icon(
          onPressed: _loadQueue,
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Refresh'),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadQueue,
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
            primary: widget.primary,
            onStart: () => _startEncounter(_items[index]),
          );
        },
      ),
    );
  }
}

/// Individual patient queue card.
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
    final canStart = item.canDeliver;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: canStart ? onStart : null,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ── Header row: name + status ──
              Row(
                children: [
                  // Avatar
                  CircleAvatar(
                    radius: 22,
                    backgroundColor: primary.withValues(alpha: 0.12),
                    child: Text(
                      item.patientName.isNotEmpty
                          ? item.patientName[0].toUpperCase()
                          : '?',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: primary,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  // Name + details
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.patientName,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [
                            if (item.fileNo.isNotEmpty) item.fileNo,
                            if (item.gender.isNotEmpty)
                              '${item.genderIcon} ${item.gender}',
                            if (item.age.isNotEmpty) item.age,
                          ].join(' · '),
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey.shade600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  StatusBadge.fromStatus(item.status),
                ],
              ),
              const SizedBox(height: 10),
              // ── Info chips ──
              Wrap(
                spacing: 8,
                runSpacing: 4,
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
                ],
              ),

              // ── HMO delivery warning ──
              if (!item.canDeliver) ...[
                const SizedBox(height: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.orange.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.warning_amber_rounded,
                          size: 16, color: Colors.orange.shade800),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          item.deliveryReason,
                          style: TextStyle(
                            fontSize: 11,
                            color: Colors.orange.shade900,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // ── Action button ──
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: canStart ? onStart : null,
                  icon: Icon(
                    item.statusCode == 2
                        ? Icons.play_arrow_rounded
                        : Icons.medical_services_rounded,
                    size: 18,
                  ),
                  label: Text(
                    item.statusCode == 2
                        ? 'Continue Encounter'
                        : item.statusCode == 3
                            ? 'View Encounter'
                            : 'Start Encounter',
                  ),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
            ],
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
    final c = color ?? Colors.grey.shade600;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 13, color: c),
        const SizedBox(width: 3),
        Text(
          label,
          style: TextStyle(fontSize: 11, color: c),
        ),
      ],
    );
  }
}
