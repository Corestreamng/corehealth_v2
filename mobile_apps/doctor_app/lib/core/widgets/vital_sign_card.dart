import 'package:flutter/material.dart';
import '../models/encounter_models.dart';

/// Color-coded vital sign card with thresholds.
class VitalSignCard extends StatelessWidget {
  final VitalSign vital;
  final bool compact;

  const VitalSignCard({super.key, required this.vital, this.compact = false});

  @override
  Widget build(BuildContext context) {
    final items = <_VitalItem>[];

    if (vital.temperature != null) {
      items.add(_VitalItem(
        icon: Icons.thermostat_outlined,
        label: 'Temp',
        value: '${vital.temperature!.toStringAsFixed(1)}°C',
        color: _tempColor(vital.temperature!),
      ));
    }
    if (vital.systolicBp != null && vital.diastolicBp != null) {
      items.add(_VitalItem(
        icon: Icons.favorite_outline,
        label: 'BP',
        value: vital.bpDisplay,
        color: _bpColor(vital.systolicBp!, vital.diastolicBp!),
      ));
    }
    if (vital.heartRate != null) {
      items.add(_VitalItem(
        icon: Icons.monitor_heart_outlined,
        label: 'HR',
        value: '${vital.heartRate} bpm',
        color: _hrColor(vital.heartRate!),
      ));
    }
    if (vital.respiratoryRate != null) {
      items.add(_VitalItem(
        icon: Icons.air,
        label: 'RR',
        value: '${vital.respiratoryRate}/min',
        color: _rrColor(vital.respiratoryRate!),
      ));
    }
    if (vital.spo2 != null) {
      items.add(_VitalItem(
        icon: Icons.bloodtype_outlined,
        label: 'SpO₂',
        value: '${vital.spo2}%',
        color: _spo2Color(vital.spo2!),
      ));
    }
    if (vital.weight != null) {
      items.add(_VitalItem(
        icon: Icons.monitor_weight_outlined,
        label: 'Weight',
        value: '${vital.weight!.toStringAsFixed(1)} kg',
        color: Colors.grey.shade700,
      ));
    }
    if (vital.height != null) {
      items.add(_VitalItem(
        icon: Icons.height,
        label: 'Height',
        value: '${vital.height!.toStringAsFixed(0)} cm',
        color: Colors.grey.shade700,
      ));
    }
    if (vital.bloodSugar != null) {
      items.add(_VitalItem(
        icon: Icons.water_drop_outlined,
        label: 'Sugar',
        value: '${vital.bloodSugar!.toStringAsFixed(0)} mg/dL',
        color: _sugarColor(vital.bloodSugar!),
      ));
    }
    if (vital.painLevel != null) {
      items.add(_VitalItem(
        icon: Icons.sentiment_dissatisfied_outlined,
        label: 'Pain',
        value: '${vital.painLevel}/10',
        color: _painColor(vital.painLevel!),
      ));
    }
    if (vital.bmi != null) {
      items.add(_VitalItem(
        icon: Icons.calculate_outlined,
        label: 'BMI',
        value: vital.bmi!.toStringAsFixed(1),
        color: _bmiColor(vital.bmi!),
      ));
    }

    if (items.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: EdgeInsets.all(compact ? 10 : 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (vital.timeTaken != null || vital.takenBy != null) ...[
              Row(
                children: [
                  Icon(Icons.access_time, size: 14, color: Colors.grey.shade500),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      [
                        if (vital.timeTaken != null) vital.timeTaken!,
                        if (vital.takenBy != null) 'by ${vital.takenBy}',
                      ].join(' — '),
                      style: TextStyle(
                        fontSize: 11,
                        color: Colors.grey.shade500,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
            ],
            Wrap(
              spacing: compact ? 8 : 12,
              runSpacing: compact ? 6 : 8,
              children: items.map((item) => _buildChip(item, compact)).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildChip(_VitalItem item, bool compact) {
    final size = compact ? 28.0 : 34.0;
    final fontSize = compact ? 11.0 : 13.0;
    final labelSize = compact ? 9.0 : 10.0;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            color: item.color.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(item.icon, size: size * 0.5, color: item.color),
        ),
        const SizedBox(height: 4),
        Text(
          item.value,
          style: TextStyle(
            fontSize: fontSize,
            fontWeight: FontWeight.w700,
            color: item.color,
          ),
        ),
        Text(
          item.label,
          style: TextStyle(
            fontSize: labelSize,
            color: Colors.grey.shade500,
          ),
        ),
      ],
    );
  }

  // ── Threshold colors ──

  static Color _tempColor(double t) {
    if (t < 36.1) return Colors.blue.shade700;
    if (t <= 37.2) return Colors.green.shade700;
    if (t <= 38.0) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _bpColor(int sys, int dia) {
    if (sys < 90 || dia < 60) return Colors.blue.shade700;
    if (sys <= 140 && dia <= 90) return Colors.green.shade700;
    if (sys <= 160 || dia <= 100) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _hrColor(int hr) {
    if (hr < 60) return Colors.blue.shade700;
    if (hr <= 100) return Colors.green.shade700;
    if (hr <= 120) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _rrColor(int rr) {
    if (rr < 12) return Colors.blue.shade700;
    if (rr <= 20) return Colors.green.shade700;
    if (rr <= 25) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _spo2Color(int spo2) {
    if (spo2 >= 95) return Colors.green.shade700;
    if (spo2 >= 90) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _sugarColor(double s) {
    if (s < 80) return Colors.blue.shade700;
    if (s <= 140) return Colors.green.shade700;
    if (s <= 200) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _painColor(int p) {
    if (p <= 3) return Colors.green.shade700;
    if (p <= 6) return Colors.orange.shade700;
    return Colors.red.shade700;
  }

  static Color _bmiColor(double bmi) {
    if (bmi < 18.5) return Colors.blue.shade700;
    if (bmi < 25) return Colors.green.shade700;
    if (bmi < 30) return Colors.orange.shade700;
    return Colors.red.shade700;
  }
}

class _VitalItem {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  _VitalItem({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });
}
