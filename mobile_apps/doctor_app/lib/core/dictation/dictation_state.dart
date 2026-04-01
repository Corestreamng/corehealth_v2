/// Represents the current state of the dictation engine.
enum DictationState {
  /// Engine is idle — not listening, no session active.
  idle,

  /// Engine is initializing (requesting permissions, warming up STT).
  initializing,

  /// Actively listening and transcribing speech.
  listening,

  /// Paused by user or silence timeout — can resume without losing context.
  paused,

  /// An error occurred (permission denied, STT unavailable, etc.).
  error,
}
