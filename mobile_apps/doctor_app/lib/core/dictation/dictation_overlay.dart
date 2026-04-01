import 'package:flutter/material.dart';
import 'dictation_service.dart';
import 'dictation_state.dart';

/// Bottom-sheet overlay showing live transcription status.
///
/// Shows: pulsing mic indicator, partial/final transcript preview,
/// sound level visualizer, and Pause / Accept / Clear controls.
class DictationOverlay extends StatefulWidget {
  final TextEditingController controller;
  final String fieldLabel;

  const DictationOverlay({
    super.key,
    required this.controller,
    this.fieldLabel = '',
  });

  @override
  State<DictationOverlay> createState() => _DictationOverlayState();
}

class _DictationOverlayState extends State<DictationOverlay>
    with SingleTickerProviderStateMixin {
  late AnimationController _pulseCtrl;
  late Animation<double> _pulseAnim;

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 1.0, end: 1.35).animate(
      CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut),
    );

    // Auto-start listening when overlay opens
    final svc = DictationService.instance;
    if (svc.state != DictationState.listening) {
      svc.startListening(widget.controller).catchError((_) {});
    }
  }

  @override
  void dispose() {
    _pulseCtrl.dispose();
    final svc = DictationService.instance;
    if (svc.state == DictationState.listening ||
        svc.state == DictationState.paused) {
      svc.stopListening();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: DictationService.instance,
      builder: (context, _) {
        final svc = DictationService.instance;
        final isListening = svc.state == DictationState.listening;
        final isPaused = svc.state == DictationState.paused;
        final isError = svc.state == DictationState.error;

        // Stop pulse animation when not listening
        if (isListening && !_pulseCtrl.isAnimating) {
          _pulseCtrl.repeat(reverse: true);
        } else if (!isListening && _pulseCtrl.isAnimating) {
          _pulseCtrl.stop();
          _pulseCtrl.value = 0.0;
        }

        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Handle bar
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 12),

                // Title row
                Row(
                  children: [
                    Icon(Icons.mic, size: 20,
                        color: isListening ? Colors.red : Colors.grey),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        widget.fieldLabel.isNotEmpty
                            ? 'Dictating: ${widget.fieldLabel}'
                            : 'Voice Dictation',
                        style: const TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w600),
                      ),
                    ),
                    // Close button
                    IconButton(
                      icon: const Icon(Icons.close, size: 20),
                      onPressed: () {
                        svc.stopListening();
                        Navigator.of(context).pop();
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Status indicator
                _StatusIndicator(
                  isListening: isListening,
                  isPaused: isPaused,
                  isError: isError,
                  soundLevel: svc.soundLevel,
                  pulseAnimation: _pulseAnim,
                ),
                const SizedBox(height: 12),

                // Status text
                Text(
                  isListening
                      ? 'Listening...'
                      : isPaused
                          ? 'Paused — tap mic to resume'
                          : isError
                              ? svc.errorMessage
                              : 'Ready',
                  style: TextStyle(
                    fontSize: 13,
                    color: isError ? Colors.red : Colors.grey.shade600,
                  ),
                ),
                const SizedBox(height: 16),

                // Partial text preview
                if (svc.partialText.isNotEmpty)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade50,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.blue.shade100),
                    ),
                    child: Text(
                      svc.partialText,
                      style: TextStyle(
                        fontSize: 14,
                        color: Colors.blue.shade800,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                  ),

                const SizedBox(height: 20),

                // Action buttons
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    // Pause / Resume
                    _ActionButton(
                      icon: isListening ? Icons.pause : Icons.play_arrow,
                      label: isListening ? 'Pause' : 'Resume',
                      color: Colors.orange,
                      onTap: () {
                        if (isListening) {
                          svc.pauseListening();
                        } else if (isPaused) {
                          svc.resumeListening();
                        } else {
                          svc.startListening(widget.controller);
                        }
                      },
                    ),
                    // Accept (stop and keep text)
                    _ActionButton(
                      icon: Icons.check_circle,
                      label: 'Accept',
                      color: Colors.green,
                      onTap: () {
                        svc.stopListening();
                        Navigator.of(context).pop();
                      },
                    ),
                    // Clear (discard and stop)
                    _ActionButton(
                      icon: Icons.delete_outline,
                      label: 'Clear',
                      color: Colors.red,
                      onTap: () {
                        svc.cancelListening();
                        Navigator.of(context).pop();
                      },
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

// ═══════════════════════════════════════════════════════════
// Sub-widgets
// ═══════════════════════════════════════════════════════════

class _StatusIndicator extends StatelessWidget {
  final bool isListening;
  final bool isPaused;
  final bool isError;
  final double soundLevel;
  final Animation<double> pulseAnimation;

  const _StatusIndicator({
    required this.isListening,
    required this.isPaused,
    required this.isError,
    required this.soundLevel,
    required this.pulseAnimation,
  });

  @override
  Widget build(BuildContext context) {
    final baseColor = isListening
        ? Colors.red
        : isPaused
            ? Colors.orange
            : isError
                ? Colors.grey
                : Colors.blue;

    // Map sound level (-2..10) to a ring size multiplier
    final levelNorm = ((soundLevel + 2) / 12).clamp(0.0, 1.0);

    return SizedBox(
      width: 80,
      height: 80,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Sound level ring
          if (isListening)
            AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              width: 60 + (levelNorm * 20),
              height: 60 + (levelNorm * 20),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: baseColor.withValues(alpha: 0.1 + (levelNorm * 0.15)),
              ),
            ),
          // Pulsing circle
          ScaleTransition(
            scale: isListening ? pulseAnimation : const AlwaysStoppedAnimation(1.0),
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: baseColor.withValues(alpha: 0.15),
                border: Border.all(color: baseColor, width: 2),
              ),
              child: Icon(
                isListening
                    ? Icons.mic
                    : isPaused
                        ? Icons.mic_off
                        : isError
                            ? Icons.error_outline
                            : Icons.mic_none,
                size: 28,
                color: baseColor,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ActionButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: color.withValues(alpha: 0.12),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }
}
