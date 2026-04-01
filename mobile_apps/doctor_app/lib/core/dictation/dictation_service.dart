import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:speech_to_text/speech_to_text.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'dictation_config.dart';
import 'dictation_state.dart';
import 'medical_vocabulary.dart';

/// Singleton service managing speech-to-text dictation.
///
/// Provides cursor-aware text insertion, automatic silence timeout,
/// medical vocabulary post-processing, and a single-listener guarantee.
class DictationService extends ChangeNotifier {
  DictationService._();
  static final DictationService _instance = DictationService._();
  static DictationService get instance => _instance;

  final SpeechToText _speech = SpeechToText();

  DictationState _state = DictationState.idle;
  DictationState get state => _state;

  String _partialText = '';
  String get partialText => _partialText;

  String _finalText = '';
  String get finalText => _finalText;

  String _errorMessage = '';
  String get errorMessage => _errorMessage;

  bool _isAvailable = false;
  bool get isAvailable => _isAvailable;

  double _soundLevel = 0.0;
  double get soundLevel => _soundLevel;

  /// The controller + cursor position currently bound to dictation.
  TextEditingController? _activeController;
  TextEditingController? get activeController => _activeController;

  /// Accumulated text from the current dictation session (before commit).
  final StringBuffer _sessionBuffer = StringBuffer();

  /// Text snapshot at the cursor when dictation started.
  String _preText = '';
  String _postText = '';

  /// Initialize STT engine. Call once on app startup or first use.
  Future<bool> initialize() async {
    if (_isAvailable) return true;
    _setState(DictationState.initializing);
    try {
      _isAvailable = await _speech.initialize(
        onError: _onError,
        onStatus: _onStatus,
        debugLogging: kDebugMode,
      );
      _setState(_isAvailable ? DictationState.idle : DictationState.error);
      if (!_isAvailable) {
        _errorMessage = 'Speech recognition not available on this device';
      }
      return _isAvailable;
    } catch (e) {
      _errorMessage = e.toString();
      _setState(DictationState.error);
      return false;
    }
  }

  /// Start listening and dictating into [controller] at its current cursor.
  Future<void> startListening(TextEditingController controller) async {
    if (_state == DictationState.listening) {
      // Already listening — stop first
      await stopListening();
    }

    if (!_isAvailable) {
      final ok = await initialize();
      if (!ok) return;
    }

    _activeController = controller;
    _sessionBuffer.clear();
    _partialText = '';
    _finalText = '';

    // Capture cursor position to enable insert-at-cursor
    final sel = controller.selection;
    final text = controller.text;
    if (sel.isValid && sel.baseOffset >= 0) {
      final base = sel.baseOffset.clamp(0, text.length);
      final extent = sel.extentOffset.clamp(0, text.length);
      _preText = text.substring(0, base);
      _postText = text.substring(extent);
    } else {
      _preText = text;
      _postText = '';
    }

    _setState(DictationState.listening);

    await _speech.listen(
      onResult: _onResult,
      listenFor: DictationConfig.maxListenDuration,
      pauseFor: DictationConfig.silenceTimeout,
      listenOptions: SpeechListenOptions(
        listenMode: ListenMode.dictation,
        cancelOnError: false,
        partialResults: true,
      ),
      onSoundLevelChange: (level) {
        _soundLevel = level;
        notifyListeners();
      },
      localeId: DictationConfig.defaultLocale,
    );
  }

  /// Pause dictation (preserves accumulated text).
  Future<void> pauseListening() async {
    if (_state != DictationState.listening) return;
    await _speech.stop();
    _setState(DictationState.paused);
  }

