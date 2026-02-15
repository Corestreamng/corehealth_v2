// Patient-side data models for encounters and health records.

class PatientEncounter {
  final int id;
  final String? doctorName;
  final String? clinicName;
  final String? diagnosis;
  final String? notes;
  final String? createdAt;
  final int labCount;
  final int imagingCount;
  final int prescriptionCount;
  final bool completed;

  PatientEncounter({
    required this.id,
    this.doctorName,
    this.clinicName,
    this.diagnosis,
    this.notes,
    this.createdAt,
    this.labCount = 0,
    this.imagingCount = 0,
    this.prescriptionCount = 0,
    this.completed = false,
  });

  factory PatientEncounter.fromJson(Map<String, dynamic> j) {
    return PatientEncounter(
      id: j['id'] ?? j['encounter_id'] ?? 0,
      doctorName: j['doctor_name']?.toString() ??
          j['doctor']?['name']?.toString(),
      clinicName: j['clinic_name']?.toString() ??
          j['clinic']?['clinic_name']?.toString(),
      diagnosis: j['doctor_diagnosis']?.toString(),
      notes: j['notes']?.toString(),
      createdAt: j['created_at']?.toString(),
      labCount: _toInt(j['lab_count']) ?? 0,
      imagingCount: _toInt(j['imaging_count']) ?? 0,
      prescriptionCount: _toInt(j['prescription_count']) ?? 0,
      completed: j['deleted_at'] != null || j['status'] == 'completed',
    );
  }
}

class PatientEncounterDetail {
  final int id;
  final String? doctorName;
  final String? clinicName;
  final String? diagnosis;
  final String? comment1;
  final String? comment2;
  final String? notes;
  final String? createdAt;
  final List<String> reasons;
  final List<PatientVital> vitals;
  final List<PatientLabResult> labs;
  final List<PatientImagingResult> imaging;
  final List<PatientPrescription> prescriptions;
  final List<PatientProcedure> procedures;

  PatientEncounterDetail({
    required this.id,
    this.doctorName,
    this.clinicName,
    this.diagnosis,
    this.comment1,
    this.comment2,
    this.notes,
    this.createdAt,
    this.reasons = const [],
    this.vitals = const [],
    this.labs = const [],
    this.imaging = const [],
    this.prescriptions = const [],
    this.procedures = const [],
  });

  factory PatientEncounterDetail.fromJson(Map<String, dynamic> j) {
    final enc = j['encounter'] ?? j;
    List<String> reasons = [];
    if (enc['reasons_for_encounter'] != null) {
      if (enc['reasons_for_encounter'] is List) {
        reasons = (enc['reasons_for_encounter'] as List)
            .map((r) => r is Map ? (r['name']?.toString() ?? '') : r.toString())
            .where((s) => s.isNotEmpty)
            .toList();
      }
    }

    return PatientEncounterDetail(
      id: enc['id'] ?? enc['encounter_id'] ?? 0,
      doctorName: enc['doctor_name']?.toString() ??
          enc['doctor']?['name']?.toString(),
      clinicName: enc['clinic_name']?.toString() ??
          enc['clinic']?['clinic_name']?.toString(),
      diagnosis: enc['doctor_diagnosis']?.toString(),
      comment1: enc['comment_1']?.toString(),
      comment2: enc['comment_2']?.toString(),
      notes: enc['notes']?.toString(),
      createdAt: enc['created_at']?.toString(),
      reasons: reasons,
      vitals: (enc['vitals'] as List? ?? [])
          .map((v) => PatientVital.fromJson(v))
          .toList(),
      labs: (enc['labs'] as List? ?? [])
          .map((l) => PatientLabResult.fromJson(l))
          .toList(),
      imaging: (enc['imaging'] as List? ?? [])
          .map((i) => PatientImagingResult.fromJson(i))
          .toList(),
      prescriptions: (enc['prescriptions'] as List? ?? [])
          .map((p) => PatientPrescription.fromJson(p))
          .toList(),
      procedures: (enc['procedures'] as List? ?? [])
          .map((p) => PatientProcedure.fromJson(p))
          .toList(),
    );
  }
}

class PatientVital {
  final int? id;
  final double? temperature;
  final int? systolic;
  final int? diastolic;
  final int? heartRate;
  final int? respiratoryRate;
  final double? spo2;
  final double? weight;
  final double? height;
  final double? bloodSugar;
  final int? painScore;
  final String? createdAt;

  PatientVital({
    this.id,
    this.temperature,
    this.systolic,
    this.diastolic,
    this.heartRate,
    this.respiratoryRate,
    this.spo2,
    this.weight,
    this.height,
    this.bloodSugar,
    this.painScore,
    this.createdAt,
  });

