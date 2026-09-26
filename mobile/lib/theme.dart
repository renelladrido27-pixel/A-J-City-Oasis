import 'package:flutter/material.dart';

/// Brand palette carried over from the web app (resources/views/home.blade.php)
/// so the mobile app matches the existing A & J OASIS identity.
class OasisColors {
  static const green = Color(0xFF1B4332);
  static const greenDark = Color(0xFF10291F);
  static const gold = Color(0xFFC9963E);
  static const goldDark = Color(0xFFB8852F);
  static const sand = Color(0xFFFAF6EE);
  static const ink = Color(0xFF1F2D27);
  static const border = Color(0xFF1F2D27);
  static const muted = Color(0xFF6B7A72);
  static const placeholderGrey = Color(0xFFB9C0BC);
}

ThemeData buildOasisTheme() {
  final base = ThemeData(useMaterial3: true);
  return base.copyWith(
    scaffoldBackgroundColor: Colors.white,
    colorScheme: base.colorScheme.copyWith(
      primary: OasisColors.green,
      secondary: OasisColors.gold,
      surface: Colors.white,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: OasisColors.ink,
      elevation: 0,
      surfaceTintColor: Colors.transparent,
      centerTitle: false,
    ),
    textTheme: base.textTheme.apply(
      bodyColor: OasisColors.ink,
      displayColor: OasisColors.ink,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(6),
        borderSide: const BorderSide(color: OasisColors.border, width: 1.4),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(6),
        borderSide: const BorderSide(color: OasisColors.border, width: 1.4),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(6),
        borderSide: const BorderSide(color: OasisColors.green, width: 2),
      ),
      hintStyle: const TextStyle(color: OasisColors.muted),
    ),
    dividerTheme: const DividerThemeData(
      color: Color(0xFFE3E1D9),
      thickness: 1,
    ),
  );
}
