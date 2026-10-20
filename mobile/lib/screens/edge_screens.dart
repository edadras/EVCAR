import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/edge.dart';
import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'charger_screen.dart';
import 'charging_screen.dart';
import 'scan_screen.dart';

/// شارژ محلی روی WiFi ایستگاه — مستقیم با لبهٔ محلی (Edge)، حتی وقتی اینترنت قطع است.
/// همان مراحل وب‌اپ: احراز کانکتور (QR) ← انتخاب کانکتور مجاز ← شروع ← شارژ زنده ← توقف.
class EdgeStationScreen extends StatefulWidget {
  const EdgeStationScreen({super.key, this.qr, this.fallback});

  /// توکن QR اسکن‌شده؛ روی لبه برای همان کانکتور مجوز می‌گیرد.
  final String? qr;

  /// پارامترهای صفحهٔ شارژر ابری اگر QR متعلق به این ایستگاه نباشد.
  final Map<String, String>? fallback;

  @override
  State<EdgeStationScreen> createState() => _EdgeStationScreenState();
}

class _EdgeStationScreenState extends State<EdgeStationScreen> {
  EdgeLink? link;
  Map<String, dynamic>? data;
  String? error;
  bool loading = true;
  bool needLogin = false;
  bool starting = false;
  String? selected;
  int startSoc = 20;
  bool qrUsed = false;
  Map<String, dynamic>? activeSession;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load({bool force = false}) async {
    setState(() {
      loading = true;
      error = null;
    });
    final edge = context.app.edge;
    link = await edge.discover(force: force || link == null);
    if (!mounted) return;
    if (link == null) {
      setState(() => loading = false);
      return;
    }
    if (!edge.hasCredentials) {
      setState(() {
        needLogin = true;
        loading = false;
      });
      return;
    }
    try {
      if (widget.qr != null && !qrUsed) {
        qrUsed = true;
        try {
          final res = await link!.api.post('app/authorize', {'method': 'qr', 'qr_token': widget.qr});
          selected = res['connector_uuid']?.toString();
          if (mounted) toast(context, '${res['message'] ?? 'مجوز ثبت شد.'}');
        } on ApiException catch (e) {
          if (!mounted) return;
          if (e.status == 422 && widget.fallback != null && context.app.loggedIn) {
            // QR متعلق به ایستگاه دیگری است؛ از مسیر ابری ادامه می‌دهیم.
            final f = widget.fallback!;
            await Navigator.of(context).pushReplacement(MaterialPageRoute(
              builder: (_) => ChargerScreen(station: f['station'], connector: int.tryParse(f['connector'] ?? ''), qr: f['qr']),
            ));
            return;
          }
          if (mounted) toast(context, e.message, error: true);
        }
      }
      final d = await link!.api.get('app/connectors');
      final status = await link!.api.get('app/charging/status');
      final conns = asList(d['connectors']);
      if (selected == null || !conns.any((c) => c['uuid'] == selected && c['authorized'] == true && c['free'] == true)) {
        selected = conns.where((c) => c['authorized'] == true && c['free'] == true).map((c) => '${c['uuid']}').firstOrNull;
      }
      if (!mounted) return;
      setState(() {
        data = d;
        activeSession = status['active'] == true ? status : null;
        loading = false;
        needLogin = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        needLogin = e.status == 401;
        error = e.status == 401 ? null : e.message;
      });
    }
  }

  Future<void> _scan() async {
    final params = await Navigator.of(context).push<Map<String, String>>(MaterialPageRoute(builder: (_) => const ScanScreen(pickOnly: true)));
    final token = params?['qr'];
    if (token == null || !mounted) return;
    final res = await runAction(context, () => link!.api.post('app/authorize', {'method': 'qr', 'qr_token': token}));
    if (res != null) {
      selected = res['connector_uuid']?.toString();
      await _load();
    }
  }

