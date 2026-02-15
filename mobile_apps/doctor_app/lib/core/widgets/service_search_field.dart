import 'dart:async';
import 'package:flutter/material.dart';

/// Debounced search field for ICPC-2 diagnosis, services, and products.
class ServiceSearchField extends StatefulWidget {
  final String hintText;
  final Future<List<Map<String, dynamic>>> Function(String term) onSearch;
  final void Function(Map<String, dynamic> item) onSelect;
  final int minChars;
  final Duration debounce;

  const ServiceSearchField({
    super.key,
    required this.hintText,
    required this.onSearch,
    required this.onSelect,
    this.minChars = 2,
    this.debounce = const Duration(milliseconds: 300),
  });

  @override
  State<ServiceSearchField> createState() => _ServiceSearchFieldState();
}

class _ServiceSearchFieldState extends State<ServiceSearchField> {
  final _controller = TextEditingController();
  final _focusNode = FocusNode();
  Timer? _debounceTimer;
  List<Map<String, dynamic>> _results = [];
  bool _isSearching = false;
  bool _showResults = false;

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _onChanged(String text) {
    _debounceTimer?.cancel();
    if (text.trim().length < widget.minChars) {
      setState(() {
        _results = [];
        _showResults = false;
      });
      return;
    }
    _debounceTimer = Timer(widget.debounce, () => _search(text.trim()));
  }

  Future<void> _search(String term) async {
    setState(() => _isSearching = true);
    try {
      final results = await widget.onSearch(term);
      if (mounted) {
        setState(() {
          _results = results;
          _showResults = true;
          _isSearching = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isSearching = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        TextField(
          controller: _controller,
          focusNode: _focusNode,
          onChanged: _onChanged,
          decoration: InputDecoration(
            hintText: widget.hintText,
            prefixIcon: const Icon(Icons.search, size: 20),
            suffixIcon: _isSearching
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : _controller.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _controller.clear();
                          setState(() {
                            _results = [];
                            _showResults = false;
                          });
                        },
                      )
                    : null,
            isDense: true,
          ),
        ),
        if (_showResults && _results.isNotEmpty)
          Container(
            constraints: const BoxConstraints(maxHeight: 220),
            margin: const EdgeInsets.only(top: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.grey.shade200),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: ListView.separated(
              shrinkWrap: true,
              padding: EdgeInsets.zero,
              itemCount: _results.length,
              separatorBuilder: (_, __) =>
                  Divider(height: 1, color: Colors.grey.shade100),
              itemBuilder: (context, index) {
                final item = _results[index];
                final display = item['display'] ??
                    item['service_name'] ??
                    item['product_name'] ??
                    item['name'] ??
                    '';
                final sub = item['category'] ?? item['sub_category'] ?? '';
                final price = item['payable_amount'] ?? item['price'];

                return ListTile(
                  dense: true,
                  title: Text(
                    display.toString(),
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                  ),
                  subtitle: sub.toString().isNotEmpty
                      ? Text(sub.toString(),
                          style: TextStyle(
                              fontSize: 11, color: Colors.grey.shade600))
                      : null,
                  trailing: price != null
                      ? Text(
                          '₦${_formatPrice(price)}',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: Colors.grey.shade700,
                          ),
                        )
                      : null,
                  onTap: () {
                    widget.onSelect(item);
                    _controller.clear();
                    setState(() {
                      _results = [];
                      _showResults = false;
                    });
                    _focusNode.unfocus();
                  },
                );
              },
            ),
          ),
        if (_showResults && _results.isEmpty && !_isSearching)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'No results found',
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey.shade500,
                fontStyle: FontStyle.italic,
              ),
            ),
          ),
      ],
    );
  }

  String _formatPrice(dynamic price) {
    final n = double.tryParse(price.toString()) ?? 0;
    if (n == n.toInt()) return n.toInt().toString();
    return n.toStringAsFixed(2);
  }
}
