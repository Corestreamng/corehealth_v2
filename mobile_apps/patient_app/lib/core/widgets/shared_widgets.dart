import 'package:flutter/material.dart';

/// Empty state placeholder with icon, title, subtitle, and optional action.
class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? action;

  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.action,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            Text(title,
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Colors.grey.shade700)),
            if (subtitle != null) ...[
              const SizedBox(height: 8),
              Text(subtitle!,
                  style: TextStyle(fontSize: 13, color: Colors.grey.shade500),
                  textAlign: TextAlign.center),
            ],
            if (action != null) ...[
              const SizedBox(height: 20),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}

/// Color-coded status badge for lab/imaging/prescription statuses.
class StatusBadge extends StatelessWidget {
  final String label;
  final Color color;
  final Color bgColor;

  const StatusBadge({
    super.key,
    required this.label,
    required this.color,
    required this.bgColor,
  });

  factory StatusBadge.fromStatus(String status) {
    final s = status.toLowerCase();
    if (s.contains('result') || s.contains('dispens') || s.contains('complet')) {
      return StatusBadge(
          label: status,
          color: Colors.green.shade800,
          bgColor: Colors.green.shade50);
    } else if (s.contains('sample') ||
        s.contains('billed') ||
        s.contains('progress') ||
        s.contains('schedul')) {
      return StatusBadge(
          label: status,
          color: Colors.orange.shade800,
          bgColor: Colors.orange.shade50);
    } else if (s.contains('cancel')) {
      return StatusBadge(
          label: status,
          color: Colors.red.shade800,
          bgColor: Colors.red.shade50);
    }
    return StatusBadge(
        label: status,
        color: Colors.blue.shade800,
        bgColor: Colors.blue.shade50);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(label,
          style:
              TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: color)),
    );
  }
}

/// Section header with icon and title.
class SectionHeader extends StatelessWidget {
  final String title;
  final IconData icon;

  const SectionHeader({super.key, required this.title, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Icon(icon, size: 18, color: Colors.grey.shade600),
          const SizedBox(width: 8),
          Text(title,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Colors.grey.shade800)),
        ],
      ),
    );
  }
}

/// Color-coded vital sign card with clinical thresholds.
class VitalCard extends StatelessWidget {
  final String label;
  final String value;
  final String? unit;
  final IconData icon;
  final Color? overrideColor;

  const VitalCard({
    super.key,
    required this.label,
    required this.value,
    this.unit,
    required this.icon,
    this.overrideColor,
  });

  @override
  Widget build(BuildContext context) {
    final color = overrideColor ?? Colors.blue.shade700;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(height: 6),
            Text(value,
                style: TextStyle(
                    fontSize: 18, fontWeight: FontWeight.w700, color: color)),
            Text(
              unit != null ? '$label ($unit)' : label,
              style: TextStyle(fontSize: 10, color: Colors.grey.shade600),
            ),
          ],
        ),
      ),
    );
  }

  /// Determine color from clinical threshold.
  static Color vitalColor(
      String type, double? val) {
    if (val == null) return Colors.grey;
    switch (type) {
      case 'temp':
        return (val >= 36.1 && val <= 37.2)
            ? Colors.green.shade700
            : Colors.red.shade700;
      case 'hr':
        return (val >= 60 && val <= 100)
            ? Colors.green.shade700
            : Colors.red.shade700;
      case 'rr':
        return (val >= 12 && val <= 20)
            ? Colors.green.shade700
            : Colors.red.shade700;
      case 'spo2':
        return val >= 95
            ? Colors.green.shade700
            : Colors.red.shade700;
      case 'sugar':
        return (val >= 80 && val <= 140)
            ? Colors.green.shade700
            : Colors.orange.shade700;
      case 'bmi':
        return (val >= 18.5 && val <= 25)
            ? Colors.green.shade700
            : Colors.orange.shade700;
      case 'pain':
        return val <= 3
            ? Colors.green.shade700
            : val <= 6
                ? Colors.orange.shade700
                : Colors.red.shade700;
      default:
        return Colors.blue.shade700;
    }
  }
}
