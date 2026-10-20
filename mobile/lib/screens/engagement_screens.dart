import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

const _star = Color(0xFFF59E0B);

/// رسید جلسهٔ شارژ: جزئیات هزینه، نمودار توان و امتیازدهی به ایستگاه (معادل /app/history/{uuid}).
class SessionReceiptScreen extends StatefulWidget {
  const SessionReceiptScreen({super.key, required this.uuid});

  final String uuid;

  @override
  State<SessionReceiptScreen> createState() => _SessionReceiptScreenState();
}

class _SessionReceiptScreenState extends State<SessionReceiptScreen> {
  final _body = GlobalKey<AsyncBodyState>();
  final _comment = TextEditingController();
  final Set<String> _tags = {};
  int _rating = 0;
  bool _sending = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _sending = true);
    final res = await runAction(context, () => context.api.post('sessions/${widget.uuid}/review', {
          'rating': _rating,
          'comment': _comment.text.trim(),
          'tags': _tags.toList(),
        }));
    if (!mounted) return;
    setState(() => _sending = false);
    if (res != null) _body.currentState?.reload();
  }

  void _share(Map<String, dynamic> r) {
    shareText(context, [
      'رسید شارژ SafirCharge',
      '${asMap(r['station'])['name']} — ${r['started_label']}',
      'انرژی: ${kwh(asDouble(r['energy_kwh']), 2)} kWh · مدت: ${fa(r['duration_min'])} دقیقه',
      for (final l in asList(r['lines'])) '${l['label']}: ${money(asInt(l['amount']))} تومان',
      'جمع کل: ${money(asInt(r['total']))} تومان',
    ].join('\n'));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: AsyncBody(
          key: _body,
          load: () => context.api.get('sessions/${widget.uuid}'),
          builder: (context, r, reload) {
            final c = context.c;
            final st = asMap(r['station']);
            final review = r['review'] is Map ? asMap(r['review']) : null;
            final curve = asList(r['curve']);
            final tags = asMap(r['tags']);
            final done = r['done'] == true;

            Widget tile(String l, String v) => Expanded(
                  child: AppCard(
                    padding: const EdgeInsets.all(12),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(l, style: TextStyle(fontSize: 11.5, color: c.muted)),
                      const SizedBox(height: 2),
                      Text(v, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
                    ]),
                  ),
                );
            Widget line(String l, String v, {bool bold = false, Color? color}) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(children: [
                    Expanded(child: Text(l, style: TextStyle(color: bold ? c.text : c.muted, fontWeight: bold ? FontWeight.w700 : null, fontSize: 13))),
                    Text(v, style: TextStyle(fontWeight: FontWeight.w800, fontSize: bold ? 15 : 13, color: color)),
                  ]),
                );

            return ListView(padding: const EdgeInsets.fromLTRB(18, 0, 18, 32), children: [
              PageHeader('رسید جلسهٔ شارژ', actions: [RoundIconButton(Icons.share_outlined, onTap: () => _share(r))]),
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(children: [
                  IconTile(done ? Icons.check_rounded : Icons.bolt_rounded, tone: done ? 'green' : 'amber', size: 54),
                  const SizedBox(height: 8),
                  Text(done ? 'پرداخت‌شده از کیف پول' : 'جلسه در جریان', style: TextStyle(fontSize: 12, color: c.muted)),
                  const SizedBox(height: 4),
                  Text.rich(TextSpan(children: [
                    TextSpan(text: money(asInt(r['total'])), style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800)),
                    TextSpan(text: ' تومان', style: TextStyle(fontSize: 13, color: c.muted)),
                  ])),
                  Text('${st['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${r['started_label']} تا ${r['stopped_label']}', style: TextStyle(fontSize: 12, color: c.muted)),
                ]),
              ),
              const SizedBox(height: 10),
              Row(children: [
                tile('انرژی', '${kwh(asDouble(r['energy_kwh']), 2)} kWh'),
                const SizedBox(width: 10),
                tile('مدت', '${fa(r['duration_min'])} دقیقه'),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                tile('میانگین توان', r['avg_power_kw'] != null ? '${kwh(asDouble(r['avg_power_kw']))} kW' : '—'),
                const SizedBox(width: 10),
                tile('CO₂ کمتر', '${kwh(asDouble(r['co2_kg']))} kg'),
              ]),
              if (curve.length > 1) ...[
                const SecTitle('نمودار توان شارژ'),
                AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('حداکثر ${kwh(curve.map((p) => asDouble(p['kw'])).fold<double>(0, (a, b) => b > a ? b : a))} kW', style: TextStyle(fontSize: 11.5, color: c.muted)),
                    const SizedBox(height: 6),
                    SizedBox(height: 110, child: CustomPaint(painter: _CurvePainter(curve.map((p) => (asDouble(p['t']), asDouble(p['kw']))).toList(), c.accent), size: Size.infinite)),
                    const SizedBox(height: 4),
                    Directionality(
                      textDirection: TextDirection.ltr,
                      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('شروع', style: TextStyle(fontSize: 11.5, color: c.muted)),
                        Text('${fa(r['duration_min'])} دقیقه', style: TextStyle(fontSize: 11.5, color: c.muted)),
                      ]),
                    ),
                  ]),
                ),
              ],
              const SecTitle('جزئیات هزینه'),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                child: Column(children: [
                  for (final l in asList(r['lines']))
                    line('${l['label']}', '${asInt(l['amount']) < 0 ? '−' : ''}${money(asInt(l['amount']).abs())} ت', color: asInt(l['amount']) < 0 ? Palette.green : null),
                  Divider(color: c.border, height: 1),
                  line('جمع کل', '${money(asInt(r['total']))} ت', bold: true),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text('نرخ انرژی: ${money(asInt(r['price_per_kwh']))} ت/kWh${r['rule'] != null ? ' (${r['rule']})' : ''}', style: TextStyle(fontSize: 12, color: c.muted)),
                    ),
                  ),
                ]),
              ),
              const SecTitle('مشخصات'),
              AppCard(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                child: Column(children: [
                  line('ایستگاه', '${st['name']}'),
                  if ('${st['address'] ?? ''}'.isNotEmpty) line('آدرس', '${st['address']}'),
                  line('شارژر / کانکتور', '${r['charger'] ?? '—'} · ${fa(r['connector'] ?? '—')}'),
                  if (r['vehicle'] != null) line('خودرو', '${r['vehicle']}'),
                ]),
              ),
              if (review != null) ...[
                const SecTitle('امتیاز شما'),
                AppCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Stars(asInt(review['rating']), size: 24),
                    if (review['comment'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text('${review['comment']}')),
                    if (review['tags'] is List && (review['tags'] as List).isNotEmpty)
                      Padding(padding: const EdgeInsets.only(top: 6), child: Text((review['tags'] as List).join(' · '), style: TextStyle(fontSize: 12, color: c.muted))),
                    if (review['reply'] != null)
                      Container(
                        margin: const EdgeInsets.only(top: 8),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(10)),
                        child: Text('پاسخ SafirCharge: ${review['reply']}', style: const TextStyle(fontSize: 12.5)),
                      ),
                  ]),
                ),
              ] else if (r['can_review'] == true) ...[
                const SecTitle('تجربهٔ شما چطور بود؟'),
                AppCard(
                  child: Column(children: [
                    Directionality(
                      textDirection: TextDirection.ltr,
                      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                        for (var i = 1; i <= 5; i++)
                          IconButton(
                            iconSize: 38,
                            onPressed: () => setState(() => _rating = i),
                            icon: Icon(i <= _rating ? Icons.star_rounded : Icons.star_outline_rounded, color: i <= _rating ? _star : c.muted),
                          ),
                      ]),
                    ),
                    Wrap(spacing: 7, runSpacing: 7, children: [
                      for (final e in tags.entries)
                        FilterChip(
                          label: Text('${e.value}', style: const TextStyle(fontSize: 12.5)),
                          selected: _tags.contains(e.key),
                          onSelected: (v) => setState(() => v ? _tags.add(e.key) : _tags.remove(e.key)),
                        ),
                    ]),
                    const SizedBox(height: 10),
                    TextField(controller: _comment, maxLines: 3, maxLength: 600, decoration: const InputDecoration(hintText: 'نظر شما (اختیاری)')),
                    AppButton('ثبت امتیاز', loading: _sending, onPressed: _rating > 0 ? _submit : null),
                  ]),
                ),
              ],
              const SizedBox(height: 16),
              Row(children: [
                Expanded(child: AppButton('مشکل در این جلسه', icon: Icons.support_agent_rounded, kind: BtnKind.ghost, onPressed: () => openScreen(context, 'ticket/new'))),
                const SizedBox(width: 10),
                if (st['code'] != null)
                  Expanded(child: AppButton('شارژ دوباره', icon: Icons.bolt_rounded, kind: BtnKind.soft, onPressed: () => openScreen(context, 'charger', {'station': st['code']}))),
              ]),
            ]);
          },
        ),
      ),
    );
  }
}

