import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../theme/middo_colors.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  late final AnimationController _intro;
  late final AnimationController _pulse;
  late final Animation<double> _fade;
  late final Animation<double> _scale;
  late final Animation<double> _markTurn;
  late final Animation<Offset> _wordSlide;

  @override
  void initState() {
    super.initState();

    _intro = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    );
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat(reverse: true);

    _fade = CurvedAnimation(parent: _intro, curve: const Interval(0, 0.45, curve: Curves.easeOut));
    _scale = Tween<double>(begin: 0.72, end: 1).animate(
      CurvedAnimation(parent: _intro, curve: const Interval(0, 0.55, curve: Curves.easeOutBack)),
    );
    _markTurn = Tween<double>(begin: -0.08, end: 0).animate(
      CurvedAnimation(parent: _intro, curve: const Interval(0, 0.5, curve: Curves.easeOutCubic)),
    );
    _wordSlide = Tween<Offset>(
      begin: const Offset(0, 0.35),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(parent: _intro, curve: const Interval(0.35, 0.85, curve: Curves.easeOutCubic)),
    );

    _intro.forward();
    Future<void>.delayed(const Duration(milliseconds: 2200), _goNext);
  }

  void _goNext() {
    if (!mounted) return;
    final next = AuthStore.instance.isAuthenticated ? '/home' : '/login';
    context.go(next);
  }

  @override
  void dispose() {
    _intro.dispose();
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFFF7F4EB),
              Color(0xFFEFE9DC),
              Color(0xFFE8F0EA),
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: AnimatedBuilder(
              animation: Listenable.merge([_intro, _pulse]),
              builder: (context, _) {
                final pulse = 1 + (_pulse.value * 0.04);
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    FadeTransition(
                      opacity: _fade,
                      child: ScaleTransition(
                        scale: _scale,
                        child: Transform.rotate(
                          angle: _markTurn.value * math.pi,
                          child: Transform.scale(
                            scale: pulse,
                            child: const MiddoAnimatedMark(size: 108),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    FadeTransition(
                      opacity: _fade,
                      child: SlideTransition(
                        position: _wordSlide,
                        child: Column(
                          children: [
                            Text(
                              'Middo',
                              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: -0.8,
                                    color: MiddoColors.ink,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Corporate',
                              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 2.2,
                                    color: MiddoColors.orange,
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 36),
                    FadeTransition(
                      opacity: CurvedAnimation(
                        parent: _intro,
                        curve: const Interval(0.7, 1, curve: Curves.easeOut),
                      ),
                      child: const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: MiddoColors.forest,
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

/// Branded Middo mark drawn as vector paths (no external SVG dependency).
class MiddoAnimatedMark extends StatelessWidget {
  const MiddoAnimatedMark({super.key, this.size = 96});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(size * 0.28),
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [MiddoColors.forest, Color(0xFF2A5A3C)],
              ),
              boxShadow: [
                BoxShadow(
                  color: MiddoColors.forest.withValues(alpha: 0.28),
                  blurRadius: 28,
                  offset: const Offset(0, 14),
                ),
              ],
            ),
          ),
          // Soft orange accent arc
          CustomPaint(
            size: Size(size, size),
            painter: const _MiddoMarkPainter(),
          ),
          // Fallback logo image if available, tinted white via ColorFiltered
          Padding(
            padding: EdgeInsets.all(size * 0.22),
            child: Image.asset(
              'assets/images/middo-logo.png',
              fit: BoxFit.contain,
              color: Colors.white,
              colorBlendMode: BlendMode.srcIn,
              errorBuilder: (_, __, ___) => Text(
                'M',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: size * 0.42,
                  fontWeight: FontWeight.w800,
                  height: 1,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MiddoMarkPainter extends CustomPainter {
  const _MiddoMarkPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final orange = Paint()
      ..color = MiddoColors.orange.withValues(alpha: 0.9)
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.055
      ..strokeCap = StrokeCap.round;

    final rect = Rect.fromLTWH(
      size.width * 0.14,
      size.height * 0.14,
      size.width * 0.72,
      size.height * 0.72,
    );
    canvas.drawArc(rect, -math.pi * 0.15, math.pi * 0.55, false, orange);

    final dot = Paint()..color = Colors.white.withValues(alpha: 0.85);
    canvas.drawCircle(
      Offset(size.width * 0.78, size.height * 0.28),
      size.width * 0.035,
      dot,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
