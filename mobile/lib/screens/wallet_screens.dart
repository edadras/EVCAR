import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

/// کیف پول: موجودی، اعتبار پاداش، باشگاه مشتریان و تراکنش‌ها با جست‌وجو و فیلتر.
class WalletTab extends StatefulWidget {
  const WalletTab({super.key});

  @override
  State<WalletTab> createState() => _WalletTabState();
}

class _WalletTabState extends State<WalletTab> {
  String filter = 'all';
  final search = TextEditingController();

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AsyncBody(
      load: () => context.api.get('wallet'),
      builder: (context, d, reload) {
        final c = context.c;
        final loyalty = asMap(d['loyalty']);
        final q = enDigits(search.text.trim());
        final txs = asList(d['transactions']).where((t) {
          if (filter != 'all' && t['ty'] != filter) return false;
          return q.isEmpty || '${t['t']} ${t['d']}'.contains(q) || enDigits('${t['a']}').contains(q);
        }).toList();

        return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 28), children: [
          Row(children: [
            const Expanded(child: Text('کیف پول', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
            RoundIconButton(Icons.receipt_long_outlined, onTap: () => openScreen(context, 'history')),
          ]),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(26),
              gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, const Color(0xFF8B6A4E), .7)!], begin: Alignment.topRight, end: Alignment.bottomLeft),
              boxShadow: [BoxShadow(color: c.accent.withValues(alpha: .3), blurRadius: 30, offset: const Offset(0, 14))],
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Text('موجودی کیف پول', style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 13)),
                const Spacer(),
                const Text('SafirCharge', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
              ]),
              const SizedBox(height: 18),
              Text.rich(TextSpan(children: [
                TextSpan(text: money(d['balance'] as num?), style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w800)),
                TextSpan(text: ' تومان', style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 14)),
              ])),
              const SizedBox(height: 20),
              Row(children: [
                _kv('اعتبار پاداش', money(d['bonus_balance'] as num?)),
                const SizedBox(width: 34),
                _kv('کارت RFID', '${d['rfid_masked'] ?? '—'}'),
              ]),
            ]),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: AppButton('افزایش موجودی', icon: Icons.add, kind: BtnKind.orange, onPressed: () => _topupSheet(context))),
            const SizedBox(width: 10),
            SizedBox(
              width: 56,
              child: AppButton('', icon: Icons.download_outlined, kind: BtnKind.ghost, onPressed: () {
                // صورت‌حساب تراکنش‌ها به‌صورت CSV (قابل باز شدن در اکسل) از برگهٔ اشتراک سیستم.
                final rows = asList(d['transactions']).map((t) => '${t['d']},"${t['t']}",${enDigits('${t['a']}').replaceAll(',', '').replaceAll('−', '-')}');
                shareText(context, ['تاریخ,شرح,مبلغ (تومان)', ...rows].join('\n'));
              }),
            ),
          ]),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              gradient: const LinearGradient(colors: [Color(0xFF7C3AED), Color(0xFFA855F7)]),
            ),
            child: Row(children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: .2), borderRadius: BorderRadius.circular(13)),
                child: const Icon(Icons.card_giftcard_rounded, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('باشگاه SafirCharge', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                  Text('${loyalty['label'] ?? ''}', style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12)),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: LinearProgressIndicator(
                      value: asInt(loyalty['progress_pct']) / 100,
                      minHeight: 7,
                      backgroundColor: Colors.white.withValues(alpha: .25),
                      color: Colors.white,
                    ),
                  ),
                ]),
              ),
            ]),
          ),
          SecTitle('تراکنش‌ها', action: 'همه', onAction: () => openScreen(context, 'history')),
          TextField(
            controller: search,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(hintText: 'جستجو در تراکنش‌ها…', prefixIcon: Icon(Icons.search_rounded)),
          ),
          const SizedBox(height: 12),
          Segmented<String>(
            options: const [('all', 'همه'), ('in', 'واریز'), ('out', 'پرداخت')],
            value: filter,
            onChanged: (v) => setState(() => filter = v),
          ),
          const SizedBox(height: 12),
          if (txs.isEmpty)
            const EmptyState('تراکنشی با این جستجو یافت نشد.', icon: Icons.receipt_outlined)
          else
            AppCard(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              child: Column(children: [
                for (final (i, t) in txs.indexed) ...[
                  if (i > 0) const Divider(),
                  ListRow(
                    icon: t['ic'] == 'plus' ? Icons.add_rounded : Icons.bolt_rounded,
                    tone: '${t['c']}',
                    title: '${t['t']}',
                    subtitle: '${t['d']}',
                    trailing: Text('${t['a']}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: t['pos'] == true ? Palette.green : c.text)),
                  ),
                ],
              ]),
            ),
        ]);
      },
    );
  }

  Widget _kv(String k, String v) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(k, style: TextStyle(color: Colors.white.withValues(alpha: .8), fontSize: 12)),
        const SizedBox(height: 2),
        Text(v, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
      ]);
}