class _CurvePainter extends CustomPainter {
  _CurvePainter(this.points, this.color);

  final List<(double, double)> points;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    if (points.length < 2) return;
    final tMax = points.last.$1 <= 0 ? 1.0 : points.last.$1;
    final kMax = points.map((p) => p.$2).fold<double>(0, (a, b) => b > a ? b : a);
    final top = kMax <= 0 ? 1.0 : kMax;
    // نمودار از چپ به راست (محور زمان) حتی در رابط راست‌به‌چپ
    Offset at((double, double) p) => Offset(size.width * p.$1 / tMax, size.height - 6 - (size.height - 18) * p.$2 / top);
    final path = Path()..moveTo(at(points.first).dx, at(points.first).dy);
    for (final p in points.skip(1)) {
      path.lineTo(at(p).dx, at(p).dy);
    }
    final area = Path.from(path)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(area, Paint()..color = color.withValues(alpha: .14));
    canvas.drawPath(path, Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4
      ..strokeJoin = StrokeJoin.round);
  }

  @override
  bool shouldRepaint(covariant _CurvePainter old) => old.points != points;
}

/// ستاره‌های امتیاز.
class Stars extends StatelessWidget {
  const Stars(this.rating, {super.key, this.size = 15});

  final int rating;
  final double size;

