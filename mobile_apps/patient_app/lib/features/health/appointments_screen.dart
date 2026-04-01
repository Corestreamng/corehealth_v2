import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';

class AppointmentsScreen extends StatefulWidget {
  const AppointmentsScreen({super.key});

  @override
  State<AppointmentsScreen> createState() => _AppointmentsScreenState();
}

class _AppointmentsScreenState extends State<AppointmentsScreen> {
  late PatientApiService _api;
  final List<PatientAppointment> _appointments = [];
  bool _isLoading = true;
  String? _error;
  String _filter = 'upcoming'; // upcoming | past | all
  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
    _load();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scroll.hasClients) return;
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200 &&
        !_loadingMore &&
        _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
      _page = 1;
      _appointments.clear();
    });

    final res = await _api.getAppointments(page: 1, filter: _filter);
    if (!mounted) return;

    if (res.success && res.data is List) {
      final items = (res.data as List)
          .whereType<Map<String, dynamic>>()
          .map(PatientAppointment.fromJson)
          .toList();
      setState(() {
        _appointments.addAll(items);
        _hasMore = items.length >= 20;
        _isLoading = false;
      });
    } else {
      setState(() {
        _error = res.message;
        _isLoading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    final nextPage = _page + 1;

    final res =
        await _api.getAppointments(page: nextPage, filter: _filter);
    if (!mounted) return;

    if (res.success && res.data is List) {
      final items = (res.data as List)
          .whereType<Map<String, dynamic>>()
          .map(PatientAppointment.fromJson)
          .toList();
      setState(() {
        _page = nextPage;
        _appointments.addAll(items);
        _hasMore = items.length >= 20;
        _loadingMore = false;
      });
    } else {
      setState(() => _loadingMore = false);
    }
  }

  void _setFilter(String f) {
    if (f == _filter) return;
    _filter = f;
    if (_scroll.hasClients) _scroll.jumpTo(0);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Appointments')),
      body: Column(
        children: [
          // Filter chips
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                _filterChip('Upcoming', 'upcoming'),
                const SizedBox(width: 8),
                _filterChip('Past', 'past'),
                const SizedBox(width: 8),
                _filterChip('All', 'all'),
              ],
            ),
          ),

          Expanded(child: _buildContent()),
        ],
      ),
    );
  }

  Widget _filterChip(String label, String value) {
    final selected = _filter == value;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => _setFilter(value),
    );
  }

  Widget _buildContent() {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
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
    if (_appointments.isEmpty) {
      return EmptyState(
        icon: Icons.calendar_today,
        title: 'No Appointments',
        subtitle: _filter == 'upcoming'
            ? 'No upcoming appointments scheduled'
            : 'No appointments found',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        controller: _scroll,
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: _appointments.length + (_loadingMore ? 1 : 0),
        itemBuilder: (_, i) {
          if (i >= _appointments.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            );
          }
          return _AppointmentCard(appointment: _appointments[i]);
        },
      ),
    );
  }
}

class _AppointmentCard extends StatelessWidget {
  final PatientAppointment appointment;
  const _AppointmentCard({required this.appointment});

  @override
  Widget build(BuildContext context) {
    final a = appointment;
    final isUpcoming = a.isUpcoming;
    final isCancelled = a.isCancelled;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: (isCancelled
                            ? Colors.red
                            : isUpcoming
                                ? Colors.blue
                                : Colors.grey)
                        .withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    isCancelled
                        ? Icons.cancel_outlined
                        : Icons.calendar_today_rounded,
                    color: isCancelled
                        ? Colors.red
                        : isUpcoming
                            ? Colors.blue
                            : Colors.grey,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(a.doctorName ?? 'Doctor',
                          style: const TextStyle(
                              fontWeight: FontWeight.w600, fontSize: 14)),
                      if (a.clinicName != null)
                        Text(a.clinicName!,
                            style: TextStyle(
                                fontSize: 12, color: Colors.grey.shade600)),
                    ],
                  ),
                ),
                StatusBadge.fromStatus(a.status ?? 'scheduled'),
              ],
            ),
            const SizedBox(height: 12),
            // Date and time row
            Row(
              children: [
                Icon(Icons.calendar_month,
                    size: 14, color: Colors.grey.shade500),
                const SizedBox(width: 6),
                Text(a.appointmentDate ?? '',
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w500)),
                if (a.startTime != null) ...[
                  const SizedBox(width: 16),
                  Icon(Icons.access_time,
                      size: 14, color: Colors.grey.shade500),
                  const SizedBox(width: 6),
                  Text(
                    a.endTime != null
                        ? '${a.startTime} - ${a.endTime}'
                        : a.startTime!,
                    style: const TextStyle(fontSize: 13),
                  ),
                ],
                if (a.durationMinutes != null) ...[
                  const SizedBox(width: 12),
                  Text('(${a.durationMinutes} min)',
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey.shade500)),
                ],
              ],
            ),
            if (a.appointmentType != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.category, size: 14, color: Colors.grey.shade500),
                  const SizedBox(width: 6),
                  Text(a.appointmentType!,
                      style: TextStyle(
                          fontSize: 12, color: Colors.grey.shade700)),
                  if (a.priority != null &&
                      a.priority!.toLowerCase() != 'normal') ...[
                    const SizedBox(width: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.orange.shade100,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(a.priority!.toUpperCase(),
                          style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                              color: Colors.orange.shade800)),
                    ),
                  ],
                ],
              ),
            ],
            if (a.reason != null && a.reason!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(a.reason!,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
            ],
            if (isCancelled &&
                a.cancellationReason != null &&
                a.cancellationReason!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text('Cancelled: ${a.cancellationReason!}',
                    style:
                        TextStyle(fontSize: 12, color: Colors.red.shade700)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
