import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/models/queue_item.dart';
import '../../core/widgets/shared_widgets.dart';
import '../../core/widgets/date_range_filter.dart';
import '../../core/widgets/status_badge.dart';

/// History container with 3 sub-tabs: Previous Encounters, My Admissions, My Referrals.
class QueueHistoryTabs extends StatefulWidget {
  final EncounterApiService api;

  const QueueHistoryTabs({super.key, required this.api});

  @override
  State<QueueHistoryTabs> createState() => _QueueHistoryTabsState();
}

class _QueueHistoryTabsState extends State<QueueHistoryTabs>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Material(
          color: Theme.of(context).cardColor,
          child: TabBar(
            controller: _tabController,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            unselectedLabelStyle: const TextStyle(fontSize: 13),
            tabs: const [
              Tab(text: 'Previous Encounters'),
              Tab(text: 'My Admissions'),
              Tab(text: 'My Referrals'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              _PreviousEncountersTab(api: widget.api),
              _MyAdmissionsTab(api: widget.api),
              _MyReferralsTab(api: widget.api),
            ],
          ),
        ),
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  Previous Encounters Sub-tab
// ═══════════════════════════════════════════════════════════════

class _PreviousEncountersTab extends StatefulWidget {
  final EncounterApiService api;
  const _PreviousEncountersTab({required this.api});

  @override
  State<_PreviousEncountersTab> createState() => _PreviousEncountersTabState();
}

class _PreviousEncountersTabState extends State<_PreviousEncountersTab>
    with AutomaticKeepAliveClientMixin {
  List<QueueItem> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  String? _startDate;
  String? _endDate;
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
        !_isLoadingMore && _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load() async {
    setState(() { _isLoading = true; _error = null; _page = 1; });

    final res = await widget.api.getPreviousEncounters(
      startDate: _startDate, endDate: _endDate, page: 1,
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

    final res = await widget.api.getPreviousEncounters(
      startDate: _startDate, endDate: _endDate, page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = (res.data!['data'] as List?)
          ?.map((j) => QueueItem.fromJson(j as Map<String, dynamic>))
          .toList() ?? [];
      final meta = res.data!['meta'] as Map<String, dynamic>?;
      setState(() {
        _items.addAll(list);
        _hasMore = meta != null && (_page < (meta['last_page'] ?? 1));
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
    return Column(
      children: [
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
        ),
        Expanded(child: _buildList()),
      ],
    );
  }

  Widget _buildList() {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline, title: 'Error', subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _load, icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }
    if (_items.isEmpty) {
      return const EmptyState(
        icon: Icons.history, title: 'No previous encounters',
        subtitle: 'Completed encounters will appear here',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
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
          return _EncounterHistoryCard(item: _items[index]);
        },
      ),
    );
  }
}

class _EncounterHistoryCard extends StatelessWidget {
  final QueueItem item;
  const _EncounterHistoryCard({required this.item});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: Colors.grey.shade200,
                child: Text(
                  item.patientName.isNotEmpty ? item.patientName[0].toUpperCase() : '?',
                  style: TextStyle(fontWeight: FontWeight.w700, color: Colors.grey.shade700),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.patientName,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (item.fileNo.isNotEmpty) item.fileNo,
                        if (item.clinicName.isNotEmpty) item.clinicName,
                        if (item.createdAt.isNotEmpty) item.createdAt,
                      ].join(' · '),
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
              StatusBadge.fromStatus(item.statusLabel),
            ],
          ),
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  My Admissions Sub-tab
// ═══════════════════════════════════════════════════════════════

class _MyAdmissionsTab extends StatefulWidget {
  final EncounterApiService api;
  const _MyAdmissionsTab({required this.api});

  @override
  State<_MyAdmissionsTab> createState() => _MyAdmissionsTabState();
}

class _MyAdmissionsTabState extends State<_MyAdmissionsTab>
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  String? _startDate;
  String? _endDate;
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
        !_isLoadingMore && _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load() async {
    setState(() { _isLoading = true; _error = null; _page = 1; });

    final res = await widget.api.getMyAdmissions(
      startDate: _startDate, endDate: _endDate, page: 1,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? res.data!['admissions'] as List? ?? [];
      final list = rawList.cast<Map<String, dynamic>>();
      final meta = res.data!['meta'] as Map<String, dynamic>?;
      setState(() {
        _items = list;
        _hasMore = meta != null && (_page < (meta['last_page'] ?? 1));
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

    final res = await widget.api.getMyAdmissions(
      startDate: _startDate, endDate: _endDate, page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? res.data!['admissions'] as List? ?? [];
      final list = rawList.cast<Map<String, dynamic>>();
      final meta = res.data!['meta'] as Map<String, dynamic>?;
      setState(() {
        _items.addAll(list);
        _hasMore = meta != null && (_page < (meta['last_page'] ?? 1));
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
    return Column(
      children: [
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
        ),
        Expanded(child: _buildList()),
      ],
    );
  }

  Widget _buildList() {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline, title: 'Error', subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _load, icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }
    if (_items.isEmpty) {
      return const EmptyState(
        icon: Icons.local_hospital_outlined, title: 'No admissions',
        subtitle: 'Your patient admissions will appear here',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
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
          return _AdmissionCard(data: _items[index]);
        },
      ),
    );
  }
}

class _AdmissionCard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _AdmissionCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final name = data['patient_name'] ?? 'Unknown';
    final fileNo = data['file_no'] ?? '';
    final ward = data['ward_name'] ?? '';
    final bed = data['bed_name'] ?? '';
    final status = data['admission_status'] ?? '';
    final admittedAt = data['admitted_at'] ?? '';
    final reason = data['admission_reason'] ?? '';

    Color statusColor;
    switch (status.toString().toLowerCase()) {
      case 'admitted': statusColor = Colors.blue; break;
      case 'discharged': statusColor = Colors.green; break;
      case 'transferred': statusColor = Colors.orange; break;
      default: statusColor = Colors.grey;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: Colors.blue.shade50,
                    child: Icon(Icons.local_hospital, size: 18, color: Colors.blue.shade700),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name.toString(),
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                        if (fileNo.toString().isNotEmpty)
                          Text(fileNo.toString(),
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      status.toString(),
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: statusColor),
                    ),
                  ),
                ],
              ),
              if (ward.toString().isNotEmpty || bed.toString().isNotEmpty || reason.toString().isNotEmpty) ...[
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    if (ward.toString().isNotEmpty) _chip(Icons.domain, ward.toString()),
                    if (bed.toString().isNotEmpty) _chip(Icons.bed, bed.toString()),
                    if (admittedAt.toString().isNotEmpty) _chip(Icons.calendar_today, admittedAt.toString()),
                  ],
                ),
              ],
              if (reason.toString().isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(reason.toString(),
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                    maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _chip(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: Colors.grey.shade600),
          const SizedBox(width: 3),
          Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  My Referrals Sub-tab
// ═══════════════════════════════════════════════════════════════

class _MyReferralsTab extends StatefulWidget {
  final EncounterApiService api;
  const _MyReferralsTab({required this.api});

  @override
  State<_MyReferralsTab> createState() => _MyReferralsTabState();
}

class _MyReferralsTabState extends State<_MyReferralsTab>
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  String? _error;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _isLoading = true; _error = null; });

    // Uses the incoming referrals endpoint (doctor's referrals sent to them)
    // We use encounterId=0 as a sentinel for "all my referrals"
    // Backend actually uses getIncomingReferrals scoped to encounter.
    // For now, show a placeholder until a doctor-scoped endpoint exists.
    // Alternatively, we could re-use the admissions endpoint context.

    // The best available endpoint is patient referrals scoped to encounter.
    // Since there's no doctor-scoped "all referrals" endpoint, we show
    // a message guiding the user to check referrals within consultations.

    setState(() {
      _items = [];
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (_isLoading) return const Center(child: CircularProgressIndicator());

    if (_error != null) {
      return EmptyState(
        icon: Icons.error_outline, title: 'Error', subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: _load, icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    if (_items.isEmpty) {
      return const EmptyState(
        icon: Icons.swap_horiz,
        title: 'Referrals',
        subtitle: 'Referrals are available within each patient\'s consultation.\n'
            'Open a patient encounter to view and manage referrals.',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        itemCount: _items.length,
        itemBuilder: (context, index) => _ReferralCard(data: _items[index]),
      ),
    );
  }
}

class _ReferralCard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ReferralCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final type = data['type'] ?? '';
    final status = data['status'] ?? '';
    final urgency = data['urgency'] ?? '';
    final targetClinic = data['target_clinic'] ?? '';
    final targetDoctor = data['target_doctor'] ?? '';
    final reason = data['reason'] ?? '';
    final createdAt = data['created_at'] ?? '';

    Color statusColor;
    switch (status.toString().toLowerCase()) {
      case 'pending': statusColor = Colors.orange; break;
      case 'accepted': statusColor = Colors.green; break;
      case 'declined': statusColor = Colors.red; break;
      default: statusColor = Colors.grey;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: Colors.purple.shade50,
                    child: Icon(Icons.swap_horiz, size: 18, color: Colors.purple.shade700),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(type.toString(),
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                        if (targetClinic.toString().isNotEmpty)
                          Text('To: ${targetClinic.toString()}',
                              style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      status.toString(),
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: statusColor),
                    ),
                  ),
                ],
              ),
              if (urgency.toString().isNotEmpty || targetDoctor.toString().isNotEmpty) ...[
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: [
                    if (urgency.toString().isNotEmpty)
                      _chip(Icons.priority_high, urgency.toString(),
                          urgency.toString().toLowerCase() == 'urgent' ? Colors.red : Colors.grey),
                    if (targetDoctor.toString().isNotEmpty)
                      _chip(Icons.person, targetDoctor.toString(), Colors.grey),
                    if (createdAt.toString().isNotEmpty)
                      _chip(Icons.calendar_today, createdAt.toString(), Colors.grey),
                  ],
                ),
              ],
              if (reason.toString().isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(reason.toString(),
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                    maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _chip(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: color),
          const SizedBox(width: 3),
          Text(label, style: TextStyle(fontSize: 11, color: color)),
        ],
      ),
    );
  }
}
