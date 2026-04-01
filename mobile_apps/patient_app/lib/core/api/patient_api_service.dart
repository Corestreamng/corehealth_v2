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

  ApiResult _handleResponse(http.Response res) {
    if (res.statusCode == 401) {
      _cachedToken = null;
      LocalStorage.clearAuthToken();
      return ApiResult.error('Session expired. Please log in again.');
    }
    return ApiResult.fromResponse(res);
  }

  Future<ApiResult> _get(Uri uri) async {
    try {
      final headers = await _authHeaders();
      final res =
          await http.get(uri, headers: headers).timeout(
                const Duration(seconds: 15),
              );
      return _handleResponse(res);
    } catch (e) {
      return ApiResult.error('Network error: $e');
    }
  }

  Future<ApiResult> _put(String url, {Map<String, dynamic>? body}) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .put(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 15));
      return _handleResponse(res);
    } catch (e) {
      return ApiResult.error('Network error: $e');
    }
  }

  Future<ApiResult> _post(String url, {Map<String, dynamic>? body}) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .post(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(const Duration(seconds: 15));
      return _handleResponse(res);
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

  // ═══════════════════════════════════════════════════════════════
  //  Referrals
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getReferrals({int page = 1, int perPage = 20}) {
    final uri = Uri.parse('$_api/referrals').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Appointments
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getAppointments({
    int page = 1,
    int perPage = 20,
    String filter = 'all',
  }) {
    final uri = Uri.parse('$_api/appointments').replace(queryParameters: {
      'page': '$page',
      'per_page': '$perPage',
      'filter': filter,
    });
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  Profile Update & Password Change
  // ═══════════════════════════════════════════════════════════════

  /// Update patient's editable profile fields.
  Future<ApiResult> updateProfile(Map<String, dynamic> fields) =>
      _put('$_api/profile', body: fields);

  /// Change patient password.
  Future<ApiResult> changePassword({
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
  //  Chat / Messaging  (restricted to encounter doctors)
  // ═══════════════════════════════════════════════════════════════

  String get _chatApi =>
      _api.replaceFirst('/api/mobile/patient', '/api/mobile/patient/chat');

  Future<ApiResult> getMyDoctors() => _get(Uri.parse('$_chatApi/my-doctors'));

  Future<ApiResult> getConversations({String filter = 'all', String? search}) {
    final params = <String, String>{'filter': filter};
    if (search != null && search.isNotEmpty) params['q'] = search;
    return _get(
        Uri.parse('$_chatApi/conversations').replace(queryParameters: params));
  }

  Future<ApiResult> getMessages(int conversationId,
      {int? afterId, int? beforeId}) {
    final params = <String, String>{};
    if (afterId != null) params['after_id'] = '$afterId';
    if (beforeId != null) params['before_id'] = '$beforeId';
    return _get(Uri.parse('$_chatApi/messages/$conversationId')
        .replace(queryParameters: params.isEmpty ? null : params));
  }

  Future<ApiResult> sendChatMessage(int conversationId, String body) =>
      _post('$_chatApi/send', body: {
        'conversation_id': conversationId,
        'body': body,
      });

  Future<ApiResult> createConversation(List<int> userIds) =>
      _post('$_chatApi/create', body: {'user_ids': userIds});

  Future<ApiResult> markChatAsRead(int conversationId) =>
      _post('$_chatApi/mark-read/$conversationId');

  Future<ApiResult> getChatUnreadCount() =>
      _get(Uri.parse('$_chatApi/unread-count'));

  Future<ApiResult> deleteChatMessage(int messageId) async {
    try {
      final headers = await _authHeaders();
      final res = await http
          .delete(Uri.parse('$_chatApi/messages/$messageId'), headers: headers)
          .timeout(const Duration(seconds: 15));
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult.error('Network error: $e');
    }
  }
}
