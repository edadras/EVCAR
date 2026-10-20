import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

/// جزئیات ایستگاه/شارژر و شروع شارژ — همان منطق صفحهٔ charger وب‌اپ:
/// فقط کانکتوری که برای کاربر مجوز دارد (اسکن QR / RFID / پلاک) و آزاد است قابل انتخاب است.
class ChargerScreen extends StatefulWidget {
  const ChargerScreen({super.key, this.station, this.connector, this.qr});

  final String? station;
  final int? connector;
  final String? qr;

  @override
  State<ChargerScreen> createState() => _ChargerScreenState();
}

class _ChargerScreenState extends State<ChargerScreen> {
  int? selectedId;
  int startSoc = 20;
  bool starting = false;
  bool qrConsumed = false;
  bool? favorite;

  Future<void> _toggleFavorite(Map<String, dynamic> st) async {
    final res = await runAction(context, () => context.api.post('favorites/${st['code']}'));
    if (res != null && mounted) setState(() => favorite = res['favorite'] == true);
  }

  void _share(Map<String, dynamic> st) {
    final lat = st['lat'], lng = st['lng'];
    shareText(context, [
      'ایستگاه شارژ ${st['name']} — SafirCharge',
      if (st['address'] != null) '${st['city'] ?? ''}، ${st['address']}',
      if (lat != null) 'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
    ].join('\n'));
  }
  String? stationCode;

  static const _stateLabels = {
    'charging': ('در حال شارژ', 'orange'),
    'offline': ('آفلاین', 'muted'),
    'fault': ('خطا', 'red'),
    'reserved': ('رزرو', 'amber'),
    'inactive': ('غیرفعال', 'muted'),
  };

  Future<Map<String, dynamic>> _load() async {
    final d = await context.api.get('charger', {
      'station': stationCode ?? widget.station,
      'connector': widget.connector,
      // توکن QR فقط بار اول مصرف می‌شود؛ تازه‌سازی‌های بعدی مجوز ذخیره‌شده را می‌خوانند.
      if (!qrConsumed) 'qr': widget.qr,
    });
    qrConsumed = true;
    stationCode = asMap(d['station'])['code']?.toString();
    final conns = asList(d['connectors']);
    final grant = asMap(d['qr_grant']);
    if (selectedId == null || !conns.any((c) => c['db_id'] == selectedId && _selectable(c))) {
      final preferred = asInt(grant['connector_id'], asInt(d['highlight_connector_id'], -1));
      final match = conns.where((c) => c['db_id'] == preferred && _selectable(c));
      selectedId = match.isNotEmpty ? asInt(match.first['db_id']) : (conns.where(_selectable).map((c) => asInt(c['db_id'])).firstOrNull);
    }
    return d;
  }

  bool _selectable(Map<String, dynamic> c) => c['authorized'] == true && c['st'] == 'free';

  Future<void> _start(Map<String, dynamic> d) async {
    if (selectedId == null) return;
    if (d['has_active_session'] == true) return openScreen(context, 'charging');
    setState(() => starting = true);
    final res = await runAction(context, () => context.api.post('charging/start', {'connector_id': selectedId, 'start_soc': startSoc}), showSuccess: false);
    if (!mounted) return;
    setState(() => starting = false);
    if (res != null) await openScreen(context, res['redirect']?.toString() ?? 'charging', asMap(res['params']));
  }

