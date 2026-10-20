import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'scan_screen.dart';

// ───────────────────────── اعلان‌ها ─────────────────────────

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _body = GlobalKey<AsyncBodyState>();

  /// مقصد لمس اعلان بر اساس داده‌های آن (رسید جلسه، تیکت، ایستگاه).
  (String, Map<String, dynamic>)? _target(Map<String, dynamic> data) {
    if (data['session'] != null) return ('session', {'uuid': data['session']});
    if (data['ticket'] != null) return ('ticket', {'id': data['ticket']});
    if (data['station'] != null) return ('charger', {'station': data['station']});
    return null;
  }

  (IconData, String) _icon(String? type) => switch (type) {
        'charging' || 'session' || 'charging_completed' || 'session_complete' => (Icons.bolt_rounded, 'blue'),
        'session_failed' => (Icons.power_off_outlined, 'red'),
        'station_free' => (Icons.ev_station_rounded, 'green'),
        'ticket_reply' || 'ticket_resolved' || 'review_reply' => (Icons.support_agent_rounded, 'violet'),
        'wallet_credit' => (Icons.savings_outlined, 'green'),
        'wallet' || 'payment' || 'low_balance' => (Icons.account_balance_wallet_outlined, 'orange'),
        'offer' || 'campaign' => (Icons.card_giftcard_outlined, 'violet'),
        'reservation' => (Icons.calendar_month_outlined, 'green'),
        _ => (Icons.notifications_none_rounded, 'blue'),
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          PageHeader('اعلان‌ها', actions: [
            TextButton(
              onPressed: () async {
                final res = await runAction(context, () => context.api.post('notifications/read'), showSuccess: false);
                if (res != null) _body.currentState?.reload();
              },
              child: const Text('خواندن همه', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ]),
          Expanded(
            child: AsyncBody(
              key: _body,
              load: () => context.api.get('notifications'),
              builder: (context, d, reload) {
                final c = context.c;
                final today = asList(d['today']);
                final earlier = asList(d['earlier']);
                Widget group(String title, List<Map<String, dynamic>> items) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      SecTitle(title, top: 8),
                      AppCard(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        child: Column(children: [
                          for (final (i, n) in items.indexed) ...[
                            if (i > 0) const Divider(),
                            ListRow(
                              icon: _icon(n['type']?.toString()).$1,
                              tone: _icon(n['type']?.toString()).$2,
                              title: '${n['title']}',
                              subtitle: '${n['body'] ?? ''}\n${fa(n['time'] ?? '')}',
                              trailing: n['read'] == true ? null : Container(width: 9, height: 9, decoration: const BoxDecoration(color: Palette.orange, shape: BoxShape.circle)),
                              onTap: _target(asMap(n['data'])) == null ? null : () => openScreen(context, _target(asMap(n['data']))!.$1, _target(asMap(n['data']))!.$2),
                            ),
                          ],
                        ]),
                      ),
                      const SizedBox(height: 12),
                    ]);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  if (today.isEmpty && earlier.isEmpty) EmptyState('اعلانی ندارید.', icon: Icons.notifications_off_outlined, action: Text('اعلان‌های شارژ، پرداخت و طرح‌ها اینجا نمایش داده می‌شوند.', style: TextStyle(color: c.muted, fontSize: 12))),
                  if (today.isNotEmpty) group('امروز', today),
                  if (earlier.isNotEmpty) group('قبل‌تر', earlier),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

// ───────────────────────── طرح‌ها ─────────────────────────

class OffersScreen extends StatelessWidget {
  const OffersScreen({super.key});

  static String badge(Map<String, dynamic> o) {
    if (o['discount_percent'] != null) return '${fa(o['discount_percent'])}%';
    if (o['bonus_amount'] != null) return '${money(asInt(o['bonus_amount']) / 100)}K';
    return '🎁';
  }

  static String summary(Map<String, dynamic> o) {
    final parts = <String>[
      if (o['discount_percent'] != null) '${fa(o['discount_percent'])}٪ تخفیف',
      if (o['bonus_amount'] != null) '${money(asInt(o['bonus_amount']))} تومان اعتبار',
      if (o['ends_at'] != null) 'تا ${fa(o['ends_at'])}',
    ];
    return parts.isEmpty ? '${o['summary'] ?? 'طرح فعال SafirCharge'}' : parts.join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('طرح‌ها و تخفیف‌ها'),
          Expanded(
            child: AsyncBody(
              load: () => context.api.get('offers'),
              builder: (context, d, reload) {
                final c = context.c;
                final list = asList(d['campaigns'])..sort((a, b) => asInt(b['discount_percent']).compareTo(asInt(a['discount_percent'])));
                final hero = list.firstOrNull;
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  if (hero == null) const EmptyState('در حال حاضر طرح فعالی وجود ندارد.', icon: Icons.card_giftcard_outlined),
                  if (hero != null)
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(24),
                        gradient: const LinearGradient(colors: [Color(0xFF2563EB), Color(0xFF7C3AED)], begin: Alignment.topRight, end: Alignment.bottomLeft),
                      ),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            const Text('پیشنهاد ویژه', style: TextStyle(color: Colors.white70, fontSize: 12)),
                            const SizedBox(height: 6),
                            Text('${hero['name']}', style: const TextStyle(color: Colors.white, fontSize: 19, fontWeight: FontWeight.w800)),
                            const SizedBox(height: 6),
                            Text(summary(hero), style: const TextStyle(color: Colors.white, fontSize: 12.5)),
                          ]),
                        ),
                        Text(badge(hero), style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w800)),
                      ]),
                    ),
                  if (list.length > 1) const SecTitle('طرح‌های دیگر'),
                  for (final o in list.skip(1))
                    AppCard(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Row(children: [
                        Container(
                          width: 58,
                          height: 58,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(16)),
                          child: Text(badge(o), style: TextStyle(color: c.accentStrong, fontWeight: FontWeight.w800, fontSize: 16)),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${o['name']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                            const SizedBox(height: 4),
                            Text(summary(o), style: TextStyle(fontSize: 12, color: c.muted)),
                          ]),
                        ),
                      ]),
                    ),
                  const SizedBox(height: 8),
                  Text('تخفیف‌ها هنگام محاسبهٔ صورتحساب شارژ به‌طور خودکار اعمال می‌شوند.', textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: c.muted)),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

