import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

/// رزرو شارژر: انتخاب ایستگاه، وضعیت زندهٔ کانکتورها، روز و بازهٔ ۳۰ دقیقه‌ای (۸ تا ۲۰).
class ReserveScreen extends StatefulWidget {
  const ReserveScreen({super.key, this.station});

  final String? station;

  @override
  State<ReserveScreen> createState() => _ReserveScreenState();
}

class _ReserveScreenState extends State<ReserveScreen> {
  static const slotMinutes = 30;
  static const _weekdays = ['دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه', 'یکشنبه'];
  final _body = GlobalKey<AsyncBodyState>();
  int? stationId;
  int? connectorId;
  int day = 0;
  int? slot;
  bool saving = false;

  DateTime _slotTime(int dayOffset, int index) {
    final now = DateTime.now();
    final base = DateTime(now.year, now.month, now.day + dayOffset);
    return base.add(Duration(hours: 8 + index ~/ 2, minutes: (index % 2) * 30));
  }

  bool _taken(DateTime dt, List<Map<String, dynamic>> reservations) {
    final until = dt.add(const Duration(minutes: slotMinutes));
    return reservations.any((r) {
      if (asInt(r['station_id']) != stationId) return false;
      final from = DateTime.tryParse('${r['from']}')?.toLocal();
      final to = DateTime.tryParse('${r['until']}')?.toLocal();
      return from != null && to != null && dt.isBefore(to) && until.isAfter(from);
    });
  }

