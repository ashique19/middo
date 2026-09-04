import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';

/// Native post-payment confirmation (success / failure / cancelled).
class PaymentResultScreen extends StatefulWidget {
  const PaymentResultScreen({
    super.key,
    required this.success,
    this.title = 'Payment',
    this.message,
    this.primaryLabel,
    this.primaryRoute,
    this.secondaryLabel = 'Go home',
    this.secondaryRoute = '/home',
    this.confirming = false,
  });

  final bool success;
  final String title;
  final String? message;
  final String? primaryLabel;
  final String? primaryRoute;
  final String secondaryLabel;
  final String secondaryRoute;
  final bool confirming;

  static Future<void> open(
    BuildContext context, {
    required bool success,
    String title = 'Payment',
    String? message,
    String? primaryLabel,
    String? primaryRoute,
    String secondaryLabel = 'Go home',
    String secondaryRoute = '/home',
    bool confirming = false,
  }) {
    return Navigator.of(context).push(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 280),
        reverseTransitionDuration: const Duration(milliseconds: 220),
        pageBuilder: (_, animation, __) {
          return FadeTransition(
            opacity: animation,
            child: PaymentResultScreen(
              success: success,
              title: title,
              message: message,
              primaryLabel: primaryLabel,
              primaryRoute: primaryRoute,
              secondaryLabel: secondaryLabel,
              secondaryRoute: secondaryRoute,
              confirming: confirming,
            ),
          );
        },
      ),
    );
  }

  @override
  State<PaymentResultScreen> createState() => _PaymentResultScreenState();
}

class _PaymentResultScreenState extends State<PaymentResultScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (widget.success) {
        MiddoHaptics.success();
      } else {
        MiddoHaptics.warning();
      }
    });
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _go(String route) {
    MiddoHaptics.selection();
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    }
    context.go(route);
  }

  @override
  Widget build(BuildContext context) {
    final ok = widget.success;
    final color = ok ? MiddoColors.forest : MiddoColors.orangeDeep;
    final icon = widget.confirming
        ? Icons.hourglass_top_rounded
        : (ok ? Icons.check_circle_rounded : Icons.error_outline_rounded);
    final headline = widget.confirming
        ? 'Confirming payment…'
        : (ok ? 'Payment successful' : 'Payment not completed');
    final body = widget.message ??
        (widget.confirming
            ? 'Hang tight while Middo verifies your payment.'
            : (ok
                ? 'You can track your lunch from Schedule or Home.'
                : 'No charge was completed. You can try again from checkout.'));

    return Scaffold(
      backgroundColor: MiddoColors.cream,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
          child: Column(
            children: [
              const Spacer(),
              ScaleTransition(
                scale: Tween(begin: 0.96, end: 1.04).animate(
                  CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
                ),
                child: Container(
                  width: 88,
                  height: 88,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, size: 48, color: color),
                ),
              ),
              const SizedBox(height: 22),
              Text(
                headline,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                widget.title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: MiddoColors.inkSoft,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                body,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MiddoColors.inkSoft,
                  height: 1.4,
                ),
              ),
              const Spacer(),
              if (widget.primaryLabel != null &&
                  widget.primaryRoute != null &&
                  !widget.confirming) ...[
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: () => _go(widget.primaryRoute!),
                    child: Text(widget.primaryLabel!),
                  ),
                ),
                const SizedBox(height: 10),
              ],
              if (!widget.confirming)
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () => _go(widget.secondaryRoute),
                    child: Text(widget.secondaryLabel),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
