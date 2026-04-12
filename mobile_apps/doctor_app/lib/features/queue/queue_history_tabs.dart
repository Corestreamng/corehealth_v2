import 'package:flutter/material.dart';
import '../../core/api/encounter_api_service.dart';
import '../../core/models/queue_item.dart';
import '../../core/widgets/shared_widgets.dart';
import '../../core/widgets/date_range_filter.dart';
import '../../core/widgets/status_badge.dart';

String _isoDate(DateTime d) =>
    '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// History container with 5 sub-tabs matching web: Previous Encounters, My Admissions, Other Admissions, My Referrals, All Referrals.
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
    _tabController = TabController(length: 5, vsync: this);
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
              Tab(text: 'Other Admissions'),
              Tab(text: 'My Referrals'),
              Tab(text: 'All Referrals'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              _PreviousEncountersTab(api: widget.api),
              _MyAdmissionsTab(api: widget.api),
              _OtherAdmissionsTab(api: widget.api),
              _MyReferralsTab(api: widget.api),
              _AllReferralsTab(api: widget.api),
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
  String? _startDate = _isoDate(DateTime.now().subtract(const Duration(days: 7)));
  String? _endDate = _isoDate(DateTime.now());
  int? _clinicId;
  int? _hmoId;
  List<Map<String, dynamic>> _clinics = [];
  List<Map<String, dynamic>> _hmos = [];
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
    _loadDropdowns();
    _scrollController.addListener(_onScroll);
  }

  Future<void> _loadDropdowns() async {
    final clinics = await widget.api.getClinics();
    final hmos = await widget.api.getHmos();
    if (!mounted) return;
    setState(() { _clinics = clinics; _hmos = hmos; });
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
      startDate: _startDate, endDate: _endDate, clinicId: _clinicId, hmoId: _hmoId, page: 1,
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
      startDate: _startDate, endDate: _endDate, clinicId: _clinicId, hmoId: _hmoId, page: _page,
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
        // Clinic + HMO filter row (matches web)
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: Row(
            children: [
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.local_hospital,
                  hint: 'All Clinics',
                  value: _clinicId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All Clinics')),
                    ..._clinics.map((c) => DropdownMenuItem(value: c['id'] as int, child: Text(c['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _clinicId = v); _load(); },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.business,
                  hint: 'All HMOs',
                  value: _hmoId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All HMOs')),
                    ..._hmos.map((h) => DropdownMenuItem(value: h['id'] as int, child: Text(h['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _hmoId = v); _load(); },
                ),
              ),
            ],
          ),
        ),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
          onReset: () {
            setState(() { _startDate = null; _endDate = null; });
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
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
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
              // Extra info row: HMO + Doctor (matching web columns)
              if (item.hmoName.isNotEmpty || item.doctorName.isNotEmpty) ...[
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    if (item.hmoName.isNotEmpty && item.hmoName != 'N/A')
                      _miniChip(Icons.shield_outlined, item.hmoName),
                    if (item.doctorName.isNotEmpty && item.doctorName != 'N/A')
                      _miniChip(Icons.person_outlined, item.doctorName),
                    if (!item.canDeliver)
                      _miniChip(Icons.warning_amber_rounded, item.deliveryReason.isNotEmpty ? item.deliveryReason : 'Delivery Blocked', Colors.orange),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _miniChip(IconData icon, String label, [Color? color]) {
    final c = color ?? Colors.grey.shade600;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: c),
          const SizedBox(width: 3),
          Text(label, style: TextStyle(fontSize: 11, color: c)),
        ],
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
  String? _startDate = _isoDate(DateTime.now().subtract(const Duration(days: 30)));
  String? _endDate = _isoDate(DateTime.now());
  int? _hmoId;
  List<Map<String, dynamic>> _hmos = [];
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
    _loadDropdowns();
    _scrollController.addListener(_onScroll);
  }

  Future<void> _loadDropdowns() async {
    final hmos = await widget.api.getHmos();
    if (!mounted) return;
    setState(() => _hmos = hmos);
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
      startDate: _startDate, endDate: _endDate, hmoId: _hmoId, page: 1,
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
      startDate: _startDate, endDate: _endDate, hmoId: _hmoId, page: _page,
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
        // HMO filter (matches web)
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: _DropdownFilter<int?>(
            icon: Icons.business,
            hint: 'All HMOs',
            value: _hmoId,
            items: [
              const DropdownMenuItem(value: null, child: Text('All HMOs')),
              ..._hmos.map((h) => DropdownMenuItem(value: h['id'] as int, child: Text(h['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
            ],
            onChanged: (v) { setState(() => _hmoId = v); _load(); },
          ),
        ),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
          onReset: () {
            setState(() { _startDate = null; _endDate = null; });
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

// ═══════════════════════════════════════════════════════════════
//  Other Admissions Sub-tab (hospital-wide)
// ═══════════════════════════════════════════════════════════════

class _OtherAdmissionsTab extends StatefulWidget {
  final EncounterApiService api;
  const _OtherAdmissionsTab({required this.api});

  @override
  State<_OtherAdmissionsTab> createState() => _OtherAdmissionsTabState();
}

class _OtherAdmissionsTabState extends State<_OtherAdmissionsTab>
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  String? _startDate = _isoDate(DateTime.now().subtract(const Duration(days: 30)));
  String? _endDate = _isoDate(DateTime.now());
  int? _doctorId;
  int? _hmoId;
  List<Map<String, dynamic>> _doctors = [];
  List<Map<String, dynamic>> _hmos = [];
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
    _loadDropdowns();
    _scrollController.addListener(_onScroll);
  }

  Future<void> _loadDropdowns() async {
    final doctors = await widget.api.getDoctors();
    final hmos = await widget.api.getHmos();
    if (!mounted) return;
    setState(() { _doctors = doctors; _hmos = hmos; });
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

    final res = await widget.api.getAllAdmissions(
      startDate: _startDate, endDate: _endDate, doctorId: _doctorId, hmoId: _hmoId, page: 1,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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

    final res = await widget.api.getAllAdmissions(
      startDate: _startDate, endDate: _endDate, doctorId: _doctorId, hmoId: _hmoId, page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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
        // Doctor + HMO filter row (matches web)
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: Row(
            children: [
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.person,
                  hint: 'All Doctors',
                  value: _doctorId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All Doctors')),
                    ..._doctors.map((d) => DropdownMenuItem(value: d['id'] as int, child: Text(d['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _doctorId = v); _load(); },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.business,
                  hint: 'All HMOs',
                  value: _hmoId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All HMOs')),
                    ..._hmos.map((h) => DropdownMenuItem(value: h['id'] as int, child: Text(h['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _hmoId = v); _load(); },
                ),
              ),
            ],
          ),
        ),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
          onReset: () {
            setState(() { _startDate = null; _endDate = null; });
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
        subtitle: 'Hospital-wide admissions will appear here',
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
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  String? _startDate = _isoDate(DateTime.now().subtract(const Duration(days: 30)));
  String? _endDate = _isoDate(DateTime.now());
  String? _direction; // sent, received, or null for all
  String? _statusFilter = 'pending';
  String? _typeFilter; // internal, external, or null for all
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

    final res = await widget.api.getMyReferralsList(
      direction: _direction,
      status: _statusFilter,
      referralType: _typeFilter,
      startDate: _startDate,
      endDate: _endDate,
      page: 1,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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

    final res = await widget.api.getMyReferralsList(
      direction: _direction,
      status: _statusFilter,
      referralType: _typeFilter,
      startDate: _startDate,
      endDate: _endDate,
      page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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
        // Direction filter chips
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            children: [
              _FilterChip(
                label: 'All',
                selected: _direction == null,
                onTap: () { setState(() => _direction = null); _load(); },
              ),
              const SizedBox(width: 6),
              _FilterChip(
                label: 'Sent',
                selected: _direction == 'sent',
                onTap: () { setState(() => _direction = 'sent'); _load(); },
              ),
              const SizedBox(width: 6),
              _FilterChip(
                label: 'Received',
                selected: _direction == 'received',
                onTap: () { setState(() => _direction = 'received'); _load(); },
              ),
              const Spacer(),
              // Type dropdown
              PopupMenuButton<String?>(
                icon: Icon(Icons.category, size: 20, color: _typeFilter != null ? Theme.of(context).colorScheme.primary : Colors.grey),
                tooltip: 'Filter by type',
                onSelected: (val) { setState(() => _typeFilter = val); _load(); },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: null, child: Text('All Types')),
                  PopupMenuItem(value: 'internal', child: Text('Internal')),
                  PopupMenuItem(value: 'external', child: Text('External')),
                ],
              ),
              // Status dropdown
              PopupMenuButton<String?>(
                icon: Icon(Icons.filter_list, size: 20, color: _statusFilter != null ? Theme.of(context).colorScheme.primary : Colors.grey),
                tooltip: 'Filter by status',
                onSelected: (val) { setState(() => _statusFilter = val); _load(); },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: null, child: Text('All Statuses')),
                  PopupMenuItem(value: 'pending', child: Text('Pending')),
                  PopupMenuItem(value: 'booked', child: Text('Booked')),
                  PopupMenuItem(value: 'completed', child: Text('Completed')),
                  PopupMenuItem(value: 'declined', child: Text('Declined')),
                  PopupMenuItem(value: 'cancelled', child: Text('Cancelled')),
                  PopupMenuItem(value: 'referred_out', child: Text('Referred Out')),
                ],
              ),
            ],
          ),
        ),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
          onReset: () {
            setState(() { _startDate = null; _endDate = null; });
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
        icon: Icons.swap_horiz, title: 'No referrals',
        subtitle: 'Your referrals will appear here',
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
          return _ReferralCard(
            data: _items[index],
            api: widget.api,
            onRefresh: _load,
            onTap: () => _showReferralDetail(context, _items[index]),
          );
        },
      ),
    );
  }

  void _showReferralDetail(BuildContext context, Map<String, dynamic> data) {
    showReferralDetailSheet(context: context, data: data, api: widget.api, onRefresh: _load);
  }
}

// ═══════════════════════════════════════════════════════════════
//  All Referrals Sub-tab (hospital-wide)
// ═══════════════════════════════════════════════════════════════

class _AllReferralsTab extends StatefulWidget {
  final EncounterApiService api;
  const _AllReferralsTab({required this.api});

  @override
  State<_AllReferralsTab> createState() => _AllReferralsTabState();
}

class _AllReferralsTabState extends State<_AllReferralsTab>
    with AutomaticKeepAliveClientMixin {
  List<Map<String, dynamic>> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  String? _startDate = _isoDate(DateTime.now().subtract(const Duration(days: 30)));
  String? _endDate = _isoDate(DateTime.now());
  String? _statusFilter;
  String? _typeFilter;
  int? _clinicId;
  int? _doctorId;
  List<Map<String, dynamic>> _clinics = [];
  List<Map<String, dynamic>> _doctors = [];
  final _scrollController = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _load();
    _loadDropdowns();
    _scrollController.addListener(_onScroll);
  }

  Future<void> _loadDropdowns() async {
    final clinics = await widget.api.getClinics();
    final doctors = await widget.api.getDoctors();
    if (!mounted) return;
    setState(() { _clinics = clinics; _doctors = doctors; });
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

    final res = await widget.api.getAllReferralsList(
      status: _statusFilter,
      referralType: _typeFilter,
      clinicId: _clinicId,
      doctorId: _doctorId,
      startDate: _startDate,
      endDate: _endDate,
      page: 1,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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

    final res = await widget.api.getAllReferralsList(
      status: _statusFilter,
      referralType: _typeFilter,
      clinicId: _clinicId,
      doctorId: _doctorId,
      startDate: _startDate,
      endDate: _endDate,
      page: _page,
    );

    if (!mounted) return;
    if (res.success && res.data != null) {
      final rawList = res.data!['data'] as List? ?? [];
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
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            children: [
              const Text('All Referrals', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              const Spacer(),
              PopupMenuButton<String?>(
                icon: Icon(Icons.category, size: 20, color: _typeFilter != null ? Theme.of(context).colorScheme.primary : Colors.grey),
                tooltip: 'Filter by type',
                onSelected: (val) { setState(() => _typeFilter = val); _load(); },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: null, child: Text('All Types')),
                  PopupMenuItem(value: 'internal', child: Text('Internal')),
                  PopupMenuItem(value: 'external', child: Text('External')),
                ],
              ),
              PopupMenuButton<String?>(
                icon: Icon(Icons.filter_list, size: 20, color: _statusFilter != null ? Theme.of(context).colorScheme.primary : Colors.grey),
                tooltip: 'Filter by status',
                onSelected: (val) { setState(() => _statusFilter = val); _load(); },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: null, child: Text('All Statuses')),
                  PopupMenuItem(value: 'pending', child: Text('Pending')),
                  PopupMenuItem(value: 'booked', child: Text('Booked')),
                  PopupMenuItem(value: 'completed', child: Text('Completed')),
                  PopupMenuItem(value: 'declined', child: Text('Declined')),
                  PopupMenuItem(value: 'cancelled', child: Text('Cancelled')),
                  PopupMenuItem(value: 'referred_out', child: Text('Referred Out')),
                ],
              ),
            ],
          ),
        ),
        // Clinic + Doctor filter row (matches web)
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
          child: Row(
            children: [
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.local_hospital,
                  hint: 'All Clinics',
                  value: _clinicId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All Clinics')),
                    ..._clinics.map((c) => DropdownMenuItem(value: c['id'] as int, child: Text(c['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _clinicId = v); _load(); },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _DropdownFilter<int?>(
                  icon: Icons.person,
                  hint: 'All Doctors',
                  value: _doctorId,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All Doctors')),
                    ..._doctors.map((d) => DropdownMenuItem(value: d['id'] as int, child: Text(d['name']?.toString() ?? '', overflow: TextOverflow.ellipsis))),
                  ],
                  onChanged: (v) { setState(() => _doctorId = v); _load(); },
                ),
              ),
            ],
          ),
        ),
        DateRangeFilter(
          initialFrom: _startDate != null ? DateTime.parse(_startDate!) : null,
          initialTo: _endDate != null ? DateTime.parse(_endDate!) : null,
          onApply: (start, end) {
            _startDate = start?.toIso8601String().split('T').first;
            _endDate = end?.toIso8601String().split('T').first;
            _load();
          },
          onReset: () {
            setState(() { _startDate = null; _endDate = null; });
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
        icon: Icons.swap_horiz, title: 'No referrals',
        subtitle: 'Hospital-wide referrals will appear here',
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
          return _ReferralCard(
            data: _items[index],
            api: widget.api,
            onRefresh: _load,
            onTap: () => _showReferralDetail(context, _items[index]),
          );
        },
      ),
    );
  }

  void _showReferralDetail(BuildContext context, Map<String, dynamic> data) {
    showReferralDetailSheet(context: context, data: data, api: widget.api, onRefresh: _load);
  }
}

// ═══════════════════════════════════════════════════════════════
//  Referral Detail Bottom Sheet
// ═══════════════════════════════════════════════════════════════

void showReferralDetailSheet({
  required BuildContext context,
  required Map<String, dynamic> data,
  required EncounterApiService api,
  required VoidCallback onRefresh,
}) {
  final referralId = data['id'];

  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: (ctx) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.7,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (ctx, scrollController) => _ReferralDetailContent(
        data: data,
        referralId: referralId,
        api: api,
        onRefresh: onRefresh,
        scrollController: scrollController,
      ),
    ),
  );
}

class _ReferralDetailContent extends StatefulWidget {
  final Map<String, dynamic> data;
  final dynamic referralId;
  final EncounterApiService api;
  final VoidCallback onRefresh;
  final ScrollController scrollController;

  const _ReferralDetailContent({
    required this.data,
    required this.referralId,
    required this.api,
    required this.onRefresh,
    required this.scrollController,
  });

  @override
  State<_ReferralDetailContent> createState() => _ReferralDetailContentState();
}

class _ReferralDetailContentState extends State<_ReferralDetailContent> {
  Map<String, dynamic>? _detail;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    if (widget.referralId == null) {
      setState(() => _loading = false);
      return;
    }
    final res = await widget.api.getReferralDetail(widget.referralId as int);
    if (!mounted) return;
    setState(() {
      _detail = res.success ? res.data : null;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final d = _detail?['referral'] ?? widget.data;
    final primary = Theme.of(context).colorScheme.primary;

    return ListView(
      controller: widget.scrollController,
      padding: const EdgeInsets.all(16),
      children: [
        // Drag handle
        Center(
          child: Container(
            width: 40, height: 4,
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),

        // Title
        Row(
          children: [
            Icon(Icons.swap_horiz, color: primary),
            const SizedBox(width: 8),
            const Text('Referral Details', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
          ],
        ),
        const SizedBox(height: 16),

        if (_loading) ...[
          const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator())),
        ] else ...[
          // Patient info card
          _sectionCard([
            _detailRow('Patient', d['patient_name']?.toString() ?? 'Unknown'),
            _detailRow('File No', d['patient_file_no']?.toString() ?? d['file_no']?.toString() ?? ''),
            _detailRow('Date', d['created_at']?.toString() ?? ''),
          ]),
          const SizedBox(height: 12),

          // Status badges
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _statusBadge(d['urgency']?.toString() ?? 'routine', _urgencyColor(d['urgency']?.toString())),
              _statusBadge(d['status']?.toString() ?? '', _statusColor(d['status']?.toString())),
              _statusBadge(
                d['referral_type'] == 'internal' ? 'Internal' : 'External',
                d['referral_type'] == 'internal' ? Colors.blue : Colors.teal,
              ),
            ],
          ),
          const SizedBox(height: 16),

          // From / To
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: _sectionCard([
                Text('Referred From', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.grey.shade500)),
                const SizedBox(height: 4),
                Text(d['referring_doctor']?.toString() ?? 'N/A', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                Text(d['referring_clinic']?.toString() ?? '', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
              ])),
              const SizedBox(width: 12),
              Expanded(child: _sectionCard([
                Text('Referred To', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.grey.shade500)),
                const SizedBox(height: 4),
                Text(d['target_doctor']?.toString() ?? d['target_clinic']?.toString() ?? 'N/A', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                Text(d['target_clinic']?.toString() ?? '', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
              ])),
            ],
          ),
          const SizedBox(height: 16),

          // Clinical info
          _sectionCard([
            if ((d['provisional_diagnosis']?.toString() ?? '').isNotEmpty) ...[
              _detailRow('Diagnosis', d['provisional_diagnosis'].toString()),
            ],
            if ((d['clinical_summary']?.toString() ?? '').isNotEmpty) ...[
              _detailRow('Clinical Summary', d['clinical_summary'].toString()),
            ],
            if ((d['reason']?.toString() ?? '').isNotEmpty) ...[
              _detailRow('Reason', d['reason'].toString()),
            ],
            if ((d['response_notes']?.toString() ?? '').isNotEmpty) ...[
              _detailRow('Response Notes', d['response_notes'].toString()),
            ],
          ]),
          const SizedBox(height: 20),

          // Action buttons
          if (d['can_accept'] == true || d['status']?.toString() == 'pending') ...[
            Row(
              children: [
                if (d['status']?.toString() == 'pending')
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _decline(context),
                      icon: const Icon(Icons.close, size: 16),
                      label: const Text('Decline'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: const BorderSide(color: Colors.red),
                      ),
                    ),
                  ),
                if (d['can_accept'] == true) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: () => _accept(context),
                      icon: const Icon(Icons.check, size: 16),
                      label: const Text('Accept'),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ],
      ],
    );
  }

  Future<void> _accept(BuildContext context) async {
    final res = await widget.api.acceptReferral(widget.referralId as int);
    if (!context.mounted) return;
    Navigator.pop(context);
    if (res.success) {
      showSuccessSnackBar(context, 'Referral accepted');
      widget.onRefresh();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to accept');
    }
  }

  Future<void> _decline(BuildContext context) async {
    final reasonCtrl = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Decline Referral', style: TextStyle(fontSize: 16)),
        content: TextField(
          controller: reasonCtrl,
          maxLength: 500,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Reason for declining',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final text = reasonCtrl.text.trim();
              if (text.isEmpty) return;
              Navigator.pop(ctx, text);
            },
            child: const Text('Decline'),
          ),
        ],
      ),
    );

    if (reason == null || !context.mounted) return;
    final res = await widget.api.declineReferral(widget.referralId as int, reason: reason);
    if (!context.mounted) return;
    Navigator.pop(context);
    if (res.success) {
      showSuccessSnackBar(context, 'Referral declined');
      widget.onRefresh();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to decline');
    }
  }

  Widget _sectionCard(List<Widget> children) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children,
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    if (value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.grey.shade500)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }

  Widget _statusBadge(String label, Color color) {
    if (label.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(
        label[0].toUpperCase() + label.substring(1),
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color),
      ),
    );
  }

  Color _statusColor(String? status) {
    switch (status?.toLowerCase()) {
      case 'pending': return Colors.orange;
      case 'booked': return Colors.blue;
      case 'completed': return Colors.green;
      case 'declined': return Colors.red;
      case 'cancelled': return Colors.grey;
      case 'referred_out': return Colors.teal;
      default: return Colors.grey;
    }
  }

  Color _urgencyColor(String? urgency) {
    switch (urgency?.toLowerCase()) {
      case 'emergency': return Colors.red;
      case 'urgent': return Colors.orange;
      default: return Colors.grey;
    }
  }
}

