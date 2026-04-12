import 'package:flutter/material.dart';
import '../../../core/api/encounter_api_service.dart';
import '../../../core/models/encounter_models.dart';
import '../../../core/widgets/vital_sign_card.dart';
import '../../../core/widgets/shared_widgets.dart';

/// Tab 2: Vitals display + recording form.
class VitalsTab extends StatefulWidget {
  final EncounterApiService api;
  final EncounterData encounter;
  final VoidCallback onVitalsRecorded;

  const VitalsTab({
    super.key,
    required this.api,
    required this.encounter,
    required this.onVitalsRecorded,
  });

  @override
  State<VitalsTab> createState() => _VitalsTabState();
}

class _VitalsTabState extends State<VitalsTab>
    with AutomaticKeepAliveClientMixin {
  bool _showForm = false;
  bool _isSaving = false;
  int _painLevel = -1; // -1 = not set

  // Form controllers
  final _tempCtrl = TextEditingController();
  final _sysCtrl = TextEditingController();
  final _diaCtrl = TextEditingController();
  final _hrCtrl = TextEditingController();
  final _rrCtrl = TextEditingController();
  final _spo2Ctrl = TextEditingController();
  final _weightCtrl = TextEditingController();
  final _heightCtrl = TextEditingController();
  final _sugarCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  String? _validationError;

  @override
  bool get wantKeepAlive => true;

  @override
  void dispose() {
    _tempCtrl.dispose();
    _sysCtrl.dispose();
    _diaCtrl.dispose();
    _hrCtrl.dispose();
    _rrCtrl.dispose();
    _spo2Ctrl.dispose();
    _weightCtrl.dispose();
    _heightCtrl.dispose();
    _sugarCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  double? get _bmi {
    final w = double.tryParse(_weightCtrl.text);
    final h = double.tryParse(_heightCtrl.text);
    if (w == null || h == null || w <= 0 || h <= 0) return null;
    return w / ((h / 100) * (h / 100));
  }

  String _bmiClassification(double bmi) {
    if (bmi < 18.5) return 'Underweight';
    if (bmi < 25) return 'Normal';
    if (bmi < 30) return 'Overweight';
    return 'Obese';
  }

  Color _bmiColor(double bmi) {
    if (bmi < 18.5) return Colors.blue.shade600;
    if (bmi < 25) return Colors.green.shade600;
    if (bmi < 30) return Colors.orange.shade600;
    return Colors.red.shade600;
  }

  Future<void> _saveVitals() async {
    // Validate: temperature is required matching web validation
    final temp = double.tryParse(_tempCtrl.text);
    if (_tempCtrl.text.trim().isEmpty) {
      setState(() => _validationError = 'Temperature is required.');
      return;
    }
    if (temp == null || temp < 34 || temp > 42) {
      setState(() => _validationError = 'Temperature must be between 34°C and 42°C.');
      return;
    }

    // Validate BP: if one is entered, both must be
    final sys = int.tryParse(_sysCtrl.text);
    final dia = int.tryParse(_diaCtrl.text);
    if ((_sysCtrl.text.isNotEmpty || _diaCtrl.text.isNotEmpty) && (sys == null || dia == null)) {
      setState(() => _validationError = 'Both systolic and diastolic BP are required.');
      return;
    }

    setState(() { _validationError = null; _isSaving = true; });

    final res = await widget.api.recordVitals(
      patientId: widget.encounter.patientId,
      encounterId: widget.encounter.id,
      temperature: temp,
      systolicBp: sys,
      diastolicBp: dia,
      heartRate: int.tryParse(_hrCtrl.text),
      respiratoryRate: int.tryParse(_rrCtrl.text),
      spo2: int.tryParse(_spo2Ctrl.text),
      weight: double.tryParse(_weightCtrl.text),
      height: double.tryParse(_heightCtrl.text),
      bloodSugar: double.tryParse(_sugarCtrl.text),
      painLevel: _painLevel >= 0 ? _painLevel : null,
      otherNotes: _notesCtrl.text.trim(),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(context, 'Vitals recorded successfully');
      _clearForm();
      setState(() => _showForm = false);
      widget.onVitalsRecorded();
    } else {
      // Show backend validation errors
      final msg = res.message.isNotEmpty ? res.message : 'Failed to save vitals';
      // Try to extract field-level errors from response
      if (res.data != null && res.data!['errors'] is Map) {
        final errors = res.data!['errors'] as Map;
        final allErrors = errors.values
            .expand((v) => v is List ? v.map((e) => e.toString()) : [v.toString()])
            .join('\n');
        setState(() => _validationError = allErrors);
      } else {
        setState(() => _validationError = msg);
      }
    }
  }

  void _clearForm() {
    for (final c in [
      _tempCtrl, _sysCtrl, _diaCtrl, _hrCtrl, _rrCtrl,
      _spo2Ctrl, _weightCtrl, _heightCtrl, _sugarCtrl,
      _notesCtrl,
    ]) {
      c.clear();
    }
    _painLevel = -1;
    _validationError = null;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final vitals = widget.encounter.vitals;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Add vitals button ──
          if (!widget.encounter.completed)
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => setState(() => _showForm = !_showForm),
                icon: Icon(
                  _showForm ? Icons.close : Icons.add,
                  size: 18,
                ),
                label: Text(_showForm ? 'Cancel' : 'Record Vitals'),
              ),
            ),
          if (_showForm) ...[
            const SizedBox(height: 12),
            _buildVitalsForm(),
          ],
          const SizedBox(height: 16),

          // ── Vitals history ──
          SectionHeader(
            title: 'Vital Signs History',
            icon: Icons.monitor_heart_outlined,
            trailing: Text(
              '${vitals.length} record${vitals.length == 1 ? '' : 's'}',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
            ),
          ),
          if (vitals.isEmpty)
            const EmptyState(
              icon: Icons.monitor_heart_outlined,
              title: 'No vitals recorded',
              subtitle: 'Tap "Record Vitals" to add the first reading',
            )
          else
            ...vitals.map((v) => VitalSignCard(vital: v)),

          const SizedBox(height: 80),
        ],
      ),
    );
  }

  Widget _buildVitalsForm() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Record New Vitals',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade800,
              ),
            ),
            const SizedBox(height: 14),

            // Validation error banner
            if (_validationError != null) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.error_outline, size: 16, color: Colors.red.shade700),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _validationError!,
                        style: TextStyle(fontSize: 12, color: Colors.red.shade700),
                      ),
                    ),
                    InkWell(
                      onTap: () => setState(() => _validationError = null),
                      child: Icon(Icons.close, size: 14, color: Colors.red.shade400),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
            ],

            // Temperature + BP
            Row(
              children: [
                Expanded(
                  child: _VitalField(
                    controller: _tempCtrl,
                    label: 'Temp (°C)',
                    icon: Icons.thermostat_outlined,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _sysCtrl,
                    label: 'Sys BP',
                    icon: Icons.favorite_outline,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _diaCtrl,
                    label: 'Dia BP',
                    icon: Icons.favorite_outline,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // HR + RR + SpO2
            Row(
              children: [
                Expanded(
                  child: _VitalField(
                    controller: _hrCtrl,
                    label: 'HR (bpm)',
                    icon: Icons.monitor_heart_outlined,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _rrCtrl,
                    label: 'RR (/min)',
                    icon: Icons.air,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _spo2Ctrl,
                    label: 'SpO₂ (%)',
                    icon: Icons.bloodtype_outlined,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Weight + Height
            Row(
              children: [
                Expanded(
                  child: _VitalField(
                    controller: _weightCtrl,
                    label: 'Weight (kg)',
                    icon: Icons.monitor_weight_outlined,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    onChanged: (_) => setState(() {}),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _heightCtrl,
                    label: 'Height (cm)',
                    icon: Icons.height,
                    keyboardType: TextInputType.number,
                    onChanged: (_) => setState(() {}),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Blood Sugar + Pain
            Row(
              children: [
                Expanded(
                  child: _VitalField(
                    controller: _sugarCtrl,
                    label: 'Sugar (mg/dL)',
                    icon: Icons.water_drop_outlined,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  ),
                ),
                const SizedBox(width: 10),
                const Expanded(child: SizedBox()),
              ],
            ),
            const SizedBox(height: 10),

            // BMI Auto-calculation
            Builder(builder: (_) {
              final bmi = _bmi;
              if (bmi == null) return const SizedBox.shrink();
              final label = _bmiClassification(bmi);
              final color = _bmiColor(bmi);
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: color.withValues(alpha: 0.4)),
                ),
                child: Row(children: [
                  Icon(Icons.monitor_weight_outlined, size: 18, color: color),
                  const SizedBox(width: 8),
                  Text('BMI: ${bmi.toStringAsFixed(1)}',
                      style: TextStyle(fontWeight: FontWeight.bold, color: color)),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
                  ),
                ]),
              );
            }),
            const SizedBox(height: 10),

            // Visual Pain Scale
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [
                  Icon(Icons.sentiment_dissatisfied_outlined, size: 18,
                      color: Theme.of(context).hintColor),
                  const SizedBox(width: 6),
                  Text('Pain Scale',
                      style: TextStyle(fontSize: 13, color: Theme.of(context).hintColor)),
                ]),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 4,
                  runSpacing: 4,
                  children: List.generate(11, (i) {
                    final selected = _painLevel == i;
                    final color = Color.lerp(
                      Colors.green, Colors.red, i / 10)!;
                    return GestureDetector(
                      onTap: () => setState(() => _painLevel = selected ? -1 : i),
                      child: Container(
                        width: 34,
                        height: 34,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: selected ? color : color.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: selected ? color : color.withValues(alpha: 0.3),
                            width: selected ? 2 : 1,
                          ),
                        ),
                        child: Text('$i',
                          style: TextStyle(
                            fontWeight: selected ? FontWeight.bold : FontWeight.normal,
                            fontSize: 13,
                            color: selected ? Colors.white : color,
                          ),
                        ),
                      ),
                    );
                  }),
                ),
              ],
            ),
            const SizedBox(height: 10),

            // Notes
            _VitalField(
              controller: _notesCtrl,
              label: 'Other Notes',
              icon: Icons.note_outlined,
              keyboardType: TextInputType.text,
            ),
            const SizedBox(height: 16),

            // Save button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _isSaving ? null : _saveVitals,
                icon: _isSaving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.save, size: 18),
                label: Text(_isSaving ? 'Saving...' : 'Save Vitals'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _VitalField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final ValueChanged<String>? onChanged;

  const _VitalField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(fontSize: 11),
        prefixIcon: Icon(icon, size: 16),
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      ),
      style: const TextStyle(fontSize: 14),
    );
  }
}
