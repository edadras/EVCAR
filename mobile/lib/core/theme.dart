import 'package:flutter/material.dart';

/// توکن‌های رنگی دقیقاً مطابق `public/assets/mobile/app.css` وب‌اپ.
@immutable
class Palette extends ThemeExtension<Palette> {
  const Palette({
    required this.accent,
    required this.accentSoft,
    required this.accentStrong,
    required this.bg,
    required this.surface,
    required this.surface2,
    required this.surface3,
    required this.text,
    required this.text2,
    required this.muted,
    required this.border,
    required this.orangeSoft,
    required this.greenSoft,
    required this.redSoft,
    required this.amberSoft,
    required this.dark,
  });

  static const orange = Color(0xFFF97316);
  static const green = Color(0xFF16A34A);
  static const red = Color(0xFFDC2626);
  static const amber = Color(0xFFD97706);
  static const violet = Color(0xFF7C3AED);
  static const chargingBg = Color(0xFF0B1B2B);

  final Color accent, accentSoft, accentStrong;
  final Color bg, surface, surface2, surface3;
  final Color text, text2, muted, border;
  final Color orangeSoft, greenSoft, redSoft, amberSoft;
  final bool dark;

  factory Palette.light(Color accent) => Palette(
        accent: accent,
        accentSoft: Color.lerp(Colors.white, accent, .12)!,
        accentStrong: Color.lerp(accent, Colors.black, .18)!,
        bg: const Color(0xFFF3F6FA),
        surface: Colors.white,
        surface2: const Color(0xFFF5F8FC),
        surface3: const Color(0xFFEDF1F7),
        text: const Color(0xFF0F1E2E),
        text2: const Color(0xFF43566B),
        muted: const Color(0xFF8194A8),
        border: const Color(0xFFE7ECF3),
        orangeSoft: const Color(0xFFFFF1E6),
        greenSoft: const Color(0xFFE7F6EC),
        redSoft: const Color(0xFFFCEAEA),
        amberSoft: const Color(0xFFFBF1DD),
        dark: false,
      );

  factory Palette.darkFor(Color accent) => Palette(
        accent: accent,
        accentSoft: Color.lerp(const Color(0xFF0E1A26), accent, .26)!,
        accentStrong: Color.lerp(accent, Colors.white, .28)!,
        bg: const Color(0xFF0A1622),
        surface: const Color(0xFF0F2030),
        surface2: const Color(0xFF122537),
        surface3: const Color(0xFF17293C),
        text: const Color(0xFFEAF1F8),
        text2: const Color(0xFFA9BACB),
        muted: const Color(0xFF6E8499),
        border: const Color(0xFF1E3346),
        orangeSoft: const Color(0xFF3A2410),
        greenSoft: const Color(0xFF11291C),
        redSoft: const Color(0xFF341818),
        amberSoft: const Color(0xFF33260F),
        dark: true,
      );

  /// رنگ‌های آیکون‌کاشی (ic-blue / ic-green / ic-orange / ...).
  (Color, Color) tone(String name) => switch (name) {
        'green' || 'ic-green' => (greenSoft, green),
        'orange' || 'ic-orange' => (orangeSoft, orange),
        'red' || 'ic-red' => (redSoft, red),
        'amber' || 'ic-amber' => (amberSoft, amber),
        'violet' || 'ic-violet' => (Color.lerp(surface, violet, .14)!, violet),
        _ => (accentSoft, accentStrong),
      };

  @override
  Palette copyWith() => this;

  @override
  Palette lerp(ThemeExtension<Palette>? other, double t) => other is Palette && t > .5 ? other : this;
}

extension PaletteContext on BuildContext {
  Palette get c => Theme.of(this).extension<Palette>()!;
}

ThemeData buildTheme(Color accent, {required bool dark}) {
  final p = dark ? Palette.darkFor(accent) : Palette.light(accent);
  final scheme = ColorScheme.fromSeed(
    seedColor: accent,
    brightness: dark ? Brightness.dark : Brightness.light,
  ).copyWith(primary: accent, surface: p.surface, onSurface: p.text, error: Palette.red);

  final base = ThemeData(
    useMaterial3: true,
    brightness: dark ? Brightness.dark : Brightness.light,
    colorScheme: scheme,
    fontFamily: 'Vazirmatn',
    scaffoldBackgroundColor: p.bg,
    extensions: [p],
  );

  OutlineInputBorder border(Color c, [double w = 1]) =>
      OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: c, width: w));

  return base.copyWith(
    textTheme: base.textTheme.apply(bodyColor: p.text, displayColor: p.text, fontFamily: 'Vazirmatn'),
    appBarTheme: AppBarTheme(
      backgroundColor: p.bg,
      foregroundColor: p.text,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(fontFamily: 'Vazirmatn', fontSize: 20, fontWeight: FontWeight.w800, color: p.text),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: p.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: border(p.border),
      enabledBorder: border(p.border),
      focusedBorder: border(accent, 1.6),
      errorBorder: border(Palette.red),
      labelStyle: TextStyle(color: p.text2, fontSize: 13),
      hintStyle: TextStyle(color: p.muted, fontSize: 13),
    ),
    dividerTheme: DividerThemeData(color: p.border, thickness: 1, space: 1),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: p.dark ? p.surface3 : const Color(0xFF0F1E2E),
      contentTextStyle: const TextStyle(fontFamily: 'Vazirmatn', color: Colors.white, fontSize: 13),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: p.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
    ),
    switchTheme: SwitchThemeData(
      thumbColor: const WidgetStatePropertyAll(Colors.white),
      trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? Palette.green : p.surface3),
      trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
    ),
  );
}
