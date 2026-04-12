import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../storage/local_storage.dart';

/// API service for all encounter/queue/clinical endpoints.
class EncounterApiService {
  final String baseUrl;

  EncounterApiService(this.baseUrl);

  String get _api => '$baseUrl/api/mobile/doctor';

  Future<Map<String, String>> _authHeaders() async {
    final token = await LocalStorage.getAuthToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  // ═══════════════════════════════════════════════════════════════
  //  Queue Management
  // ═══════════════════════════════════════════════════════════════

  /// Fetch queue listing.
  /// [status] filter by specific status code. If null, fetch all.
  Future<ApiResult> getQueues({
    int? status,
    String? startDate,
    String? endDate,
    int page = 1,
    int perPage = 30,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (status != null) params['filter_status'] = '$status';
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;

    final uri = Uri.parse('$_api/queues').replace(queryParameters: params);
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Encounter Lifecycle
  // ═══════════════════════════════════════════════════════════════

  /// Start / resume encounter for a queue entry.
  Future<ApiResult> startEncounter({
    required int queueId,
    required int patientId,
    int? reqEntryId,
  }) async {
    return _post('$_api/encounters/start', body: {
      'patient_id': patientId,
      'queue_id': queueId,
      if (reqEntryId != null) 'req_entry_id': reqEntryId,
    });
  }

  /// Get full encounter detail (vitals, labs, imaging, rx, procedures).
  Future<ApiResult> getEncounterDetail(int encounterId) async {
    return _get(Uri.parse('$_api/encounters/$encounterId'));
  }

  /// Get encounter summary (diagnosis, labs, imaging, rx overview).
  Future<ApiResult> getEncounterSummary(int encounterId) async {
    return _get(Uri.parse('$_api/encounters/$encounterId/summary'));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Diagnosis & Notes
  // ═══════════════════════════════════════════════════════════════

  /// Save diagnosis (ICPC-2 codes + doctor notes).
  Future<ApiResult> saveDiagnosis(int encounterId, {
    required String doctorDiagnosis,
    bool? diagnosisApplicable,
    List<String>? reasonsForEncounter,
    String? perDiagnosisComments,
    String? comment1,
    String? comment2,
  }) async {
    return _post('$_api/encounters/$encounterId/save-diagnosis', body: {
      'doctor_diagnosis': doctorDiagnosis,
      if (diagnosisApplicable != null)
        'diagnosis_applicable': diagnosisApplicable ? '1' : '0',
      if (reasonsForEncounter != null)
        'reasons_for_encounter': reasonsForEncounter,
      if (perDiagnosisComments != null)
        'per_diagnosis_comments': perDiagnosisComments,
      if (comment1 != null) 'reasons_for_encounter_comment_1': comment1,
      if (comment2 != null) 'reasons_for_encounter_comment_2': comment2,
    });
  }

  /// Update encounter notes.
  Future<ApiResult> updateNotes(int encounterId, {
    required String notes,
    bool? diagnosisApplicable,
    String? reasonsForEncounter,
    String? perDiagnosisComments,
    String? comment1,
    String? comment2,
  }) async {
    return _put('$_api/encounters/$encounterId/notes', body: {
      'notes': notes,
      if (diagnosisApplicable != null)
        'diagnosis_applicable': diagnosisApplicable,
      if (reasonsForEncounter != null)
        'reasons_for_encounter': reasonsForEncounter,
      if (perDiagnosisComments != null)
        'per_diagnosis_comments': perDiagnosisComments,
      if (comment1 != null) 'reasons_for_encounter_comment_1': comment1,
      if (comment2 != null) 'reasons_for_encounter_comment_2': comment2,
    });
  }

  /// Autosave notes (fire-and-forget, no loading UI needed).
  Future<ApiResult> autosaveNotes({
    required int encounterId,
    required String notes,
  }) async {
    return _post('$_api/encounters/autosave', body: {
      'encounter_id': encounterId,
      'notes': notes,
    });
  }

  // ═══════════════════════════════════════════════════════════════
  //  Lab / Imaging / Prescription / Procedure saves
  // ═══════════════════════════════════════════════════════════════

  /// Save lab requests.
  Future<ApiResult> saveLabs(int encounterId, {
    required List<int> serviceIds,
    required List<String> notes,
  }) async {
    return _post('$_api/encounters/$encounterId/save-labs', body: {
      'consult_invest_id': serviceIds,
      'consult_invest_note': notes,
    });
  }

  /// Save imaging requests.
  Future<ApiResult> saveImaging(int encounterId, {
    required List<int> serviceIds,
    required List<String> notes,
  }) async {
    return _post('$_api/encounters/$encounterId/save-imaging', body: {
      'consult_imaging_id': serviceIds,
      'consult_imaging_note': notes,
    });
  }

  /// Save prescriptions.
  Future<ApiResult> savePrescriptions(int encounterId, {
    required List<int> productIds,
    required List<String> doses,
  }) async {
    return _post('$_api/encounters/$encounterId/save-prescriptions', body: {
      'consult_presc_id': productIds,
      'consult_presc_dose': doses,
    });
  }

  /// Save procedure requests.
  Future<ApiResult> saveProcedures(int encounterId, {
    required List<Map<String, dynamic>> procedures,
  }) async {
    return _post('$_api/encounters/$encounterId/save-procedures', body: {
      'procedures': procedures,
    });
  }

  // ═══════════════════════════════════════════════════════════════
  //  Finalize / Delete
  // ═══════════════════════════════════════════════════════════════

  /// Finalize encounter (end consultation / admit).
  /// Backend only processes: end_consultation, consult_admit, admit_note, queue_id.
  /// Follow-up scheduling is a separate call via [scheduleFollowUp].
  Future<ApiResult> finalizeEncounter(int encounterId, {
    required bool endConsultation,
    bool admit = false,
    String? admitNote,
    int? queueId,
  }) async {
    return _post('$_api/encounters/$encounterId/finalize', body: {
      'end_consultation': endConsultation ? 1 : 0,
      if (admit) 'consult_admit': 1,
      if (admitNote != null) 'admit_note': admitNote,
      if (queueId != null) 'queue_id': queueId,
    });
  }

  /// Schedule a follow-up appointment after concluding an encounter.
  Future<ApiResult> scheduleFollowUp(int encounterId, {
    required String appointmentDate,
    String? startTime,
    String? reason,
    bool isPrepaid = false,
  }) async {
    return _post('$_api/encounters/$encounterId/schedule-followup', body: {
      'appointment_date': appointmentDate,
      if (startTime != null) 'start_time': startTime,
      if (reason != null) 'reason': reason,
      'is_prepaid': isPrepaid ? 1 : 0,
    });
  }

  /// Delete encounter.
  Future<ApiResult> deleteEncounter(int encounterId) async {
    return _delete('$_api/encounters/$encounterId');
  }

  /// Delete individual lab/imaging/prescription/procedure.
  /// Backend requires [reason] (max 500 chars) for all four.
  Future<ApiResult> deleteLab(int encounterId, int labId, {required String reason}) =>
      _delete('$_api/encounters/$encounterId/labs/$labId', body: {'reason': reason});
  Future<ApiResult> deleteImaging(int encounterId, int imagingId, {required String reason}) =>
      _delete('$_api/encounters/$encounterId/imaging/$imagingId', body: {'reason': reason});
  Future<ApiResult> deletePrescription(int encounterId, int rxId, {required String reason}) =>
      _delete('$_api/encounters/$encounterId/prescriptions/$rxId', body: {'reason': reason});
  Future<ApiResult> deleteProcedure(int encounterId, int procId, {required String reason}) =>
      _delete('$_api/encounters/$encounterId/procedures/$procId', body: {'reason': reason});

  // ═══════════════════════════════════════════════════════════════
  //  Procedure Sub-endpoints
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getProcedureDetails(int procedureId) =>
      _get(Uri.parse('$_api/procedures/$procedureId'));
  Future<ApiResult> cancelProcedure(int procedureId, {String? reason}) =>
      _post('$_api/procedures/$procedureId/cancel', body: {
        if (reason != null) 'cancellation_reason': reason,
      });
  Future<ApiResult> getProcedureTeam(int procedureId) =>
      _get(Uri.parse('$_api/procedures/$procedureId/team'));
  Future<ApiResult> addProcedureTeamMember(int procedureId, {
    required int staffId,
    required String role,
  }) =>
      _post('$_api/procedures/$procedureId/team', body: {
        'staff_id': staffId,
        'role': role,
      });
  Future<ApiResult> deleteProcedureTeamMember(int procedureId, int memberId) =>
      _delete('$_api/procedures/$procedureId/team/$memberId');
  Future<ApiResult> getProcedureNotes(int procedureId) =>
      _get(Uri.parse('$_api/procedures/$procedureId/notes'));
  Future<ApiResult> addProcedureNote(int procedureId, {required String note}) =>
      _post('$_api/procedures/$procedureId/notes', body: {'note': note});
  Future<ApiResult> deleteProcedureNote(int procedureId, int noteId) =>
      _delete('$_api/procedures/$procedureId/notes/$noteId');

  // ═══════════════════════════════════════════════════════════════
  //  Patient History
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getEncounterHistory(int patientId, {int page = 1}) =>
      _get(Uri.parse('$_api/patient/$patientId/encounter-history?page=$page'));
  Future<ApiResult> getLabHistory(int patientId, {int page = 1}) =>
      _get(Uri.parse('$_api/patient/$patientId/lab-history?page=$page'));
  Future<ApiResult> getImagingHistory(int patientId, {int page = 1}) =>
      _get(Uri.parse('$_api/patient/$patientId/imaging-history?page=$page'));
  Future<ApiResult> getPrescriptionHistory(int patientId, {int page = 1}) =>
      _get(Uri.parse('$_api/patient/$patientId/prescription-history?page=$page'));
  Future<ApiResult> getProcedureHistory(int patientId, {int page = 1}) =>
      _get(Uri.parse('$_api/patient/$patientId/procedure-history?page=$page'));

  // ═══════════════════════════════════════════════════════════════
  //  Search / Autocomplete
  // ═══════════════════════════════════════════════════════════════

  /// Search ICPC-2 diagnosis codes.
  Future<List<Map<String, dynamic>>> searchDiagnosis(String term) async {
    final res = await _get(
      Uri.parse('$_api/search/diagnosis').replace(
        queryParameters: {'q': term},
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
    }
    return [];
  }

  /// Search services (labs, imaging, procedures).
  Future<List<Map<String, dynamic>>> searchServices(String term, {int? patientId, int? categoryId}) async {
    final params = <String, String>{'term': term};
    if (patientId != null) params['patient_id'] = '$patientId';
    if (categoryId != null) params['category_id'] = '$categoryId';
    final res = await _get(
      Uri.parse('$_api/search/services').replace(
        queryParameters: params,
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
    }
    return [];
  }

  /// Search products (medications).
  Future<List<Map<String, dynamic>>> searchProducts(String term, {int? patientId, String? type}) async {
    final params = <String, String>{'term': term};
    if (patientId != null) params['patient_id'] = '$patientId';
    if (type != null) params['type'] = type;
    final res = await _get(
      Uri.parse('$_api/search/products').replace(
        queryParameters: params,
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
    }
    return [];
  }

  // ═══════════════════════════════════════════════════════════════
  //  Queue Stats
  // ═══════════════════════════════════════════════════════════════

  /// Fetch queue stats summary.
  Future<ApiResult> getQueueStats() async {
    return _get(Uri.parse('$_api/queues/stats'));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Admissions
  // ═══════════════════════════════════════════════════════════════

  /// Get my current admissions.
  Future<ApiResult> getMyAdmissions({
    String? startDate,
    String? endDate,
    int? hmoId,
    int page = 1,
    int perPage = 30,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    if (hmoId != null) params['hmo_id'] = '$hmoId';
    return _get(Uri.parse('$_api/admissions').replace(queryParameters: params));
  }

  /// Get previous/completed encounters (status=3 in legacy, now completed+cancelled+no-show).
  Future<ApiResult> getPreviousEncounters({
    String? startDate,
    String? endDate,
    int? clinicId,
    int? hmoId,
    int page = 1,
    int perPage = 30,
  }) async {
    final params = <String, String>{
      'status': '3',
      'page': '$page',
      'per_page': '$perPage',
    };
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    if (clinicId != null) params['clinic_id'] = '$clinicId';
    if (hmoId != null) params['hmo_id'] = '$hmoId';
    return _get(Uri.parse('$_api/queues').replace(queryParameters: params));
  }

  /// Get admission history for a patient.
  Future<ApiResult> getPatientAdmissions(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/admissions'));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Single-item Add endpoints
  // ═══════════════════════════════════════════════════════════════

  /// Add a single lab request to an encounter.
  Future<ApiResult> addLab(int encounterId, {
    required int serviceId,
    String? note,
    String? priority,
  }) async {
    return _post('$_api/encounters/$encounterId/add-lab', body: {
      'service_id': serviceId,
      if (note != null) 'note': note,
      if (priority != null) 'priority': priority,
    });
  }

  /// Add a single imaging request to an encounter.
  Future<ApiResult> addImaging(int encounterId, {
    required int serviceId,
    String? note,
    String? priority,
  }) async {
    return _post('$_api/encounters/$encounterId/add-imaging', body: {
      'service_id': serviceId,
      if (note != null) 'note': note,
      if (priority != null) 'priority': priority,
    });
  }

  /// Add a single prescription to an encounter.
  /// Backend only stores product_id + dose (pipe-delimited string).
  Future<ApiResult> addPrescription(int encounterId, {
    required int productId,
    String? dose,
  }) async {
    return _post('$_api/encounters/$encounterId/add-prescription', body: {
      'product_id': productId,
      if (dose != null) 'dose': dose,
    });
  }

  /// Update a single prescription's dose string.
  Future<ApiResult> updatePrescriptionDose(int encounterId, int prescriptionId, {
    required String dose,
  }) async {
    return _put('$_api/encounters/$encounterId/prescriptions/$prescriptionId/dose', body: {
      'dose': dose,
    });
  }

  /// Add a single procedure request to an encounter.
  Future<ApiResult> addProcedure(int encounterId, {
    required int serviceId,
    String? preNotes,
    String? priority,
    String? scheduledDate,
  }) async {
    return _post('$_api/encounters/$encounterId/add-procedure', body: {
      'service_id': serviceId,
      if (preNotes != null) 'pre_notes': preNotes,
      if (priority != null) 'priority': priority,
      if (scheduledDate != null) 'scheduled_date': scheduledDate,
    });
  }

  // ═══════════════════════════════════════════════════════════════
  //  Lab / Imaging note updates
  // ═══════════════════════════════════════════════════════════════

  /// Update a lab request's note.
  Future<ApiResult> updateLabNote(int encounterId, int labId, {
    required String note,
  }) async {
    return _put('$_api/encounters/$encounterId/labs/$labId/note', body: {'note': note});
  }

  /// Update an imaging request's note.
  Future<ApiResult> updateImagingNote(int encounterId, int imagingId, {
    required String note,
  }) async {
    return _put('$_api/encounters/$encounterId/imaging/$imagingId/note', body: {'note': note});
  }

  // ═══════════════════════════════════════════════════════════════
  //  Re-prescription
  // ═══════════════════════════════════════════════════════════════

  /// Get recent encounter list for re-prescribing.
  Future<ApiResult> getRecentEncounters(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/recent-encounters'));
  }

  /// Get all items (labs/imaging/rx/procedures) from a past encounter.
  Future<ApiResult> getEncounterItems(int encounterId) async {
    return _get(Uri.parse('$_api/encounters/$encounterId/items'));
  }

  /// Re-prescribe items from a previous encounter into current encounter.
  /// Backend expects source_type (labs|imaging|prescriptions|procedures) + source_ids[].
  /// Call once per item type.
  Future<ApiResult> rePrescribe(int currentEncounterId, {
    required String sourceType,
    required List<int> sourceIds,
  }) async {
    return _post('$_api/encounters/$currentEncounterId/re-prescribe', body: {
      'source_type': sourceType,
      'source_ids': sourceIds,
    });
  }

  // ═══════════════════════════════════════════════════════════════
  //  Allergy Management
  // ═══════════════════════════════════════════════════════════════

  /// Get patient allergies.
  Future<ApiResult> getPatientAllergies(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/allergies'));
  }

  /// Add an allergy to a patient.
  Future<ApiResult> addPatientAllergy(int patientId, {
    required String allergy,
  }) async {
    return _post('$_api/patient/$patientId/allergies', body: {'allergy': allergy});
  }

  /// Remove an allergy from a patient.
  Future<ApiResult> deletePatientAllergy(int patientId, String allergy) async {
    return _delete('$_api/patient/$patientId/allergies/$allergy');
  }

  // ═══════════════════════════════════════════════════════════════
  //  Medication Chart
  // ═══════════════════════════════════════════════════════════════

  /// Get prescribed drugs and direct administration entries for medication chart.
  Future<ApiResult> getMedicationChart(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/medication-chart'));
  }

  /// Get fluid and solid intake/output chart data.
  Future<ApiResult> getIntakeOutput(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/intake-output'));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Referrals
  // ═══════════════════════════════════════════════════════════════

  /// Get all referrals for an encounter.
  Future<ApiResult> getEncounterReferrals(int encounterId) async {
    return _get(Uri.parse('$_api/encounters/$encounterId/referrals'));
  }

  /// Create a new referral.
  Future<ApiResult> createReferral(int encounterId, {
    required int patientId,
    required String referralType,
    int? targetClinicId,
    int? targetDoctorId,
    String? externalFacilityName,
    String? externalDoctorName,
    String? externalFacilityAddress,
    String? externalFacilityPhone,
    required String reason,
    String? clinicalSummary,
    String? provisionalDiagnosis,
    String urgency = 'routine',
  }) async {
    return _post('$_api/encounters/$encounterId/referrals', body: {
      'patient_id': patientId,
      'referral_type': referralType,
      if (targetClinicId != null) 'target_clinic_id': targetClinicId,
      if (targetDoctorId != null) 'target_doctor_id': targetDoctorId,
      if (externalFacilityName != null) 'external_facility_name': externalFacilityName,
      if (externalDoctorName != null) 'external_doctor_name': externalDoctorName,
      if (externalFacilityAddress != null) 'external_facility_address': externalFacilityAddress,
      if (externalFacilityPhone != null) 'external_facility_phone': externalFacilityPhone,
      'reason': reason,
      if (clinicalSummary != null) 'clinical_summary': clinicalSummary,
      if (provisionalDiagnosis != null) 'provisional_diagnosis': provisionalDiagnosis,
      'urgency': urgency,
    });
  }

  /// Update an existing referral.
  Future<ApiResult> updateReferral(int encounterId, int referralId, {
    String? referralType,
    int? targetClinicId,
    int? targetDoctorId,
    String? externalFacilityName,
    String? externalDoctorName,
    String? externalFacilityAddress,
    String? externalFacilityPhone,
    String? reason,
    String? clinicalSummary,
    String? provisionalDiagnosis,
    String? urgency,
    int? status,
    String? responseNotes,
  }) async {
    return _put('$_api/encounters/$encounterId/referrals/$referralId', body: {
      if (referralType != null) 'referral_type': referralType,
      if (targetClinicId != null) 'target_clinic_id': targetClinicId,
      if (targetDoctorId != null) 'target_doctor_id': targetDoctorId,
      if (externalFacilityName != null) 'external_facility_name': externalFacilityName,
      if (externalDoctorName != null) 'external_doctor_name': externalDoctorName,
      if (externalFacilityAddress != null) 'external_facility_address': externalFacilityAddress,
      if (externalFacilityPhone != null) 'external_facility_phone': externalFacilityPhone,
      if (reason != null) 'reason': reason,
      if (clinicalSummary != null) 'clinical_summary': clinicalSummary,
      if (provisionalDiagnosis != null) 'provisional_diagnosis': provisionalDiagnosis,
      if (urgency != null) 'urgency': urgency,
      if (status != null) 'status': status,
      if (responseNotes != null) 'response_notes': responseNotes,
    });
  }

  /// Delete a referral.
  Future<ApiResult> deleteReferral(int encounterId, int referralId) async {
    return _delete('$_api/encounters/$encounterId/referrals/$referralId');
  }

  /// Get incoming referrals for doctor (from other doctors to me).
  Future<ApiResult> getIncomingReferrals(int encounterId) async {
    return _get(Uri.parse('$_api/encounters/$encounterId/referrals/incoming'));
  }

  /// Get all referrals for a patient (across all encounters).
  Future<ApiResult> getPatientReferrals(int patientId) async {
    return _get(Uri.parse('$_api/patient/$patientId/referrals'));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Clinics & Doctors (dropdowns for referrals)
  // ═══════════════════════════════════════════════════════════════

  /// Get list of clinics.
  Future<List<Map<String, dynamic>>> getClinics() async {
    final res = await _get(Uri.parse('$_api/clinics'));
    if (res.success) {
      final body = res.rawBody;
      if (body is List) return List<Map<String, dynamic>>.from(body.whereType<Map>());
      if (body is Map && body['data'] is List) {
        return List<Map<String, dynamic>>.from(
            (body['data'] as List).whereType<Map>());
      }
    }
    return [];
  }

  /// Get list of HMOs for filter dropdowns.
  Future<List<Map<String, dynamic>>> getHmos() async {
    final res = await _get(Uri.parse('$_api/hmos'));
    if (res.success) {
      final body = res.rawBody;
      if (body is List) return List<Map<String, dynamic>>.from(body.whereType<Map>());
      if (body is Map && body['data'] is List) {
        return List<Map<String, dynamic>>.from(
            (body['data'] as List).whereType<Map>());
      }
    }
    return [];
  }

  /// Get list of doctors.
  Future<List<Map<String, dynamic>>> getDoctors() async {
    final res = await _get(Uri.parse('$_api/doctors'));
    if (res.success) {
      final body = res.rawBody;
      if (body is List) return List<Map<String, dynamic>>.from(body.whereType<Map>());
      if (body is Map && body['data'] is List) {
        return List<Map<String, dynamic>>.from(
            (body['data'] as List).whereType<Map>());
      }
    }
    return [];
  }

  // ═══════════════════════════════════════════════════════════════
  //  Vitals
  // ═══════════════════════════════════════════════════════════════

  /// Record a vital sign.
  Future<ApiResult> recordVitals({
    required int patientId,
    required int encounterId,
    double? temperature,
    int? systolicBp,
    int? diastolicBp,
    int? heartRate,
    int? respiratoryRate,
    int? spo2,
    double? weight,
    double? height,
    double? bloodSugar,
    int? painLevel,
    String? otherNotes,
  }) async {
    // Build blood pressure as "120/80" string to match web field format
    String? bp;
    if (systolicBp != null && diastolicBp != null) {
      bp = '$systolicBp/$diastolicBp';
    }

    // Auto-calculate BMI
    double? bmi;
    if (weight != null && height != null && height > 0) {
      final hm = height / 100;
      bmi = double.parse((weight / (hm * hm)).toStringAsFixed(1));
    }

    return _post('$_api/vitals', body: {
      'patient_id': patientId,
      'encounter_id': encounterId,
      // Required: temperature mapped to web field name
      if (temperature != null) 'bodyTemperature': temperature,
      // Required: datetime (use current time)
      'datetimeField': DateTime.now().toIso8601String(),
      // Blood pressure as combined "sys/dia" string
      if (bp != null) 'bloodPressure': bp,
      if (heartRate != null) 'heartRate': heartRate,
      if (respiratoryRate != null) 'respiratoryRate': respiratoryRate,
      if (spo2 != null) 'spo2': spo2,
      if (weight != null) 'bodyWeight': weight,
      if (height != null) 'height': height,
      if (bloodSugar != null) 'bloodSugar': bloodSugar,
      if (painLevel != null) 'painScore': painLevel,
      if (bmi != null) 'bmi': bmi,
      if (otherNotes != null && otherNotes.isNotEmpty)
        'otherNotes': otherNotes,
    });
  }

  /// Get patient vitals history.
  Future<ApiResult> getPatientVitals(int patientId) =>
      _get(Uri.parse('$_api/patient/$patientId/vitals'));

  // ═══════════════════════════════════════════════════════════════
  //  Nursing Notes
  // ═══════════════════════════════════════════════════════════════

  /// Get nursing notes for a patient.
  Future<ApiResult> getNursingNotes(int patientId, {int? typeId, int page = 1}) {
    final params = <String, String>{'page': '$page'};
    if (typeId != null) params['type_id'] = '$typeId';
    return _get(Uri.parse('$_api/patient/$patientId/nursing-notes').replace(queryParameters: params));
  }

  /// Get nursing note type list.
  Future<ApiResult> getNoteTypes() =>
      _get(Uri.parse('$_api/nursing-note-types'));

  // ═══════════════════════════════════════════════════════════════
  //  Clinic Note Templates
  // ═══════════════════════════════════════════════════════════════

  /// Get clinic note templates grouped by category.
  Future<ApiResult> getClinicNoteTemplates({int? clinicId}) {
    final params = <String, String>{};
    if (clinicId != null) params['clinic_id'] = '$clinicId';
    return _get(Uri.parse('$_api/clinic-note-templates').replace(queryParameters: params));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Doctor Profile & Settings
  // ═══════════════════════════════════════════════════════════════

  /// Get doctor's full profile.
  Future<ApiResult> getDoctorProfile() =>
      _get(Uri.parse('$_api/profile'));

  /// Update doctor profile fields.
  Future<ApiResult> updateDoctorProfile(Map<String, dynamic> fields) =>
      _put('$_api/profile', body: fields);

  /// Change doctor password.
  Future<ApiResult> changeDoctorPassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) =>
      _post('$_api/change-password', body: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': confirmPassword,
      });

  // ═══════════════════════════════════════════════════════════════
  //  My Investigations (cross-patient)
  // ═══════════════════════════════════════════════════════════════

  /// Get lab + imaging ordered by this doctor.
  /// [type]: 'all', 'lab', or 'imaging'
  /// [status]: optional status filter
  Future<ApiResult> getMyInvestigations({
    String type = 'all',
    int? status,
    int perPage = 20,
  }) {
    final params = <String, String>{
      'type': type,
      'per_page': '$perPage',
    };
    if (status != null) params['status'] = '$status';
    return _get(Uri.parse('$_api/my-investigations').replace(queryParameters: params));
  }

  // ═══════════════════════════════════════════════════════════════
  //  Appointment Actions
  // ═══════════════════════════════════════════════════════════════

  /// Check in a scheduled appointment.
  Future<ApiResult> checkInAppointment(int appointmentId) =>
      _post('$_api/appointments/$appointmentId/check-in');

  /// Cancel an appointment.
  Future<ApiResult> cancelAppointment(int appointmentId, {String? reason}) =>
      _post('$_api/appointments/$appointmentId/cancel', body: {
        if (reason != null) 'reason': reason,
      });

  /// Mark appointment as no-show.
  Future<ApiResult> markNoShow(int appointmentId) =>
      _post('$_api/appointments/$appointmentId/no-show');

  /// Reschedule an appointment.
  Future<ApiResult> rescheduleAppointment(int appointmentId, {
    required String appointmentDate,
    required String startTime,
    String? endTime,
    int? doctorId,
    String? reason,
  }) =>
      _post('$_api/appointments/$appointmentId/reschedule', body: {
        'appointment_date': appointmentDate,
        'start_time': startTime,
        if (endTime != null) 'end_time': endTime,
        if (doctorId != null) 'doctor_id': doctorId,
        if (reason != null) 'reason': reason,
      });

  /// Reassign appointment to a different doctor.
  Future<ApiResult> reassignDoctor(int appointmentId, {
    required int doctorId,
    String? reason,
  }) =>
      _post('$_api/appointments/$appointmentId/reassign', body: {
        'doctor_id': doctorId,
        if (reason != null) 'reason': reason,
      });

  /// Get available time slots for a specific date/clinic/doctor.
  Future<ApiResult> getAvailableSlots({
    required int clinicId,
    required String date,
    int? doctorId,
  }) {
    final params = <String, String>{
      'clinic_id': '$clinicId',
      'date': date,
    };
    if (doctorId != null) params['doctor_id'] = '$doctorId';
    return _get(Uri.parse('$_api/appointments/available-slots').replace(queryParameters: params));
  }

  /// Get doctors filtered by clinic (for reassign).
  Future<List<Map<String, dynamic>>> getDoctorsForClinic(int clinicId) async {
    final res = await _get(Uri.parse('$_api/doctors').replace(queryParameters: {'clinic_id': '$clinicId'}));
    if (res.success) {
      final body = res.rawBody;
      if (body is List) return List<Map<String, dynamic>>.from(body.whereType<Map>());
      if (body is Map && body['data'] is List) {
        return List<Map<String, dynamic>>.from(
            (body['data'] as List).whereType<Map>());
      }
    }
    return [];
  }

  // ═══════════════════════════════════════════════════════════════
  //  Doctor Referral Lists (queue history)
  // ═══════════════════════════════════════════════════════════════

  /// Get my referrals (sent/received) with pagination.
  Future<ApiResult> getMyReferralsList({
    String? direction,
    String? status,
    String? referralType,
    String? startDate,
    String? endDate,
    int page = 1,
    int perPage = 20,
  }) {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (direction != null) params['direction'] = direction;
    if (status != null) params['status'] = status;
    if (referralType != null) params['referral_type'] = referralType;
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    return _get(Uri.parse('$_api/referrals/my-list').replace(queryParameters: params));
  }

  /// Get all referrals (hospital-wide) with pagination.
  Future<ApiResult> getAllReferralsList({
    String? status,
    String? referralType,
    int? clinicId,
    int? doctorId,
    String? startDate,
    String? endDate,
    int page = 1,
    int perPage = 20,
  }) {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (status != null) params['status'] = status;
    if (referralType != null) params['referral_type'] = referralType;
    if (clinicId != null) params['clinic_id'] = '$clinicId';
    if (doctorId != null) params['doctor_id'] = '$doctorId';
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    return _get(Uri.parse('$_api/referrals/all-list').replace(queryParameters: params));
  }

  /// Get referral detail.
  Future<ApiResult> getReferralDetail(int referralId) =>
      _get(Uri.parse('$_api/referrals/$referralId/detail'));

  /// Accept a pending referral.
  Future<ApiResult> acceptReferral(int referralId) =>
      _post('$_api/referrals/$referralId/accept');

  /// Decline a pending referral.
  Future<ApiResult> declineReferral(int referralId, {required String reason}) =>
      _post('$_api/referrals/$referralId/decline', body: {'reason': reason});

  // ═══════════════════════════════════════════════════════════════
  //  All Admissions (hospital-wide)
  // ═══════════════════════════════════════════════════════════════

  /// Get all admissions (hospital-wide, not just doctor's clinic).
  Future<ApiResult> getAllAdmissions({
    String? startDate,
    String? endDate,
    int? doctorId,
    int? hmoId,
    int page = 1,
    int perPage = 20,
  }) {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
    };
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    if (doctorId != null) params['doctor_id'] = '$doctorId';
    if (hmoId != null) params['hmo_id'] = '$hmoId';
    return _get(Uri.parse('$_api/admissions/all').replace(queryParameters: params));
  }

  // ═══════════════════════════════════════════════════════════════
  //  HTTP Helpers
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> _get(Uri uri) async {
    debugPrint('[EncounterApi] GET → $uri');
    try {
      final headers = await _authHeaders();
      final res = await http
          .get(uri, headers: headers)
          .timeout(const Duration(seconds: 30));
      debugPrint('[EncounterApi] GET ← ${res.statusCode} $uri');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[EncounterApi] GET ERROR $uri: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _post(String url, {Map<String, dynamic>? body}) async {
    debugPrint('[EncounterApi] POST → $url');
    try {
      final headers = await _authHeaders();
      final res = await http
          .post(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 30));
      debugPrint('[EncounterApi] POST ← ${res.statusCode} $url');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[EncounterApi] POST ERROR $url: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _put(String url, {Map<String, dynamic>? body}) async {
    debugPrint('[EncounterApi] PUT → $url');
    try {
      final headers = await _authHeaders();
      final res = await http
          .put(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 30));
      debugPrint('[EncounterApi] PUT ← ${res.statusCode} $url');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[EncounterApi] PUT ERROR $url: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _delete(String url, {Map<String, dynamic>? body}) async {
    debugPrint('[EncounterApi] DELETE → $url');
    try {
      final headers = await _authHeaders();
      final res = await http
          .delete(Uri.parse(url),
              headers: headers,
              body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 30));
      debugPrint('[EncounterApi] DELETE ← ${res.statusCode} $url');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[EncounterApi] DELETE ERROR $url: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }
}

/// Parsed API result. Handles both `{status, message, data}` and raw arrays.
class ApiResult {
  final bool success;
  final int statusCode;
  final String message;
  final Map<String, dynamic>? data;
  final dynamic rawBody;

  ApiResult({
    required this.success,
    this.statusCode = 0,
    this.message = '',
    this.data,
    this.rawBody,
  });

  factory ApiResult.fromResponse(http.Response res) {
    dynamic body;
    try {
      body = jsonDecode(res.body);
    } catch (_) {
      body = null;
    }

    if (body is Map<String, dynamic>) {
      return ApiResult(
        success: body['status'] == true || (res.statusCode >= 200 && res.statusCode < 300 && body['success'] != false),
        statusCode: res.statusCode,
        message: body['message']?.toString() ?? '',
        data: body['data'] is Map<String, dynamic> ? body['data'] : body,
        rawBody: body,
      );
    }

    // Raw array response (e.g. search endpoints)
    return ApiResult(
      success: res.statusCode >= 200 && res.statusCode < 300,
      statusCode: res.statusCode,
      rawBody: body,
    );
  }
}
