/// Configuration constants for voice dictation.
class DictationConfig {
  DictationConfig._();

  /// Silence window before auto-pause (user requested 60 seconds).
  static const Duration silenceTimeout = Duration(seconds: 60);

  /// Maximum continuous listen duration per session.
  static const Duration maxListenDuration = Duration(minutes: 5);

  /// Default locale for STT engine.
  static const String defaultLocale = 'en_US';

  /// Minimum confidence threshold to accept a result.
  static const double minConfidence = 0.4;

  /// Whether to insert a space before appending new text.
  static const bool autoSpaceBetweenChunks = true;
}
