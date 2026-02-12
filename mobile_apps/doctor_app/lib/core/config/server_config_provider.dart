import 'package:flutter/material.dart';
import '../storage/local_storage.dart';

/// Manages the CoreHealth instance base URL.
class ServerConfigProvider extends ChangeNotifier {
  String? _baseUrl;
  bool _isConfigured = false;

  String? get baseUrl => _baseUrl;
  bool get isConfigured => _isConfigured;

  /// Full API base, e.g. "https://hospital.com/api/mobile"
  String get apiBase => '${_baseUrl ?? ""}/api/mobile';

  ServerConfigProvider() {
    _baseUrl = LocalStorage.baseUrl;
    _isConfigured = _baseUrl != null && _baseUrl!.isNotEmpty;
  }

  Future<void> setServerUrl(String url) async {
    // Normalise: remove trailing slash
    url = url.trimRight().replaceAll(RegExp(r'/+$'), '');

    // Ensure it has a scheme
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'http://$url';
    }

    _baseUrl = url;
    _isConfigured = true;
    await LocalStorage.setBaseUrl(url);
    notifyListeners();
  }

  Future<void> clearServer() async {
    _baseUrl = null;
    _isConfigured = false;
    await LocalStorage.clearAll();
    notifyListeners();
  }
}
