import 'dart:convert';
import 'package:http/http.dart' as http;
import '../storage/local_storage.dart';

/// Result wrapper for patient API calls.
class ApiResult {
  final bool success;
  final String message;
  final dynamic data;

  ApiResult({required this.success, this.message = '', this.data});

  factory ApiResult.fromResponse(http.Response res) {
    try {
      final body = jsonDecode(res.body);
      if (body is Map<String, dynamic>) {
        return ApiResult(
          success: body['success'] == true ||
              body['status'] == true ||
              (res.statusCode >= 200 && res.statusCode < 300),
          message: body['message']?.toString() ?? '',
          data: body['data'] ?? body,
        );
      }
      // Raw list response
      return ApiResult(success: res.statusCode < 300, data: body);
    } catch (_) {
      return ApiResult(
        success: false,
        message: 'Invalid server response (${res.statusCode})',
      );
    }
  }

  factory ApiResult.error(String msg) =>
      ApiResult(success: false, message: msg);
}

/// Patient-facing API service — all read-only health records.
class PatientApiService {
  final String _api;
  String? _cachedToken;

  PatientApiService(String baseUrl)
      : _api = '${baseUrl.replaceAll(RegExp(r'/+$'), '')}/api/mobile/patient';

  // ─── Auth headers ────────────────────────────────────────────
  Future<Map<String, String>> _authHeaders() async {
    _cachedToken ??= await LocalStorage.getAuthToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (_cachedToken != null) 'Authorization': 'Bearer $_cachedToken',
    };
  }

  Future<ApiResult> _get(Uri uri) async {
    try {
      final headers = await _authHeaders();
      final res =
          await http.get(uri, headers: headers).timeout(
                const Duration(seconds: 15),
              );
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult.error('Network error: $e');
    }
  }

  // ═══════════════════════════════════════════════════════════════
  //  Profile
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getProfile() => _get(Uri.parse('$_api/profile'));

  // ═══════════════════════════════════════════════════════════════
  //  Encounters
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getEncounters({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/encounters').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  Future<ApiResult> getEncounterDetail(int encounterId) =>
      _get(Uri.parse('$_api/encounters/$encounterId'));

  // ═══════════════════════════════════════════════════════════════
  //  Vitals
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getVitals({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/vitals').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Lab Results
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getLabResults({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/lab-results').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Imaging Results
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getImagingResults({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/imaging-results').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Prescriptions
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getPrescriptions({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/prescriptions').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Procedures
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getProcedures({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/procedures').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Admissions
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getAdmissions({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/admissions').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }
}
