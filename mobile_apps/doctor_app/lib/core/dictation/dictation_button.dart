import 'package:flutter/material.dart';
import 'dictation_service.dart';
import 'dictation_state.dart';
import 'dictation_overlay.dart';

/// A small mic icon button that starts dictation for a [TextEditingController].
///
/// Place next to any text field label. Tapping opens the dictation overlay
/// and begins listening. Pulses red while actively listening.
class DictationButton extends StatelessWidget {
  final TextEditingController controller;
  final String fieldLabel;

  const DictationButton({
    super.key,
    required this.controller,
    this.fieldLabel = '',
  });

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: DictationService.instance,
      builder: (context, _) {
        final svc = DictationService.instance;
        final isActive = svc.state == DictationState.listening &&
            svc.activeController == controller;
        final isPaused = svc.state == DictationState.paused &&
            svc.activeController == controller;

        return GestureDetector(
          onTap: () => _onTap(context, svc),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: isActive
                  ? Colors.red.withValues(alpha: 0.15)
                  : isPaused
                      ? Colors.orange.withValues(alpha: 0.12)
                      : Colors.transparent,
              shape: BoxShape.circle,
            ),
            child: Icon(
              isActive
                  ? Icons.mic
                  : isPaused
                      ? Icons.mic_off
                      : Icons.mic_none,
              size: 20,
              color: isActive
                  ? Colors.red
                  : isPaused
                      ? Colors.orange
                      : Colors.grey.shade600,
            ),
          ),
        );
      },
    );
  }

  void _onTap(BuildContext context, DictationService svc) {
    // If already listening to a different controller, stop it first
    if (svc.state == DictationState.listening &&
        svc.activeController != controller) {
      svc.stopListening();
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => DictationOverlay(
        controller: controller,
        fieldLabel: fieldLabel,
      ),
    );
  }
}
