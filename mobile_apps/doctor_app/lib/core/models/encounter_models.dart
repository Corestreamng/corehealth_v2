/// Full encounter data from /api/mobile/doctor/encounters/{id}
class EncounterData {
  final int id;
  final int patientId;
  final int? doctorId;
  final int? serviceRequestId;
  final bool completed;
  final String? notes;
  final String? doctorDiagnosis;
  final String? diagnosisApplicable;
  final List<String> reasonsForEncounter;
  final String? reasonsComment1;
  final String? reasonsComment2;
  final String createdAt;

  // Nested data
  final PatientInfo patient;
  final ClinicInfo? clinic;
  final List<VitalSign> vitals;
  final List<LabRequest> labs;
  final List<ImagingRequest> imaging;
  final List<Prescription> prescriptions;
  final List<ProcedureData> procedures;
  final Map<String, dynamic>? settings;

  EncounterData({
    required this.id,
    required this.patientId,
    this.doctorId,
    this.serviceRequestId,
    required this.completed,
    this.notes,
    this.doctorDiagnosis,
    this.diagnosisApplicable,
    required this.reasonsForEncounter,
    this.reasonsComment1,
    this.reasonsComment2,
    required this.createdAt,
    required this.patient,
    this.clinic,
    required this.vitals,
    required this.labs,
    required this.imaging,
    required this.prescriptions,
    required this.procedures,
    this.settings,
  });

  factory EncounterData.fromJson(Map<String, dynamic> json) {
    final enc = json['encounter'] ?? json;
    return EncounterData(
      id: enc['id'] ?? 0,
      patientId: enc['patient_id'] ?? 0,
      doctorId: enc['doctor_id'],
      serviceRequestId: enc['service_request_id'],
      completed: enc['completed'] == true || enc['completed'] == 1,
      notes: enc['notes'],
      doctorDiagnosis: enc['doctor_diagnosis'],
      diagnosisApplicable: enc['diagnosis_applicable'],
      reasonsForEncounter: enc['reasons_for_encounter'] is List
          ? List<String>.from(enc['reasons_for_encounter'])
          : [],
      reasonsComment1: enc['reasons_for_encounter_comment_1'],
      reasonsComment2: enc['reasons_for_encounter_comment_2'],
      createdAt: enc['created_at'] ?? '',
      patient: PatientInfo.fromJson(json['patient'] ?? {}),
      clinic: json['clinic'] != null
          ? ClinicInfo.fromJson(json['clinic'])
          : null,
      vitals: (json['vitals'] as List?)
              ?.map((v) => VitalSign.fromJson(v))
              .toList() ??
          [],
      labs: (json['labs'] as List?)
              ?.map((l) => LabRequest.fromJson(l))
              .toList() ??
          [],
      imaging: (json['imaging'] as List?)
              ?.map((i) => ImagingRequest.fromJson(i))
              .toList() ??
          [],
      prescriptions: (json['prescriptions'] as List?)
              ?.map((p) => Prescription.fromJson(p))
              .toList() ??
          [],
      procedures: (json['procedures'] as List?)
              ?.map((p) => ProcedureData.fromJson(p))
              .toList() ??
          [],
      settings: json['settings'],
    );
  }
}

class PatientInfo {
  final int id;
  final String name;
  final String fileNo;
  final String gender;
  final String? dob;
  final String? bloodGroup;
  final String? genotype;
  final List<String> allergies;
  final String? phone;
  final String? address;
  final String? hmoName;
  final String? hmoNo;
  final String? insuranceScheme;
  final String? medicalHistory;

  PatientInfo({
    required this.id,
    required this.name,
    required this.fileNo,
    required this.gender,
    this.dob,
    this.bloodGroup,
    this.genotype,
    required this.allergies,
    this.phone,
    this.address,
    this.hmoName,
    this.hmoNo,
    this.insuranceScheme,
    this.medicalHistory,
  });

  factory PatientInfo.fromJson(Map<String, dynamic> json) {
    List<String> parseAllergies(dynamic v) {
      if (v is List) return List<String>.from(v);
      if (v is String && v.isNotEmpty) return [v];
      return [];
    }

    return PatientInfo(
      id: json['id'] ?? 0,
      name: json['name'] ?? 'Unknown',
      fileNo: json['file_no'] ?? '',
      gender: json['gender'] ?? '',
      dob: json['dob'],
      bloodGroup: json['blood_group'],
      genotype: json['genotype'],
      allergies: parseAllergies(json['allergies']),
      phone: json['phone'],
      address: json['address'],
      hmoName: json['hmo_name'],
      hmoNo: json['hmo_no'],
      insuranceScheme: json['insurance_scheme'],
      medicalHistory: json['medical_history'],
    );
  }