// ───────────────────────── حریم خصوصی / قوانین ─────────────────────────

class LegalScreen extends StatelessWidget {
  const LegalScreen({super.key, required this.kind});

  final String kind;

  @override
  Widget build(BuildContext context) {
    final title = kind == 'privacy' ? 'حریم خصوصی و امنیت' : 'قوانین و مقررات';
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          PageHeader(title),
          Expanded(
            child: AsyncBody(
              load: () => context.api.get('legal/$kind'),
              builder: (context, d, _) => ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                AppCard(
                  padding: const EdgeInsets.all(18),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    IconTile(kind == 'privacy' ? Icons.shield_outlined : Icons.gavel_rounded, size: 52),
                    const SizedBox(height: 14),
                    Text('${d['content'] ?? ''}', style: TextStyle(fontSize: 14, height: 2, color: context.c.text2)),
                  ]),
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

// ───────────────────────── تأیید ورود با پلاک (ANPR) ─────────────────────────

class ArrivalScreen extends StatefulWidget {
  const ArrivalScreen({super.key});

  @override
  State<ArrivalScreen> createState() => _ArrivalScreenState();
}

class _ArrivalScreenState extends State<ArrivalScreen> {
  Map<String, dynamic>? data;
  String? error;
  bool busy = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(const Duration(seconds: 4), (_) => _load());
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final d = await context.api.get('arrival');
      if (!mounted) return;
      setState(() {
        data = d;
        error = null;
      });
      if (d['auto_start_status'] == 'started' && d['charging_session_uuid'] != null) {
        _poll?.cancel();
        await openScreen(context, 'charging');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    }
  }

  Future<void> _post(String path) async {
    setState(() => busy = true);
    final res = await runAction(context, () => context.api.post(path));
    if (!mounted) return;
    setState(() => busy = false);
    if (res == null) return;
    if (path.endsWith('cancel')) {
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const ScanScreen()));
    } else if (res['ok'] == true) {
      await openScreen(context, 'charging');
    } else {
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final d = data;
    final pending = d?['pending'] == true;
    final connector = asMap(d?['connector']);
    final expires = asInt(d?['expires_in_sec']);
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('تأیید اتصال'),
          Expanded(
            child: d == null
                ? Center(child: Text(error ?? 'در حال بررسی وضعیت ورود…', style: TextStyle(color: c.muted)))
                : !pending
                    ? EmptyState('ورود فعالی برای تأیید وجود ندارد.\nوقتی دوربین ایستگاه پلاک شما را بخواند، اینجا نمایش داده می‌شود.',
                        icon: Icons.document_scanner_outlined, action: AppButton('بازگشت به خانه', expand: false, onPressed: () => openScreen(context, 'home')))
                    : ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 20), children: [
                        const SizedBox(height: 16),
                        const Center(child: CircleAvatar(radius: 7, backgroundColor: Palette.green)),
                        const SizedBox(height: 12),
                        Center(child: Text(fa(d['plate'] ?? '—'), style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: 1))),
                        Center(child: Text('پلاک شما در ایستگاه شناسایی شد', style: TextStyle(color: c.muted))),
                        const SizedBox(height: 20),
                        AppCard(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                          child: Column(children: [
                            ListRow(icon: Icons.place_outlined, title: 'ایستگاه', subtitle: '${asMap(d['station'])['name'] ?? '—'}'),
                            const Divider(),
                            ListRow(
                              icon: Icons.power_outlined,
                              tone: 'green',
                              title: 'کانکتور',
                              subtitle: fa(connector['label'] ?? '—'),
                              trailing: Pill(
                                switch (connector['ocpp_status']) { 'Preparing' => 'کابل وصل است', 'Available' => 'آماده', 'Charging' => 'در حال شارژ', _ => 'منتظر' },
                                tone: connector['ocpp_status'] == 'Preparing' ? 'green' : 'orange',
                              ),
                            ),
                            const Divider(),
                            ListRow(icon: Icons.videocam_outlined, tone: 'violet', title: 'دوربین', subtitle: '${d['camera'] ?? '—'}'),
                          ]),
                        ),
                        const SizedBox(height: 12),
                        Text('${d['auto_start_message'] ?? ''}', style: TextStyle(color: c.text2, height: 1.7)),
                        if (expires > 0) ...[
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border)),
                            child: Row(children: [
                              Expanded(child: Text('زمان باقی‌مانده برای تأیید', style: TextStyle(color: c.muted))),
                              Text(duration(expires), style: const TextStyle(fontWeight: FontWeight.w800)),
                            ]),
                          ),
                        ],
                        const SizedBox(height: 14),
                        AppButton('لغو و انتخاب کانکتور دیگر', icon: Icons.qr_code_scanner_rounded, kind: BtnKind.ghost, onPressed: d['can_cancel'] == false || busy ? null : () => _post('arrival/cancel')),
                        const SizedBox(height: 8),
                        Text('اگر این کانکتور مشکل دارد، ورود را لغو کنید و QR کانکتور دیگر را اسکن کنید.', style: TextStyle(fontSize: 12, color: c.muted)),
                      ]),
          ),
          if (pending)
            Container(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
              decoration: BoxDecoration(color: c.surface, border: Border(top: BorderSide(color: c.border))),
              child: Row(children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('مرحله بعد', style: TextStyle(fontSize: 12, color: c.muted)),
                    Text(d!['can_confirm'] == true ? 'کابل وصل شد؟ شارژ را شروع کنید' : 'منتظر آماده‌شدن کانکتور', style: const TextStyle(fontWeight: FontWeight.w800)),
                  ]),
                ),
                AppButton('شروع شارژ', icon: Icons.bolt_rounded, kind: BtnKind.orange, expand: false, loading: busy, onPressed: d['can_confirm'] == true ? () => _post('arrival/confirm') : null),
              ]),
            ),
        ]),
      ),
    );
  }
}

