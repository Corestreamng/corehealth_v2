import 'dart:convert';
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
  /// [status] 1=New, 2=Continuing, 3=Previous
  Future<ApiResult> getQueues({
    int status = 1,
    String? startDate,
    String? endDate,
    int page = 1,
    int perPage = 30,
  }) async {
    final params = <String, String>{
      'status': '$status',
      'page': '$page',
      'per_page': '$perPage',
    };
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;

    final uri = Uri.parse('$_api/queues').replace(queryParameters: params);
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Encounter Lifecycle
  // ═══════════════════════════════════════════════════════════════

  /// Start / resume encounter for a queue entry.
  Future<ApiResult> startEncounter({required int queueId}) async {
    return _post('$_api/encounters/start', body: {'queue_id': queueId});
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
    String? diagnosisApplicable,
    List<String>? reasonsForEncounter,
    String? comment1,
    String? comment2,
  }) async {
    return _post('$_api/encounters/$encounterId/save-diagnosis', body: {
      'doctor_diagnosis': doctorDiagnosis,
      if (diagnosisApplicable != null)
        'diagnosis_applicable': diagnosisApplicable,
      if (reasonsForEncounter != null)
        'reasons_for_encounter': reasonsForEncounter,
      if (comment1 != null) 'reasons_for_encounter_comment_1': comment1,
      if (comment2 != null) 'reasons_for_encounter_comment_2': comment2,
    });
  }

  /// Update encounter notes.
  Future<ApiResult> updateNotes(int encounterId, {
    required String notes,
  }) async {
    return _put('$_api/encounters/$encounterId/notes', body: {
      'notes': notes,
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

  /// Delete encounter.
  Future<ApiResult> deleteEncounter(int encounterId) async {
    return _delete('$_api/encounters/$encounterId');
  }

  /// Delete individual lab/imaging/prescription/procedure.
  Future<ApiResult> deleteLab(int encounterId, int labId) =>
      _delete('$_api/encounters/$encounterId/labs/$labId');
  Future<ApiResult> deleteImaging(int encounterId, int imagingId) =>
      _delete('$_api/encounters/$encounterId/imaging/$imagingId');
  Future<ApiResult> deletePrescription(int encounterId, int rxId) =>
      _delete('$_api/encounters/$encounterId/prescriptions/$rxId');
  Future<ApiResult> deleteProcedure(int encounterId, int procId) =>
      _delete('$_api/encounters/$encounterId/procedures/$procId');

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
        queryParameters: {'term': term},
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
    }
    return [];
  }

  /// Search services (labs, imaging, procedures).
  Future<List<Map<String, dynamic>>> searchServices(String term) async {
    final res = await _get(
      Uri.parse('$_api/search/services').replace(
        queryParameters: {'term': term},
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
    }
    return [];
  }

  /// Search products (medications).
  Future<List<Map<String, dynamic>>> searchProducts(String term) async {
    final res = await _get(
      Uri.parse('$_api/search/products').replace(
        queryParameters: {'term': term},
      ),
    );
    if (res.success && res.rawBody is List) {
      return List<Map<String, dynamic>>.from(res.rawBody as List);
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
  }) async {
    return _post('$_api/vitals', body: {
      'patient_id': patientId,
      'encounter_id': encounterId,
      if (temperature != null) 'temperature': temperature,
      if (systolicBp != null) 'systolic_bp': systolicBp,
      if (diastolicBp != null) 'diastolic_bp': diastolicBp,
      if (heartRate != null) 'heart_rate': heartRate,
      if (respiratoryRate != null) 'respiratory_rate': respiratoryRate,
      if (spo2 != null) 'spo2': spo2,
      if (weight != null) 'weight': weight,
      if (height != null) 'height': height,
      if (bloodSugar != null) 'blood_sugar': bloodSugar,
      if (painLevel != null) 'pain_level': painLevel,
    });
  }

  /// Get patient vitals history.
  Future<ApiResult> getPatientVitals(int patientId) =>
      _get(Uri.parse('$_api/patient/$patientId/vitals'));

  // ═══════════════════════════════════════════════════════════════
  //  HTTP Helpers
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> _get(Uri uri) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .get(uri, headers: headers)
          .timeout(const Duration(seconds: 20));
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _post(String url, {Map<String, dynamic>? body}) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .post(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 20));
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _put(String url, {Map<String, dynamic>? body}) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .put(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 20));
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _delete(String url) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .delete(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 20));
      return ApiResult.fromResponse(res);
    } catch (e) {
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