  @override
  Widget build(BuildContext context) => Directionality(
        textDirection: TextDirection.ltr,
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          for (var i = 1; i <= 5; i++) Icon(Icons.star_rounded, size: size, color: i <= rating ? _star : context.c.border),
        ]),
      );
}

/// تحلیل هزینه و مصرف ۶ ماه اخیر (معادل /app/insights).
class InsightsScreen extends StatelessWidget {
  const InsightsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('تحلیل هزینه و مصرف', subtitle: '۶ ماه اخیر'),
          Expanded(
            child: AsyncBody(
              load: () => context.api.get('insights'),
              builder: (context, d, reload) {
                final c = context.c;
                final t = asMap(d['totals']);
                final months = asList(d['months']);
                final maxCost = months.fold<int>(1, (a, m) => asInt(m['cost']) > a ? asInt(m['cost']) : a);
                final change = d['month_change_pct'];
                final top = d['top_station'] is Map ? asMap(d['top_station']) : null;

                Widget tile(String l, String v) => AppCard(
                      padding: const EdgeInsets.all(12),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(l, style: TextStyle(fontSize: 11.5, color: c.muted)),
                        const SizedBox(height: 2),
                        Text(v, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
                      ]),
                    );

                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 32), children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(20),
                      gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.violet, .55)!]),
                    ),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Text('هزینهٔ کل شارژ', style: TextStyle(color: Colors.white70, fontSize: 12)),
                      const SizedBox(height: 4),
                      Text('${money(asInt(t['cost']))} تومان', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800)),
                      if (change != null)
                        Text('این ماه ${fa(asInt(change).abs())}٪ ${asInt(change) > 0 ? 'بیشتر' : 'کمتر'} از ماه قبل', style: const TextStyle(color: Colors.white, fontSize: 12)),
                    ]),
                  ),
                  const SecTitle('هزینهٔ ماهانه'),
                  AppCard(
                    child: SizedBox(
                      height: 170,
                      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                        for (var i = 0; i < months.length; i++)
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 4),
                              child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
                                Text(asInt(months[i]['cost']) > 0 ? '${fa((asInt(months[i]['cost']) / 1000).round())}k' : '', style: TextStyle(fontSize: 10, color: c.muted)),
                                const SizedBox(height: 4),
                                Flexible(
                                  child: FractionallySizedBox(
                                    heightFactor: (asInt(months[i]['cost']) / maxCost).clamp(.03, 1.0),
                                    child: Container(
                                      constraints: const BoxConstraints(maxWidth: 34),
                                      decoration: BoxDecoration(
                                        color: i == months.length - 1 ? c.accent : c.accentSoft,
                                        borderRadius: const BorderRadius.vertical(top: Radius.circular(9), bottom: Radius.circular(4)),
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text('${months[i]['label']}', style: TextStyle(fontSize: 10.5, color: c.text2), maxLines: 1, overflow: TextOverflow.fade, softWrap: false),
                              ]),
                            ),
                          ),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 14),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 2.3,
                    children: [
                      tile('جلسات', fa(t['sessions'])),
                      tile('انرژی', '${kwh(asDouble(t['energy']))} kWh'),
                      tile('میانگین هر kWh', t['avg_per_kwh'] != null ? '${money(asInt(t['avg_per_kwh']))} ت' : '—'),
                      tile('میانگین مدت', t['avg_minutes'] != null ? '${fa(t['avg_minutes'])} دقیقه' : '—'),
                      tile('شارژ شبانه', '${fa(t['night_share'])}٪'),
                      tile('مسافت تقریبی', '${money(asInt(t['km']))} km'),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: c.greenSoft, borderRadius: BorderRadius.circular(18)),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Text('💚 صرفه‌جویی نسبت به خودروی بنزینی', style: TextStyle(fontWeight: FontWeight.w700, color: Palette.green)),
                      const SizedBox(height: 8),
                      Row(children: [const Expanded(child: Text('هزینهٔ سوخت معادل کمتر')), Text('${money(asInt(t['fuel_saving']))} ت', style: const TextStyle(fontWeight: FontWeight.w800))]),
                      const SizedBox(height: 4),
                      Row(children: [const Expanded(child: Text('کاهش انتشار CO₂')), Text('${money(asInt(t['co2_kg']))} kg', style: const TextStyle(fontWeight: FontWeight.w800))]),
                      const SizedBox(height: 6),
                      Text('برآورد بر اساس ۶٫۵ کیلومتر به‌ازای هر kWh و مصرف ۸ لیتر بنزین در ۱۰۰ کیلومتر.', style: TextStyle(fontSize: 11.5, color: c.text2)),
                    ]),
                  ),
                  if (top != null) ...[
                    const SizedBox(height: 12),
                    AppCard(
                      onTap: () => openScreen(context, 'charger', {'station': top['code']}),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('ایستگاه پرتکرار شما', style: TextStyle(fontSize: 12, color: c.muted)),
                            Text('${top['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          ]),
                        ),
                        Pill('${fa(top['sessions'])} جلسه'),
                      ]),
                    ),
                  ],
                  const SizedBox(height: 12),
                  AppCard(
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Icon(Icons.lightbulb_outline_rounded, color: Palette.green),
                      const SizedBox(width: 10),
                      Expanded(child: Text('${d['tip']}', style: const TextStyle(fontSize: 13, height: 1.7))),
                    ]),
                  ),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