/// برگهٔ انتخاب مبلغ (مانند sheet وب‌اپ) → صفحهٔ تأیید پرداخت.
Future<void> _topupSheet(BuildContext context) async {
  final amount = await showModalBottomSheet<int>(
    context: context,
    isScrollControlled: true,
    builder: (_) => const _AmountSheet(),
  );
  if (amount != null && context.mounted) await push(context, TopupScreen(amount: amount));
}

class _AmountSheet extends StatefulWidget {
  const _AmountSheet();

  @override
  State<_AmountSheet> createState() => _AmountSheetState();
}

class _AmountSheetState extends State<_AmountSheet> {
  final ctl = TextEditingController(text: '200000');
  static const presets = [100000, 200000, 500000, 1000000];

  int get value => int.tryParse(enDigits(ctl.text).replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;

  @override
  void dispose() {
    ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final v = value;
    final valid = v >= 10000 && v <= 50000000;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 10, 20, 20 + MediaQuery.of(context).viewInsets.bottom + MediaQuery.of(context).padding.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Center(child: Container(width: 42, height: 5, decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(5)))),
        const SizedBox(height: 14),
        const Text('افزایش موجودی', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        const SizedBox(height: 16),
        Text('مبلغ مورد نظر (تومان)', style: TextStyle(fontSize: 13, color: c.text2, fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        TextField(
          controller: ctl,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
          onChanged: (_) => setState(() {}),
          decoration: const InputDecoration(suffixText: 'تومان'),
        ),
        const SizedBox(height: 6),
        Text(valid ? '${money(v)} تومان' : 'مبلغ باید بین ۱۰٬۰۰۰ تا ۵۰٬۰۰۰٬۰۰۰ تومان باشد',
            textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: valid ? c.muted : Palette.red)),
        const SizedBox(height: 14),
        Row(children: [
          for (final p in presets)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: GestureDetector(
                  onTap: () => setState(() => ctl.text = '$p'),
                  child: Container(
                    height: 42,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: v == p ? c.accentSoft : c.surface2,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: v == p ? c.accent : c.border),
                    ),
                    child: Text(money(p), style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: v == p ? c.accentStrong : c.text)),
                  ),
                ),
              ),
            ),
        ]),
        const SizedBox(height: 16),
        ListRow(icon: Icons.credit_card_rounded, title: 'درگاه بانکی', subtitle: 'انتخاب درگاه در مرحلهٔ بعد'),
        const SizedBox(height: 14),
        AppButton('ادامه و پرداخت', icon: Icons.add, kind: BtnKind.orange, onPressed: valid ? () => Navigator.pop(context, v) : null),
      ]),
    );
  }
}

/// تأیید پرداخت و انتخاب درگاه (topup-confirm).
class TopupScreen extends StatefulWidget {
  const TopupScreen({super.key, this.amount = 200000});

  final int amount;

  @override
  State<TopupScreen> createState() => _TopupScreenState();
}

class _TopupScreenState extends State<TopupScreen> with WidgetsBindingObserver {
  String? gateway;
  bool paying = false;
  List<Map<String, dynamic>> gateways = [];

