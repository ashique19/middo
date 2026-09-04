import 'package:flutter/services.dart';

/// Light, intentional haptics for key Middo moments.
abstract final class MiddoHaptics {
  static Future<void> selection() => HapticFeedback.selectionClick();

  static Future<void> light() => HapticFeedback.lightImpact();

  static Future<void> success() => HapticFeedback.mediumImpact();

  static Future<void> warning() => HapticFeedback.heavyImpact();
}