/// بخش امتیاز و نظرات در صفحهٔ ایستگاه.
class StationReviewsSection extends StatelessWidget {
  const StationReviewsSection({super.key, required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final count = asInt(data['count']);
    if (count == 0) {
      return AppCard(child: Text('هنوز نظری ثبت نشده است. پس از شارژ در این ایستگاه از رسید جلسه امتیاز دهید.', style: TextStyle(fontSize: 13, color: c.muted, height: 1.7)));
    }
    final rating = asDouble(data['rating']);
    final dist = asMap(data['distribution']);
    return AppCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Column(children: [
            Text(fa(rating.toStringAsFixed(1)), style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w800, height: 1)),
            const SizedBox(height: 4),
            Stars(rating.round(), size: 14),
            Text('${fa(count)} نظر', style: TextStyle(fontSize: 11, color: c.muted)),
          ]),
          const SizedBox(width: 18),
          Expanded(
            child: Column(children: [
              for (var s = 5; s >= 1; s--)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(children: [
                    Text(fa(s), style: TextStyle(fontSize: 11, color: c.muted)),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(value: count > 0 ? asInt(dist['$s']) / count : 0, minHeight: 6, backgroundColor: c.surface2, color: _star),
                      ),
                    ),
                  ]),
                ),
            ]),
          ),
        ]),
        if (asList(data['top_tags']).isNotEmpty) ...[
          const SizedBox(height: 12),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final t in asList(data['top_tags'])) Pill('${t['tag']} · ${fa(t['count'])}', tone: 'blue'),
          ]),
        ],
        for (final rv in asList(data['items'])) ...[
          Divider(color: c.border, height: 24),
          Row(children: [Expanded(child: Text('${rv['author']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))), Stars(asInt(rv['rating']), size: 13)]),
          if (rv['comment'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text('${rv['comment']}', style: const TextStyle(fontSize: 13, height: 1.7))),
          if (rv['tags'] is List && (rv['tags'] as List).isNotEmpty) Text((rv['tags'] as List).join(' · '), style: TextStyle(fontSize: 12, color: c.muted)),
          if (rv['reply'] != null)
            Container(
              margin: const EdgeInsets.only(top: 6),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(10)),
              child: Text('پاسخ SafirCharge: ${rv['reply']}', style: const TextStyle(fontSize: 12.5)),
            ),
          Text('${rv['date']}', style: TextStyle(fontSize: 11.5, color: c.muted)),
        ],
      ]),
    );
  }
}