  factory PatientVital.fromJson(Map<String, dynamic> j) {
    return PatientVital(
      id: _toInt(j['id']),
      temperature: _toDouble(j['temperature']),
      systolic: _toInt(j['systolic_bp'] ?? j['systolic']),
      diastolic: _toInt(j['diastolic_bp'] ?? j['diastolic']),
      heartRate: _toInt(j['heart_rate'] ?? j['pulse']),
      respiratoryRate: _toInt(j['respiratory_rate']),
      spo2: _toDouble(j['spo2'] ?? j['oxygen_saturation']),
      weight: _toDouble(j['weight']),
      height: _toDouble(j['height']),
      bloodSugar: _toDouble(j['blood_sugar']),
      painScore: _toInt(j['pain_score']),
      createdAt: j['created_at']?.toString(),
    );
  }

  String get bpDisplay =>
      (systolic != null && diastolic != null) ? '$systolic/$diastolic' : '—';

  double? get bmi {
    if (weight != null && height != null && height! > 0) {
      final hm = height! / 100;
      return weight! / (hm * hm);
    }
    return null;
  }
}

class PatientLabResult {
  final int id;
  final String? serviceName;
  final String? result;
  final String? resultDate;
  final String? note;
  final int status;
  final String? createdAt;

  PatientLabResult({
    required this.id,
    this.serviceName,
    this.result,
    this.resultDate,
    this.note,
    this.status = 1,
    this.createdAt,
  });

  factory PatientLabResult.fromJson(Map<String, dynamic> j) {
    return PatientLabResult(
      id: j['id'] ?? j['lab_service_request_id'] ?? 0,
      serviceName: j['service_name']?.toString() ??
          j['service']?['service_name']?.toString(),
      result: j['result']?.toString(),
      resultDate: j['result_date']?.toString(),
      note: j['note']?.toString(),
      status: _toInt(j['status']) ?? 1,
      createdAt: j['created_at']?.toString(),
    );
  }

  String get statusLabel {
    switch (status) {
      case 3:
        return 'Results Ready';
      case 2:
        return 'Sample Taken';
      default:
        return 'Requested';
    }
  }
}

class PatientImagingResult {
  final int id;
  final String? serviceName;
  final String? result;
  final String? resultDate;
  final String? note;
  final int status;
  final String? createdAt;

  PatientImagingResult({
    required this.id,
    this.serviceName,
    this.result,
    this.resultDate,
    this.note,
    this.status = 1,
    this.createdAt,
  });

  factory PatientImagingResult.fromJson(Map<String, dynamic> j) {
    return PatientImagingResult(
      id: j['id'] ?? j['imaging_service_request_id'] ?? 0,
      serviceName: j['service_name']?.toString() ??
          j['service']?['service_name']?.toString(),
      result: j['result']?.toString(),
      resultDate: j['result_date']?.toString(),
      note: j['note']?.toString(),
      status: _toInt(j['status']) ?? 1,
      createdAt: j['created_at']?.toString(),
    );
  }

  String get statusLabel {
    switch (status) {
      case 3:
        return 'Results Ready';
      case 2:
        return 'Billed';
      default:
        return 'Requested';
    }
  }
}

class PatientPrescription {
  final int id;
  final String? productName;
  final String? dose;
  final int? qty;
  final int status;
  final String? createdAt;

  PatientPrescription({
    required this.id,
    this.productName,
    this.dose,
    this.qty,
    this.status = 1,
    this.createdAt,
  });

  factory PatientPrescription.fromJson(Map<String, dynamic> j) {
    return PatientPrescription(
      id: j['id'] ?? j['product_request_id'] ?? 0,
      productName: j['product_name']?.toString() ??
          j['product']?['product_name']?.toString(),
      dose: j['dose']?.toString(),
      qty: _toInt(j['qty']),
      status: _toInt(j['status']) ?? 1,
      createdAt: j['created_at']?.toString(),
    );
  }

  String get statusLabel {
    switch (status) {
      case 3:
        return 'Dispensed';
      case 2:
        return 'Billed';
      default:
        return 'Prescribed';
    }
  }
}

class PatientProcedure {
  final int id;
  final String? serviceName;
  final String? procedureStatus;
  final String? priority;
  final String? scheduledDate;
  final String? outcome;
  final String? createdAt;

  PatientProcedure({
    required this.id,
    this.serviceName,
    this.procedureStatus,
    this.priority,
    this.scheduledDate,
    this.outcome,
    this.createdAt,
  });

  factory PatientProcedure.fromJson(Map<String, dynamic> j) {
    return PatientProcedure(
      id: j['id'] ?? j['procedure_id'] ?? 0,
      serviceName: j['service_name']?.toString() ??
          j['service']?['service_name']?.toString(),
      procedureStatus: j['procedure_status']?.toString(),
      priority: j['priority']?.toString(),
      scheduledDate: j['scheduled_date']?.toString(),
      outcome: j['outcome']?.toString(),
      createdAt: j['created_at']?.toString(),
    );
  }
}

// ─── Helpers ─────────────────────────────────────────────────────
int? _toInt(dynamic v) {
  if (v == null) return null;
  if (v is int) return v;
  if (v is double) return v.toInt();
  return int.tryParse(v.toString());
}

double? _toDouble(dynamic v) {
  if (v == null) return null;
  if (v is double) return v;
  if (v is int) return v.toDouble();
  return double.tryParse(v.toString());
}