  Future<void> _start() async {
    if (selected == null) return;
    setState(() => starting = true);
    final res = await runAction(
      context,
      () => link!.api.post('app/charging/start', {'connector_uuid': selected, 'start_soc': startSoc, 'target_soc': context.app.chargeTargetSoc}),
      showSuccess: false,
    );
    if (!mounted) return;
    setState(() => starting = false);
    if (res != null) await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => ChargingScreen(edge: link)));
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    Widget body;
    if (loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (link == null) {
      body = ListView(padding: const EdgeInsets.all(18), children: [
        EmptyState(
          'ایستگاه محلی پیدا نشد.\nگوشی را به WiFi ایستگاه شارژ وصل کنید و دوباره تلاش کنید.',
          icon: Icons.wifi_find_rounded,
          action: AppButton('جست‌وجوی دوباره', icon: Icons.refresh, expand: false, onPressed: () => _load(force: true)),
        ),
        AppCard(
          child: ListRow(
            icon: Icons.router_outlined,
            title: 'آدرس دستی لبهٔ ایستگاه',
            subtitle: context.app.edge.manualUrl ?? 'مثلاً http://station.local یا http://192.168.1.10',
            chevron: true,
            onTap: () async {
              await openScreen(context, 'server');
              if (mounted) _load(force: true);
            },
          ),
        ),
      ]);
    } else if (needLogin) {
      body = _EdgeLogin(link: link!, onDone: () => _load());
    } else if (error != null) {
      body = ListView(children: [EmptyState(error!, icon: Icons.error_outline, action: AppButton('تلاش دوباره', expand: false, onPressed: _load))]);
    } else {
      body = _station(c);
    }
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          PageHeader('شارژ محلی ایستگاه', subtitle: link?.label ?? 'اتصال مستقیم روی WiFi ایستگاه'),
          Expanded(child: body),
          if (!loading && link != null && !needLogin && error == null && activeSession == null) _startBar(c),
        ]),
      ),
    );
  }

  Widget _station(Palette c) {
    final d = data ?? {};
    final st = asMap(d['station']);
    final conns = asList(d['connectors']);
    final sel = conns.where((x) => x['uuid'] == selected).firstOrNull;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 20), children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.green, .55)!]),
          ),
          child: Row(children: [
            const Icon(Icons.wifi_rounded, color: Colors.white, size: 30),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${st['name'] ?? 'ایستگاه'}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                Text(st['wifi_ssid'] != null ? 'WiFi: ${st['wifi_ssid']} · بدون نیاز به اینترنت' : 'اتصال مستقیم به کنترلر ایستگاه',
                    style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ]),
            ),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              const Text('موجودی', style: TextStyle(color: Colors.white70, fontSize: 11)),
              Text(money(asInt(d['wallet_balance'])), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
            ]),
          ]),
        ),
        if (activeSession != null) ...[
          const SizedBox(height: 14),
          AppCard(
            color: c.greenSoft,
            onTap: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => ChargingScreen(edge: link))),
            child: Row(children: [
              const IconTile(Icons.bolt_rounded, tone: 'green'),
              const SizedBox(width: 12),
              Expanded(child: Text('جلسهٔ شارژ فعال دارید · ${fa(activeSession!['pct'])}٪', style: const TextStyle(fontWeight: FontWeight.w800))),
              const LtrIcon(Icons.chevron_left_rounded),
            ]),
          ),
        ],
        const SizedBox(height: 14),
        AppCard(
          padding: const EdgeInsets.all(14),
          child: Column(children: [
            Row(children: [
              Icon(Icons.verified_user_outlined, color: sel?['authorized'] == true ? Palette.green : c.accent),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  sel?['authorized'] == true ? 'مجوز شروع شارژ فعال است' : 'برای شروع شارژ QR کانکتور را اسکن کنید یا کارت RFID را روی شارژر بزنید.',
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, height: 1.6),
                ),
              ),
            ]),
            const SizedBox(height: 12),
            AppButton('اسکن QR کانکتور', icon: Icons.qr_code_scanner_rounded, kind: BtnKind.soft, height: 46, onPressed: _scan),
          ]),
        ),
        const SecTitle('کانکتورهای ایستگاه'),
        if (conns.isEmpty) const EmptyState('کانکتوری در این ایستگاه ثبت نشده است.', icon: Icons.power_off_outlined),
        for (final cn in conns) _connector(c, cn),
      ]),
    );
  }

  Widget _connector(Palette c, Map<String, dynamic> cn) {
    final ok = cn['authorized'] == true && cn['free'] == true;
    final isSel = cn['uuid'] == selected;
    final (label, tone) = !(cn['online'] == true)
        ? ('آفلاین', 'muted')
        : cn['free'] != true
            ? (cn['ocpp_status'] == 'Charging' ? 'در حال شارژ' : 'مشغول', 'orange')
            : cn['authorized'] == true
                ? ('مجاز', 'green')
                : ('نیاز به احراز', 'muted');
    return GestureDetector(
      onTap: ok ? () => setState(() => selected = '${cn['uuid']}') : null,
      child: Opacity(
        opacity: ok || cn['free'] == true ? 1 : .6,
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: isSel ? c.accentSoft : c.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isSel ? c.accent : c.border, width: 1.5),
          ),
          child: Row(children: [
            IconTile(Icons.power_outlined, tone: cn['free'] == true ? 'green' : 'orange', size: 44),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${cn['charge_point_id']} · کانکتور ${fa(cn['number'])}', style: const TextStyle(fontWeight: FontWeight.w700)),
                Text('حداکثر توان ${fa(cn['power_kw'])} kW', style: TextStyle(fontSize: 12, color: c.muted)),
              ]),
            ),
            Pill(label, tone: tone),
          ]),
        ),
      ),
    );
  }

  Widget _startBar(Palette c) {
    final conns = asList(data?['connectors']);
    final sel = conns.where((x) => x['uuid'] == selected).firstOrNull;
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
      decoration: BoxDecoration(color: c.surface, border: Border(top: BorderSide(color: c.border))),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Text('کانکتور انتخاب‌شده', style: TextStyle(fontSize: 12, color: c.muted)),
            Text(sel == null ? 'ابتدا احراز هویت کنید' : '${sel['charge_point_id']} · کانکتور ${fa(sel['number'])}', style: const TextStyle(fontWeight: FontWeight.w800)),
            Row(children: [
              Text('شارژ فعلی', style: TextStyle(fontSize: 12, color: c.muted)),
              const SizedBox(width: 6),
              DropdownButton<int>(
                value: startSoc,
                isDense: true,
                underline: const SizedBox(),
                items: [for (final p in const [5, 10, 15, 20, 25, 30, 40, 50, 60, 70, 80]) DropdownMenuItem(value: p, child: Text('${fa(p)}٪'))],
                onChanged: (v) => setState(() => startSoc = v ?? 20),
              ),
            ]),
          ]),
        ),
        AppButton('شروع شارژ', icon: Icons.bolt_rounded, kind: BtnKind.orange, expand: false, loading: starting, onPressed: sel == null ? null : _start),
      ]),
    );
  }
}

