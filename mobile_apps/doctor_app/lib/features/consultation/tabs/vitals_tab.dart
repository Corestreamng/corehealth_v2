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
  final _painCtrl = TextEditingController();

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
    _painCtrl.dispose();
    super.dispose();
  }

  Future<void> _saveVitals() async {
    // At least one field must be filled
    final hasValue = [
      _tempCtrl, _sysCtrl, _diaCtrl, _hrCtrl, _rrCtrl,
      _spo2Ctrl, _weightCtrl, _heightCtrl, _sugarCtrl, _painCtrl,
    ].any((c) => c.text.trim().isNotEmpty);

    if (!hasValue) {
      showErrorSnackBar(context, 'Please fill at least one vital sign');
      return;
    }

    setState(() => _isSaving = true);

    final res = await widget.api.recordVitals(
      patientId: widget.encounter.patientId,
      encounterId: widget.encounter.id,
      temperature: double.tryParse(_tempCtrl.text),
      systolicBp: int.tryParse(_sysCtrl.text),
      diastolicBp: int.tryParse(_diaCtrl.text),
      heartRate: int.tryParse(_hrCtrl.text),
      respiratoryRate: int.tryParse(_rrCtrl.text),
      spo2: int.tryParse(_spo2Ctrl.text),
      weight: double.tryParse(_weightCtrl.text),
      height: double.tryParse(_heightCtrl.text),
      bloodSugar: double.tryParse(_sugarCtrl.text),
      painLevel: int.tryParse(_painCtrl.text),
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res.success) {
      showSuccessSnackBar(context, 'Vitals recorded successfully');
      _clearForm();
      setState(() => _showForm = false);
      widget.onVitalsRecorded();
    } else {
      showErrorSnackBar(
          context, res.message.isNotEmpty ? res.message : 'Failed to save vitals');
    }
  }

  void _clearForm() {
    for (final c in [
      _tempCtrl, _sysCtrl, _diaCtrl, _hrCtrl, _rrCtrl,
      _spo2Ctrl, _weightCtrl, _heightCtrl, _sugarCtrl, _painCtrl,
    ]) {
      c.clear();
    }
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
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _VitalField(
                    controller: _heightCtrl,
                    label: 'Height (cm)',
                    icon: Icons.height,
                    keyboardType: TextInputType.number,
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
                Expanded(
                  child: _VitalField(
                    controller: _painCtrl,
                    label: 'Pain (0–10)',
                    icon: Icons.sentiment_dissatisfied_outlined,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
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

  const _VitalField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
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
