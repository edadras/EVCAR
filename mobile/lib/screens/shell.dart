import 'dart:async';

import 'package:flutter/material.dart';

import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'account_screens.dart';
import 'edge_screens.dart';
import 'home_screen.dart';
import 'map_screen.dart';
import 'scan_screen.dart';
import 'wallet_screens.dart';

/// کنترل تب‌های پایین از هر جای اپ (مثلاً «بازگشت به خانه»).
class ShellController extends ChangeNotifier {
  ShellController._();
  static final instance = ShellController._();

  int index = 0;
  int tick = 0;

  void go(int i) {
    index = i;
    tick++;
    notifyListeners();
  }

  /// بارگذاری دوبارهٔ تب فعلی (پس از بازگشت از صفحات دیگر).
  void refresh() {
    tick++;
    notifyListeners();
  }
}

/// هر بار که به صفحهٔ ریشه برمی‌گردیم، داده‌های تب فعلی تازه می‌شود (مثل بارگذاری مجدد صفحه در وب).
class ShellRefreshObserver extends NavigatorObserver {
  @override
  void didPop(Route<dynamic> route, Route<dynamic>? previousRoute) {
    if (previousRoute?.isFirst ?? false) ShellController.instance.refresh();
  }
}

class Shell extends StatefulWidget {
  const Shell({super.key});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  Timer? _discover;

  @override
  void initState() {
    super.initState();
    // مانند edge-router وب‌اپ: لبهٔ محلی ایستگاه به‌طور دوره‌ای جست‌وجو می‌شود.
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final app = context.app;
      app.edge.discover();
      // موقعیت واقعی گوشی جایگزین موقعیت مرجع می‌شود تا فاصله‌ها درست باشند.
      if (await app.location.sync(app.api)) ShellController.instance.refresh();
    });
    _discover = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) context.app.edge.discover(force: true);
    });
  }

  @override
  void dispose() {
    _discover?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ctl = ShellController.instance;
    final edge = context.app.edge;
    return ListenableBuilder(
      listenable: Listenable.merge([ctl, edge]),
      builder: (context, _) {
        final tabs = <Widget>[const HomeTab(), const MapTab(), const WalletTab(), const AccountTab()];
        return Scaffold(
          body: SafeArea(
            bottom: false,
            child: Column(children: [
              if (edge.active != null) _EdgeBanner(label: edge.active!.label),
              Expanded(child: KeyedSubtree(key: ValueKey('${ctl.index}-${ctl.tick}'), child: tabs[ctl.index])),
            ]),
          ),
          bottomNavigationBar: _TabBar(index: ctl.index, onTap: ctl.go),
        );
      },
    );
  }
}

/// نوار «اتصال محلی» وقتی گوشی روی WiFi ایستگاه است (badge «محلی» وب‌اپ).
class _EdgeBanner extends StatelessWidget {
  const _EdgeBanner({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Palette.green,
      child: InkWell(
        onTap: () => push(context, const EdgeStationScreen()),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          child: Row(children: [
            const Icon(Icons.wifi_rounded, color: Colors.white, size: 18),
            const SizedBox(width: 8),
            Expanded(child: Text('$label · شارژ بدون اینترنت', overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700))),
            const Text('ورود', style: TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w800)),
            const LtrIcon(Icons.chevron_left_rounded, color: Colors.white, size: 18),
          ]),
        ),
      ),
    );
  }
}

class _TabBar extends StatelessWidget {
  const _TabBar({required this.index, required this.onTap});

  final int index;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    Widget item(int i, IconData icon, IconData active, String label) {
      final sel = i == index;
      return Expanded(
        child: InkWell(
          onTap: () => onTap(i),
          child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(sel ? active : icon, color: sel ? c.accent : c.muted, size: 24),
            const SizedBox(height: 4),
            Text(label, style: TextStyle(fontSize: 11, fontWeight: sel ? FontWeight.w800 : FontWeight.w600, color: sel ? c.accent : c.muted)),
          ]),
        ),
      );
    }

    return Container(
      decoration: BoxDecoration(
        color: c.surface,
        border: Border(top: BorderSide(color: c.border)),
        boxShadow: c.dark ? null : const [BoxShadow(color: Color(0x0F0F1E2E), blurRadius: 24, offset: Offset(0, -6))],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 70,
          child: Row(children: [
            item(0, Icons.home_outlined, Icons.home_rounded, 'پیشخان'),
            item(1, Icons.map_outlined, Icons.map_rounded, 'نقشه'),
            Expanded(
              child: Center(
                child: GestureDetector(
                  onTap: () => push(context, const ScanScreen()),
                  child: Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(19),
                      gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.orange, .55)!], begin: Alignment.topRight, end: Alignment.bottomLeft),
                      boxShadow: [BoxShadow(color: c.accent.withValues(alpha: .35), blurRadius: 16, offset: const Offset(0, 6))],
                      border: Border.all(color: c.surface, width: 3),
                    ),
                    child: const Icon(Icons.qr_code_scanner_rounded, color: Colors.white, size: 27),
                  ),
                ),
              ),
            ),
            item(2, Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, 'کیف پول'),
            item(3, Icons.person_outline_rounded, Icons.person_rounded, 'حساب'),
          ]),
        ),
      ),
    );
  }
}