/// ورود پیامکی روی خود ایستگاه (وقتی اینترنت نیست یا امضای ابری منقضی شده).
class _EdgeLogin extends StatefulWidget {
  const _EdgeLogin({required this.link, required this.onDone});

  final EdgeLink link;
  final VoidCallback onDone;

  @override
  State<_EdgeLogin> createState() => _EdgeLoginState();
}

class _EdgeLoginState extends State<_EdgeLogin> {
  final phone = TextEditingController();
  final code = TextEditingController();
  bool sent = false;
  bool busy = false;
  String? hint;

  @override
  void initState() {
    super.initState();
    final p = context.app.user?['phone'];
    if (p != null) phone.text = '$p';
  }

  Future<void> _request() async {
    setState(() => busy = true);
    try {
      final res = await widget.link.api.post('auth/otp/request', {'phone': enDigits(phone.text.trim()), 'purpose': 'login'});
      setState(() {
        sent = true;
        hint = res['debug_code'] != null ? 'کد آزمایشی: ${fa(res['debug_code'])}' : null;
      });
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _verify() async {
    setState(() => busy = true);
    try {
      final res = await widget.link.api.post('auth/otp/verify', {'phone': enDigits(phone.text.trim()), 'code': enDigits(code.text.trim())});
      final token = res['edge_session_token']?.toString();
      if (token == null) throw ApiException('ورود محلی انجام نشد.');
      if (!mounted) return;
      await context.app.edge.setSessionToken(token);
      widget.onDone();
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(16)),
        child: Text('اینترنت در دسترس نیست یا مدت اعتبار ورود آفلاین تمام شده است. با کد پیامکی ارسال‌شده از مودم ایستگاه وارد شوید.',
            style: TextStyle(fontSize: 13, color: c.text2, height: 1.8)),
      ),
      const SizedBox(height: 18),
      LabeledField(label: 'شماره موبایل', controller: phone, keyboard: TextInputType.phone, ltr: true, textAlign: TextAlign.left, hint: '۰۹۱۲۳۴۵۶۷۸۹'),
      if (sent) ...[
        LabeledField(label: 'کد تأیید ۶ رقمی', controller: code, keyboard: TextInputType.number, ltr: true, textAlign: TextAlign.center, maxLength: 6),
        if (hint != null) Text(hint!, style: TextStyle(fontSize: 12, color: c.muted)),
        const SizedBox(height: 10),
        AppButton('ورود و ادامهٔ شارژ', loading: busy, onPressed: _verify),
        TextButton(onPressed: busy ? null : _request, child: const Text('ارسال مجدد کد')),
      ] else
        AppButton('ارسال کد تأیید', loading: busy, onPressed: _request),
    ]);
  }
}
