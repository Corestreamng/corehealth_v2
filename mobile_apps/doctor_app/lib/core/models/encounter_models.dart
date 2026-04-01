import 'dart:convert';

/// Safe String? conversion – handles int, double, bool, etc.
String? _str(dynamic v) => v?.toString();

/// A single diagnosis entry with per-diagnosis status/course.
class DiagnosisEntry {
  final String code;
  final String name;
  final String display;
  String status;  // N/A, Query, Differential, Confirmed
  String course;  // N/A, Acute, Chronic, Recurrent

  DiagnosisEntry({
    required this.code,
    required this.name,
    required this.display,
    this.status = 'N/A',
    this.course = 'N/A',
  });

  factory DiagnosisEntry.fromJson(Map<String, dynamic> json) {
    return DiagnosisEntry(
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      display: json['display']?.toString() ??
          json['value']?.toString() ??
          '${json['code'] ?? ''} - ${json['name'] ?? ''}',
      status: json['comment_1']?.toString() ?? 'N/A',
      course: json['comment_2']?.toString() ?? 'N/A',
    );
  }

  /// Parse from legacy "CODE-Name" string.
  factory DiagnosisEntry.fromLegacy(String s) {
    final parts = s.split('-');
    final code = parts.isNotEmpty ? parts[0].trim() : s;
    final name = parts.length > 1 ? parts.sublist(1).join('-').trim() : '';
    return DiagnosisEntry(
      code: code,
      name: name,
      display: s,
    );
  }