// ═══════════════════════════════════════════════════════════════
//  Shared Widgets
// ═══════════════════════════════════════════════════════════════

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? primary.withValues(alpha: 0.1) : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: selected ? primary : Colors.grey.shade300),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
            color: selected ? primary : Colors.grey.shade700,
          ),
        ),
      ),
    );
  }
}

/// Compact dropdown filter for history tab filter rows.
class _DropdownFilter<T> extends StatelessWidget {
  final IconData icon;
  final String hint;
  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;

  const _DropdownFilter({
    required this.icon,
    required this.hint,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(8),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          value: value,
          isExpanded: true,
          isDense: true,
          icon: Icon(icon, size: 16, color: Colors.grey.shade600),
          style: TextStyle(fontSize: 12, color: Colors.grey.shade800),
          items: items,
          onChanged: onChanged,
        ),
      ),
    );
  }
}

class _ReferralCard extends StatelessWidget {
  final Map<String, dynamic> data;
  final EncounterApiService api;
  final VoidCallback onRefresh;
  final VoidCallback? onTap;

  const _ReferralCard({required this.data, required this.api, required this.onRefresh, this.onTap});

  @override
  Widget build(BuildContext context) {
    final type = data['referral_type']?.toString() ?? '';
    final status = data['status']?.toString() ?? '';
    final urgency = data['urgency']?.toString() ?? '';
    final targetClinic = data['target_clinic']?.toString() ?? '';
    final targetDoctor = data['target_doctor']?.toString() ?? '';
    final patientName = data['patient_name']?.toString() ?? 'Unknown';
    final patientFileNo = data['patient_file_no']?.toString() ?? '';
    final referringDoctor = data['referring_doctor']?.toString() ?? '';
    final referringClinic = data['referring_clinic']?.toString() ?? '';
    final reason = data['reason']?.toString() ?? '';
    final createdAt = data['created_at']?.toString() ?? '';
    final canAccept = data['can_accept'] == true;
    final referralId = data['id'];

    Color statusColor;
    switch (status.toLowerCase()) {
      case 'pending': statusColor = Colors.orange; break;
      case 'booked': statusColor = Colors.blue; break;
      case 'completed': statusColor = Colors.green; break;
      case 'declined': statusColor = Colors.red; break;
      case 'cancelled': statusColor = Colors.grey; break;
      default: statusColor = Colors.grey;
    }

    Color urgencyColor;
    switch (urgency.toLowerCase()) {
      case 'emergency': urgencyColor = Colors.red; break;
      case 'urgent': urgencyColor = Colors.orange; break;
      default: urgencyColor = Colors.grey;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
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
                        Text(patientName,
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                        if (patientFileNo.isNotEmpty)
                          Text(patientFileNo,
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
                      status[0].toUpperCase() + status.substring(1),
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: statusColor),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              // Info row
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: [
                  _chip(Icons.arrow_upward, 'From: $referringDoctor${referringClinic.isNotEmpty ? ' ($referringClinic)' : ''}', Colors.grey),
                  _chip(Icons.arrow_downward, 'To: $targetClinic${targetDoctor.isNotEmpty ? ' ($targetDoctor)' : ''}', Colors.grey),
                  if (urgency.isNotEmpty)
                    _chip(Icons.priority_high, urgency[0].toUpperCase() + urgency.substring(1), urgencyColor),
                  _chip(
                    type == 'internal' ? Icons.business : Icons.public,
                    type == 'internal' ? 'Internal' : 'External',
                    Colors.grey,
                  ),
                  if (createdAt.isNotEmpty) _chip(Icons.calendar_today, createdAt, Colors.grey),
                ],
              ),
              if (reason.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(reason, style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                    maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
              if (canAccept) ...[
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: () => _decline(context, referralId),
                      child: Text('Decline', style: TextStyle(color: Colors.red.shade700, fontSize: 12)),
                    ),
                    const SizedBox(width: 8),
                    FilledButton.icon(
                      onPressed: () => _accept(context, referralId),
                      icon: const Icon(Icons.check, size: 14),
                      label: const Text('Accept', style: TextStyle(fontSize: 12)),
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
      ),
    );
  }

  Future<void> _accept(BuildContext context, int referralId) async {
    final res = await api.acceptReferral(referralId);
    if (!context.mounted) return;
    if (res.success) {
      showSuccessSnackBar(context, 'Referral accepted');
      onRefresh();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to accept');
    }
  }

  Future<void> _decline(BuildContext context, int referralId) async {
    final reasonCtrl = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Decline Referral', style: TextStyle(fontSize: 16)),
        content: TextField(
          controller: reasonCtrl,
          maxLength: 500,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Reason for declining',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final text = reasonCtrl.text.trim();
              if (text.isEmpty) return;
              Navigator.pop(ctx, text);
            },
            child: const Text('Decline'),
          ),
        ],
      ),
    );

    if (reason == null || !context.mounted) return;
    final res = await api.declineReferral(referralId, reason: reason);
    if (!context.mounted) return;
    if (res.success) {
      showSuccessSnackBar(context, 'Referral declined');
      onRefresh();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Failed to decline');
    }
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