  String _fmt(DateTime d) {
    String p(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${p(d.month)}-${p(d.day)} ${p(d.hour)}:${p(d.minute)}:00';
  }

  String _time(DateTime d) => fa('${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}');

  String _dayLabel(int offset) {
    if (offset == 0) return 'امروز';
    if (offset == 1) return 'فردا';
    final d = DateTime.now().add(Duration(days: offset));
    return _weekdays[d.weekday - 1];
  }

  Future<void> _submit() async {
    if (stationId == null || connectorId == null || slot == null) return;
    final from = _slotTime(day, slot!);
    setState(() => saving = true);
    final res = await runAction(context, () => context.api.post('reservations', {
          'station_id': stationId,
          'connector_id': connectorId,
          'reserved_from': _fmt(from),
          'reserved_until': _fmt(from.add(const Duration(minutes: slotMinutes))),
        }), showSuccess: false);
    if (!mounted) return;
    setState(() => saving = false);
    if (res == null) return;
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const CircleAvatar(radius: 34, backgroundColor: Palette.green, child: Icon(Icons.check_rounded, color: Colors.white, size: 40)),
        title: const Text('رزرو شما ثبت شد', style: TextStyle(fontWeight: FontWeight.w800)),
        content: Text('${res['message'] ?? ''}\nکانکتور تا ۱۵ دقیقه پس از زمان رزرو برای شما نگه داشته می‌شود.', textAlign: TextAlign.center, style: const TextStyle(height: 1.8)),
        actions: [FilledButton(onPressed: () => Navigator.pop(ctx), child: const Text('باشه'))],
      ),
    );
    setState(() => slot = null);
    _body.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: AsyncBody(
          key: _body,
          load: () => context.api.get('reservations', {'station': widget.station}),
          builder: (context, d, reload) {
            final c = context.c;
            final stations = asList(d['stations']);
            final reservations = asList(d['reservations']);
            stationId ??= asInt(d['default_station_id'], stations.isNotEmpty ? asInt(stations.first['id']) : -1);
            final st = stations.where((s) => asInt(s['id']) == stationId).firstOrNull ?? stations.firstOrNull;
            final stats = asMap(st?['stats']);
            final conns = asList(st?['connectors']);
            if (connectorId == null || !conns.any((x) => asInt(x['db_id']) == connectorId && x['free'] == true)) {
              connectorId = conns.where((x) => x['free'] == true).map((x) => asInt(x['db_id'])).firstOrNull;
            }
            final selFrom = slot == null ? null : _slotTime(day, slot!);
            final upcoming = reservations.where((r) => DateTime.tryParse('${r['until']}')?.isAfter(DateTime.now()) ?? false).toList();

            return Column(children: [
              const PageHeader('رزرو شارژ'),
              Expanded(
                child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 20), children: [
                  if (stations.isEmpty) const EmptyState('ایستگاه فعالی برای رزرو وجود ندارد.', icon: Icons.ev_station_outlined),
                  if (st != null) ...[
                    AppCard(
                      padding: const EdgeInsets.all(14),
                      onTap: () => _pickStation(stations),
                      child: Row(children: [
                        const IconTile(Icons.place_outlined),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('${st['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                            Text('${st['city'] ?? ''}', style: TextStyle(fontSize: 12, color: c.muted)),
                          ]),
                        ),
                        LtrIcon(Icons.chevron_left_rounded, color: c.muted),
                      ]),
                    ),
                    const SizedBox(height: 12),
                    Row(children: [
                      for (final (k, l, color) in [('free', 'آزاد اکنون', Palette.green), ('busy', 'مشغول', Palette.orange), ('soon', 'خارج از دسترس', c.muted)])
                        Expanded(
                          child: AppCard(
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            child: Column(children: [
                              Text(fa(stats[k] ?? 0), style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
                              Text(l, style: TextStyle(fontSize: 11.5, color: c.muted)),
                            ]),
                          ),
                        ),
                    ]),
                    const SecTitle('وضعیت کانکتورها'),
                    for (final cn in conns) _connector(c, cn),
                    const SecTitle('انتخاب زمان رزرو'),
                    Row(children: [
                      for (var i = 0; i < 3; i++)
                        Expanded(
                          child: GestureDetector(
                            onTap: () => setState(() {
                              day = i;
                              slot = null;
                            }),
                            child: Container(
                              margin: const EdgeInsets.symmetric(horizontal: 4),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              decoration: BoxDecoration(
                                color: day == i ? c.accent : c.surface,
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: day == i ? c.accent : c.border),
                              ),
                              child: Column(children: [
                                Text(_dayLabel(i), style: TextStyle(fontWeight: FontWeight.w800, color: day == i ? Colors.white : c.text)),
                                Text(fa('${DateTime.now().add(Duration(days: i)).day}'), style: TextStyle(fontSize: 12, color: day == i ? Colors.white70 : c.muted)),
                              ]),
                            ),
                          ),
                        ),
                    ]),
                    const SizedBox(height: 12),
                    GridView.count(
                      crossAxisCount: 4,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 8,
                      crossAxisSpacing: 8,
                      childAspectRatio: 2.1,
                      children: [
                        for (var i = 0; i < 24; i++)
                          Builder(builder: (context) {
                            final dt = _slotTime(day, i);
                            final taken = !dt.isAfter(DateTime.now()) || _taken(dt, reservations);
                            final sel = slot == i;
                            return GestureDetector(
                              onTap: taken ? null : () => setState(() => slot = i),
                              child: Container(
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: sel ? c.accentSoft : (taken ? c.surface3 : c.surface),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: sel ? c.accent : c.border, width: sel ? 1.6 : 1),
                                ),
                                child: Text(_time(dt),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 13,
                                      color: taken ? c.muted : (sel ? c.accentStrong : c.text),
                                      decoration: taken ? TextDecoration.lineThrough : null,
                                    )),
                              ),
                            );
                          }),
                      ],
                    ),
                  ],
                  if (upcoming.isNotEmpty) ...[
                    const SecTitle('رزروهای من'),
                    AppCard(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      child: Column(children: [
                        for (final r in upcoming)
                          ListRow(
                            icon: Icons.event_available_outlined,
                            tone: 'green',
                            title: '${r['station']}',
                            subtitle: () {
                              final f = DateTime.tryParse('${r['from']}')?.toLocal();
                              return f == null ? '' : fa('${f.year}/${f.month}/${f.day} · ${_time(f)}');
                            }(),
                            trailing: Pill(r['status'] == 'confirmed' ? 'تأییدشده' : '${r['status']}', tone: 'blue'),
                          ),
                      ]),
                    ),
                  ],
                ]),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
                decoration: BoxDecoration(color: c.surface, border: Border(top: BorderSide(color: c.border))),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('زمان انتخابی', style: TextStyle(fontSize: 12, color: c.muted)),
                      Text(selFrom == null ? '—' : '${_dayLabel(day)} ساعت ${_time(selFrom)}', style: const TextStyle(fontWeight: FontWeight.w800)),
                    ]),
                  ),
                  AppButton('ثبت رزرو', icon: Icons.calendar_month_outlined, expand: false, loading: saving, onPressed: slot != null && connectorId != null ? _submit : null),
                ]),
              ),
            ]);
          },
        ),
      ),
    );
  }

  Widget _connector(Palette c, Map<String, dynamic> cn) {
    final free = cn['free'] == true;
    final charging = cn['charging'] == true;
    final sel = asInt(cn['db_id']) == connectorId;
    const labels = {'offline': 'آفلاین', 'fault': 'خارج از سرویس', 'reserved': 'رزرو شده', 'inactive': 'غیرفعال'};
    final subtitle = free
        ? 'آمادهٔ رزرو'
        : charging
            ? 'خودروی فعلی: ${fa(cn['soc'] ?? 0)}٪ شارژ شده'
            : '${cn['reason'] ?? labels[cn['state']] ?? 'در دسترس نیست'}';
    return GestureDetector(
      onTap: free ? () => setState(() => connectorId = asInt(cn['db_id'])) : null,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: sel ? c.accentSoft : c.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: sel ? c.accent : c.border, width: 1.5),
        ),
        child: Row(children: [
          IconTile(Icons.power_outlined, tone: free ? 'green' : (charging ? 'orange' : 'amber'), size: 44),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('کانکتور ${cn['id']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 3),
              Text(subtitle, style: TextStyle(fontSize: 12, color: c.muted)),
              if (charging) ...[
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(value: asInt(cn['soc']) / 100, minHeight: 5, color: Palette.orange, backgroundColor: c.surface3),
                ),
              ],
            ]),
          ),
          const SizedBox(width: 8),
          if (free)
            const Pill('آزاد · آمادهٔ شارژ', tone: 'green')
          else if (charging)
            Column(children: [
              Text(cn['eta'] != null ? '~${fa(cn['eta'])} دق' : '—', style: const TextStyle(fontWeight: FontWeight.w800)),
              Text('تا آزاد شدن', style: TextStyle(fontSize: 11, color: c.muted)),
            ])
          else
            Pill(labels[cn['state']] ?? 'در دسترس نیست', tone: 'muted'),
        ]),
      ),
    );
  }

  Future<void> _pickStation(List<Map<String, dynamic>> stations) async {
    final id = await showModalBottomSheet<int>(
      context: context,
      builder: (ctx) => SafeArea(
        child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(18), children: [
          const Text('انتخاب ایستگاه', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          for (final s in stations)
            ListTile(
              title: Text('${s['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text('${s['city'] ?? ''}'),
              trailing: asInt(s['id']) == stationId ? const Icon(Icons.check_circle, color: Palette.green) : null,
              onTap: () => Navigator.pop(ctx, asInt(s['id'])),
            ),
        ]),
      ),
    );
    if (id != null) {
      setState(() {
        stationId = id;
        connectorId = null;
        slot = null;
      });
    }
  }
}
