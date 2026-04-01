// Patient-side data models for encounters and health records.

/// A single diagnosis entry with per-diagnosis status/course.
class DiagnosisEntry {
  final String code;
  final String name;
  final String display;
  final String status;  // N/A, Query, Differential, Confirmed
  final String course;  // N/A, Acute, Chronic, Recurrent

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
}

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
  final String? startedAt;
  final String? completedAt;
  final List<String> reasons;
  final List<DiagnosisEntry> diagnosisEntries;
  final List<PatientVital> vitals;
  final List<PatientLabResult> labs;
  final List<PatientImagingResult> imaging;
  final List<PatientPrescription> prescriptions;
  final List<PatientProcedure> procedures;
  final List<PatientReferral> referrals;
  final List<PatientNursingNote> nursingNotes;
  final PatientAdmission? admission;

  PatientEncounterDetail({
    required this.id,
    this.doctorName,
    this.clinicName,
    this.diagnosis,
    this.comment1,
    this.comment2,
    this.notes,
    this.createdAt,
    this.startedAt,
    this.completedAt,
    this.reasons = const [],
    this.diagnosisEntries = const [],
    this.vitals = const [],
    this.labs = const [],
    this.imaging = const [],
    this.prescriptions = const [],
    this.procedures = const [],
    this.referrals = const [],
    this.nursingNotes = const [],
    this.admission,
  });

  factory PatientEncounterDetail.fromJson(Map<String, dynamic> j) {
    final enc = j['encounter'] ?? j;
    List<String> reasons = [];
    List<DiagnosisEntry> diagnosisEntries = [];
    if (enc['reasons_for_encounter'] != null) {
      if (enc['reasons_for_encounter'] is List) {
        final rawList = enc['reasons_for_encounter'] as List;
        reasons = rawList
            .map((r) => r is Map ? (r['name']?.toString() ?? '') : r.toString())
            .where((s) => s.isNotEmpty)
            .toList();
        diagnosisEntries = rawList
            .whereType<Map>()
            .map((m) => DiagnosisEntry.fromJson(Map<String, dynamic>.from(m)))
            .toList();
      }
    }

    // Parse vitals — they come at the top level of data, not inside encounter
    final vitalsJson = j['vitals'] as List? ?? enc['vitals'] as List? ?? [];
    final labsJson = j['labs'] as List? ?? enc['labs'] as List? ?? [];
    final imagingJson = j['imaging'] as List? ?? enc['imaging'] as List? ?? [];
    final rxJson = j['prescriptions'] as List? ?? enc['prescriptions'] as List? ?? [];
    final procJson = j['procedures'] as List? ?? enc['procedures'] as List? ?? [];
    final refJson = j['referrals'] as List? ?? enc['referrals'] as List? ?? [];
    final notesJson = j['nursing_notes'] as List? ?? enc['nursing_notes'] as List? ?? [];

    PatientAdmission? admission;
    final admJson = j['admission'] ?? enc['admission'];
    if (admJson is Map<String, dynamic>) {
      admission = PatientAdmission.fromJson(admJson);
    }

    return PatientEncounterDetail(
      id: enc['id'] ?? enc['encounter_id'] ?? 0,
      doctorName: enc['doctor_name']?.toString() ??
          enc['doctor']?['name']?.toString(),
      clinicName: enc['clinic_name']?.toString() ??
          enc['clinic']?['clinic_name']?.toString(),
      diagnosis: enc['doctor_diagnosis']?.toString() ?? enc['notes']?.toString(),
      comment1: enc['comment_1']?.toString(),
      comment2: enc['comment_2']?.toString(),
      notes: enc['notes']?.toString(),
      createdAt: enc['created_at']?.toString(),
      startedAt: enc['started_at']?.toString(),
      completedAt: enc['completed_at']?.toString(),
      reasons: reasons,
      diagnosisEntries: diagnosisEntries,
      vitals: vitalsJson
          .map((v) => PatientVital.fromJson(v))
          .toList(),
      labs: labsJson
          .map((l) => PatientLabResult.fromJson(l))
          .toList(),
      imaging: imagingJson
          .map((i) => PatientImagingResult.fromJson(i))
          .toList(),
      prescriptions: rxJson
          .map((p) => PatientPrescription.fromJson(p))
          .toList(),
      procedures: procJson
          .map((p) => PatientProcedure.fromJson(p))
          .toList(),
      referrals: refJson
          .map((r) => PatientReferral.fromJson(r))
          .toList(),
      nursingNotes: notesJson
          .map((n) => PatientNursingNote.fromJson(n))
          .toList(),
      admission: admission,
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
    // Parse blood_pressure "120/80" into systolic/diastolic parts
    int? sysBp = _toInt(j['systolic_bp'] ?? j['systolic']);
    int? diaBp = _toInt(j['diastolic_bp'] ?? j['diastolic']);
    if (sysBp == null && j['blood_pressure'] != null) {
      final parts = j['blood_pressure'].toString().split('/');
      if (parts.length == 2) {
        sysBp = int.tryParse(parts[0].trim());
        diaBp = int.tryParse(parts[1].trim());
      }
    }

    return PatientVital(
      id: _toInt(j['id']),
      temperature: _toDouble(j['temperature']),
      systolic: sysBp,
      diastolic: diaBp,
      heartRate: _toInt(j['heart_rate'] ?? j['pulse']),
      respiratoryRate: _toInt(j['respiratory_rate']),
      spo2: _toDouble(j['spo2'] ?? j['oxygen_saturation']),
      weight: _toDouble(j['weight']),
      height: _toDouble(j['height']),
      bloodSugar: _toDouble(j['blood_sugar']),
      painScore: _toInt(j['pain_score'] ?? j['pain_level']),
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
  // Tracking fields
  final String? billedBy;
  final String? sampleTakenBy;
  final String? resultEnteredBy;
  final String? approvedBy;
  final String? rejectionReason;
  final List<String> attachments;

  PatientLabResult({
    required this.id,
    this.serviceName,
    this.result,
    this.resultDate,
    this.note,
    this.status = 1,
    this.createdAt,
    this.billedBy,
    this.sampleTakenBy,
    this.resultEnteredBy,
    this.approvedBy,
    this.rejectionReason,
    this.attachments = const [],
  });

  factory PatientLabResult.fromJson(Map<String, dynamic> j) {
    List<String> parseAttachments(dynamic val) {
      if (val is List) return val.map((e) => e.toString()).toList();
      return [];
    }

    return PatientLabResult(
      id: j['id'] ?? j['lab_service_request_id'] ?? 0,
      serviceName: j['service_name']?.toString() ??
          j['service']?['service_name']?.toString(),
      result: j['result']?.toString(),
      resultDate: j['result_date']?.toString(),
      note: j['note']?.toString(),
      status: _toInt(j['status']) ?? 1,
      createdAt: j['created_at']?.toString(),
      billedBy: j['billed_by_name']?.toString() ?? j['billed_by']?.toString(),
      sampleTakenBy: j['sample_taken_by_name']?.toString() ?? j['sample_taken_by']?.toString(),
      resultEnteredBy: j['result_entered_by_name']?.toString() ?? j['result_entered_by']?.toString(),
      approvedBy: j['approved_by_name']?.toString() ?? j['approved_by']?.toString(),
      rejectionReason: j['rejection_reason']?.toString(),
      attachments: parseAttachments(j['attachments'] ?? j['files']),
    );
  }

  String get statusLabel {
    switch (status) {
      case 0: return 'Dismissed';
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Sample Taken';
      case 4: return 'Results Ready';
      case 5: return 'Pending Approval';
      case 6: return 'Rejected';
      default: return 'Requested';
    }
  }

  bool get isRejected => status == 6;
  bool get hasResult => result != null && result!.isNotEmpty;
}

class PatientImagingResult {
  final int id;
  final String? serviceName;
  final String? result;
  final String? resultDate;
  final String? note;
  final int status;
  final String? createdAt;
  // Tracking fields
  final String? billedBy;
  final String? resultEnteredBy;
  final String? approvedBy;
  final String? rejectionReason;
  final String? clinicalIndication;
  final List<String> attachments;

  PatientImagingResult({
    required this.id,
    this.serviceName,
    this.result,
    this.resultDate,
    this.note,
    this.status = 1,
    this.createdAt,
    this.billedBy,
    this.resultEnteredBy,
    this.approvedBy,
    this.rejectionReason,
    this.clinicalIndication,
    this.attachments = const [],
  });

  factory PatientImagingResult.fromJson(Map<String, dynamic> j) {
    List<String> parseAttachments(dynamic val) {
      if (val is List) return val.map((e) => e.toString()).toList();
      return [];
    }

    return PatientImagingResult(
      id: j['id'] ?? j['imaging_service_request_id'] ?? 0,
      serviceName: j['service_name']?.toString() ??
          j['service']?['service_name']?.toString(),
      result: j['result']?.toString(),
      resultDate: j['result_date']?.toString(),
      note: j['note']?.toString(),
      status: _toInt(j['status']) ?? 1,
      createdAt: j['created_at']?.toString(),
      billedBy: j['billed_by_name']?.toString() ?? j['billed_by']?.toString(),
      resultEnteredBy: j['result_entered_by_name']?.toString() ?? j['result_entered_by']?.toString(),
      approvedBy: j['approved_by_name']?.toString() ?? j['approved_by']?.toString(),
      rejectionReason: j['rejection_reason']?.toString(),
      clinicalIndication: j['clinical_indication']?.toString() ?? j['note']?.toString(),
      attachments: parseAttachments(j['attachments'] ?? j['files']),
    );
  }

  String get statusLabel {
    switch (status) {
      case 0: return 'Dismissed';
      case 1: return 'Requested';
      case 2: return 'Billed';
      case 3: return 'Results Ready';
      default: return 'Requested';
    }
  }

  bool get isRejected => rejectionReason != null && rejectionReason!.isNotEmpty;
  bool get hasResult => result != null && result!.isNotEmpty;
}

class PatientPrescription {
  final int id;
  final String? productName;
  final String? dose;
  final int? qty;
  final int status;
  final String? createdAt;
  // Extended dose fields
  final String? frequency;
  final String? duration;
  final String? durationUnit;
  final String? route;
  final String? specialInstruction;

  PatientPrescription({
    required this.id,
    this.productName,
    this.dose,
    this.qty,
    this.status = 1,
    this.createdAt,
    this.frequency,
    this.duration,
    this.durationUnit,
    this.route,
    this.specialInstruction,
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
      frequency: j['frequency']?.toString(),
      duration: j['duration']?.toString(),
      durationUnit: j['duration_unit']?.toString(),
      route: j['route']?.toString(),
      specialInstruction: j['special_instruction']?.toString() ?? j['instruction']?.toString(),
    );
  }

  String get statusLabel {
    switch (status) {
      case 0: return 'Dismissed';
      case 1: return 'Prescribed';
      case 2: return 'Billed';
      case 3: return 'Dispensed';
      default: return 'Prescribed';
    }
  }

  String get doseDisplay {
    final parts = <String>[];
    if (dose != null && dose!.isNotEmpty) parts.add(dose!);
    if (route != null && route!.isNotEmpty) parts.add(route!);
    if (frequency != null && frequency!.isNotEmpty) parts.add(frequency!);
    if (duration != null && duration!.isNotEmpty) {
      final unit = durationUnit ?? 'days';
      parts.add('× $duration $unit');
    }
    return parts.isNotEmpty ? parts.join(' • ') : '';
  }
}

class PatientProcedure {
  final int id;
  final String? serviceName;
  final String? procedureStatus;
  final String? priority;
  final String? scheduledDate;
  final String? scheduledTime;
  final String? outcome;
  final String? outcomeNotes;
  final String? preNotes;
  final String? postNotes;
  final String? operatingRoom;
  final String? createdAt;

  PatientProcedure({
    required this.id,
    this.serviceName,
    this.procedureStatus,
    this.priority,
    this.scheduledDate,
    this.scheduledTime,
    this.outcome,
    this.outcomeNotes,
    this.preNotes,
    this.postNotes,
    this.operatingRoom,
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
      scheduledTime: j['scheduled_time']?.toString(),
      outcome: j['outcome']?.toString(),
      outcomeNotes: j['outcome_notes']?.toString(),
      preNotes: j['pre_notes']?.toString() ?? j['pre_operative_notes']?.toString(),
      postNotes: j['post_notes']?.toString(),
      operatingRoom: j['operating_room']?.toString(),
      createdAt: j['created_at']?.toString(),
    );
  }
}

/// Patient admission record.
class PatientAdmission {
  final int id;
  final String? doctorName;
  final String? admissionStatus;
  final String? admissionReason;
  final String? dischargeReason;
  final bool discharged;
  final String? dischargeDate;
  final String? priority;
  final String? bedInfo;
  final int? daysAdmitted;
  final String? createdAt;

  PatientAdmission({
    required this.id,
    this.doctorName,
    this.admissionStatus,
    this.admissionReason,
    this.dischargeReason,
    this.discharged = false,
    this.dischargeDate,
    this.priority,
    this.bedInfo,
    this.daysAdmitted,
    this.createdAt,
  });

  factory PatientAdmission.fromJson(Map<String, dynamic> j) {
    return PatientAdmission(
      id: j['id'] ?? 0,
      doctorName: j['doctor_name']?.toString(),
      admissionStatus: j['admission_status']?.toString(),
      admissionReason: j['admission_reason']?.toString(),
      dischargeReason: j['discharge_reason']?.toString(),
      discharged: j['discharged'] == true || j['discharged'] == 1,
      dischargeDate: j['discharge_date']?.toString(),
      priority: j['priority']?.toString(),
      bedInfo: j['bed_info']?.toString(),
      daysAdmitted: _toInt(j['days_admitted']),
      createdAt: j['created_at']?.toString(),
    );
  }

  bool get isActive => !discharged && admissionStatus?.toLowerCase() != 'discharged';

  String get statusLabel {
    if (discharged) return 'Discharged';
    final s = admissionStatus?.toLowerCase() ?? '';
    if (s.contains('admit')) return 'Admitted';
    if (s.contains('pending')) return 'Pending';
    return admissionStatus ?? 'Unknown';
  }
}

/// Patient referral record.
class PatientReferral {
  final int id;
  final String? referralType;
  final String? fromDoctor;
  final String? fromClinic;
  final String? toDoctor;
  final String? toClinic;
  final String? reason;
  final String? clinicalSummary;
  final String? provisionalDiagnosis;
  final String? urgency;
  final String? status;
  final String? actionNotes;
  final String? createdAt;

  PatientReferral({
    required this.id,
    this.referralType,
    this.fromDoctor,
    this.fromClinic,
    this.toDoctor,
    this.toClinic,
    this.reason,
    this.clinicalSummary,
    this.provisionalDiagnosis,
    this.urgency,
    this.status,
    this.actionNotes,
    this.createdAt,
  });

  factory PatientReferral.fromJson(Map<String, dynamic> j) {
    return PatientReferral(
      id: j['id'] ?? 0,
      referralType: j['referral_type']?.toString(),
      fromDoctor: j['from_doctor']?.toString(),
      fromClinic: j['from_clinic']?.toString(),
      toDoctor: j['to_doctor']?.toString(),
      toClinic: j['to_clinic']?.toString(),
      reason: j['reason']?.toString(),
      clinicalSummary: j['clinical_summary']?.toString(),
      provisionalDiagnosis: j['provisional_diagnosis']?.toString(),
      urgency: j['urgency']?.toString(),
      status: j['status']?.toString(),
      actionNotes: j['action_notes']?.toString(),
      createdAt: j['created_at']?.toString(),
    );
  }

  bool get isUrgent => urgency == 'urgent' || urgency == 'emergency';
}

/// Patient nursing note record.
class PatientNursingNote {
  final int id;
  final String? note;
  final String? type;
  final String? createdBy;
  final String? createdAt;

  PatientNursingNote({
    required this.id,
    this.note,
    this.type,
    this.createdBy,
    this.createdAt,
  });

  factory PatientNursingNote.fromJson(Map<String, dynamic> j) {
    return PatientNursingNote(
      id: j['id'] ?? 0,
      note: j['note']?.toString(),
      type: j['type']?.toString(),
      createdBy: j['created_by']?.toString(),
      createdAt: j['created_at']?.toString(),
    );
  }
}

/// Patient appointment record.
class PatientAppointment {
  final int id;
  final String? doctorName;
  final String? clinicName;
  final String? appointmentDate;
  final String? startTime;
  final String? endTime;
  final int? durationMinutes;
  final String? status;
  final String? priority;
  final String? appointmentType;
  final String? reason;
  final String? notes;
  final String? cancellationReason;
  final String? createdAt;

  PatientAppointment({
    required this.id,
    this.doctorName,
    this.clinicName,
    this.appointmentDate,
    this.startTime,
    this.endTime,
    this.durationMinutes,
    this.status,
    this.priority,
    this.appointmentType,
    this.reason,
    this.notes,
    this.cancellationReason,
    this.createdAt,
  });

  factory PatientAppointment.fromJson(Map<String, dynamic> j) {
    return PatientAppointment(
      id: j['id'] ?? 0,
      doctorName: j['doctor_name']?.toString(),
      clinicName: j['clinic_name']?.toString(),
      appointmentDate: j['appointment_date']?.toString(),
      startTime: j['start_time']?.toString(),
      endTime: j['end_time']?.toString(),
      durationMinutes: _toInt(j['duration_minutes']),
      status: j['status']?.toString(),
      priority: j['priority']?.toString(),
      appointmentType: j['appointment_type']?.toString(),
      reason: j['reason']?.toString(),
      notes: j['notes']?.toString(),
      cancellationReason: j['cancellation_reason']?.toString(),
      createdAt: j['created_at']?.toString(),
    );
  }

  bool get isUpcoming {
    if (appointmentDate == null) return false;
    final d = DateTime.tryParse(appointmentDate!);
    if (d == null) return false;
    return d.isAfter(DateTime.now().subtract(const Duration(days: 1))) &&
        (status == 'scheduled' || status == 'checked_in' || status == 'rescheduled');
  }

  bool get isCancelled => status == 'cancelled';
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
