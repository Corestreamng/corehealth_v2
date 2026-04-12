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

String _isoDate(DateTime d) =>
    '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

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
  // Default: today → +30 days (matches web my_queues page)
  String? _startDate = _isoDate(DateTime.now());
  String? _endDate = _isoDate(DateTime.now().add(const Duration(days: 30)));

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

      // Deduplicate using composite key (scheduled appointments all have queueId=0,
      // so queueId alone would treat page-2+ appointment rows as duplicates).
      String dedupKey(QueueItem e) =>
          e.queueId > 0 ? 'q${e.queueId}' : 'a${e.appointmentId}';
      final existingKeys = _items.map(dedupKey).toSet();
      final newItems = list.where((e) => !existingKeys.contains(dedupKey(e))).toList();

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

  Future<void> _handleAppointmentAction(QueueItem item, String action) async {
    if (item.appointmentId == null) {
      showErrorSnackBar(context, 'No appointment linked to this queue entry');
      return;
    }
    final apptId = item.appointmentId!;

    switch (action) {
      case 'check_in':
        final confirm = await showConfirmDialog(
          context,
          title: 'Check In',
          message: 'Check in ${item.patientName}?',
        );
        if (confirm != true || !mounted) return;
        _showLoadingThenRun(() => _api.checkInAppointment(apptId), 'Checked in');
        break;

      case 'no_show':
        final confirm = await showConfirmDialog(
          context,
          title: 'Mark No-Show',
          message: 'Mark ${item.patientName} as no-show?',
        );
        if (confirm != true || !mounted) return;
        _showLoadingThenRun(() => _api.markNoShow(apptId), 'Marked as no-show');
        break;

      case 'cancel':
        final reason = await _showReasonDialog('Cancel Appointment', 'Reason for cancellation');
        if (reason == null || !mounted) return;
        _showLoadingThenRun(() => _api.cancelAppointment(apptId, reason: reason), 'Appointment cancelled');
        break;

      case 'reschedule':
        await _showRescheduleDialog(item);
        break;

      case 'reassign':
        await _showReassignDialog(item);
        break;
    }
  }

  Future<void> _showLoadingThenRun(Future<ApiResult> Function() apiCall, String successMsg) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    final res = await apiCall();
    if (!mounted) return;
    Navigator.of(context).pop();

    if (res.success) {
      showSuccessSnackBar(context, successMsg);
      _loadStats();
      _loadQueue();
    } else {
      showErrorSnackBar(context, res.message.isNotEmpty ? res.message : 'Action failed');
    }
  }

  Future<String?> _showReasonDialog(String title, String hint) async {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title, style: const TextStyle(fontSize: 16)),
        content: TextField(
          controller: controller,
          maxLength: 500,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: hint,
            border: const OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (text.isEmpty) return;
              Navigator.pop(ctx, text);
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
  }

  Future<void> _showRescheduleDialog(QueueItem item) async {
    if (item.appointmentId == null) return;
    DateTime? date;
    TimeOfDay? time;
    final reasonCtrl = TextEditingController();
    List<Map<String, dynamic>> availableSlots = [];
    bool loadingSlots = false;
    String? selectedSlotTime;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) {
          Future<void> fetchSlots(DateTime selectedDate) async {
            if (item.clinicId == null) return;
            setDialogState(() {
              loadingSlots = true;
              availableSlots = [];
              selectedSlotTime = null;
            });
            final dateStr = '${selectedDate.year}-${selectedDate.month.toString().padLeft(2, '0')}-${selectedDate.day.toString().padLeft(2, '0')}';
            final res = await _api.getAvailableSlots(
              clinicId: item.clinicId!,
              date: dateStr,
              doctorId: item.staffId,
            );
            if (!ctx.mounted) return;
            if (res.success && res.data != null) {
              final slots = (res.data!['slots'] as List?)
                  ?.map((s) => Map<String, dynamic>.from(s as Map))
                  .toList() ?? [];
              setDialogState(() {
                availableSlots = slots;
                loadingSlots = false;
              });
            } else {
              setDialogState(() => loadingSlots = false);
            }
          }

          return AlertDialog(
            title: const Text('Reschedule Appointment', style: TextStyle(fontSize: 16)),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Reschedule count warning
                  if (item.rescheduleCount > 0)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.orange.shade200),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.info_outline, size: 16, color: Colors.orange.shade800),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Reschedule #${item.rescheduleCount + 1}',
                              style: TextStyle(fontSize: 12, color: Colors.orange.shade900, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.calendar_today),
                    title: Text(date != null ? '${date!.year}-${date!.month.toString().padLeft(2, '0')}-${date!.day.toString().padLeft(2, '0')}' : 'Select Date'),
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: ctx,
                        initialDate: DateTime.now().add(const Duration(days: 1)),
                        firstDate: DateTime.now(),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                      );
                      if (picked != null) {
                        setDialogState(() {
                          date = picked;
                          time = null;
                          selectedSlotTime = null;
                        });
                        fetchSlots(picked);
                      }
                    },
                  ),
                  // Available slots or manual time picker
                  if (loadingSlots)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))),
                    ),
                  if (!loadingSlots && availableSlots.isNotEmpty) ...[
                    const Padding(
                      padding: EdgeInsets.only(top: 8, bottom: 4),
                      child: Text('Available Slots', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    ),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: availableSlots.map((slot) {
                        final slotTime = slot['time']?.toString() ?? '';
                        final available = slot['available'] == true;
                        final isSelected = selectedSlotTime == slotTime;
                        return ChoiceChip(
                          label: Text(slotTime, style: TextStyle(fontSize: 11, color: !available ? Colors.grey : null)),
                          selected: isSelected,
                          onSelected: available ? (sel) {
                            setDialogState(() {
                              selectedSlotTime = sel ? slotTime : null;
                              if (sel) {
                                final parts = slotTime.split(':');
                                if (parts.length >= 2) {
                                  time = TimeOfDay(hour: int.tryParse(parts[0]) ?? 0, minute: int.tryParse(parts[1]) ?? 0);
                                }
                              } else {
                                time = null;
                              }
                            });
                          } : null,
                          visualDensity: VisualDensity.compact,
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 4),
                    const Divider(),
                    const Text('Or pick a custom time:', style: TextStyle(fontSize: 11, color: Colors.grey)),
                  ],
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.access_time),
                    title: Text(time != null ? '${time!.hour.toString().padLeft(2, '0')}:${time!.minute.toString().padLeft(2, '0')}' : 'Select Time'),
                    onTap: () async {
                      final picked = await showTimePicker(context: ctx, initialTime: TimeOfDay.now());
                      if (picked != null) {
                        setDialogState(() {
                          time = picked;
                          selectedSlotTime = null;
                        });
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    decoration: const InputDecoration(
                      labelText: 'Reason',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: '', child: Text('-- Select reason --')),
                      DropdownMenuItem(value: 'Patient requested', child: Text('Patient requested')),
                      DropdownMenuItem(value: 'Doctor schedule change', child: Text('Doctor schedule change')),
                      DropdownMenuItem(value: 'Emergency rescheduling', child: Text('Emergency rescheduling')),
                      DropdownMenuItem(value: 'Clinic unavailable', child: Text('Clinic unavailable')),
                    ],
                    onChanged: (v) => reasonCtrl.text = v ?? '',
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              FilledButton(
                onPressed: date != null && time != null ? () => Navigator.pop(ctx, true) : null,
                child: const Text('Reschedule'),
              ),
            ],
          );
        },
      ),
    );

    if (confirmed != true || date == null || time == null || !mounted) return;

    final dateStr = '${date!.year}-${date!.month.toString().padLeft(2, '0')}-${date!.day.toString().padLeft(2, '0')}';
    final timeStr = '${time!.hour.toString().padLeft(2, '0')}:${time!.minute.toString().padLeft(2, '0')}';
    final reason = reasonCtrl.text.trim();

    _showLoadingThenRun(
      () => _api.rescheduleAppointment(
        item.appointmentId!,
        appointmentDate: dateStr,
        startTime: timeStr,
        reason: reason.isNotEmpty ? reason : null,
      ),
      'Appointment rescheduled',
    );
  }

  Future<void> _showReassignDialog(QueueItem item) async {
    if (item.appointmentId == null) return;

    // Filter doctors by clinic if clinicId is available (matches web behavior)
    final doctors = item.clinicId != null
        ? await _api.getDoctorsForClinic(item.clinicId!)
        : await _api.getDoctors();
    if (!mounted) return;

    int? selectedDoctorId;
    final reasonCtrl = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Reassign Doctor', style: TextStyle(fontSize: 16)),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  decoration: const InputDecoration(
                    labelText: 'Select Doctor',
                    border: OutlineInputBorder(),
                  ),
                  items: doctors.map((d) => DropdownMenuItem<int>(
                    value: d['id'] as int,
                    child: Text(d['name']?.toString() ?? 'Unknown'),
                  )).toList(),
                  onChanged: (v) => setDialogState(() => selectedDoctorId = v),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  decoration: const InputDecoration(
                    labelText: 'Reason',
                    border: OutlineInputBorder(),
                  ),
                  items: const [
                    DropdownMenuItem(value: '', child: Text('-- Select reason --')),
                    DropdownMenuItem(value: 'Doctor on leave', child: Text('Doctor on leave')),
                    DropdownMenuItem(value: 'Doctor unavailable', child: Text('Doctor unavailable')),
                    DropdownMenuItem(value: 'Patient request', child: Text('Patient request')),
                    DropdownMenuItem(value: 'Schedule conflict', child: Text('Schedule conflict')),
                  ],
                  onChanged: (v) => reasonCtrl.text = v ?? '',
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(
              onPressed: selectedDoctorId != null ? () => Navigator.pop(ctx, true) : null,
              child: const Text('Reassign'),
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || selectedDoctorId == null || !mounted) return;

    final reason = reasonCtrl.text.trim();
    _showLoadingThenRun(
      () => _api.reassignDoctor(
        item.appointmentId!,
        doctorId: selectedDoctorId!,
        reason: reason.isNotEmpty ? reason : null,
      ),
      'Doctor reassigned',
    );
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
          onReset: () {
            _startDate = null;
            _endDate = null;
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
      StatusPill(
        label: 'No-Show',
        value: QueueItem.noShow,
        color: Colors.deepOrange,
        count: getStat('no_show'),
      ),
      StatusPill(
        label: 'Cancelled',
        value: QueueItem.cancelled,
        color: Colors.red,
        count: getStat('cancelled'),
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
            onAction: (action) => _handleAppointmentAction(_items[index], action),
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
            _StatCell('Scheduled', s['scheduled'] ?? 0, Colors.purple,
              subDetail: '${s['scheduled_today'] ?? 0} today, ${s['scheduled_future'] ?? 0} upcoming'),
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
  final String? subDetail;

  const _StatCell(this.label, this.value, this.color, {this.subDetail});

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
          if (subDetail != null) ...[
            const SizedBox(height: 1),
            Text(
              subDetail!,
              style: TextStyle(
                fontSize: 8,
                color: Colors.grey.shade500,
                fontWeight: FontWeight.w400,
              ),
            ),
          ],
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
  final void Function(String action) onAction;

  const _QueueCard({
    required this.item,
    required this.primary,
    required this.onStart,
    required this.onAction,
  });

  // Matches web: encounter can only start for active statuses with delivery
  bool get _canStartEncounter =>
      (item.statusCode == QueueItem.waiting ||
          item.statusCode == QueueItem.vitalsPending ||
          item.statusCode == QueueItem.ready ||
          item.statusCode == QueueItem.inConsultation) &&
      item.queueId > 0 &&
      item.canDeliver;

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
          onTap: _canStartEncounter ? onStart : null,
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
                    // Appointment time
                    if (item.appointmentTime != null && item.appointmentTime!.isNotEmpty)
                      _InfoChip(
                        icon: Icons.access_time,
                        label: item.appointmentTime!,
                        color: Colors.teal.shade700,
                      ),
                  ],
                ),

                // Consultation elapsed timer for "In Consult" status
                if (item.statusCode == QueueItem.inConsultation &&
                    item.consultationStartedAt != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: _ConsultationTimer(item: item),
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
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.deliveryReason.isNotEmpty ? item.deliveryReason : 'HMO Service Delivery Warning',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.orange.shade900,
                                ),
                              ),
                              if (item.deliveryHint.isNotEmpty)
                                Padding(
                                  padding: const EdgeInsets.only(top: 2),
                                  child: Text(
                                    item.deliveryHint,
                                    style: TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: Colors.orange.shade700),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // Encounter button for active queue items (matches web "Open Encounter")
                if (_canStartEncounter) ...[
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: onStart,
                      icon: const Icon(Icons.medical_services_outlined, size: 16),
                      label: const Text('Open Encounter'),
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ],
                // Next-step hint (matches web context menu guidance)
                if (item.nextStep.isNotEmpty && !_canStartEncounter)
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Row(
                      children: [
                        Icon(Icons.lightbulb_outline, size: 14, color: Colors.blue.shade300),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            item.nextStep,
                            style: TextStyle(fontSize: 11, color: Colors.blue.shade400, fontStyle: FontStyle.italic),
                          ),
                        ),
                      ],
                    ),
                  ),
                // Appointment action buttons (visibility matches web context menu rules)
                if (item.appointmentId != null) ...[
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        // Check-In: Scheduled only
                        if (item.statusCode == QueueItem.scheduled)
                          _ActionBtn(
                            icon: Icons.login,
                            label: 'Check In',
                            color: Colors.green,
                            onTap: () => onAction('check_in'),
                          ),
                        // Reschedule: Scheduled, No-Show, or Cancelled (matches backend)
                        if (item.statusCode == QueueItem.scheduled ||
                            item.statusCode == QueueItem.noShow ||
                            item.statusCode == QueueItem.cancelled)
                          _ActionBtn(
                            icon: Icons.schedule,
                            label: 'Reschedule',
                            color: Colors.blue,
                            onTap: () => onAction('reschedule'),
                          ),
                        // Reassign: Scheduled only
                        if (item.statusCode == QueueItem.scheduled)
                          _ActionBtn(
                            icon: Icons.swap_horiz,
                            label: 'Reassign',
                            color: Colors.purple,
                            onTap: () => onAction('reassign'),
                          ),
                        // No-Show: Scheduled only
                        if (item.statusCode == QueueItem.scheduled)
                          _ActionBtn(
                            icon: Icons.person_off,
                            label: 'No-Show',
                            color: Colors.orange,
                            onTap: () => onAction('no_show'),
                          ),
                        // Cancel: Scheduled, Waiting, Vitals
                        if (item.statusCode == QueueItem.scheduled ||
                            item.statusCode == QueueItem.waiting ||
                            item.statusCode == QueueItem.vitalsPending)
                          _ActionBtn(
                            icon: Icons.cancel_outlined,
                            label: 'Cancel',
                            color: Colors.red,
                            onTap: () => onAction('cancel'),
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

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ActionBtn({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: color.withValues(alpha: 0.3)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: color),
              const SizedBox(width: 4),
              Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: color)),
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

/// Live ticking consultation elapsed timer — matches web initMiniTimers().
class _ConsultationTimer extends StatefulWidget {
  final QueueItem item;
  const _ConsultationTimer({required this.item});

  @override
  State<_ConsultationTimer> createState() => _ConsultationTimerState();
}

class _ConsultationTimerState extends State<_ConsultationTimer> {
  Timer? _timer;
  int _elapsedSeconds = 0;

  @override
  void initState() {
    super.initState();
    _calcElapsed();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) _calcElapsed();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _calcElapsed() {
    final startStr = widget.item.consultationStartedAt;
    if (startStr == null) return;
    final start = DateTime.tryParse(startStr);
    if (start == null) return;

    final now = DateTime.now().toUtc();
    var elapsed = now.difference(start.toUtc()).inSeconds - widget.item.consultationPausedSeconds;

    // If currently paused, subtract time since last paused
    if (widget.item.isPaused && widget.item.lastPausedAt != null) {
      final pausedAt = DateTime.tryParse(widget.item.lastPausedAt!);
      if (pausedAt != null) {
        elapsed -= now.difference(pausedAt.toUtc()).inSeconds;
      }
    }

    if (elapsed < 0) elapsed = 0;
    setState(() => _elapsedSeconds = elapsed);
  }

  String _format(int totalSec) {
    final h = totalSec ~/ 3600;
    final m = (totalSec % 3600) ~/ 60;
    final s = totalSec % 60;
    if (h > 0) {
      return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
    }
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final isPaused = widget.item.isPaused;
    final color = isPaused ? Colors.orange : Colors.blue;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isPaused ? Icons.pause_circle_outline : Icons.timer_outlined,
            size: 14,
            color: color,
          ),
          const SizedBox(width: 6),
          Text(
            _format(_elapsedSeconds),
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              fontFamily: 'monospace',
              color: color,
            ),
          ),
          if (isPaused) ...[
            const SizedBox(width: 4),
            Text('paused', style: TextStyle(fontSize: 10, color: color)),
          ],
        ],
      ),
    );
  }
}
