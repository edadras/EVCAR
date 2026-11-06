import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/theme.dart';

/// تصاویر برند (هم‌سان با `public/assets/app-img` وب‌اپ).
abstract final class Art {
  static const loginHero = 'assets/img/login-hero.webp';
  static const homeHero = 'assets/img/home-hero.webp';
  static const navigator = 'assets/img/navigator.webp';
  static const station = 'assets/img/station-default.webp';
  static const assistant = 'assets/img/assistant.webp';
  static const scan = 'assets/img/scan.webp';
  static const offers = 'assets/img/offers.webp';
  static const wallet = 'assets/img/wallet.webp';
  static const battery = 'assets/img/battery.webp';
  static const logoWhite = 'assets/img/quad-logo-white.png';
  static const icon = 'assets/img/quad-icon-256.png';
}

/// رنگ‌های برند QUAD EV.
abstract final class Brand {
  static const navy = Color(0xFF07131F);
  static const navy2 = Color(0xFF0E2236);
  static const cyan = Color(0xFF22D3EE);
  static const gradient = LinearGradient(
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
    colors: [Color(0xFF0B2A44), Color(0xFF0F4C75), Color(0xFF0E7490)],
  );
}

bool _reduced(BuildContext context) => MediaQuery.maybeDisableAnimationsOf(context) ?? false;

/// انیمیشن تکرارشونده‌ای که با «کاهش حرکت» سیستم متوقف می‌شود.
mixin _Loop<T extends StatefulWidget> on State<T> {
  AnimationController get _c;
  bool get _reverse => true;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_reduced(context)) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat(reverse: _reverse);
    }
  }
}

/// ورود پلکانی (fade + slide) — معادل `.fx-rv` وب‌اپ.
class Reveal extends StatefulWidget {
  const Reveal({super.key, required this.child, this.index = 0, this.offset = 18});
  final Widget child;
  final int index;
  final double offset;

  @override
  State<Reveal> createState() => _RevealState();
}

class _RevealState extends State<Reveal> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 520));
  late final Animation<double> _a = CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);

  Timer? _delay;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_c.status != AnimationStatus.dismissed || _delay != null) return;
    if (_reduced(context)) {
      _c.value = 1;
    } else {
      _delay = Timer(Duration(milliseconds: 60 * math.min(widget.index, 8)), _c.forward);
    }
  }

  @override
  void dispose() {
    _delay?.cancel();
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return widget.child;
    return AnimatedBuilder(
      animation: _a,
      child: widget.child,
      builder: (_, child) => Opacity(
        opacity: _a.value,
        child: Transform.translate(offset: Offset(0, (1 - _a.value) * widget.offset), child: child),
      ),
    );
  }
}

/// بازخورد فشار (scale down) — معادل `:active` وب‌اپ.
class Pressable extends StatefulWidget {
  const Pressable({super.key, required this.child, this.onTap, this.scale = .97});
  final Widget child;
  final VoidCallback? onTap;
  final double scale;

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  bool _down = false;

  void _set(bool v) {
    if (widget.onTap != null && _down != v) setState(() => _down = v);
  }

  @override
  Widget build(BuildContext context) => Listener(
        onPointerDown: (_) => _set(true),
        onPointerUp: (_) => _set(false),
        onPointerCancel: (_) => _set(false),
        child: AnimatedScale(
          scale: _down ? widget.scale : 1,
          duration: const Duration(milliseconds: 140),
          curve: Curves.easeOut,
          child: GestureDetector(onTap: widget.onTap, child: widget.child),
        ),
      );
}

/// زوم و حرکت آرام تصویر (Ken Burns).
class KenBurns extends StatefulWidget {
  const KenBurns({super.key, required this.child, this.seconds = 18});
  final Widget child;
  final int seconds;

  @override
  State<KenBurns> createState() => _KenBurnsState();
}

class _KenBurnsState extends State<KenBurns> with SingleTickerProviderStateMixin, _Loop {
  @override
  late final AnimationController _c =
      AnimationController(vsync: this, duration: Duration(seconds: widget.seconds));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return widget.child;
    return AnimatedBuilder(
      animation: _c,
      child: widget.child,
      builder: (_, child) {
        final t = Curves.easeInOut.transform(_c.value);
        return Transform.scale(
          scale: 1.04 + .08 * t,
          child: Transform.translate(offset: Offset(-10 * t, -6 * t), child: child),
        );
      },
    );
  }
}

/// شناوری آرام بالا/پایین (برای تصاویر بُرش‌خورده).
class Floating extends StatefulWidget {
  const Floating({super.key, required this.child, this.amplitude = 6, this.seconds = 4});
  final Widget child;
  final double amplitude;
  final int seconds;

  @override
  State<Floating> createState() => _FloatingState();
}

class _FloatingState extends State<Floating> with SingleTickerProviderStateMixin, _Loop {
  @override
  late final AnimationController _c =
      AnimationController(vsync: this, duration: Duration(seconds: widget.seconds));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return widget.child;
    return AnimatedBuilder(
      animation: _c,
      child: widget.child,
      builder: (_, child) => Transform.translate(
        offset: Offset(0, -widget.amplitude * Curves.easeInOut.transform(_c.value)),
        child: child,
      ),
    );
  }
}

/// شمارش عدد از صفر تا مقدار نهایی.
class CountUp extends StatelessWidget {
  const CountUp({super.key, required this.value, required this.format, this.style});
  final num value;
  final String Function(num v) format;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return Text(format(value), style: style);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: value.toDouble()),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeOutCubic,
      builder: (_, v, _) => Text(format(value is int ? v.round() : v), style: style),
    );
  }
}

/// جرقه‌های شناور روی پس‌زمینهٔ تیره.
class Sparks extends StatefulWidget {
  const Sparks({super.key, this.count = 18, this.color = Brand.cyan});
  final int count;
  final Color color;