  Map<String, dynamic> toJson() => {
    'code': code,
    'name': name,
    'value': '$code-$name',
    'comment_1': status,
    'comment_2': course,
  };
}

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
  final List<DiagnosisEntry> diagnosisEntries;
  final List<String> reasonsForEncounter; // legacy display strings
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
  final List<Referral> referrals;
  final Map<String, dynamic>? settings;

  bool get requireDiagnosis => settings?['require_diagnosis'] == true;
  int get noteEditDuration => settings?['note_edit_duration'] ?? 30;

  EncounterData({
    required this.id,
    required this.patientId,
    this.doctorId,
    this.serviceRequestId,
    required this.completed,
    this.notes,
    this.doctorDiagnosis,
    this.diagnosisApplicable,
    this.diagnosisEntries = const [],
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
    this.referrals = const [],
    this.settings,
  });

  factory EncounterData.fromJson(Map<String, dynamic> json) {
    final enc = json['encounter'] ?? json;

    // Parse reasons — handle JSON array of objects, JSON string, comma-separated string, or list
    final rawReasons = enc['reasons_for_encounter'];
    List<DiagnosisEntry> diagEntries = [];
    List<String> reasonStrings = [];

    if (rawReasons is List) {
      for (final item in rawReasons) {
        if (item is Map<String, dynamic>) {
          diagEntries.add(DiagnosisEntry.fromJson(item));
          reasonStrings.add(item['value']?.toString() ?? item['display']?.toString() ?? '');
        } else {
          final s = item.toString();
          diagEntries.add(DiagnosisEntry.fromLegacy(s));
          reasonStrings.add(s);
        }
      }
    } else if (rawReasons is String && rawReasons.isNotEmpty) {
      // Try JSON decode first (backend may store as JSON string)
      try {
        final decoded = jsonDecode(rawReasons);
        if (decoded is List) {
          for (final item in decoded) {
            if (item is Map<String, dynamic>) {
              diagEntries.add(DiagnosisEntry.fromJson(item));
              reasonStrings.add(item['value']?.toString() ?? item['display']?.toString() ?? '');
            } else {
              final s = item.toString();
              diagEntries.add(DiagnosisEntry.fromLegacy(s));
              reasonStrings.add(s);
            }
          }
        }
      } catch (_) {
        // Not JSON — comma-separated legacy string
        for (final s in rawReasons.split(',')) {
          final t = s.trim();
          if (t.isNotEmpty) {
            diagEntries.add(DiagnosisEntry.fromLegacy(t));
            reasonStrings.add(t);
          }
        }
      }
    }

    // Also check existing_diagnosis from startEncounter response
    final existingDiag = json['existing_diagnosis'];
    if (existingDiag is List && diagEntries.isEmpty) {
      for (final item in existingDiag) {
        if (item is Map<String, dynamic>) {
          diagEntries.add(DiagnosisEntry.fromJson(item));
          reasonStrings.add(item['value']?.toString() ?? item['display']?.toString() ?? '');
        } else {
          final s = item.toString();
          diagEntries.add(DiagnosisEntry.fromLegacy(s));
          reasonStrings.add(s);
        }
      }
    }
    return EncounterData(
      id: enc['id'] ?? 0,
      patientId: enc['patient_id'] ?? 0,
      doctorId: enc['doctor_id'],
      serviceRequestId: enc['service_request_id'],
      completed: enc['completed'] == true || enc['completed'] == 1,
      notes: _str(enc['notes']),
      doctorDiagnosis: _str(enc['doctor_diagnosis']),
      diagnosisApplicable: _str(enc['diagnosis_applicable']),
      diagnosisEntries: diagEntries,
      reasonsForEncounter: reasonStrings,
      reasonsComment1: _str(enc['reasons_for_encounter_comment_1']),
      reasonsComment2: _str(enc['reasons_for_encounter_comment_2']),
      createdAt: _str(enc['created_at']) ?? '',
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
      referrals: (json['referrals'] as List?)
              ?.map((r) => Referral.fromJson(r))
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
  // Extended fields
  final String? nationality;
  final String? ethnicity;
  final String? disability;
  final String? nokName;
  final String? nokPhone;
  final String? nokAddress;
  final String? nokRelationship;
  final String? photoUrl;
  final String? email;
  final String? occupation;

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
    this.nationality,
    this.ethnicity,
    this.disability,
    this.nokName,
    this.nokPhone,
    this.nokAddress,
    this.nokRelationship,
    this.photoUrl,
    this.email,
    this.occupation,
  });

  factory PatientInfo.fromJson(Map<String, dynamic> json) {
    List<String> parseAllergies(dynamic v) {
      if (v is List) return List<String>.from(v);
      if (v is String && v.isNotEmpty) return [v];
      return [];
    }

    return PatientInfo(
      id: json['id'] ?? 0,
      name: _str(json['name']) ?? 'Unknown',
      fileNo: _str(json['file_no']) ?? '',
      gender: _str(json['gender']) ?? '',
      dob: _str(json['dob']),
      bloodGroup: _str(json['blood_group']),
      genotype: _str(json['genotype']),
      allergies: parseAllergies(json['allergies']),
      phone: _str(json['phone']),
      address: _str(json['address']),
      hmoName: _str(json['hmo_name']),
      hmoNo: _str(json['hmo_no']),
      insuranceScheme: _str(json['insurance_scheme']),
      medicalHistory: _str(json['medical_history']),
      nationality: _str(json['nationality']),
      ethnicity: _str(json['ethnicity']),
      disability: _str(json['disability']),
      nokName: _str(json['nok_name'] ?? json['next_of_kin_name']),
      nokPhone: _str(json['nok_phone'] ?? json['next_of_kin_phone']),
      nokAddress: _str(json['nok_address'] ?? json['next_of_kin_address']),
      nokRelationship: _str(json['nok_relationship'] ?? json['next_of_kin_relationship']),
      photoUrl: _str(json['photo_url'] ?? json['photo']),
      email: _str(json['email']),
      occupation: _str(json['occupation']),
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
    // Parse blood_pressure "120/80" into systolic/diastolic parts
    int? sysBp = _toInt(json['systolic_bp']);
    int? diaBp = _toInt(json['diastolic_bp']);
    if (sysBp == null && json['blood_pressure'] != null) {
      final parts = json['blood_pressure'].toString().split('/');
      if (parts.length == 2) {
        sysBp = int.tryParse(parts[0].trim());
        diaBp = int.tryParse(parts[1].trim());
      }
    }

    return VitalSign(
      id: json['id'] ?? 0,
      temperature: _toDouble(json['temperature']),
      systolicBp: sysBp,
      diastolicBp: diaBp,
      heartRate: _toInt(json['heart_rate']),
      respiratoryRate: _toInt(json['respiratory_rate']),
      spo2: _toInt(json['spo2']),
      weight: _toDouble(json['weight']),
      height: _toDouble(json['height']),
      bloodSugar: _toDouble(json['blood_sugar']),
      painLevel: _toInt(json['pain_level'] ?? json['pain_score']),
      timeTaken: _str(json['time_taken'] ?? json['created_at']),
      takenBy: _str(json['taken_by']),
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
  // Extended fields
  final String? priority;
  final String? labNumber;
  final String? billedByName;
  final String? sampleTakenByName;
  final String? sampleDate;
  final String? billedDate;
  final String? approvedAt;
  final String? approvedByName;
  final String? rejectionReason;
  final String? doctorName;
  final List<Map<String, dynamic>> attachments;

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
    this.priority,
    this.labNumber,
    this.billedByName,
    this.sampleTakenByName,
    this.sampleDate,
    this.billedDate,
    this.approvedAt,
    this.approvedByName,
    this.rejectionReason,
    this.doctorName,
    this.attachments = const [],
  });

  factory LabRequest.fromJson(Map<String, dynamic> json) {
    return LabRequest(
      id: json['id'] ?? 0,
      serviceId: json['service_id'],
      serviceName: _str(json['service_name'] ?? json['service']?['service_name']) ?? 'Unknown',
      note: _str(json['note']),
      status: _str(json['status_label']) ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      result: _str(json['result']),
      resultData: json['result_data'],
      resultDate: _str(json['result_date']),
      resultBy: _str(json['result_by_name'] ?? json['result_by']),
      sampleTaken: json['sample_taken'] == true || json['sample_taken'] == 1,
      createdAt: _str(json['created_at']) ?? '',
      priority: _str(json['priority']),
      labNumber: _str(json['lab_number']),
      billedByName: _str(json['billed_by_name'] ?? json['billed_by']),
      sampleTakenByName: _str(json['sample_taken_by_name'] ?? json['sample_taken_by']),
      sampleDate: _str(json['sample_date']),
      billedDate: _str(json['billed_date']),
      approvedAt: _str(json['approved_at']),
      approvedByName: _str(json['approved_by_name'] ?? json['approved_by']),
      rejectionReason: _str(json['rejection_reason']),
      doctorName: _str(json['doctor_name']),
      attachments: json['attachments'] is List
          ? List<Map<String, dynamic>>.from(
              (json['attachments'] as List).whereType<Map>())
          : [],
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Sample Taken';
      case 4: return 'Result Ready';
      case 5: return 'Pending Approval';
      case 6: return 'Rejected';
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
  // Extended fields
  final String? priority;
  final String? billedByName;
  final String? billedDate;
  final String? approvedAt;
  final String? approvedByName;
  final String? rejectionReason;
  final String? doctorName;
  final List<Map<String, dynamic>> attachments;

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
    this.priority,
    this.billedByName,
    this.billedDate,
    this.approvedAt,
    this.approvedByName,
    this.rejectionReason,
    this.doctorName,
    this.attachments = const [],
  });

  factory ImagingRequest.fromJson(Map<String, dynamic> json) {
    return ImagingRequest(
      id: json['id'] ?? 0,
      serviceId: json['service_id'],
      serviceName: _str(json['service_name'] ?? json['service']?['service_name']) ?? 'Unknown',
      note: _str(json['note']),
      status: _str(json['status_label']) ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      result: _str(json['result']),
      resultData: json['result_data'],
      resultDate: _str(json['result_date']),
      resultBy: _str(json['result_by_name'] ?? json['result_by']),
      createdAt: _str(json['created_at']) ?? '',
      priority: _str(json['priority']),
      billedByName: _str(json['billed_by_name'] ?? json['billed_by']),
      billedDate: _str(json['billed_date']),
      approvedAt: _str(json['approved_at']),
      approvedByName: _str(json['approved_by_name'] ?? json['approved_by']),
      rejectionReason: _str(json['rejection_reason']),
      doctorName: _str(json['doctor_name']),
      attachments: json['attachments'] is List
          ? List<Map<String, dynamic>>.from(
              (json['attachments'] as List).whereType<Map>())
          : [],
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Result Ready';
      case 5: return 'Pending Approval';
      case 6: return 'Rejected';
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
  // Extended fields
  final String? frequency;
  final String? duration;
  final String? durationUnit;
  final String? route;
  final String? specialInstruction;
  final String? doctorName;

  Prescription({
    required this.id,
    this.productId,
    required this.productName,
    this.dose,
    this.qty,
    required this.status,
    required this.statusCode,
    required this.createdAt,
    this.frequency,
    this.duration,
    this.durationUnit,
    this.route,
    this.specialInstruction,
    this.doctorName,
  });

  factory Prescription.fromJson(Map<String, dynamic> json) {
    return Prescription(
      id: json['id'] ?? 0,
      productId: json['product_id'],
      productName: _str(json['product_name'] ?? json['product']?['product_name']) ?? 'Unknown',
      dose: _str(json['dose']),
      qty: json['qty'],
      status: _str(json['status_label']) ?? _statusLabel(json['status']),
      statusCode: json['status'] is int ? json['status'] : 1,
      createdAt: _str(json['created_at']) ?? '',
      frequency: _str(json['frequency']),
      duration: _str(json['duration']),
      durationUnit: _str(json['duration_unit']),
      route: _str(json['route']),
      specialInstruction: _str(json['special_instruction']),
      doctorName: _str(json['doctor_name']),
    );
  }

  static String _statusLabel(dynamic status) {
    switch (status) {
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Ready for Dispensing';
      case 4: return 'Dispensed';
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
      serviceName: _str(json['service_name'] ?? json['service']?['service_name']) ?? 'Unknown',
      procedureStatus: _str(json['procedure_status']) ?? 'requested',
      priority: _str(json['priority']) ?? 'routine',
      scheduledDate: _str(json['scheduled_date']),
      scheduledTime: _str(json['scheduled_time']),
      preNotes: _str(json['pre_notes']),
      postNotes: _str(json['post_notes']),
      operatingRoom: _str(json['operating_room']),
      outcome: _str(json['outcome']),
      requestedBy: _str(json['requested_by_name'] ?? json['requested_by']),
      requestedOn: _str(json['requested_on']),
      cancellationReason: _str(json['cancellation_reason']),
      createdAt: _str(json['created_at']) ?? '',
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
      date: _str(json['date'] ?? json['created_at']) ?? '',
      doctorName: _str(json['doctor_name']) ?? '',
      clinicName: _str(json['clinic_name']) ?? '',
      completed: json['completed'] == true || json['completed'] == 1,
      labCount: json['lab_count'] ?? 0,
      imagingCount: json['imaging_count'] ?? 0,
      prescriptionCount: json['prescription_count'] ?? 0,
      procedureCount: json['procedure_count'] ?? 0,
    );
  }
}

/// Specialist referral model.
class Referral {
  final int id;
  final int encounterId;
  final int patientId;
  final int fromDoctorId;
  final int? toDoctorId;
  final int? toClinicId;
  final String? fromDoctorName;
  final String? toDoctorName;
  final String? toClinicName;
  final String? reason;
  final String? notes;
  final int urgency;
  final int status;
  final String urgencyLabel;
  final String statusLabel;
  final String? appointmentDate;
  final String? responseNotes;
  final String createdAt;
  final String? updatedAt;

  Referral({
    required this.id,
    required this.encounterId,
    required this.patientId,
    required this.fromDoctorId,
    this.toDoctorId,
    this.toClinicId,
    this.fromDoctorName,
    this.toDoctorName,
    this.toClinicName,
    this.reason,
    this.notes,
    required this.urgency,
    required this.status,
    required this.urgencyLabel,
    required this.statusLabel,
    this.appointmentDate,
    this.responseNotes,
    required this.createdAt,
    this.updatedAt,
  });

  factory Referral.fromJson(Map<String, dynamic> json) {
    return Referral(
      id: json['id'] ?? 0,
      encounterId: json['encounter_id'] ?? 0,
      patientId: json['patient_id'] ?? 0,
      fromDoctorId: json['from_doctor_id'] ?? 0,
      toDoctorId: json['to_doctor_id'],
      toClinicId: json['to_clinic_id'],
      fromDoctorName: _str(json['from_doctor_name']),
      toDoctorName: _str(json['to_doctor_name']),
      toClinicName: _str(json['to_clinic_name']),
      reason: _str(json['reason']),
      notes: _str(json['notes']),
      urgency: json['urgency'] is int ? json['urgency'] : 0,
      status: json['status'] is int ? json['status'] : 0,
      urgencyLabel: _str(json['urgency_label']) ?? _urgencyLabel(json['urgency']),
      statusLabel: _str(json['status_label']) ?? _statusLabelRef(json['status']),
      appointmentDate: _str(json['appointment_date']),
      responseNotes: _str(json['response_notes']),
      createdAt: _str(json['created_at']) ?? '',
      updatedAt: _str(json['updated_at']),
    );
  }

  static String _urgencyLabel(dynamic v) {
    switch (v) {
      case 0: return 'Routine';
      case 1: return 'Urgent';
      case 2: return 'Emergency';
      default: return 'Routine';
    }
  }

  static String _statusLabelRef(dynamic v) {
    switch (v) {
      case 0: return 'Pending';
      case 1: return 'Accepted';
      case 2: return 'Declined';
      case 3: return 'Completed';
      default: return 'Pending';
    }
  }

  bool get isUrgent => urgency >= 1;
  bool get isEmergency => urgency == 2;
}

/// Admission history item.
class AdmissionRecord {
  final int id;
  final int patientId;
  final String? patientName;
  final String? bedName;
  final String? wardName;
  final String? clinicName;
  final String? admitNote;
  final String? dischargeNote;
  final String? admittedAt;
  final String? dischargedAt;
  final String status;
  final String? admittedByName;
  final String? dischargedByName;

  AdmissionRecord({
    required this.id,
    required this.patientId,
    this.patientName,
    this.bedName,
    this.wardName,
    this.clinicName,
    this.admitNote,
    this.dischargeNote,
    this.admittedAt,
    this.dischargedAt,
    required this.status,
    this.admittedByName,
    this.dischargedByName,
  });

  factory AdmissionRecord.fromJson(Map<String, dynamic> json) {
    return AdmissionRecord(
      id: json['id'] ?? 0,
      patientId: json['patient_id'] ?? 0,
      patientName: _str(json['patient_name'] ?? json['patient']?['name']),
      bedName: _str(json['bed_name'] ?? json['bed']?['bed_name']),
      wardName: _str(json['ward_name'] ?? json['bed']?['ward']?['ward_name']),
      clinicName: _str(json['clinic_name']),
      admitNote: _str(json['admit_note']),
      dischargeNote: _str(json['discharge_note']),
      admittedAt: _str(json['admitted_at'] ?? json['created_at']),
      dischargedAt: _str(json['discharged_at']),
      status: _str(json['status']) ?? 'admitted',
      admittedByName: _str(json['admitted_by_name']),
      dischargedByName: _str(json['discharged_by_name']),
    );
  }

  bool get isActive => status == 'admitted';
}

/// Queue statistics summary.
class QueueStats {
  final int waiting;
  final int inConsultation;
  final int completed;
  final int total;
  final int scheduled;
  final int cancelled;

  QueueStats({
    required this.waiting,
    required this.inConsultation,
    required this.completed,
    required this.total,
    required this.scheduled,
    required this.cancelled,
  });

  factory QueueStats.fromJson(Map<String, dynamic> json) {
    return QueueStats(
      waiting: json['waiting'] ?? 0,
      inConsultation: json['in_consultation'] ?? 0,
      completed: json['completed'] ?? 0,
      total: json['total'] ?? 0,
      scheduled: json['scheduled'] ?? 0,
      cancelled: json['cancelled'] ?? 0,
    );
  }
}

/// A single item from a previous encounter for re-prescription.
class RecentEncounterItem {
  final int id;
  final String type; // 'lab', 'imaging', 'prescription', 'procedure'
  final String name;
  final String? dose;
  final String? note;
  final String date;

  RecentEncounterItem({
    required this.id,
    required this.type,
    required this.name,
    this.dose,
    this.note,
    required this.date,
  });

  factory RecentEncounterItem.fromJson(Map<String, dynamic> json) {
    return RecentEncounterItem(
      id: json['id'] ?? 0,
      type: _str(json['type']) ?? 'prescription',
      name: _str(json['name'] ?? json['product_name'] ?? json['service_name']) ?? 'Unknown',
      dose: _str(json['dose']),
      note: _str(json['note']),
      date: _str(json['date'] ?? json['created_at']) ?? '',
    );
  }
}
