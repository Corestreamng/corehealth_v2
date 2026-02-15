import 'package:flutter/material.dart';
import '../api/patient_api_service.dart';
import 'shared_widgets.dart';

/// A reusable paginated list with pull-to-refresh and infinite scroll.
/// Used across all patient health record screens.
class PaginatedList<T> extends StatefulWidget {
  /// Fetch function that takes page number and returns ApiResult
  final Future<ApiResult> Function(int page) fetcher;

  /// Parse each item from JSON map
  final T Function(Map<String, dynamic> json) parser;

  /// Build a single item widget
  final Widget Function(BuildContext context, T item) itemBuilder;

  /// Icon and text for empty state
  final IconData emptyIcon;
  final String emptyTitle;
  final String? emptySubtitle;

  const PaginatedList({
    super.key,
    required this.fetcher,
    required this.parser,
    required this.itemBuilder,
    this.emptyIcon = Icons.inbox_rounded,
    this.emptyTitle = 'No records found',
    this.emptySubtitle,
  });

  @override
  State<PaginatedList<T>> createState() => PaginatedListState<T>();
}

class PaginatedListState<T> extends State<PaginatedList<T>> {
  final List<T> _items = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  int _page = 1;
  String? _error;
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadPage(1);
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
      _loadPage(_page + 1);
    }
  }

  Future<void> _loadPage(int page) async {
    if (page == 1) {
      setState(() {
        _isLoading = true;
        _error = null;
      });
    } else {
      setState(() => _isLoadingMore = true);
    }

    final res = await widget.fetcher(page);

    if (!mounted) return;

    if (res.success) {
      final raw = res.data;
      List items = [];
      int? lastPage;

      if (raw is Map<String, dynamic>) {
        items = raw['data'] as List? ?? [];
        lastPage = raw['last_page'] as int?;
      } else if (raw is List) {
        items = raw;
        _hasMore = false;
      }

      setState(() {
        if (page == 1) _items.clear();
        for (final item in items) {
          if (item is Map<String, dynamic>) {
            _items.add(widget.parser(item));
          }
        }
        _page = page;
        if (lastPage != null) _hasMore = page < lastPage;
        _isLoading = false;
        _isLoadingMore = false;
      });
    } else {
      setState(() {
        _error = res.message;
        _isLoading = false;
        _isLoadingMore = false;
      });
    }
  }

  Future<void> refresh() => _loadPage(1);

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _items.isEmpty) {
      return EmptyState(
        icon: Icons.error_outline,
        title: 'Error',
        subtitle: _error,
        action: ElevatedButton.icon(
          onPressed: () => _loadPage(1),
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry'),
        ),
      );
    }

    if (_items.isEmpty) {
      return EmptyState(
        icon: widget.emptyIcon,
        title: widget.emptyTitle,
        subtitle: widget.emptySubtitle,
      );
    }

    return RefreshIndicator(
      onRefresh: () => _loadPage(1),
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
        itemCount: _items.length + (_isLoadingMore ? 1 : 0),
        itemBuilder: (ctx, i) {
          if (i >= _items.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            );
          }
          return widget.itemBuilder(ctx, _items[i]);
        },
      ),
    );
  }
}