  @override
  State<Sparks> createState() => _SparksState();
}

class _SparksState extends State<Sparks> with SingleTickerProviderStateMixin, _Loop {
  @override
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(seconds: 12));
  @override
  bool get _reverse => false;
  late final List<List<double>> _p = List.generate(widget.count, (i) {
    final r = math.Random(i * 7919);
    return [r.nextDouble(), r.nextDouble(), .5 + r.nextDouble(), 1 + r.nextDouble() * 2];
  });

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return const SizedBox.shrink();
    return IgnorePointer(
      child: RepaintBoundary(
        child: CustomPaint(painter: _SparkPainter(_c, _p, widget.color), size: Size.infinite),
      ),
    );
  }
}

class _SparkPainter extends CustomPainter {
  _SparkPainter(this.t, this.p, this.color) : super(repaint: t);
  final Animation<double> t;
  final List<List<double>> p;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    for (final s in p) {
      final y = (s[1] - t.value * s[2]) % 1.0;
      final fade = math.sin(y * math.pi);
      final paint = Paint()
        ..color = color.withValues(alpha: .55 * fade)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2);
      canvas.drawCircle(Offset(s[0] * size.width, y * size.height), s[3], paint);
    }
  }

  @override
  bool shouldRepaint(_SparkPainter old) => false;
}

/// بنر تصویری با گرادیان و متن — معادل `.fx-hero` وب‌اپ.
class ImageBanner extends StatelessWidget {
  const ImageBanner({
    super.key,
    required this.image,
    required this.child,
    this.height = 150,
    this.onTap,
    this.kenBurns = true,
    this.alignment = Alignment.center,
  });
  final String image;
  final Widget child;
  final double height;
  final VoidCallback? onTap;
  final bool kenBurns;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) {
    final img = Image.asset(image, fit: BoxFit.cover, alignment: alignment, width: double.infinity, height: height);
    return Pressable(
      onTap: onTap,
      child: Container(
        height: height,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          color: Brand.navy,
          boxShadow: [BoxShadow(color: Brand.navy.withValues(alpha: .25), blurRadius: 22, offset: const Offset(0, 10))],
        ),
        child: Stack(fit: StackFit.expand, children: [
          kenBurns ? KenBurns(child: img) : img,
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerRight,
                end: Alignment.centerLeft,
                colors: [Color(0xE607131F), Color(0x9907131F), Color(0x1007131F)],
                stops: [0, .5, 1],
              ),
            ),
          ),
          Padding(padding: const EdgeInsets.all(18), child: child),
        ]),
      ),
    );
  }
}

/// تصویر خالی‌بودن صفحه با شناوری و هالهٔ رنگی.
class EmptyArt extends StatelessWidget {
  const EmptyArt({super.key, required this.image, this.size = 150});
  final String image;
  final double size;

  @override
  Widget build(BuildContext context) {
    final accent = context.c.accent;
    return SizedBox(
      width: size,
      height: size,
      child: Stack(alignment: Alignment.center, children: [
        Container(
          width: size * .82,
          height: size * .82,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(colors: [accent.withValues(alpha: .20), accent.withValues(alpha: 0)]),
          ),
        ),
        Floating(child: Image.asset(image, width: size, height: size, fit: BoxFit.contain)),
      ]),
    );
  }
}

/// تصویر ایستگاه از سرور (آدرس نسبی یا کامل)؛ در نبود آن تصویر پیش‌فرض برند.
class StationImage extends StatelessWidget {
  const StationImage({super.key, required this.url, required this.baseUrl, this.width, this.height, this.radius = 16});
  final String? url;
  final String baseUrl;
  final double? width, height;
  final double radius;

  static String? resolve(String? url, String baseUrl) {
    if (url == null || url.isEmpty) return null;
    if (url.startsWith('http')) return url;
    final base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    return url.startsWith('/') ? '$base$url' : '$base/$url';
  }

  @override
  Widget build(BuildContext context) {
    final fallback = Image.asset(Art.station, width: width, height: height, fit: BoxFit.cover);
    final src = resolve(url, baseUrl);
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: src == null
          ? fallback
          : Image.network(
              src,
              width: width,
              height: height,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => fallback,
              frameBuilder: (_, child, frame, sync) => sync
                  ? child
                  : AnimatedOpacity(opacity: frame == null ? 0 : 1, duration: const Duration(milliseconds: 300), child: child),
            ),
    );
  }
}

/// خط اسکن متحرک روی کادر دوربین.
class ScanLine extends StatefulWidget {
  const ScanLine({super.key});

  @override
  State<ScanLine> createState() => _ScanLineState();
}

class _ScanLineState extends State<ScanLine> with SingleTickerProviderStateMixin, _Loop {
  @override
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 2200));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_reduced(context)) return const SizedBox.shrink();
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, _) => Align(
          alignment: Alignment(0, -.85 + 1.7 * Curves.easeInOut.transform(_c.value)),
          child: Container(
            height: 3,
            margin: const EdgeInsets.symmetric(horizontal: 22),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(3),
              gradient: LinearGradient(colors: [Brand.cyan.withValues(alpha: 0), Brand.cyan, Brand.cyan.withValues(alpha: 0)]),
              boxShadow: [BoxShadow(color: Brand.cyan.withValues(alpha: .7), blurRadius: 14)],
            ),
          ),
        ),
      ),
    );
  }
}

/// لوگوی QUAD EV.
class QuadLogo extends StatelessWidget {
  const QuadLogo({super.key, this.height = 34});
  final double height;

  @override
  Widget build(BuildContext context) => Image.asset(Art.logoWhite, height: height, fit: BoxFit.contain);
}
