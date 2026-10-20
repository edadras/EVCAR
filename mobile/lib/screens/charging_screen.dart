import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/edge.dart';
import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/gauge.dart';

/// شارژ در حال انجام: درصد باتری، انرژی، توان، مدت، هزینه و زمان باقی‌مانده.
/// هر ۵ ثانیه از `charging/status` (داده‌های واقعی MeterValues شارژر) به‌روز می‌شود.
class ChargingScreen extends StatefulWidget {
  const ChargingScreen({super.key, this.edge});

  /// در حالت محلی، وضعیت و توقف مستقیم از لبهٔ ایستگاه خوانده/ارسال می‌شود.
  final EdgeLink? edge;

  @override
  State<ChargingScreen> createState() => _ChargingScreenState();
}

class _ChargingScreenState extends State<ChargingScreen> {
  Map<String, dynamic>? session;
  Map<String, dynamic> live = {};
  int balance = 0;
  bool loading = true;
  String? error;
  bool stopping = false;
  Timer? _poll;
  Timer? _tick;
  EdgeLink? edge;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    edge ??= widget.edge;
  }

  /// پاسخ `app/charging/status` لبه به شکل جلسهٔ ابری.
  Map<String, dynamic>? _edgeSession(Map<String, dynamic> d) => d['active'] == true
      ? {
          'station': d['station'],
          'charge_point': d['charge_point'],
          'connector_number': d['connector_number'],
          'pct': d['pct'],
          'energy_kwh': d['energy'],
          'cost': d['cost'],
        }
      : null;

  Future<void> _loadEdge(EdgeLink link) async {
    final d = await link.api.get('app/charging/status');
    if (!mounted) return;
    setState(() {
      edge = link;
      session = _edgeSession(d);
      live = d;
      balance = asInt(d['wallet_balance']);
      loading = false;
      error = null;
    });
  }

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) => _refresh());
    // مدت شارژ بین دو نمونه هم مثل وب‌اپ ثانیه‌به‌ثانیه جلو می‌رود.
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (session != null && mounted) setState(() => live['secs'] = asInt(live['secs']) + 1);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    _tick?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    if (edge != null) {
      try {
        return await _loadEdge(edge!);
      } on ApiException catch (e) {
        if (mounted) {
          setState(() {
            loading = false;
            error = e.message;
          });
        }
        return;
      }
    }
    final edgeManager = context.app.edge;
    try {
      final d = await context.api.get('charging');
      if (!mounted) return;
      setState(() {
        session = d['session'] as Map<String, dynamic>?;
        live = asMap(d['live']);
        balance = asInt(d['wallet_balance']);
        loading = false;
        error = null;
      });
    } on ApiException catch (e) {
      // بدون اینترنت: اگر به WiFi ایستگاه وصلیم، جلسه از لبهٔ محلی خوانده می‌شود.
      final link = await edgeManager.discover();
      if (link != null && edgeManager.hasCredentials) {
        try {
          return await _loadEdge(link);
        } on ApiException {
          // ادامه با پیام خطای ابری
        }
      }
      if (mounted) {
        setState(() {
          loading = false;
          error = e.message;
        });
      }
    }
  }

  Future<void> _refresh() async {
    if (session == null) return;
    try {
      final d = edge != null ? await edge!.api.get('app/charging/status') : await context.api.get('charging/status');
      if (!mounted) return;
      if (d['active'] == true) {
        setState(() {
          live = d;
          if (edge != null) balance = asInt(d['wallet_balance'], balance);
        });
      } else {
        _poll?.cancel();
        await _finished();
      }
    } on ApiException {
      // قطعی لحظه‌ای شبکه؛ نمونهٔ بعدی دوباره تلاش می‌کند.
    }
  }

  Future<void> _stop() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('توقف شارژ', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        content: const Text('شارژ متوقف شود؟ هزینهٔ انرژی تحویل‌شده از کیف پول کسر می‌شود.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('ادامهٔ شارژ')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Palette.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('توقف')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => stopping = true);
    final res = await runAction(context, () => edge != null ? edge!.api.post('app/charging/stop') : context.api.post('charging/stop'), showSuccess: edge == null);
    if (!mounted) return;
    setState(() => stopping = false);
    if (res == null) return;
    if (edge != null) {
      _poll?.cancel();
      await _finished(energy: asDouble(res['energy_kwh'], asDouble(live['energy'])), cost: (asDouble(res['cost_amount']) / 10).round());
    } else if (res['redirect'] == 'history') {
      await openScreen(context, 'history');
    } else {
      setState(() => live['state'] = 'stop_requested');
    }
  }

  /// پایان جلسه: در حالت ابری سوابق باز می‌شود؛ در حالت محلی خلاصهٔ جلسه نمایش داده می‌شود
  /// (صورتحساب پس از اتصال ایستگاه به اینترنت با سرور همگام می‌شود).
  Future<void> _finished({double? energy, int? cost}) async {
    if (edge == null) {
      toast(context, 'جلسهٔ شارژ پایان یافت؛ صورتحساب در سوابق ثبت شد.');
      await openScreen(context, 'history');
      return;
    }
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const CircleAvatar(radius: 30, backgroundColor: Palette.green, child: Icon(Icons.check_rounded, color: Colors.white, size: 36)),
        title: const Text('شارژ پایان یافت', style: TextStyle(fontWeight: FontWeight.w800)),
        content: Text(
          'انرژی: ${kwh(energy ?? asDouble(live['energy']), 2)} kWh\nهزینه: ${money(cost ?? asInt(live['cost']))} تومان\n\nصورتحساب پس از اتصال ایستگاه به اینترنت در سوابق شما ثبت می‌شود.',
          textAlign: TextAlign.center,
          style: const TextStyle(height: 1.9),
        ),
        actions: [FilledButton(onPressed: () => Navigator.pop(ctx), child: const Text('باشه'))],
      ),
    );
    if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.of(context).padding.top;
    return Scaffold(
      backgroundColor: Palette.chargingBg,
      body: Container(
        decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF0B1D2E), Color(0xFF060E18)], begin: Alignment.topCenter, end: Alignment.bottomCenter)),
        child: loading
            ? const Center(child: CircularProgressIndicator(color: Colors.white))
            : session == null
                ? _empty(top)
                : _live(context, top),
      ),
    );
  }

  Widget _header(double top, String title, {String? sub}) => Padding(
        padding: EdgeInsets.fromLTRB(18, top + 8, 18, 0),
        child: Row(children: [
          RoundIconButton(Icons.chevron_right_rounded, dark: true, size: 24, onTap: () => Navigator.maybePop(context)),
          Expanded(
            child: Column(children: [
              if (sub != null) Text(sub, style: TextStyle(color: Colors.white.withValues(alpha: .8), fontSize: 12)),
              Text(title, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
            ]),
          ),
          RoundIconButton(Icons.support_agent_rounded, dark: true, onTap: () => openScreen(context, 'support')),
        ]),
      );

  Widget _empty(double top) => Column(children: [
        _header(top, error != null ? 'خطا' : 'شارژ فعال نیست'),
        Expanded(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text(error ?? 'جلسهٔ شارژ فعالی ندارید.', textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF9FB4C8), fontSize: 15, height: 1.8)),
                const SizedBox(height: 20),
                AppButton(error != null ? 'تلاش دوباره' : 'بازگشت به خانه', kind: BtnKind.orange, expand: false, onPressed: error != null ? _load : () => openScreen(context, 'home')),
              ]),
            ),
          ),
        ),
      ]);

  Widget _live(BuildContext context, double top) {
    final s = session!;
    final pct = asInt(live['pct'], asInt(s['pct']));
    final goal = asInt(live['goal'], 80);
    final energy = asDouble(live['energy'], asDouble(s['energy_kwh']));
    final power = asInt(live['power']);
    final eta = live['eta'];
    final stopRequested = live['state'] == 'stop_requested';
    final connector = s['connector_number'] != null ? 'کانکتور ${fa(s['connector_number'])}' : '${s['charge_point'] ?? '—'}';

    Widget stat(IconData icon, String value, String unit, String label) => Expanded(
          child: Container(
            margin: const EdgeInsets.all(5),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .06),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: Colors.white.withValues(alpha: .1)),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: .1), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, color: const Color(0xFF7DB9E8), size: 20),
              ),
              const SizedBox(height: 14),
              Text.rich(TextSpan(children: [
                TextSpan(text: value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
                if (unit.isNotEmpty) TextSpan(text: ' $unit', style: const TextStyle(color: Color(0xFF9FB4C8), fontSize: 12)),
              ])),
              const SizedBox(height: 4),
              Text(label, style: const TextStyle(color: Color(0xFF9FB4C8), fontSize: 12)),
            ]),
          ),
        );

    return Column(children: [
      _header(top, '${s['station'] ?? ''} · $connector', sub: edge != null ? 'در حال شارژ · اتصال محلی ایستگاه' : 'در حال شارژ'),
      Expanded(
        child: ListView(padding: const EdgeInsets.fromLTRB(13, 20, 13, 20), children: [
          Center(
            child: RingGauge(
              pct: pct.toDouble(),
              size: 250,
              stroke: 18,
              track: Colors.white.withValues(alpha: .12),
              colors: const [Color(0xFF16A34A), Color(0xFF7EE2A6), Color(0xFF16A34A)],
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.bolt_rounded, color: Color(0xFF7EE2A6), size: 34),
                Text('${fa(pct)}٪', style: const TextStyle(color: Colors.white, fontSize: 56, fontWeight: FontWeight.w800, height: 1.1)),
                const Text('شارژ باتری', style: TextStyle(color: Color(0xFF9FB4C8), fontSize: 13)),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  decoration: BoxDecoration(color: Palette.green.withValues(alpha: .18), borderRadius: BorderRadius.circular(20)),
                  child: Text('+${kwh(energy, energy < 10 ? 2 : 1)} kWh افزوده شد', style: const TextStyle(color: Color(0xFF7EE2A6), fontSize: 12, fontWeight: FontWeight.w700)),
                ),
              ]),
            ),
          ),
          const SizedBox(height: 18),
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const _FlowDots(),
            const SizedBox(width: 8),
            Text(stopRequested ? 'دستور توقف ارسال شد؛ منتظر پاسخ شارژر…' : 'انرژی در حال انتقال به خودرو…',
                style: const TextStyle(color: Color(0xFFCFE0F0), fontSize: 13, fontWeight: FontWeight.w600)),
          ]),
          const SizedBox(height: 16),
          Row(children: [
            stat(Icons.bolt_rounded, power > 0 ? fa(power) : '—', 'kW', 'توان لحظه‌ای'),
            stat(Icons.schedule_rounded, duration(asInt(live['secs'])), '', 'مدت شارژ'),
          ]),
          Row(children: [
            stat(Icons.toll_outlined, money(asInt(live['cost'], asInt(s['cost']))), '', 'هزینه (تومان)'),
            stat(Icons.timelapse_rounded, eta != null ? '~${fa(eta)}' : '—', 'دق', 'تا شارژ کامل'),
          ]),
          Container(
            margin: const EdgeInsets.all(5),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: .06), borderRadius: BorderRadius.circular(18), border: Border.all(color: Colors.white.withValues(alpha: .1))),
            child: Column(children: [
              Row(children: [
                Expanded(child: Text('هدف شارژ: ${fa(goal)}٪', style: const TextStyle(color: Color(0xFFCFE0F0), fontSize: 13, fontWeight: FontWeight.w600))),
                Text('${fa((goal - pct).clamp(0, 100))}٪ مانده', style: const TextStyle(color: Color(0xFFCFE0F0), fontSize: 13, fontWeight: FontWeight.w600)),
              ]),
              const SizedBox(height: 10),
              LayoutBuilder(builder: (context, box) {
                return Stack(clipBehavior: Clip.none, children: [
                  Container(height: 10, decoration: BoxDecoration(color: Colors.white.withValues(alpha: .1), borderRadius: BorderRadius.circular(6))),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 600),
                    height: 10,
                    width: box.maxWidth * (pct.clamp(0, 100) / 100),
                    decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF16A34A), Color(0xFF7EE2A6)]), borderRadius: BorderRadius.circular(6)),
                  ),
                  PositionedDirectional(
                    start: box.maxWidth * (goal.clamp(0, 100) / 100) - 1.5,
                    top: -4,
                    child: Container(width: 3, height: 18, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(2))),
                  ),
                ]);
              }),
            ]),
          ),
        ]),
      ),
      Container(
        padding: EdgeInsets.fromLTRB(18, 14, 18, 14 + MediaQuery.of(context).padding.bottom),
        decoration: BoxDecoration(color: const Color(0xFF0E2233), border: Border(top: BorderSide(color: Colors.white.withValues(alpha: .08)))),
        child: Column(children: [
          Row(children: [
            const Expanded(child: Text('پرداخت از: کیف پول SafirCharge', style: TextStyle(color: Color(0xFFCFE0F0), fontSize: 12.5))),
            Text('موجودی: ${money(balance)}', style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w800)),
          ]),
          const SizedBox(height: 12),
          AppButton(
            stopRequested ? 'در حال توقف شارژ…' : 'توقف شارژ',
            icon: Icons.stop_circle_outlined,
            kind: BtnKind.red,
            loading: stopping,
            onPressed: stopRequested ? null : _stop,
          ),
        ]),
      ),
    ]);
  }
}

class _FlowDots extends StatefulWidget {
  const _FlowDots();

  @override
  State<_FlowDots> createState() => _FlowDotsState();
}

class _FlowDotsState extends State<_FlowDots> with SingleTickerProviderStateMixin {
  late final AnimationController ctl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();

  @override
  void dispose() {
    ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: ctl,
      builder: (_, _) => Row(children: [
        for (var i = 0; i < 3; i++)
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 2),
            width: 7,
            height: 7,
            decoration: BoxDecoration(
              color: const Color(0xFF7EE2A6).withValues(alpha: .3 + .7 * (1 - ((ctl.value * 3 - i) % 3).clamp(0, 1))),
              shape: BoxShape.circle,
            ),
          ),
      ]),
    );
  }
}
