import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/fx.dart';
import '../widgets/gauge.dart';
import '../widgets/station_card.dart';

/// پیشخان: جلسهٔ فعال، موجودی، طرح ویژه، مسیریاب، دسترسی سریع و نزدیک‌ترین ایستگاه‌ها.
class HomeTab extends StatefulWidget {
  const HomeTab({super.key});

  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> {
  String filter = 'all';

  @override
  Widget build(BuildContext context) {
    return AsyncBody(
      load: () => context.api.get('home'),
      builder: (context, d, reload) {
        final c = context.c;
        final active = d['active_session'] as Map<String, dynamic>?;
        final offer = d['featured_offer'] as Map<String, dynamic>?;
        final user = asMap(d['user']);
        final all = asList(d['nearby_stations']);
        final stations = filterStations(all, filter);
        var i = 0;
        Widget rv(Widget w) => Reveal(index: i++, child: w);

        return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 28), children: [
          rv(Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(fa(d['header_date']), style: TextStyle(fontSize: 12, color: c.muted)),
                Text('سلام، ${d['first_name'] ?? ''} 👋', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
              ]),
            ),
            RoundIconButton(Icons.notifications_none_rounded, badge: asInt(d['unread_notifications']) > 0, onTap: () => openScreen(context, 'notifications')),
          ])),
          const SizedBox(height: 14),
          if (active != null)
            rv(_ActiveCard(active))
          else
            rv(ImageBanner(
              image: Art.homeHero,
              height: 168,
              onTap: () => openScreen(context, 'map'),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                const Pill('QUAD EV', tone: 'blue'),
                const SizedBox(height: 10),
                const Text('شارژ بعدی‌ات\nهمین نزدیکی است', style: TextStyle(color: Colors.white, fontSize: 19, height: 1.45, fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                Text(all.isEmpty ? 'نقشهٔ ایستگاه‌ها را ببینید' : '${fa(all.length)} ایستگاه در نزدیکی شما',
                    style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 12.5)),
              ]),
            )),
          const SizedBox(height: 14),
          rv(AppCard(
            padding: const EdgeInsets.all(14),
            onTap: () => openScreen(context, 'wallet'),
            child: Row(children: [
              Floating(amplitude: 3, child: Image.asset(Art.wallet, width: 50, height: 50)),
              const SizedBox(width: 11),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('موجودی کیف پول', style: TextStyle(fontSize: 12, color: c.muted)),
                  Row(crossAxisAlignment: CrossAxisAlignment.baseline, textBaseline: TextBaseline.alphabetic, children: [
                    CountUp(
                      value: (user['balance'] as num?) ?? 0,
                      format: (v) => money(v),
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                    ),
                    Text(' تومان', style: TextStyle(fontSize: 12, color: c.text2)),
                  ]),
                ]),
              ),
              GestureDetector(
                onTap: () => openScreen(context, 'topup/confirm'),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Brand.cyan, .45)!]),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Row(children: [
                    Icon(Icons.add, color: Colors.white, size: 16),
                    SizedBox(width: 4),
                    Text('افزایش', style: TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700)),
                  ]),
                ),
              ),
            ]),
          )),
          if (offer != null) ...[
            const SizedBox(height: 14),
            rv(Pressable(
              onTap: () => openScreen(context, 'offers'),
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(18),
                  gradient: const LinearGradient(colors: [Color(0xFF2563EB), Color(0xFF7C3AED)], begin: Alignment.centerRight, end: Alignment.centerLeft),
                  boxShadow: [BoxShadow(color: const Color(0xFF4F46E5).withValues(alpha: .3), blurRadius: 18, offset: const Offset(0, 8))],
                ),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(
                        '${offer['name']}${offer['discount_percent'] != null ? ' — ${fa(offer['discount_percent'])}٪' : ''}',
                        style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 2),
                      Text('${fa(d['campaigns_count'])} طرح جدید برای شما فعال است', style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12)),
                    ]),
                  ),
                  Floating(child: Image.asset(Art.offers, width: 62, height: 62)),
                ]),
              ),
            )),
          ],
          const SizedBox(height: 14),
          rv(ImageBanner(
            image: Art.navigator,
            height: 118,
            onTap: () => openScreen(context, 'navigator'),
            child: Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Text('مسیریاب برقی', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text('برنامه‌ریزی مسیر، مصرف انرژی و توقف شارژ', style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 12)),
                ]),
              ),
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: .16), shape: BoxShape.circle),
                child: const Icon(Icons.navigation_rounded, color: Colors.white, size: 20),
              ),
            ]),
          )),
          const SizedBox(height: 16),
          rv(Row(children: [
            for (final (icon, tone, label, screen) in [
              (Icons.qr_code_scanner_rounded, 'blue', 'اسکن QR', 'scan'),
              (Icons.calendar_month_outlined, 'green', 'رزرو شارژر', 'reserve'),
              (Icons.card_giftcard_outlined, 'violet', 'طرح‌ها', 'offers'),
              (Icons.receipt_long_outlined, 'orange', 'سوابق', 'history'),
            ])
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: AppCard(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    onTap: () => openScreen(context, screen),
                    child: Column(children: [
                      IconTile(icon, tone: tone, size: 40),
                      const SizedBox(height: 8),
                      Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                    ]),
                  ),
                ),
              ),
          ])),
          const SizedBox(height: 14),
          rv(AppCard(
            padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
            onTap: () => openScreen(context, 'assistant'),
            child: Row(children: [
              Floating(amplitude: 4, child: Image.asset(Art.assistant, width: 56, height: 56)),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('دستیار هوشمند پشتیبانی', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800)),
                  Text('سؤال دارید؟ همین حالا بپرسید — پاسخ فوری', style: TextStyle(fontSize: 12, color: c.muted)),
                ]),
              ),
              LtrIcon(Icons.chevron_left_rounded, color: c.muted),
            ]),
          )),
          SecTitle('نزدیک‌ترین ایستگاه‌ها', action: 'نقشه', onAction: () => openScreen(context, 'map')),
          Segmented<String>(
            options: const [('all', 'همه'), ('fast', 'شارژ سریع'), ('public', 'عمومی')],
            value: filter,
            onChanged: (v) => setState(() => filter = v),
          ),
          const SizedBox(height: 14),
          if (stations.isEmpty) const EmptyState('ایستگاهی با این فیلتر یافت نشد.', art: Art.station),
          for (final s in stations) rv(StationCard(station: s, onTap: () => openScreen(context, 'charger', {'station': s['code']}))),
        ]);
      },
    );
  }
}

