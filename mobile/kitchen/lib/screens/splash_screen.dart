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
  late final AnimationController _intro = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  );
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300),
  )..repeat(reverse: true);
  late final AnimationController _steam = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
  )..repeat();

  late final Animation<double> _fade = CurvedAnimation(
    parent: _intro,
    curve: const Interval(0, 0.45, curve: Curves.easeOut),
  );
  late final Animation<double> _scale = Tween<double>(begin: 0.72, end: 1).animate(
    CurvedAnimation(parent: _intro, curve: const Interval(0, 0.55, curve: Curves.easeOutBack)),
  );
  late final Animation<Offset> _wordSlide = Tween<Offset>(
    begin: const Offset(0, 0.35),
    end: Offset.zero,
  ).animate(
    CurvedAnimation(parent: _intro, curve: const Interval(0.35, 0.85, curve: Curves.easeOutCubic)),
  );

  @override
  void initState() {
    super.initState();
    _intro.forward();
    Future<void>.delayed(const Duration(milliseconds: 2200), _goNext);
  }

  void _goNext() {
    if (!mounted) return;
    _pulse.stop();
    _steam.stop();
    if (AuthStore.instance.isAuthenticated) {
      context.go('/home');
    } else {
      context.go('/login');
    }
  }

  @override
  void dispose() {
    _intro.dispose();
    _pulse.dispose();
    _steam.dispose();
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
              animation: Listenable.merge([_intro, _pulse, _steam]),
              builder: (context, _) {
                final pulse = 1 + (_pulse.value * 0.035);
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    FadeTransition(
                      opacity: _fade,
                      child: ScaleTransition(
                        scale: _scale,
                        child: Transform.scale(
                          scale: pulse,
                          child: SizedBox(
                            width: 112,
                            height: 112,
                            child: Stack(
                              alignment: Alignment.center,
                              clipBehavior: Clip.none,
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(28),
                                  child: Image.asset(
                                    'assets/images/middo-kitchen-app-icon.png',
                                    width: 112,
                                    height: 112,
                                    fit: BoxFit.cover,
                                  ),
                                ),
                                Positioned(
                                  top: -6,
                                  child: CustomPaint(
                                    size: const Size(48, 36),
                                    painter: _SteamPainter(progress: _steam.value),
                                  ),
                                ),
                              ],
                            ),
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
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -0.8,
                                    color: MiddoColors.forest,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Kitchen',
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

class _SteamPainter extends CustomPainter {
  const _SteamPainter({required this.progress});

  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = MiddoColors.orange.withValues(alpha: 0.55 + 0.35 * math.sin(progress * math.pi * 2))
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.2
      ..strokeCap = StrokeCap.round;

    for (var i = 0; i < 2; i++) {
      final x = size.width * (0.35 + i * 0.28);
      final rise = (progress + i * 0.35) % 1.0;
      final path = Path();
      final top = size.height * (0.85 - rise * 0.9);
      path.moveTo(x, size.height * 0.9);
      path.cubicTo(
        x + 5,
        size.height * 0.65,
        x - 6,
        size.height * 0.4,
        x + (i == 0 ? -2 : 3),
        top,
      );
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _SteamPainter oldDelegate) =>
      oldDelegate.progress != progress;
}
