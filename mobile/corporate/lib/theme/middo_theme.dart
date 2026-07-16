import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'middo_colors.dart';

ThemeData buildMiddoTheme() {
  final base = ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    scaffoldBackgroundColor: MiddoColors.cream,
    colorScheme: ColorScheme.fromSeed(
      seedColor: MiddoColors.orange,
      primary: MiddoColors.orange,
      secondary: MiddoColors.forest,
      surface: MiddoColors.white,
      onPrimary: Colors.white,
      onSecondary: Colors.white,
      onSurface: MiddoColors.ink,
    ),
  );

  final textTheme = GoogleFonts.plusJakartaSansTextTheme(base.textTheme).apply(
    bodyColor: MiddoColors.ink,
    displayColor: MiddoColors.ink,
  );

  return base.copyWith(
    textTheme: textTheme,
    appBarTheme: AppBarTheme(
      backgroundColor: MiddoColors.cream,
      foregroundColor: MiddoColors.ink,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: textTheme.titleLarge?.copyWith(
        fontWeight: FontWeight.w800,
        letterSpacing: -0.4,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: MiddoColors.orange,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: MiddoColors.orange,
        side: const BorderSide(color: Color(0xFFDDD3BE)),
        backgroundColor: MiddoColors.creamDeep,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFDDD3BE)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFDDD3BE)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: MiddoColors.orange, width: 1.5),
      ),
      labelStyle: textTheme.labelMedium?.copyWith(
        color: MiddoColors.inkSoft,
        fontWeight: FontWeight.w800,
      ),
    ),
    cardTheme: CardThemeData(
      color: MiddoColors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: MiddoColors.creamBorder),
      ),
    ),
    dividerColor: MiddoColors.creamBorder,
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: MiddoColors.white,
      indicatorColor: MiddoColors.forest.withValues(alpha: 0.12),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: selected ? MiddoColors.forest : MiddoColors.muted,
        );
      }),
      iconTheme: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return IconThemeData(
          color: selected ? MiddoColors.forest : MiddoColors.muted,
        );
      }),
    ),
  );
}