  /// پرداختی که صفحهٔ بانک آن برای کاربر باز شده و منتظر نتیجهٔ آن هستیم.
  String? pendingPayment;
  String? waitMessage;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // بازگشت از مرورگر (یا لینک safircharge://payment) → بررسی فوری نتیجه.
    if (state == AppLifecycleState.resumed && pendingPayment != null) _check();
  }

  Future<Map<String, dynamic>> _load() async {
    final d = await context.api.get('wallet');
    gateways = asList(d['gateways']);
    gateway ??= gateways.isNotEmpty ? '${gateways.first['id']}' : null;
    return d;
  }

  Future<void> _pay() async {
    if (gateway == null) return;
    setState(() => paying = true);
    final res = await runAction(context, () => context.api.post('topup', {'amount': widget.amount, 'gateway': gateway}), showSuccess: false);
    if (!mounted) return;
    setState(() => paying = false);
    if (res == null) return;

    final url = res['url']?.toString();
    if (url != null && res['payment'] != null) {
      // درگاه واقعی: صفحهٔ بانک در مرورگر باز می‌شود؛ نتیجه پس از بازگشت از سرور خوانده می‌شود.
      setState(() {
        pendingPayment = '${res['payment']}';
        waitMessage = 'پرداخت را در صفحهٔ بانک کامل کنید؛ پس از بازگشت، نتیجه خودکار بررسی می‌شود.';
      });
      final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      if (!opened && mounted) toast(context, 'مرورگر برای باز کردن درگاه پیدا نشد.', error: true);
      _poll?.cancel();
      var ticks = 0;
      _poll = Timer.periodic(const Duration(seconds: 5), (t) {
        if (++ticks > 180) t.cancel(); // حداکثر ۱۵ دقیقه
        _check();
      });
      return;
    }

    final params = asMap(res['params']);
    await Navigator.of(context).pushReplacement(MaterialPageRoute(
      builder: (_) => TopupResultScreen(amount: asInt(params['amount'], widget.amount), payment: params['payment']?.toString()),
    ));
  }

  Future<void> _check() async {
    final uuid = pendingPayment;
    if (uuid == null) return;
    try {
      final p = await context.api.get('payments/$uuid');
      if (!mounted || pendingPayment == null) return;
      final status = p['status'];
      if (status == 'paid') {
        _poll?.cancel();
        pendingPayment = null;
        await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => TopupResultScreen(amount: asInt(p['amount']), payment: uuid)));
      } else if (status == 'failed' || status == 'cancelled') {
        _poll?.cancel();
        setState(() {
          pendingPayment = null;
          waitMessage = status == 'cancelled' ? 'پرداخت لغو شد. مبلغی به کیف پول اضافه نشد.' : 'پرداخت ناموفق بود. اگر مبلغی کسر شده، حداکثر تا ۷۲ ساعت بازگردانده می‌شود.';
        });
      }
    } catch (_) {
      // نتیجه هنوز آماده نیست یا شبکه قطع است؛ بررسی بعدی دوباره انجام می‌شود.
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final user = context.app.user ?? {};
    Widget row(String k, String v, {bool total = false}) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(children: [
            Expanded(child: Text(k, style: TextStyle(color: total ? c.text : c.muted, fontWeight: total ? FontWeight.w800 : FontWeight.w500))),
            Text(v, style: TextStyle(fontWeight: FontWeight.w800, fontSize: total ? 16 : 14)),
          ]),
        );
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('تأیید پرداخت'),
          Expanded(
            child: AsyncBody(
              refreshable: false,
              load: _load,
              builder: (context, d, _) => ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 20), children: [
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 24),
                  decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(22)),
                  child: Column(children: [
                    Text('مبلغ افزایش موجودی', style: TextStyle(color: c.text2, fontSize: 13)),
                    const SizedBox(height: 6),
                    Text.rich(TextSpan(children: [
                      TextSpan(text: money(widget.amount), style: TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: c.accentStrong)),
                      TextSpan(text: ' تومان', style: TextStyle(fontSize: 14, color: c.text2)),
                    ])),
                  ]),
                ),
                if (waitMessage != null) ...[
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: pendingPayment != null ? c.amberSoft : c.redSoft,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Row(children: [
                      if (pendingPayment != null)
                        const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Palette.amber))
                      else
                        const Icon(Icons.error_outline, color: Palette.red),
                      const SizedBox(width: 12),
                      Expanded(child: Text(waitMessage!, style: TextStyle(fontSize: 13, color: c.text2, height: 1.7))),
                      if (pendingPayment != null) TextButton(onPressed: _check, child: const Text('بررسی')),
                    ]),
                  ),
                ],
                const SizedBox(height: 16),
                AppCard(
                  child: Column(children: [
                    row('مبلغ شارژ', '${money(widget.amount)} تومان'),
                    row('کارمزد تراکنش', 'رایگان'),
                    row('کیف پول مقصد', fa(user['phone'] ?? '—')),
                    const Divider(),
                    row('مبلغ قابل پرداخت', '${money(widget.amount)} تومان', total: true),
                  ]),
                ),
                const SecTitle('درگاه پرداخت'),
                for (final g in gateways)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      padding: const EdgeInsets.all(14),
                      border: gateway == g['id'] ? c.accent : null,
                      color: gateway == g['id'] ? c.accentSoft : null,
                      onTap: () => setState(() => gateway = '${g['id']}'),
                      child: Row(children: [
                        Container(
                          width: 42,
                          height: 42,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(color: _gwColor(g['id']), borderRadius: BorderRadius.circular(12)),
                          child: Text('${g['logo']}', style: TextStyle(fontWeight: FontWeight.w800, color: g['id'] == 'zarin' ? const Color(0xFF5A4500) : Colors.white)),
                        ),
                        const SizedBox(width: 12),
                        Expanded(child: Text('${g['label']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))),
                        Icon(gateway == g['id'] ? Icons.radio_button_checked : Icons.radio_button_off, color: gateway == g['id'] ? c.accent : c.muted),
                      ]),
                    ),
                  ),
                const SizedBox(height: 6),
                Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Icon(Icons.shield_outlined, color: Palette.green, size: 20),
                  const SizedBox(width: 10),
                  Expanded(child: Text('اتصال شما رمزنگاری‌شده است. پس از تأیید، به درگاه بانکی منتقل می‌شوید.', style: TextStyle(fontSize: 12.5, color: c.text2, height: 1.7))),
                ]),
              ]),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 16),
            child: AppButton(pendingPayment != null ? 'باز کردن دوبارهٔ درگاه' : 'انتقال به درگاه پرداخت',
                icon: Icons.credit_card_rounded, kind: BtnKind.orange, loading: paying, onPressed: gateway == null ? null : _pay),
          ),
        ]),
      ),
    );
  }

  Color _gwColor(Object? id) => switch (id) { 'mellat' => const Color(0xFFD6212A), 'saman' => const Color(0xFF00AA55), 'zarin' => const Color(0xFFFFC107), _ => Colors.blueGrey };
}

