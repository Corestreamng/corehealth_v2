import 'package:flutter/material.dart';
import '../../core/api/patient_api_service.dart';
import '../../core/models/patient_models.dart';
import '../../core/storage/local_storage.dart';
import '../../core/widgets/paginated_list.dart';
import '../../core/widgets/shared_widgets.dart';

/// Shows the patient's vitals history — paginated.
class VitalsScreen extends StatefulWidget {
  const VitalsScreen({super.key});

  @override
  State<VitalsScreen> createState() => _VitalsScreenState();
}

class _VitalsScreenState extends State<VitalsScreen> {
  late PatientApiService _api;

  @override
  void initState() {
    super.initState();
    _api = PatientApiService(LocalStorage.baseUrl ?? '');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Vitals')),
      body: PaginatedList<PatientVital>(
        fetcher: (page) => _api.getVitals(page: page),
        parser: (j) => PatientVital.fromJson(j),
        emptyIcon: Icons.monitor_heart_rounded,
        emptyTitle: 'No vitals recorded',
        emptySubtitle: 'Your vital sign history will appear here',
        itemBuilder: (ctx, vital) => Card(
          margin: const EdgeInsets.only(bottom: 10),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (vital.createdAt != null)
                  Text(vital.createdAt!,
                      style: TextStyle(
                          fontSize: 10, color: Colors.grey.shade500)),
                const SizedBox(height: 8),
                GridView.count(
                  crossAxisCount: 3,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 6,
                  crossAxisSpacing: 6,
                  childAspectRatio: 1.5,
                  children: [
                    if (vital.temperature != null)
                      VitalCard(
                        label: 'Temperature',
                        value: '${vital.temperature}°',
                        unit: '°C',
                        icon: Icons.thermostat,
                        overrideColor:
                            VitalCard.vitalColor('temp', vital.temperature),
                      ),
                    if (vital.systolic != null)
                      VitalCard(
                        label: 'Blood Pressure',
                        value: vital.bpDisplay,
                        unit: 'mmHg',
                        icon: Icons.favorite,
                        overrideColor: VitalCard.vitalColor(
                            'bp', vital.systolic?.toDouble()),
                      ),
                    if (vital.heartRate != null)
                      VitalCard(
                        label: 'Heart Rate',
                        value: '${vital.heartRate}',
                        unit: 'bpm',
                        icon: Icons.monitor_heart,
                        overrideColor: VitalCard.vitalColor(
                            'hr', vital.heartRate?.toDouble()),
                      ),
                    if (vital.respiratoryRate != null)
                      VitalCard(
                        label: 'Resp Rate',
                        value: '${vital.respiratoryRate}',
                        unit: '/min',
                        icon: Icons.air,
                        overrideColor: VitalCard.vitalColor(
                            'rr', vital.respiratoryRate?.toDouble()),
                      ),
                    if (vital.spo2 != null)
                      VitalCard(
                        label: 'SpO₂',
                        value: '${vital.spo2}%',
                        icon: Icons.opacity,
                        overrideColor:
                            VitalCard.vitalColor('spo2', vital.spo2),
                      ),
                    if (vital.weight != null)
                      VitalCard(
                        label: 'Weight',
                        value: '${vital.weight}',
                        unit: 'kg',
                        icon: Icons.fitness_center,
                      ),
                    if (vital.bloodSugar != null)
                      VitalCard(
                        label: 'Blood Sugar',
                        value: '${vital.bloodSugar}',
                        unit: 'mg/dL',
                        icon: Icons.water_drop,
                        overrideColor:
                            VitalCard.vitalColor('sugar', vital.bloodSugar),
                      ),
                    if (vital.bmi != null)
                      VitalCard(
                        label: 'BMI',
                        value: vital.bmi!.toStringAsFixed(1),
                        icon: Icons.accessibility_new,
                        overrideColor:
                            VitalCard.vitalColor('bmi', vital.bmi),
                      ),
                    if (vital.painScore != null)
                      VitalCard(
                        label: 'Pain',
                        value: '${vital.painScore}/10',
                        icon: Icons.sentiment_dissatisfied,
                        overrideColor: VitalCard.vitalColor(
                            'pain', vital.painScore?.toDouble()),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
