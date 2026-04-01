import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/shared_widgets.dart';

class ReferralsScreen extends StatefulWidget {
  const ReferralsScreen({super.key});

  @override
  State<ReferralsScreen> createState() => _ReferralsScreenState();
}

class _ReferralsScreenState extends State<ReferralsScreen> {
  late PatientApiService _api;
  final List<PatientReferral> _referrals = [];
  bool _isLoading = true;
  String? _error;
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
      _referrals.clear();
    });

    final res = await _api.getReferrals(page: 1);
    if (!mounted) return;

    if (res.success && res.data is List) {
      final items = (res.data as List)
          .whereType<Map<String, dynamic>>()
          .map(PatientReferral.fromJson)
          .toList();
      setState(() {
        _referrals.addAll(items);
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

    final res = await _api.getReferrals(page: nextPage);
    if (!mounted) return;

    if (res.success && res.data is List) {
      final items = (res.data as List)
          .whereType<Map<String, dynamic>>()
          .map(PatientReferral.fromJson)
          .toList();
      setState(() {
        _page = nextPage;
        _referrals.addAll(items);
        _hasMore = items.length >= 20;
        _loadingMore = false;
      });
    } else {
      setState(() => _loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Referrals')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
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
    if (_referrals.isEmpty) {
      return const EmptyState(
        icon: Icons.swap_horiz,
        title: 'No Referrals',
        subtitle: 'You have no specialist referrals',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        controller: _scroll,
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: _referrals.length + (_loadingMore ? 1 : 0),
        itemBuilder: (_, i) {
          if (i >= _referrals.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            );
          }
          return _ReferralCard(referral: _referrals[i]);
        },
      ),
    );
  }
}

class _ReferralCard extends StatelessWidget {
  final PatientReferral referral;
  const _ReferralCard({required this.referral});

  @override
  Widget build(BuildContext context) {
    final r = referral;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.swap_horiz, size: 20, color: Colors.indigo.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    r.referralType == 'external'
                        ? 'External Referral'
                        : 'Internal Referral',
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                ),
                if (r.isUrgent)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.red.shade100,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text((r.urgency ?? '').toUpperCase(),
                        style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: Colors.red.shade800)),
                  ),
                if (!r.isUrgent && r.status != null)
                  StatusBadge.fromStatus(r.status!),
              ],
            ),
            const SizedBox(height: 10),
            if (r.fromDoctor != null)
              _infoRow('From',
                  '${r.fromDoctor}${r.fromClinic != null ? ' (${r.fromClinic})' : ''}'),
            if (r.toDoctor != null && r.toDoctor!.isNotEmpty)
              _infoRow('To Doctor', r.toDoctor!),
            if (r.toClinic != null && r.toClinic!.isNotEmpty)
              _infoRow('To Clinic', r.toClinic!),
            if (r.reason != null && r.reason!.isNotEmpty)
              _infoRow('Reason', r.reason!),
            if (r.provisionalDiagnosis != null &&
                r.provisionalDiagnosis!.isNotEmpty)
              _infoRow('Diagnosis', r.provisionalDiagnosis!),
            if (r.clinicalSummary != null &&
                r.clinicalSummary!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Clinical Summary',
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: Colors.blue.shade800)),
                    const SizedBox(height: 4),
                    Text(r.clinicalSummary!,
                        style: TextStyle(
                            fontSize: 12, color: Colors.blue.shade900)),
                  ],
                ),
              ),
            ],
            if (r.actionNotes != null && r.actionNotes!.isNotEmpty) ...[
              const SizedBox(height: 6),
              _infoRow('Action', r.actionNotes!),
            ],
            const SizedBox(height: 4),
            Text(r.createdAt ?? '',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 80,
            child: Text(label,
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }
}