  /// Resume after pause — starts a new listen session, appending to buffer.
  Future<void> resumeListening() async {
    if (_state != DictationState.paused || _activeController == null) return;

    // Update pre-text with committed content so far
    _preText = _activeController!.text.substring(
        0, _activeController!.selection.baseOffset.clamp(0, _activeController!.text.length));
    _postText = _activeController!.text.substring(
        _activeController!.selection.extentOffset.clamp(0, _activeController!.text.length));

    _partialText = '';

    _setState(DictationState.listening);

    await _speech.listen(
      onResult: _onResult,
      listenFor: DictationConfig.maxListenDuration,
      pauseFor: DictationConfig.silenceTimeout,
      listenOptions: SpeechListenOptions(
        listenMode: ListenMode.dictation,
        cancelOnError: false,
        partialResults: true,
      ),
      onSoundLevelChange: (level) {
        _soundLevel = level;
        notifyListeners();
      },
      localeId: DictationConfig.defaultLocale,
    );
  }

  /// Stop dictation entirely and finalize text into the controller.
  Future<void> stopListening() async {
    await _speech.stop();
    _commitPartialToBuffer();
    _setState(DictationState.idle);
    _activeController = null;
    _soundLevel = 0.0;
  }

  /// Discard current session text and stop.
  Future<void> cancelListening() async {
    await _speech.stop();
    // Restore original text
    if (_activeController != null) {
      _activeController!.text = _preText + _postText;
      _activeController!.selection = TextSelection.collapsed(
        offset: _preText.length,
      );
    }
    _sessionBuffer.clear();
    _partialText = '';
    _finalText = '';
    _setState(DictationState.idle);
    _activeController = null;
    _soundLevel = 0.0;
  }

  // ═══════════════════════════════════════════════════════════
  // Internal handlers
  // ═══════════════════════════════════════════════════════════

  void _onResult(SpeechRecognitionResult result) {
    if (_activeController == null) return;

    String recognized = result.recognizedWords;

    if (result.finalResult) {
      // Apply medical vocabulary post-processing
      recognized = MedicalVocabulary.process(recognized);
      _commitText(recognized);
      _partialText = '';
      _finalText = recognized;
    } else {
      _partialText = recognized;
      // Live preview: show partial in the text field
      _previewText(recognized);
    }

    notifyListeners();
  }

  void _onError(dynamic error) {
    final errorMsg = error?.toString() ?? 'Unknown error';
    // "error_no_match" is normal — silence timeout, not a real error
    if (errorMsg.contains('error_no_match') || errorMsg.contains('error_speech_timeout')) {
      if (_state == DictationState.listening) {
        _setState(DictationState.paused);
      }
      return;
    }
    _errorMessage = errorMsg;
    _setState(DictationState.error);
  }

  void _onStatus(String status) {
    if (status == 'done' && _state == DictationState.listening) {
      // Silence timeout triggered — move to paused
      _commitPartialToBuffer();
      _setState(DictationState.paused);
    }
  }

  /// Commit recognized text into the controller at cursor.
  void _commitText(String text) {
    if (_activeController == null || text.isEmpty) return;

    final spacer = _needsSpace() ? ' ' : '';
    _sessionBuffer.write('$spacer$text');

    // Update the text field
    final newPre = '$_preText${_sessionBuffer.toString()}';
    _activeController!.text = '$newPre$_postText';
    _activeController!.selection = TextSelection.collapsed(
      offset: newPre.length,
    );
  }

  /// Show partial (non-final) text as a live preview in the field.
  void _previewText(String partial) {
    if (_activeController == null) return;

    final spacer = _needsSpace() ? ' ' : '';
    final preview = '$_preText${_sessionBuffer.toString()}$spacer$partial';
    _activeController!.text = '$preview$_postText';
    _activeController!.selection = TextSelection.collapsed(
      offset: preview.length,
    );
  }

  void _commitPartialToBuffer() {
    if (_partialText.isNotEmpty) {
      _commitText(MedicalVocabulary.process(_partialText));
      _partialText = '';
    }
  }

  bool _needsSpace() {
    final buf = _sessionBuffer.toString();
    if (buf.isEmpty && _preText.isEmpty) return false;
    final lastChar = buf.isNotEmpty ? buf[buf.length - 1] : _preText[_preText.length - 1];
    return lastChar != ' ' && lastChar != '\n';
  }

  void _setState(DictationState newState) {
    if (_state == newState) return;
    _state = newState;
    notifyListeners();
  }
}