class _ActiveCard extends StatelessWidget {
  const _ActiveCard(this.s);

  final Map<String, dynamic> s;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    Widget stat(String l, String v) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(children: [
            Expanded(child: Text(l, style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 13))),
            Text(v, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
          ]),
        );
    return Pressable(
      onTap: () => openScreen(context, 'charging'),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: const LinearGradient(colors: [Brand.navy2, Color(0xFF0F4C75)], begin: Alignment.topRight, end: Alignment.bottomLeft),
          boxShadow: [BoxShadow(color: c.accent.withValues(alpha: .35), blurRadius: 34, offset: const Offset(0, 16))],
        ),
        child: Stack(children: [
          const Positioned.fill(child: Sparks(count: 14)),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(children: [
              Row(children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: .14), borderRadius: BorderRadius.circular(20)),
                  child: const Row(mainAxisSize: MainAxisSize.min, children: [
                    CircleAvatar(radius: 3, backgroundColor: Color(0xFF7EE2A6)),
                    SizedBox(width: 6),
                    Text('در حال شارژ', style: TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700)),
                  ]),
                ),
                const Spacer(),
                Flexible(child: Text('${s['station'] ?? ''} · ${s['charge_point'] ?? ''}', overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 12))),
              ]),
              const SizedBox(height: 16),
              Row(children: [
                RingGauge(
                  pct: asDouble(s['pct']),
                  child: Text('${fa(s['pct'])}٪', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                ),
                const SizedBox(width: 18),
                Expanded(
                  child: Column(children: [
                    stat('انرژی تحویلی', '${kwh(s['energy_kwh'] as num?)} kWh'),
                    stat('توان لحظه‌ای', '${fa(s['power'])} kW'),
                    stat('زمان باقی‌مانده', s['eta'] != null ? '~${fa(s['eta'])} دقیقه' : '—'),
                  ]),
                ),
              ]),
              const SizedBox(height: 14),
              Container(height: 1, color: Colors.white.withValues(alpha: .16)),
              const SizedBox(height: 14),
              Row(children: [
                Text('هزینهٔ تاکنون', style: TextStyle(color: Colors.white.withValues(alpha: .85), fontSize: 12)),
                const SizedBox(width: 8),
                Text('${money(s['cost'] as num?)} تومان', style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                  decoration: BoxDecoration(color: Brand.cyan.withValues(alpha: .22), borderRadius: BorderRadius.circular(11)),
                  child: const Row(children: [
                    Text('مشاهدهٔ جلسه', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
                    LtrIcon(Icons.chevron_left_rounded, color: Colors.white, size: 16),
                  ]),
                ),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}
