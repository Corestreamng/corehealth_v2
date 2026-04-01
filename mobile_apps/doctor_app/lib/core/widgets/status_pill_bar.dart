import 'package:flutter/material.dart';

/// A filter definition for a status pill.
class StatusPill {
  final String label;
  final int? value;
  final Color color;
  final int count;

  const StatusPill({
    required this.label,
    this.value,
    required this.color,
    this.count = 0,
  });
}

/// Horizontal scrollable status filter pills with count badges.
class StatusPillBar extends StatelessWidget {
  final List<StatusPill> pills;
  final int? selectedValue;
  final ValueChanged<int?> onSelected;

  const StatusPillBar({
    super.key,
    required this.pills,
    required this.selectedValue,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        itemCount: pills.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final pill = pills[index];
          final isSelected = pill.value == selectedValue;
          return FilterChip(
            selected: isSelected,
            label: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(pill.label),
                if (pill.count > 0) ...[
                  const SizedBox(width: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(
                      color: isSelected ? Colors.white.withValues(alpha: 0.3) : pill.color.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      '${pill.count}',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: isSelected ? Colors.white : pill.color,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            selectedColor: pill.color,
            checkmarkColor: Colors.white,
            labelStyle: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: isSelected ? Colors.white : pill.color,
            ),
            side: BorderSide(color: pill.color.withValues(alpha: 0.3)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            showCheckmark: false,
            onSelected: (_) => onSelected(isSelected ? null : pill.value),
          );
        },
      ),
    );
  }
}
