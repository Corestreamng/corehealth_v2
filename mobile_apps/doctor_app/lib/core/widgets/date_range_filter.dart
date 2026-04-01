import 'package:flutter/material.dart';

/// Collapsible date range filter with From/To date pickers and Apply/Reset buttons.
class DateRangeFilter extends StatefulWidget {
  final DateTime? initialFrom;
  final DateTime? initialTo;
  final void Function(DateTime? from, DateTime? to) onApply;
  final VoidCallback? onReset;

  const DateRangeFilter({
    super.key,
    this.initialFrom,
    this.initialTo,
    required this.onApply,
    this.onReset,
  });

  @override
  State<DateRangeFilter> createState() => _DateRangeFilterState();
}

class _DateRangeFilterState extends State<DateRangeFilter> {
  bool _expanded = false;
  DateTime? _from;
  DateTime? _to;

  @override
  void initState() {
    super.initState();
    _from = widget.initialFrom;
    _to = widget.initialTo;
  }

  Future<void> _pickDate(bool isFrom) async {
    final initial = isFrom ? _from : _to;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() {
        if (isFrom) {
          _from = picked;
        } else {
          _to = picked;
        }
      });
    }
  }

  String _formatDate(DateTime? d) {
    if (d == null) return 'Select';
    return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final hasFilter = _from != null || _to != null;
    return Column(
      children: [
        InkWell(
          onTap: () => setState(() => _expanded = !_expanded),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                Icon(Icons.filter_list,
                    size: 18, color: hasFilter ? Theme.of(context).colorScheme.primary : Colors.grey),
                const SizedBox(width: 6),
                Text(
                  hasFilter
                      ? '${_formatDate(_from)} → ${_formatDate(_to)}'
                      : 'Date Filter',
                  style: TextStyle(
                    fontSize: 13,
                    color: hasFilter ? Theme.of(context).colorScheme.primary : Colors.grey.shade600,
                    fontWeight: hasFilter ? FontWeight.w600 : FontWeight.normal,
                  ),
                ),
                const Spacer(),
                Icon(
                  _expanded ? Icons.expand_less : Icons.expand_more,
                  size: 20,
                  color: Colors.grey,
                ),
              ],
            ),
          ),
        ),
        if (_expanded)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickDate(true),
                    icon: const Icon(Icons.calendar_today, size: 14),
                    label: Text(_formatDate(_from), style: const TextStyle(fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                    ),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 6),
                  child: Text('→', style: TextStyle(color: Colors.grey)),
                ),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickDate(false),
                    icon: const Icon(Icons.calendar_today, size: 14),
                    label: Text(_formatDate(_to), style: const TextStyle(fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                SizedBox(
                  height: 36,
                  child: ElevatedButton(
                    onPressed: () => widget.onApply(_from, _to),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                    ),
                    child: const Text('Apply', style: TextStyle(fontSize: 12)),
                  ),
                ),
                if (hasFilter) ...[
                  const SizedBox(width: 4),
                  SizedBox(
                    height: 36,
                    width: 36,
                    child: IconButton(
                      onPressed: () {
                        setState(() {
                          _from = null;
                          _to = null;
                        });
                        widget.onReset?.call();
                      },
                      icon: const Icon(Icons.clear, size: 16),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.grey.shade200,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
      ],
    );
  }
}
