import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/fmt.dart';
import '../core/state.dart';
import '../core/theme.dart';
import 'common.dart';
import 'fx.dart';

/// ایستگاه‌های سایر اپراتورها (externalMapStations سرور) — فقط اطلاع‌رسانی، بدون رزرو/پرداخت در QUAD EV.

const kExtBlue = Color(0xFF3B82F6);

Color? hexColor(Object? v) {
  final s = '${v ?? ''}'.replaceFirst('#', '');
  if (s.length != 6) return null;
  final n = int.tryParse(s, radix: 16);
  return n == null ? null : Color(0xFF000000 | n);
}

double distanceKm(double lat1, double lng1, double lat2, double lng2) {
  const r = math.pi / 180;
  final x = (lat2 - lat1) * r;
  final y = (lng2 - lng1) * r * math.cos((lat1 + lat2) / 2 * r);
  return math.sqrt(x * x + y * y) * 6371;
}

String distanceLabel(double km) => km < 1 ? '${fa((km * 1000).round())} متر' : '${fa(km.toStringAsFixed(1))} کیلومتر';

bool extMatches(Map<String, dynamic> s, String filter, String query) {
  if (filter == 'quad' || filter == 'free' || filter == 'public' || filter == 'fav') return false;
  if (filter == 'fast' && s['fast'] != true) return false;
  final q = query.trim();
  return q.isEmpty || '${s['name']} ${s['operator']} ${s['city'] ?? ''} ${s['address'] ?? ''}'.contains(q);
}

Future<void> openDirections(double lat, double lng) =>
    launchUrl(Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng'), mode: LaunchMode.externalApplication);

class OperatorLogo extends StatelessWidget {
  const OperatorLogo(this.url, {super.key, this.size = 40, this.radius = 10, this.borderColor});
  final String? url;
  final double size, radius;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final src = StationImage.resolve(url, context.app.baseUrl);
    return Container(
      width: size,
      height: size,
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: borderColor ?? context.c.border, width: borderColor != null ? 1.5 : 1),
      ),
      clipBehavior: Clip.antiAlias,
      child: src == null
          ? Icon(Icons.ev_station_rounded, size: size * .55, color: kExtBlue)
          : Image.network(src, fit: BoxFit.contain, errorBuilder: (_, _, _) => Icon(Icons.ev_station_rounded, size: size * .55, color: kExtBlue)),
    );
  }
}

/// پین کوچک دایره‌ای با لوگوی اپراتور (مثل op-pin وب).
class ExtPin extends StatelessWidget {
  const ExtPin(this.station, {super.key});
  final Map<String, dynamic> station;

  @override
  Widget build(BuildContext context) => Opacity(
        opacity: .92,
        child: DecoratedBox(
          decoration: const BoxDecoration(shape: BoxShape.circle, boxShadow: [BoxShadow(color: Color(0x40000000), blurRadius: 4, offset: Offset(0, 1))]),
          child: OperatorLogo(station['logo'] as String?, size: 24, radius: 12, borderColor: hexColor(station['color']) ?? Colors.white),
        ),
      );
}

class ExtStationTile extends StatelessWidget {
  const ExtStationTile(this.station, {super.key, this.km, this.onTap});
  final Map<String, dynamic> station;
  final double? km;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final s = station;
    final meta = [
      '${s['operator'] ?? ''}',
      if (s['kw'] != null) '${s['kw']} kW',
      if ((s['connectors'] ?? '') != '') '${s['connectors']}',
    ].join(' · ');
    return AppCard(
      margin: const EdgeInsets.only(bottom: 11),
      padding: const EdgeInsets.all(12),
      onTap: onTap,
      child: Row(children: [
        OperatorLogo(s['logo'] as String?, size: 50, radius: 13),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${s['name'] ?? '—'}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(meta, style: TextStyle(fontSize: 12, color: c.muted)),
            if (km != null) ...[
              const SizedBox(height: 6),
              Text(distanceLabel(km!), style: TextStyle(fontSize: 12, color: c.text2, fontWeight: FontWeight.w600)),
            ],
          ]),
        ),
      ]),
    );
  }
}

Future<void> showExternalStation(BuildContext context, Map<String, dynamic> s) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) => _ExtDetail(s),
  );
}

class _ExtDetail extends StatelessWidget {
  const _ExtDetail(this.s);
  final Map<String, dynamic> s;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final image = StationImage.resolve(s['image'] as String?, context.app.baseUrl);
    final rows = <(String, String?)>[
      ('آدرس', s['address'] as String?),
      ('سوکت', s['connectors'] as String?),
      ('حداکثر توان', s['kw'] != null ? '${s['kw']} کیلووات' : null),
      ('تعداد درگاه', s['ports'] as String?),
      ('تعرفه اعلامی', s['tariff'] != null ? '${s['tariff']} تومان / kWh' : null),
      ('ساعات کاری', s['hours'] as String?),
      ('امکانات', s['facilities'] as String?),
      ('تلفن', (s['phone'] ?? s['op_phone']) as String?),
    ].where((r) => (r.$2 ?? '').trim().isNotEmpty).toList();

    return Container(
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * .82),
      decoration: BoxDecoration(color: c.bg, borderRadius: const BorderRadius.vertical(top: Radius.circular(26))),
      child: SafeArea(
        top: false,
        child: ListView(shrinkWrap: true, padding: const EdgeInsets.fromLTRB(20, 10, 20, 20), children: [
          Center(child: Container(width: 44, height: 5, decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(4)))),
          const SizedBox(height: 14),
          Row(children: [
            OperatorLogo(s['logo'] as String?, size: 48, radius: 12),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${s['name'] ?? '—'}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, height: 1.5)),
                Text('${s['operator'] ?? ''}${(s['network'] ?? '') != '' ? ' · ${s['network']}' : ''}', style: TextStyle(fontSize: 12, color: c.muted)),
              ]),
            ),
          ]),
          if (image != null) ...[
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Image.network(image, height: 140, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
            ),
          ],
          const SizedBox(height: 10),
          for (final r in rows)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(border: Border(top: BorderSide(color: c.border))),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                SizedBox(width: 92, child: Text(r.$1, style: TextStyle(fontSize: 12.5, color: c.muted))),
                Expanded(child: Text(r.$2!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, height: 1.6))),
              ]),
            ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(10)),
            child: Text('این ایستگاه متعلق به ${s['operator'] ?? 'اپراتور دیگری'} است؛ رزرو و پرداخت آن از QUAD EV انجام نمی‌شود.',
                style: const TextStyle(fontSize: 12, color: Color(0xFFC2410C), height: 1.7)),
          ),
          const SizedBox(height: 14),
          AppButton('مسیریابی تا ایستگاه', icon: Icons.directions_rounded, onPressed: () => openDirections(asDouble(s['lat']), asDouble(s['lng']))),
        ]),
      ),
    );
  }
}