  String get age {
    if (dob == null || dob!.isEmpty) return '';
    try {
      final birth = DateTime.parse(dob!);
      final now = DateTime.now();
      final years = now.year - birth.year -
          (now.month < birth.month ||
                  (now.month == birth.month && now.day < birth.day)
              ? 1
              : 0);
      if (years < 1) {
        final months = (now.year - birth.year) * 12 + now.month - birth.month;
        return '$months months';
      }
      return '$years years';
    } catch (_) {
      return '';
    }
  }
}

class ClinicInfo {
  final int id;
  final String name;

  ClinicInfo({required this.id, required this.name});

  factory ClinicInfo.fromJson(Map<String, dynamic> json) {
    return ClinicInfo(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
    );
  }
}

class VitalSign {
  final int id;
  final double? temperature;
  final int? systolicBp;
  final int? diastolicBp;
  final int? heartRate;
  final int? respiratoryRate;
  final int? spo2;
  final double? weight;
  final double? height;
  final double? bloodSugar;
  final int? painLevel;
  final String? timeTaken;
  final String? takenBy;

  VitalSign({
    required this.id,
    this.temperature,
    this.systolicBp,
    this.diastolicBp,
    this.heartRate,
    this.respiratoryRate,
    this.spo2,
    this.weight,
    this.height,
    this.bloodSugar,
    this.painLevel,
    this.timeTaken,
    this.takenBy,
  });

  factory VitalSign.fromJson(Map<String, dynamic> json) {
    return VitalSign(
      id: json['id'] ?? 0,
      temperature: _toDouble(json['temperature']),
      systolicBp: _toInt(json['systolic_bp']),
      diastolicBp: _toInt(json['diastolic_bp']),
      heartRate: _toInt(json['heart_rate']),
      respiratoryRate: _toInt(json['respiratory_rate']),
      spo2: _toInt(json['spo2']),
      weight: _toDouble(json['weight']),
      height: _toDouble(json['height']),
      bloodSugar: _toDouble(json['blood_sugar']),
      painLevel: _toInt(json['pain_level']),
      timeTaken: json['time_taken'] ?? json['created_at'],
      takenBy: json['taken_by'],
    );
  }

  static double? _toDouble(dynamic v) {
    if (v == null) return null;
    if (v is double) return v;
    if (v is int) return v.toDouble();
    return double.tryParse(v.toString());
  }

  static int? _toInt(dynamic v) {
    if (v == null) return null;
    if (v is int) return v;
    return int.tryParse(v.toString());
  }

  String get bpDisplay {
    if (systolicBp == null || diastolicBp == null) return '—';
    return '$systolicBp/$diastolicBp';
  }

  double? get bmi {
    if (weight == null || height == null || height == 0) return null;
    final h = height! / 100;
    return weight! / (h * h);
  }
}

class LabRequest {
  final int id;
  final int? serviceId;
  final String serviceName;
  final String? note;
  final String status;
  final int statusCode;
  final String? result;
  final dynamic resultData;
  final String? resultDate;
  final String? resultBy;
  final bool sampleTaken;
  final String createdAt;

  LabRequest({
    required this.id,
    this.serviceId,
    required this.serviceName,
    this.note,
    required this.status,
    required this.statusCode,
    this.result,
    this.resultData,
    this.resultDate,
    this.resultBy,
    required this.sampleTaken,
    required this.createdAt,
  });

  factory LabRequest.fromJson(Map<String, dynamic> json) {
    return LabRequest(
      id: json['id'] ?? 0,
      serviceId: json['service_id'],
      serviceName: json['service_name'] ?? json['service']?['service_name'] ?? 'Unknown',
      note: json['note'],
      status: json['status_label'] ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      result: json['result'],
      resultData: json['result_data'],
      resultDate: json['result_date'],
      resultBy: json['result_by_name'] ?? json['result_by']?.toString(),
      sampleTaken: json['sample_taken'] == true || json['sample_taken'] == 1,
      createdAt: json['created_at'] ?? '',
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Sample Taken';
      case 3: return 'Results Ready';
      default: return 'Unknown';
    }
  }
}

class ImagingRequest {
  final int id;
  final int? serviceId;
  final String serviceName;
  final String? note;
  final String status;
  final int statusCode;
  final String? result;
  final dynamic resultData;
  final String? resultDate;
  final String? resultBy;
  final String createdAt;

  ImagingRequest({
    required this.id,
    this.serviceId,
    required this.serviceName,
    this.note,
    required this.status,
    required this.statusCode,
    this.result,
    this.resultData,
    this.resultDate,
    this.resultBy,
    required this.createdAt,
  });

