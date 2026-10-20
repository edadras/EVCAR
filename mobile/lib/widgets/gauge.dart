import 'dart:math' as math;

import 'package:flutter/material.dart';

/// حلقهٔ درصد شارژ (gauge وب‌اپ).
class RingGauge extends StatelessWidget {
  const RingGauge({super.key, required this.pct, this.size = 92, this.stroke = 8, this.track, this.colors, this.child});

  final double pct;
  final double size;
  final double stroke;
  final Color? track;
  final List<Color>? colors;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: pct.clamp(0, 100) / 100),
        duration: const Duration(milliseconds: 700),
        curve: Curves.easeOutCubic,
        builder: (_, v, _) => CustomPaint(
          painter: _RingPainter(v, stroke, track ?? Colors.white.withValues(alpha: .25), colors ?? const [Colors.white, Colors.white]),
          child: Center(child: child),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.value, this.stroke, this.track, this.colors);

  final double value;
  final double stroke;
  final Color track;
  final List<Color> colors;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final r = (size.width - stroke) / 2;
    final center = rect.center;
    canvas.drawCircle(center, r, Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..color = track);
    if (value <= 0) return;
    final arc = Rect.fromCircle(center: center, radius: r);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round
      ..shader = SweepGradient(
        colors: colors,
        transform: const GradientRotation(-math.pi / 2),
      ).createShader(arc);
    // جهت پرشدن مانند وب‌اپ: ساعت‌گرد از بالا.
    canvas.drawArc(arc, -math.pi / 2, 2 * math.pi * value, false, paint);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.value != value || old.colors != colors || old.track != track;
}
