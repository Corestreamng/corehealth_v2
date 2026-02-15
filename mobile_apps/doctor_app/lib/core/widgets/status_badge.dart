import 'package:flutter/material.dart';

/// Colored status badge used throughout the app.
class StatusBadge extends StatelessWidget {
  final String label;
  final Color? color;
  final IconData? icon;
  final bool small;

  const StatusBadge({
    super.key,
    required this.label,
    this.color,
    this.icon,
    this.small = false,
  });

  /// Convenience constructors for common statuses.
  factory StatusBadge.requested() => const StatusBadge(
        label: 'Requested',
        color: Color(0xFFF57F17),
        icon: Icons.schedule,
      );
  factory StatusBadge.inProgress() => const StatusBadge(
        label: 'In Progress',
        color: Color(0xFF1565C0),
        icon: Icons.play_circle_outline,
      );
  factory StatusBadge.completed() => const StatusBadge(
        label: 'Completed',
        color: Color(0xFF2E7D32),
        icon: Icons.check_circle_outline,
      );
  factory StatusBadge.cancelled() => const StatusBadge(
        label: 'Cancelled',
        color: Color(0xFFC62828),
        icon: Icons.cancel_outlined,
      );

  /// Smart constructor from status string.
  factory StatusBadge.fromStatus(String status) {
    final s = status.toLowerCase();
    if (s.contains('request') || s.contains('new') || s.contains('pending')) {
      return StatusBadge(label: status, color: const Color(0xFFF57F17), icon: Icons.schedule);
    }
    if (s.contains('sample') || s.contains('billed') || s.contains('progress') || s.contains('continuing')) {
      return StatusBadge(label: status, color: const Color(0xFF1565C0), icon: Icons.play_circle_outline);
    }
    if (s.contains('result') || s.contains('complete') || s.contains('dispens') || s.contains('done')) {
      return StatusBadge(label: status, color: const Color(0xFF2E7D32), icon: Icons.check_circle_outline);
    }
    if (s.contains('cancel') || s.contains('void') || s.contains('delete')) {
      return StatusBadge(label: status, color: const Color(0xFFC62828), icon: Icons.cancel_outlined);
    }
    return StatusBadge(label: status);
  }

  @override
  Widget build(BuildContext context) {
    final c = color ?? Colors.grey.shade600;
    final fontSize = small ? 10.0 : 12.0;
    final hPad = small ? 6.0 : 10.0;
    final vPad = small ? 2.0 : 4.0;
    final iconSize = small ? 12.0 : 14.0;

    return Container(
      padding: EdgeInsets.symmetric(horizontal: hPad, vertical: vPad),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: iconSize, color: c),
            SizedBox(width: small ? 3 : 4),
          ],
          Text(
            label,
            style: TextStyle(
              color: c,
              fontSize: fontSize,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