class TopupResultScreen extends StatelessWidget {
  const TopupResultScreen({super.key, this.amount, this.payment});

  final int? amount;
  final String? payment;

  static const _gatewayNames = {'mellat': 'به‌پرداخت ملت', 'saman': 'سامان (سپ)', 'zarinpal': 'زرین‌پال'};

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: AsyncBody(
          refreshable: false,
          load: () async {
            final api = context.api;
            final w = await api.get('wallet');
            final p = payment != null ? await api.get('payments/$payment') : <String, dynamic>{};
            return {...w, 'payment': p};
          },
          builder: (context, d, _) {
            final c = context.c;
            final p = asMap(d['payment']);
            final paid = p.isEmpty || p['status'] == 'paid';
            final paidAt = DateTime.tryParse('${p['paid_at']}')?.toLocal() ?? DateTime.now();
            String two(int n) => n.toString().padLeft(2, '0');
            Widget row(String k, String v) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 7),
                  child: Row(children: [Expanded(child: Text(k, style: TextStyle(color: c.muted, fontSize: 13))), Text(v, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13))]),
                );
            return Column(children: [
              Expanded(
                child: ListView(padding: const EdgeInsets.fromLTRB(22, 40, 22, 20), children: [
                  Center(
                    child: Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(colors: paid ? const [Palette.green, Color(0xFF4ADE80)] : const [Palette.red, Color(0xFFF87171)]),
                        boxShadow: [BoxShadow(color: (paid ? Palette.green : Palette.red).withValues(alpha: .35), blurRadius: 34, offset: const Offset(0, 16))],
                      ),
                      child: Icon(paid ? Icons.check_rounded : Icons.close_rounded, color: Colors.white, size: 50),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Center(child: Text(paid ? 'پرداخت موفق بود' : 'پرداخت ناموفق بود', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
                  const SizedBox(height: 6),
                  Center(child: Text(paid ? 'موجودی کیف پول شما با موفقیت افزایش یافت.' : 'مبلغی به کیف پول اضافه نشد.', style: TextStyle(color: c.muted))),
                  const SizedBox(height: 26),
                  AppCard(
                    padding: const EdgeInsets.all(20),
                    child: Column(children: [
                      Text.rich(TextSpan(children: [
                        TextSpan(text: money(asInt(p['amount'], amount ?? 0)), style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
                        TextSpan(text: ' تومان', style: TextStyle(fontSize: 14, color: c.text2)),
                      ])),
                      const SizedBox(height: 14),
                      const Divider(),
                      const SizedBox(height: 6),
                      row('موجودی جدید', '${money(d['balance'] as num?)} تومان'),
                      row('شمارهٔ پیگیری', fa(p['reference'] ?? '—')),
                      row('درگاه', _gatewayNames[p['gateway']] ?? '${p['gateway'] ?? '—'}'),
                      row('تاریخ', fa('${paidAt.year}/${two(paidAt.month)}/${two(paidAt.day)} — ${two(paidAt.hour)}:${two(paidAt.minute)}')),
                    ]),
                  ),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 16),
                child: Row(children: [
                  Expanded(child: AppButton('بازگشت به کیف پول', kind: BtnKind.orange, onPressed: () => openScreen(context, 'wallet'))),
                  if (paid) ...[
                    const SizedBox(width: 10),
                    SizedBox(
                      width: 56,
                      child: AppButton('', icon: Icons.ios_share_rounded, kind: BtnKind.ghost, onPressed: () => shareText(context, 'رسید پرداخت SafirCharge\nمبلغ: ${money(asInt(p['amount'], amount ?? 0))} تومان\nشمارهٔ پیگیری: ${fa(p['reference'] ?? '—')}\nتاریخ: ${fa('${paidAt.year}/${two(paidAt.month)}/${two(paidAt.day)} ${two(paidAt.hour)}:${two(paidAt.minute)}')}')),
                    ),
                  ],
                ]),
              ),
            ]);
          },
        ),
      ),
    );
  }
}
