import 'package:flutter/material.dart';
import '../storage/local_storage.dart';

/// Provides dynamic theming from the CoreHealth instance's hos_color.
class ThemeProvider extends ChangeNotifier {
  Color _primaryColor = const Color(0xFF0066CC); // default fallback
  String _siteName = '';
  String? _logoBase64;

  Color get primaryColor => _primaryColor;
  String get siteName => _siteName;
  String? get logoBase64 => _logoBase64;

  ThemeProvider() {
    _loadCached();
  }

  void _loadCached() {
    final cached = LocalStorage.hosColor;
    if (cached != null && cached.isNotEmpty) {
      _primaryColor = _hexToColor(cached);
    }
    _siteName = LocalStorage.siteName ?? '';
    _logoBase64 = LocalStorage.logoBase64;
  }

  /// Call after fetching /api/mobile/instance-info
  Future<void> updateFromInstance({
    required String hosColor,
    required String siteName,
    String? logoBase64,
  }) async {
    _primaryColor = _hexToColor(hosColor);
    _siteName = siteName;
    _logoBase64 = logoBase64;

    // Persist
    await LocalStorage.setHosColor(hosColor);
    await LocalStorage.setSiteName(siteName);
    if (logoBase64 != null) {
      await LocalStorage.setLogoBase64(logoBase64);
    }

    notifyListeners();
  }

  static Color _hexToColor(String hex) {
    hex = hex.replaceAll('#', '');
    if (hex.length == 6) hex = 'FF$hex';
    return Color(int.parse(hex, radix: 16));
  }
}
