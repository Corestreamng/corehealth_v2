import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Centralised local storage for the Patient app.
class LocalStorage {
  static late SharedPreferences _prefs;
  static const _secure = FlutterSecureStorage();

  static const _keyBaseUrl        = 'base_url';
  static const _keyOnboardingDone = 'onboarding_done';
  static const _keyAuthToken      = 'auth_token';
  static const _keyPatientJson    = 'patient_json';
  static const _keyHosColor       = 'hos_color';
  static const _keySiteName       = 'site_name';
  static const _keyLogoBase64     = 'logo_base64';

  static Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  // ── Base URL
  static String? get baseUrl => _prefs.getString(_keyBaseUrl);
  static Future<void> setBaseUrl(String url) =>
      _prefs.setString(_keyBaseUrl, url);

  // ── Onboarding
  static bool get onboardingDone =>
      _prefs.getBool(_keyOnboardingDone) ?? false;
  static Future<void> setOnboardingDone(bool v) =>
      _prefs.setBool(_keyOnboardingDone, v);

  // ── Auth token (secure)
  static Future<String?> getAuthToken() =>
      _secure.read(key: _keyAuthToken);
  static Future<void> setAuthToken(String token) =>
      _secure.write(key: _keyAuthToken, value: token);
  static Future<void> clearAuthToken() =>
      _secure.delete(key: _keyAuthToken);

  // ── Patient JSON cache
  static String? get patientJson => _prefs.getString(_keyPatientJson);
  static Future<void> setPatientJson(String json) =>
      _prefs.setString(_keyPatientJson, json);

  // ── Branding cache
  static String? get hosColor => _prefs.getString(_keyHosColor);
  static Future<void> setHosColor(String color) =>
      _prefs.setString(_keyHosColor, color);

  static String? get siteName => _prefs.getString(_keySiteName);
  static Future<void> setSiteName(String name) =>
      _prefs.setString(_keySiteName, name);

  static String? get logoBase64 => _prefs.getString(_keyLogoBase64);
  static Future<void> setLogoBase64(String b64) =>
      _prefs.setString(_keyLogoBase64, b64);

  // ── Clear all
  static Future<void> clearAll() async {
    await _prefs.clear();
    await _secure.deleteAll();
  }

  static Future<void> clearSession() async {
    await clearAuthToken();
    await _prefs.remove(_keyPatientJson);
  }
}
