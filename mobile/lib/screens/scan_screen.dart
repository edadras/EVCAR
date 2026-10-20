import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/fmt.dart';
import '../core/state.dart';
import '../widgets/common.dart';
import 'charger_screen.dart';

/// محتوای QR روی کانکتور را به پارامترهای صفحهٔ شارژر تبدیل می‌کند.
/// QR می‌تواند آدرس کامل صفحهٔ شارژر وب‌اپ (`/app/charger?station=..&connector=..&qr=..`) یا فقط توکن باشد.
Map<String, String>? parseChargerQr(String raw) {
  final value = raw.trim();
  if (value.isEmpty) return null;
  final uri = Uri.tryParse(value);
  if (uri != null && uri.hasScheme && uri.queryParameters.isNotEmpty) {
    final q = uri.queryParameters;
    if (q['qr'] == null && q['station'] == null) return null;
    return {
      if (q['station'] != null) 'station': q['station']!,
      if (q['connector'] != null) 'connector': q['connector']!,
      if (q['qr'] != null) 'qr': q['qr']!,
    };
  }
  if (RegExp(r'^[A-Za-z0-9_\-:.]{4,128}$').hasMatch(value)) return {'qr': value};
  return null;
}

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  final controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates, formats: const [BarcodeFormat.qrCode]);
  bool handled = false;
  List<Map<String, dynamic>> targets = [];

  @override
  void initState() {
    super.initState();
    // فهرست کانکتورهای آماده مانند وب‌اپ فقط در نسخهٔ توسعه (برای تست بدون QR فیزیکی).
    if (kDebugMode) {
      context.api.get('scan').then((d) {
        if (mounted) setState(() => targets = asList(d['targets']));
      }).catchError((_) {});
    }
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  void _open(Map<String, String> params) {
    if (handled) return;
    handled = true;
    controller.stop();
    // صفحهٔ اسکن با صفحهٔ شارژر جایگزین می‌شود.
    Navigator.of(context).pushReplacement(MaterialPageRoute(
      builder: (_) => ChargerScreen(station: params['station'], connector: int.tryParse(params['connector'] ?? ''), qr: params['qr']),
    ));
  }

  void _onDetect(BarcodeCapture capture) {
    for (final b in capture.barcodes) {
      final parsed = b.rawValue == null ? null : parseChargerQr(b.rawValue!);
      if (parsed != null) return _open(parsed);
    }
    toast(context, 'این QR متعلق به شارژرهای SafirCharge نیست.', error: true);
  }

  Future<void> _manual() async {
    final ctl = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('ورود دستی کد', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        content: TextField(controller: ctl, autofocus: true, textDirection: TextDirection.ltr, decoration: const InputDecoration(hintText: 'کد درج‌شده روی کانکتور')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(ctx, ctl.text), child: const Text('ادامه')),
        ],
      ),
    );
    if (code == null || !mounted) return;
    final parsed = parseChargerQr(enDigits(code));
    if (parsed == null) return toast(context, 'کد واردشده معتبر نیست.', error: true);
    _open(parsed);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF07121C),
      body: SafeArea(
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 0),
            child: Row(children: [
              RoundIconButton(Icons.chevron_right_rounded, dark: true, size: 24, onTap: () => Navigator.pop(context)),
              const Expanded(child: Center(child: Text('اسکن کد QR', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)))),
              RoundIconButton(Icons.flashlight_on_outlined, dark: true, onTap: () => controller.toggleTorch()),
            ]),
          ),
          Expanded(
            child: Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(28),
                  child: SizedBox(
                    width: 260,
                    height: 260,
                    child: Stack(fit: StackFit.expand, children: [
                      MobileScanner(
                        controller: controller,
                        onDetect: _onDetect,
                        errorBuilder: (context, error) => Container(
                          color: const Color(0xFF0E2233),
                          padding: const EdgeInsets.all(20),
                          alignment: Alignment.center,
                          child: Text(
                            error.errorCode == MobileScannerErrorCode.permissionDenied
                                ? 'دسترسی دوربین داده نشده است. از تنظیمات گوشی اجازهٔ دوربین را فعال کنید یا کد را دستی وارد کنید.'
                                : 'دوربین در دسترس نیست. کد را دستی وارد کنید.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Color(0xFFCFE0F0), fontSize: 13, height: 1.8),
                          ),
                        ),
                      ),
                      const _Corners(),
                    ]),
                  ),
                ),
                const SizedBox(height: 18),
                const Text('کد QR روی شارژر یا کانکتور را داخل کادر قرار دهید', style: TextStyle(color: Color(0xFFCFE0F0), fontSize: 13)),
              ]),
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
            decoration: const BoxDecoration(color: Color(0xFF0E2233), borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              for (final t in targets.take(4))
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: AppButton('رفتن به ${t['label']}', icon: Icons.bolt_rounded, kind: BtnKind.soft, height: 46, onPressed: () {
                    _open({
                      if (t['station'] != null) 'station': '${t['station']}',
                      if (t['connector'] != null) 'connector': '${t['connector']}',
                      if (t['qr'] != null) 'qr': '${t['qr']}',
                    });
                  }),
                ),
              AppButton('ورود دستی کد', icon: Icons.keyboard_alt_outlined, kind: BtnKind.ghost, onPressed: _manual),
              const SizedBox(height: 14),
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.shield_outlined, color: Color(0xFF4ADE80), size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text('پس از اسکن، مجوز شروع شارژ همان کانکتور برای ۲۰ دقیقه برای حساب شما فعال می‌شود.',
                      style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 12.5, height: 1.7)),
                ),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _Corners extends StatelessWidget {
  const _Corners();

  @override
  Widget build(BuildContext context) => IgnorePointer(child: CustomPaint(painter: _CornerPainter()));
}

class _CornerPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = const Color(0xFFF97316)
      ..strokeWidth = 5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    const l = 34.0, m = 12.0;
    final w = size.width, h = size.height;
    for (final (a, b, c) in [
      (Offset(m, m + l), Offset(m, m), Offset(m + l, m)),
      (Offset(w - m - l, m), Offset(w - m, m), Offset(w - m, m + l)),
      (Offset(m, h - m - l), Offset(m, h - m), Offset(m + l, h - m)),
      (Offset(w - m - l, h - m), Offset(w - m, h - m), Offset(w - m, h - m - l)),
    ]) {
      canvas.drawPath(Path()..moveTo(a.dx, a.dy)..lineTo(b.dx, b.dy)..lineTo(c.dx, c.dy), p);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