/// کارت «وقتی آزاد شد خبرم کن» برای ایستگاهی که همهٔ کانکتورهایش مشغول‌اند.
class WatchStationCard extends StatefulWidget {
  const WatchStationCard({super.key, required this.code, required this.watching});

  final String code;
  final bool watching;

  @override
  State<WatchStationCard> createState() => _WatchStationCardState();
}

class _WatchStationCardState extends State<WatchStationCard> {
  late bool _on = widget.watching;
  bool _busy = false;

  Future<void> _toggle() async {
    setState(() => _busy = true);
    final res = await runAction(context, () => context.api.post('stations/${widget.code}/watch'));
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (res != null) _on = res['watching'] == true;
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return AppCard(
      margin: const EdgeInsets.only(top: 4),
      color: _on ? c.greenSoft : c.accentSoft,
      border: _on ? Palette.green : c.accent,
      padding: const EdgeInsets.all(14),
      child: Row(children: [
        IconTile(_on ? Icons.notifications_active_rounded : Icons.notifications_none_rounded, tone: _on ? 'green' : 'blue', size: 40),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('همهٔ کانکتورها مشغول‌اند', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            const SizedBox(height: 2),
            Text(_on ? 'به محض آزاد شدن به شما اعلان می‌دهیم.' : 'وقتی کانکتوری آزاد شد خبرتان کنیم؟', style: TextStyle(fontSize: 12, color: c.text2)),
          ]),
        ),
        AppButton(_on ? 'لغو' : 'خبرم کن', expand: false, height: 40, kind: _on ? BtnKind.ghost : BtnKind.primary, loading: _busy, onPressed: _toggle),
      ]),
    );
  }
}

/// برگهٔ گزارش خرابی ایستگاه.
Future<void> showReportProblemSheet(BuildContext context, {required String code, required String name, required Map<String, dynamic> problems, List<String> connectors = const []}) async {
  final ticket = await showModalBottomSheet<Map<String, dynamic>>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _ReportSheet(code: code, name: name, problems: problems, connectors: connectors),
  );
  if (ticket != null && context.mounted) {
    await openScreen(context, 'ticket', {'id': ticket['id']});
  }
}

class _ReportSheet extends StatefulWidget {
  const _ReportSheet({required this.code, required this.name, required this.problems, required this.connectors});

  final String code, name;
  final Map<String, dynamic> problems;
  final List<String> connectors;

  @override
  State<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<_ReportSheet> {
  late String _problem = widget.problems.keys.first;
  String? _connector;
  final _desc = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _desc.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() => _sending = true);
    final res = await runAction(context, () => context.api.post('stations/${widget.code}/report', {
          'problem': _problem,
          'connector': _connector,
          'description': _desc.text.trim(),
        }));
    if (!mounted) return;
    setState(() => _sending = false);
    if (res != null) Navigator.of(context).pop(asMap(res['ticket']));
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: EdgeInsets.fromLTRB(18, 8, 18, 18 + MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Center(child: Container(width: 44, height: 5, decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(3)))),
          const SizedBox(height: 12),
          Text('گزارش مشکل — ${widget.name}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          Text('گزارش مستقیم برای تیم فنی ارسال و از بخش پشتیبانی قابل پیگیری است.', style: TextStyle(fontSize: 12, color: c.muted)),
          const SizedBox(height: 10),
          RadioGroup<String>(
            groupValue: _problem,
            onChanged: (v) => setState(() => _problem = v ?? _problem),
            child: Column(children: [
              for (final e in widget.problems.entries)
                RadioListTile<String>(value: e.key, title: Text('${e.value}', style: const TextStyle(fontSize: 13.5)), dense: true, contentPadding: EdgeInsets.zero),
            ]),
          ),
          if (widget.connectors.isNotEmpty)
            DropdownButtonFormField<String>(
              initialValue: _connector,
              decoration: const InputDecoration(hintText: 'کانکتور (اختیاری)'),
              items: [for (final cn in widget.connectors) DropdownMenuItem(value: cn, child: Text(cn))],
              onChanged: (v) => setState(() => _connector = v),
            ),
          const SizedBox(height: 10),
          TextField(controller: _desc, maxLines: 3, maxLength: 1500, decoration: const InputDecoration(hintText: 'توضیح کوتاه (مثلاً پیغام خطای روی صفحه)')),
          AppButton('ثبت گزارش', icon: Icons.report_problem_outlined, kind: BtnKind.orange, loading: _sending, onPressed: _send),
        ]),
      ),
    );
  }
}