  Future<void> _navigate(Map<String, dynamic> st) async {
    final lat = st['lat'], lng = st['lng'];
    if (lat == null || lng == null) return;
    final uri = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng');
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && mounted) {
      toast(context, 'برنامهٔ مسیریابی در دسترس نیست.', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AsyncBody(
        load: _load,
        builder: (context, d, reload) {
          final c = context.c;
          final st = asMap(d['station']);
          final conns = asList(d['connectors']);
          final tariff = asMap(d['tariff']);
          final sel = conns.where((x) => x['db_id'] == selectedId).firstOrNull;
          final authorized = sel != null && sel['authorized'] == true;
          final grantType = asMap(d['qr_grant'])['type'] != null ? 'اسکن QR' : 'احراز هویت';
          final amenities = (st['amenities'] as List?)?.map((e) => '$e').toList() ?? const <String>[];

          return Stack(children: [
            ListView(padding: EdgeInsets.zero, children: [
              Container(
                height: 200 + MediaQuery.of(context).padding.top,
                decoration: BoxDecoration(gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.orange, .5)!], begin: Alignment.topRight, end: Alignment.bottomLeft)),
                child: Stack(children: [
                  Center(child: Icon(Icons.ev_station_outlined, size: 72, color: Colors.white.withValues(alpha: .5))),
                  PositionedDirectional(
                    top: MediaQuery.of(context).padding.top + 12,
                    start: 18,
                    child: RoundIconButton(Icons.chevron_right_rounded, size: 24, onTap: () => Navigator.maybePop(context)),
                  ),
                  PositionedDirectional(
                    top: MediaQuery.of(context).padding.top + 12,
                    end: 18,
                    child: RoundIconButton(Icons.share_outlined, onTap: () => _share(st)),
                  ),
                  PositionedDirectional(
                    bottom: 16,
                    start: 18,
                    child: Pill('${fa(d['free_count'])} کانکتور آزاد', dot: true),
                  ),
                ]),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 18, 150),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${st['name']}', style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text([st['city'], st['address']].where((e) => e != null && '$e'.isNotEmpty).join('، '), style: TextStyle(color: c.muted, fontSize: 13)),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    if (st['rating'] != null) Pill('★ ${fa(st['rating'])}${st['reviews'] != null ? ' (${fa(st['reviews'])})' : ''}', tone: 'orange'),
                    Pill(fa(st['hours']), tone: 'green'),
                    Pill('حداکثر ${fa(st['max_power_kw'])} kW', tone: 'blue'),
                  ]),
                  const SizedBox(height: 16),
                  Row(children: [
                    Expanded(child: AppButton('مسیریابی', icon: Icons.navigation_outlined, kind: BtnKind.soft, height: 48, onPressed: st['lat'] == null ? null : () => _navigate(st))),
                    const SizedBox(width: 10),
                    Expanded(child: AppButton('رزرو', icon: Icons.calendar_month_outlined, kind: BtnKind.soft, height: 48, onPressed: () => openScreen(context, 'reserve', {'station': st['code']}))),
                    const SizedBox(width: 10),
                    SizedBox(
                      width: 48,
                      child: AppButton('', icon: favorite ?? st['favorite'] == true ? Icons.star_rounded : Icons.star_outline_rounded, kind: BtnKind.ghost, height: 48, onPressed: () => _toggleFavorite(st)),
                    ),
                  ]),
                  const SizedBox(height: 20),
                  AppCard(
                    padding: const EdgeInsets.all(14),
                    child: Column(children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: authorized ? c.greenSoft : c.accentSoft,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: (authorized ? Palette.green : c.accent).withValues(alpha: .25)),
                        ),
                        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(color: c.surface, borderRadius: BorderRadius.circular(12)),
                            child: Icon(Icons.verified_user_outlined, color: authorized ? Palette.green : c.accentStrong, size: 20),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(authorized ? 'مجوز شروع شارژ فعال است' : 'برای شروع شارژ احراز هویت لازم است',
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                              const SizedBox(height: 4),
                              Text(
                                authorized ? 'روش احراز: $grantType' : 'QR کانکتور را اسکن کنید، کارت RFID را بزنید، یا منتظر تشخیص پلاک باشید.',
                                style: TextStyle(fontSize: 12, color: c.muted, height: 1.65),
                              ),
                            ]),
                          ),
                        ]),
                      ),
                      if (!authorized) ...[
                        const SizedBox(height: 12),
                        Row(children: [
                          Expanded(child: _AuthAction(Icons.qr_code_scanner_rounded, 'اسکن QR', () => openScreen(context, 'scan'))),
                          const SizedBox(width: 10),
                          Expanded(child: _AuthAction(Icons.document_scanner_outlined, 'تأیید پلاک', () => openScreen(context, 'arrival'))),
                        ]),
                      ],
                    ]),
                  ),
                  const SecTitle('کانکتور مجاز'),
                  if (conns.isEmpty) const EmptyState('برای این ایستگاه کانکتوری ثبت نشده است.', icon: Icons.power_off_outlined),
                  for (final cn in conns) _connector(c, cn),
                  const SecTitle('تعرفه'),
                  AppCard(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    child: Column(children: [
                      ListRow(
                        icon: Icons.bolt_rounded,
                        title: 'هزینهٔ انرژی',
                        subtitle: 'هر کیلووات‌ساعت',
                        trailing: _price(c, tariff['energy'], 'ت/kWh'),
                      ),
                      ListRow(
                        icon: Icons.schedule_rounded,
                        tone: 'amber',
                        title: 'هزینهٔ اشغال',
                        subtitle: 'پس از پایان شارژ${tariff['idle_grace'] != null ? ' · ${fa(tariff['idle_grace'])} دقیقه رایگان' : ''}',
                        trailing: _price(c, tariff['idle'], 'ت/دقیقه'),
                      ),
                      ListRow(
                        icon: Icons.eco_outlined,
                        tone: 'green',
                        title: 'ساعات کم‌باری',
                        subtitle: '${tariff['offpeak_hours'] ?? '—'}',
                        trailing: _price(c, tariff['offpeak'], 'ت/kWh', color: Palette.green),
                      ),
                    ]),
                  ),
                  if (amenities.isNotEmpty) ...[
                    const SecTitle('امکانات رفاهی'),
                    Wrap(spacing: 18, runSpacing: 10, children: [
                      for (final a in amenities)
                        Row(mainAxisSize: MainAxisSize.min, children: [
                          Icon(Icons.check_circle_outline, size: 18, color: c.accent),
                          const SizedBox(width: 6),
                          Text(a, style: TextStyle(color: c.text2, fontSize: 12.5, fontWeight: FontWeight.w600)),
                        ]),
                    ]),
                  ],
                ]),
              ),
            ]),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                padding: EdgeInsets.fromLTRB(18, 14, 18, 14 + MediaQuery.of(context).padding.bottom),
                decoration: BoxDecoration(
                  color: c.surface,
                  border: Border(top: BorderSide(color: c.border)),
                  boxShadow: const [BoxShadow(color: Color(0x0F0F1E2E), blurRadius: 24, offset: Offset(0, -6))],
                ),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                      Text('کانکتور انتخاب‌شده', style: TextStyle(fontSize: 12, color: c.muted)),
                      Text(sel != null ? '${sel['name']}' : 'ابتدا احراز هویت کنید', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
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
                  AppButton(
                    d['has_active_session'] == true ? 'جلسهٔ فعال' : 'شروع شارژ',
                    icon: Icons.bolt_rounded,
                    kind: BtnKind.orange,
                    expand: false,
                    loading: starting,
                    onPressed: d['has_active_session'] == true ? () => openScreen(context, 'charging') : (authorized && sel['st'] == 'free' ? () => _start(d) : null),
                  ),
                ]),
              ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _price(Palette c, Object? v, String unit, {Color? color}) => asInt(v) > 0
      ? Text.rich(TextSpan(children: [
          TextSpan(text: money(asInt(v)), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: color)),
          TextSpan(text: ' $unit', style: TextStyle(fontSize: 11.5, color: c.muted)),
        ]))
      : const Text('—');

  Widget _connector(Palette c, Map<String, dynamic> cn) {
    final busy = cn['st'] == 'busy';
    final allowed = cn['authorized'] == true;
    final selected = cn['db_id'] == selectedId;
    final (label, tone) = busy ? (_stateLabels[cn['state']] ?? ('مشغول', 'orange')) : (allowed ? ('مجاز', 'green') : ('نیاز به احراز', 'muted'));
    return Opacity(
      opacity: busy ? .55 : (allowed ? 1 : .72),
      child: GestureDetector(
        onTap: allowed && !busy ? () => setState(() => selectedId = asInt(cn['db_id'])) : null,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: selected ? c.accentSoft : c.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: selected ? c.accent : (allowed ? Palette.green.withValues(alpha: .45) : c.border), width: 1.5),
          ),
          child: Row(children: [
            IconTile(Icons.power_outlined, tone: '${cn['ic']}', size: 46),
            const SizedBox(width: 13),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('کانکتور ${cn['id']} · ${cn['t']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                const SizedBox(height: 4),
                Text('حداکثر توان ${cn['pw']}${busy && cn['reason'] != null ? ' · ${cn['reason']}' : ''}', style: TextStyle(fontSize: 12, color: c.muted)),
              ]),
            ),
            Pill(label, tone: tone),
          ]),
        ),
      ),
    );
  }
}

class _AuthAction extends StatelessWidget {
  const _AuthAction(this.icon, this.label, this.onTap);

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 76,
        decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          IconTile(icon, size: 40),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
        ]),
      ),
    );
  }
}