// ───────────────────────── آدرس سرور ─────────────────────────

/// آدرس سرور ابری SafirCharge (برای استقرارهای اختصاصی یا محیط تست).
class ServerSettingsScreen extends StatefulWidget {
  const ServerSettingsScreen({super.key});

  @override
  State<ServerSettingsScreen> createState() => _ServerSettingsScreenState();
}

class _ServerSettingsScreenState extends State<ServerSettingsScreen> {
  late final ctl = TextEditingController(text: context.app.baseUrl);
  late final edgeCtl = TextEditingController(text: context.app.edge.manualUrl ?? '');
  String? status;
  String? edgeStatus;
  bool ok = false;
  bool testing = false;
  bool probing = false;

  Future<void> _probeEdge() async {
    setState(() {
      probing = true;
      edgeStatus = null;
    });
    final edge = context.app.edge;
    await edge.setManualUrl(edgeCtl.text);
    final link = edge.active;
    if (!mounted) return;
    setState(() {
      probing = false;
      edgeStatus = link != null ? 'متصل به ${link.label}' : 'لبهٔ ایستگاهی در این شبکه پیدا نشد.';
    });
  }

  Future<void> _test() async {
    setState(() {
      testing = true;
      status = null;
    });
    final app = context.app;
    final previous = app.baseUrl;
    await app.setBaseUrl(ctl.text);
    try {
      await app.api.get('legal/terms');
      ok = true;
      status = 'اتصال به سرور برقرار است.';
    } on ApiException catch (e) {
      ok = false;
      status = e.message;
      await app.setBaseUrl(previous);
    }
    if (mounted) setState(() => testing = false);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('آدرس سرور'),
          Expanded(
            child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
              LabeledField(label: 'آدرس سرور ابری', controller: ctl, hint: 'https://cloud.safircharge.ir', ltr: true, keyboard: TextInputType.url),
              if (status != null) Text(status!, style: TextStyle(color: ok ? Palette.green : Palette.red, fontWeight: FontWeight.w600)),
              const SizedBox(height: 10),
              Text('اپ با این آدرس به API اپ موبایل (/api/v1/mobile) وصل می‌شود. پس از تغییر سرور، دوباره وارد حساب شوید.',
                  style: TextStyle(fontSize: 12.5, color: c.muted, height: 1.7)),
              const SecTitle('لبهٔ محلی ایستگاه (اختیاری)'),
              LabeledField(label: 'آدرس کنترلر ایستگاه روی WiFi', controller: edgeCtl, hint: 'http://station.local', ltr: true, keyboard: TextInputType.url),
              if (edgeStatus != null)
                Text(edgeStatus!, style: TextStyle(color: context.app.edge.active != null ? Palette.green : Palette.red, fontWeight: FontWeight.w600)),
              const SizedBox(height: 10),
              AppButton('جست‌وجوی ایستگاه', icon: Icons.wifi_find_rounded, kind: BtnKind.soft, loading: probing, onPressed: _probeEdge),
              const SizedBox(height: 10),
              Text('ایستگاه‌های ثبت‌شده در سرور به‌صورت خودکار جست‌وجو می‌شوند؛ این آدرس فقط برای ایستگاه‌هایی است که خودکار پیدا نمی‌شوند.',
                  style: TextStyle(fontSize: 12.5, color: c.muted, height: 1.7)),
            ]),
          ),
          Padding(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), child: AppButton('بررسی و ذخیره', icon: Icons.check_rounded, loading: testing, onPressed: _test)),
        ]),
      ),
    );
  }
}
