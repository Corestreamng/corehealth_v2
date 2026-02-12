import 'dart:convert';
import 'package:http/http.dart' as http;
import '../storage/local_storage.dart';

/// Lightweight API client for CoreHealth mobile endpoints.
class ApiClient {
  final String baseUrl;

  ApiClient(this.baseUrl);

  String get _apiBase => '$baseUrl/api/mobile';

  Map<String, String> _headers({bool auth = false, String? token}) {
    final h = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (auth && token != null) {
      h['Authorization'] = 'Bearer $token';
    }
    return h;
  }

  // ── Public ────────────────────────────────────────────────────────

  /// Fetch branding info (no auth).
  Future<ApiResponse> getInstanceInfo() async {
    return _get('/instance-info');
  }

  /// Verify the server URL is reachable and is a CoreHealth instance.
  Future<bool> verifyServer() async {
    try {
      final res = await http
          .get(Uri.parse('$_apiBase/instance-info'))
          .timeout(const Duration(seconds: 10));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        return body['status'] == true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  // ── Auth ───────────────────────────────────────────────────────────

  /// Staff login (Doctor app).
  Future<ApiResponse> staffLogin({
    required String email,
    required String password,
  }) async {
    return _post('/staff/login', body: {
      'email': email,
      'password': password,
    });
  }

  /// Patient login (Patient app).
  Future<ApiResponse> patientLogin({
    required String patientId,
    required String phone,
  }) async {
    return _post('/patient/login', body: {
      'patient_id': patientId,
      'phone': phone,
    });
  }

  /// Logout current user.
  Future<ApiResponse> logout() async {
    final token = await LocalStorage.getAuthToken();
    return _post('/logout', auth: true, token: token);
  }

  // ── Helpers ────────────────────────────────────────────────────────

  Future<ApiResponse> _get(String path,
      {bool auth = false, String? token}) async {
    try {
      final res = await http
          .get(
            Uri.parse('$_apiBase$path'),
            headers: _headers(auth: auth, token: token),
          )
          .timeout(const Duration(seconds: 15));
      return ApiResponse.fromHttpResponse(res);
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 0,
        message: 'Network error: ${e.toString()}',
      );
    }
  }

  Future<ApiResponse> _post(String path,
      {Map<String, dynamic>? body,
      bool auth = false,
      String? token}) async {
    try {
      final res = await http
          .post(
            Uri.parse('$_apiBase$path'),
            headers: _headers(auth: auth, token: token),
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(const Duration(seconds: 15));
      return ApiResponse.fromHttpResponse(res);
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: 0,
        message: 'Network error: ${e.toString()}',
      );
    }
  }
}

/// Simple API response wrapper.
class ApiResponse {
  final bool success;
  final int statusCode;
  final String message;
  final Map<String, dynamic>? data;

  ApiResponse({
    required this.success,
    required this.statusCode,
    this.message = '',
    this.data,
  });

  factory ApiResponse.fromHttpResponse(http.Response res) {
    Map<String, dynamic>? body;
    try {
      body = jsonDecode(res.body);
    } catch (_) {
      body = null;
    }

    return ApiResponse(
      success: body?['status'] == true,
      statusCode: res.statusCode,
      message: body?['message'] ?? '',
      data: body?['data'],
    );
  }
}
