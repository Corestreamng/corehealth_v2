import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';
import '../../core/storage/local_storage.dart';
import '../../core/theme/theme_provider.dart';
import '../server_setup/server_setup_screen.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _controller = PageController();
  int _currentPage = 0;

  final _pages = const [
    _OnboardingPage(
      icon: Icons.favorite_rounded,
      title: 'Your Health, Your Hands',
      subtitle:
          'Access your medical records, appointments, lab results, '
          'and prescriptions — all in one place, anytime.',
      gradient: [Color(0xFF0066CC), Color(0xFF0088FF)],
    ),
    _OnboardingPage(
      icon: Icons.calendar_month_rounded,
      title: 'Appointments & Visits',
      subtitle:
          'View your upcoming appointments, past consultation history, '
          'and doctor notes. Never miss a follow-up again.',
      gradient: [Color(0xFF00897B), Color(0xFF26A69A)],
    ),
    _OnboardingPage(
      icon: Icons.science_rounded,
      title: 'Lab & Imaging Results',
      subtitle:
          'Get notified when your results are ready. View lab reports, '
          'imaging studies, and track trends over time.',
      gradient: [Color(0xFF5E35B1), Color(0xFF7E57C2)],
    ),
    _OnboardingPage(
      icon: Icons.medication_rounded,
      title: 'Prescriptions & Medications',
      subtitle:
          'See your current prescriptions, dosage instructions, and '
          'medication refill status — right from your phone.',
      gradient: [Color(0xFFE65100), Color(0xFFFF8A65)],
    ),
  ];

  void _finish() async {
    await LocalStorage.setOnboardingDone(true);
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const ServerSetupScreen()),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final primary = context.watch<ThemeProvider>().primaryColor;
    final isLast = _currentPage == _pages.length - 1;

    return Scaffold(
      body: Stack(
        children: [
          PageView.builder(
            controller: _controller,
            itemCount: _pages.length,
            onPageChanged: (i) => setState(() => _currentPage = i),
            itemBuilder: (_, i) => _pages[i],
          ),
          Positioned(
            top: MediaQuery.of(context).padding.top + 16,
            right: 20,
            child: TextButton(
              onPressed: _finish,
              child: Text(
                'Skip',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.9),
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: EdgeInsets.fromLTRB(
                  32, 24, 32, MediaQuery.of(context).padding.bottom + 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  SmoothPageIndicator(
                    controller: _controller,
                    count: _pages.length,
                    effect: ExpandingDotsEffect(
                      activeDotColor: Colors.white,
                      dotColor: Colors.white.withValues(alpha: 0.35),
                      dotHeight: 8,
                      dotWidth: 8,
                      expansionFactor: 3,
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () {
                      if (isLast) {
                        _finish();
                      } else {
                        _controller.nextPage(
                          duration: const Duration(milliseconds: 400),
                          curve: Curves.easeInOut,
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: primary,
                      padding: EdgeInsets.symmetric(
                        horizontal: isLast ? 32 : 24,
                        vertical: 14,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          isLast ? 'Get Started' : 'Next',
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Icon(
                          isLast
                              ? Icons.arrow_forward_rounded
                              : Icons.arrow_forward_ios_rounded,
                          size: 18,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OnboardingPage extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final List<Color> gradient;

  const _OnboardingPage({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: gradient,
        ),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 40),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 140,
            height: 140,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: 0.15),
            ),
            child: Center(
              child: Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.2),
                ),
                child: Icon(icon, size: 52, color: Colors.white),
              ),
            ),
          ),
          const SizedBox(height: 48),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: Colors.white,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 20),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 16,
              height: 1.6,
              color: Colors.white.withValues(alpha: 0.9),
            ),
          ),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}
