import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../storage/local_storage.dart';
import 'encounter_api_service.dart' show ApiResult;

/// API service for doctor chat / messaging endpoints.
class ChatApiService {
  final String baseUrl;

  ChatApiService(this.baseUrl);

  String get _api => '$baseUrl/api/mobile/doctor/chat';

  Future<Map<String, String>> _authHeaders() async {
    final token = await LocalStorage.getAuthToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<Map<String, String>> _authHeadersMultipart() async {
    final token = await LocalStorage.getAuthToken();
    return {
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  // ═══════════════════════════════════════════════════════════════
  //  Conversations
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getConversations({
    String filter = 'all',
    String? search,
    int page = 1,
  }) async {
    final params = <String, String>{
      'filter': filter,
      'page': '$page',
    };
    if (search != null && search.isNotEmpty) params['q'] = search;
    final uri =
        Uri.parse('$_api/conversations').replace(queryParameters: params);
    return _get(uri);
  }

  Future<ApiResult> createConversation(List<int> userIds) async {
    return _post('$_api/create',
        body: {'user_ids': userIds});
  }

  Future<ApiResult> archiveConversation(int conversationId) async {
    return _post('$_api/archive/$conversationId');
  }

  Future<ApiResult> unarchiveConversation(int conversationId) async {
    return _post('$_api/unarchive/$conversationId');
  }

  // ═══════════════════════════════════════════════════════════════
  //  Messages
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getMessages(int conversationId,
      {int? afterId, int? beforeId}) async {
    final params = <String, String>{};
    if (afterId != null) params['after_id'] = '$afterId';
    if (beforeId != null) params['before_id'] = '$beforeId';
    final uri = Uri.parse('$_api/messages/$conversationId')
        .replace(queryParameters: params.isEmpty ? null : params);
    return _get(uri);
  }

  Future<ApiResult> sendMessage(int conversationId, String body) async {
    return _post('$_api/send', body: {
      'conversation_id': conversationId,
      'body': body,
    });
  }

  Future<ApiResult> sendMessageWithAttachments(
    int conversationId,
    String? body,
    List<String> filePaths,
  ) async {
    try {
      final headers = await _authHeadersMultipart();
      final request =
          http.MultipartRequest('POST', Uri.parse('$_api/send'));
      request.headers.addAll(headers);
      request.fields['conversation_id'] = '$conversationId';
      if (body != null && body.isNotEmpty) request.fields['body'] = body;
      for (final path in filePaths) {
        request.files
            .add(await http.MultipartFile.fromPath('attachments[]', path));
      }
      final streamed = await request.send().timeout(const Duration(seconds: 60));
      final res = await http.Response.fromStream(streamed);
      return ApiResult.fromResponse(res);
    } catch (e) {
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> deleteMessage(int messageId) async {
    return _delete('$_api/messages/$messageId');
  }

  Future<ApiResult> markAsRead(int conversationId) async {
    return _post('$_api/mark-read/$conversationId');
  }

  // ═══════════════════════════════════════════════════════════════
  //  Unread & Search
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> getUnreadCount() async {
    return _get(Uri.parse('$_api/unread-count'));
  }

  Future<ApiResult> searchUsers(String query) async {
    final uri = Uri.parse('$_api/search-users')
        .replace(queryParameters: {'q': query});
    return _get(uri);
  }

  // ═══════════════════════════════════════════════════════════════
  //  HTTP helpers
  // ═══════════════════════════════════════════════════════════════

  Future<ApiResult> _get(Uri uri) async {
    debugPrint('[ChatApi] GET → $uri');
    try {
      final headers = await _authHeaders();
      final res =
          await http.get(uri, headers: headers).timeout(const Duration(seconds: 30));
      debugPrint('[ChatApi] GET ← ${res.statusCode}');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[ChatApi] GET ERROR: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _post(String url, {Map<String, dynamic>? body}) async {
    debugPrint('[ChatApi] POST → $url');
    try {
      final headers = await _authHeaders();
      final res = await http
          .post(Uri.parse(url),
              headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(const Duration(seconds: 30));
      debugPrint('[ChatApi] POST ← ${res.statusCode}');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[ChatApi] POST ERROR: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }

  Future<ApiResult> _delete(String url) async {
    debugPrint('[ChatApi] DELETE → $url');
    try {
      final headers = await _authHeaders();
      final res = await http
          .delete(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 30));
      debugPrint('[ChatApi] DELETE ← ${res.statusCode}');
      return ApiResult.fromResponse(res);
    } catch (e) {
      debugPrint('[ChatApi] DELETE ERROR: $e');
      return ApiResult(success: false, message: 'Network error: $e');
    }
  }
}