  factory ImagingRequest.fromJson(Map<String, dynamic> json) {
    return ImagingRequest(
      id: json['id'] ?? 0,
      serviceId: json['service_id'],
      serviceName: json['service_name'] ?? json['service']?['service_name'] ?? 'Unknown',
      note: json['note'],
      status: json['status_label'] ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      result: json['result'],
      resultData: json['result_data'],
      resultDate: json['result_date'],
      resultBy: json['result_by_name'] ?? json['result_by']?.toString(),
      createdAt: json['created_at'] ?? '',
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Results Ready';
      default: return 'Unknown';
    }
  }
}

class Prescription {
  final int id;
  final int? productId;
  final String productName;
  final String? dose;
  final int? qty;
  final String status;
  final int statusCode;
  final String createdAt;

  Prescription({
    required this.id,
    this.productId,
    required this.productName,
    this.dose,
    this.qty,
    required this.status,
    required this.statusCode,
    required this.createdAt,
  });

  factory Prescription.fromJson(Map<String, dynamic> json) {
    return Prescription(
      id: json['id'] ?? 0,
      productId: json['product_id'],
      productName: json['product_name'] ?? json['product']?['product_name'] ?? 'Unknown',
      dose: json['dose'],
      qty: json['qty'],
      status: json['status_label'] ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      createdAt: json['created_at'] ?? '',
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Dispensed';
      default: return 'Unknown';
    }
  }
}

class ProcedureData {
  final int id;
  final int? serviceId;
  final String serviceName;
  final String procedureStatus;
  final String priority;
  final String? scheduledDate;
  final String? scheduledTime;
  final String? preNotes;
  final String? postNotes;
  final String? operatingRoom;
  final String? outcome;
  final String? requestedBy;
  final String? requestedOn;
  final String? cancellationReason;
  final String createdAt;

  ProcedureData({
    required this.id,
    this.serviceId,
    required this.serviceName,
    required this.procedureStatus,
    required this.priority,
    this.scheduledDate,
    this.scheduledTime,
    this.preNotes,
    this.postNotes,
    this.operatingRoom,
    this.outcome,
    this.requestedBy,
    this.requestedOn,
    this.cancellationReason,
    required this.createdAt,
  });

  factory ProcedureData.fromJson(Map<String, dynamic> json) {
    return ProcedureData(
      id: json['id'] ?? 0,
      serviceId: json['service_id'],
      serviceName: json['service_name'] ?? json['service']?['service_name'] ?? 'Unknown',
      procedureStatus: json['procedure_status'] ?? 'requested',
      priority: json['priority'] ?? 'routine',
      scheduledDate: json['scheduled_date'],
      scheduledTime: json['scheduled_time'],
      preNotes: json['pre_notes'],
      postNotes: json['post_notes'],
      operatingRoom: json['operating_room'],
      outcome: json['outcome'],
      requestedBy: json['requested_by_name'] ?? json['requested_by']?.toString(),
      requestedOn: json['requested_on'],
      cancellationReason: json['cancellation_reason'],
      createdAt: json['created_at'] ?? '',
    );
  }
}

/// Diagnosis code from ICPC-2 search.
class DiagnosisCode {
  final int id;
  final String code;
  final String name;
  final String? category;
  final String? subCategory;
  final String display;

  DiagnosisCode({
    required this.id,
    required this.code,
    required this.name,
    this.category,
    this.subCategory,
    required this.display,
  });

  factory DiagnosisCode.fromJson(Map<String, dynamic> json) {
    return DiagnosisCode(
      id: json['id'] ?? 0,
      code: json['code'] ?? '',
      name: json['name'] ?? '',
      category: json['category'],
      subCategory: json['sub_category'],
      display: json['display'] ?? '${json['code']} - ${json['name']}',
    );
  }
}

/// Encounter history item.
class EncounterHistoryItem {
  final int id;
  final String date;
  final String doctorName;
  final String clinicName;
  final bool completed;
  final int labCount;
  final int imagingCount;
  final int prescriptionCount;
  final int procedureCount;

  EncounterHistoryItem({
    required this.id,
    required this.date,
    required this.doctorName,
    required this.clinicName,
    required this.completed,
    required this.labCount,
    required this.imagingCount,
    required this.prescriptionCount,
    required this.procedureCount,
  });

  factory EncounterHistoryItem.fromJson(Map<String, dynamic> json) {
    return EncounterHistoryItem(
      id: json['id'] ?? 0,
      date: json['date'] ?? json['created_at'] ?? '',
      doctorName: json['doctor_name'] ?? '',
      clinicName: json['clinic_name'] ?? '',
      completed: json['completed'] == true || json['completed'] == 1,
      labCount: json['lab_count'] ?? 0,
      imagingCount: json['imaging_count'] ?? 0,
      prescriptionCount: json['prescription_count'] ?? 0,
      procedureCount: json['procedure_count'] ?? 0,
    );
  }
}
