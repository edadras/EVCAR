import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/theme.dart';
import 'common.dart';

/// کارت ایستگاه (stationCards سرور): نام، نوع، فاصله، کانکتور آزاد و قیمت هر kWh.
class StationCard extends StatelessWidget {
  const StationCard({super.key, required this.station, this.onTap});

  final Map<String, dynamic> station;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final s = station;
    final hasFree = s['has_free'] == true;
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      onTap: onTap,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        IconTile(Icons.ev_station_outlined, tone: hasFree ? 'green' : 'amber', size: 58, iconSize: 28),
        const SizedBox(width: 13),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              if (s['favorite'] == true) const Padding(padding: EdgeInsetsDirectional.only(end: 4), child: Icon(Icons.star_rounded, color: Palette.orange, size: 18)),
              Expanded(child: Text('${s['name'] ?? '—'}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800))),
              Pill.web('${s['status'] ?? ''}', '${s['status_pill'] ?? 'pill-green'}'),
            ]),
            const SizedBox(height: 4),
            Text('${s['type'] ?? ''}', style: TextStyle(fontSize: 12, color: c.muted)),
            const SizedBox(height: 8),
            Row(children: [
              Icon(Icons.near_me_outlined, size: 15, color: c.text2),
              const SizedBox(width: 4),
              Text('${s['distance'] ?? ''}', style: TextStyle(fontSize: 12, color: c.text2, fontWeight: FontWeight.w600)),
              Padding(padding: const EdgeInsets.symmetric(horizontal: 8), child: Text('·', style: TextStyle(color: c.muted))),
              Flexible(child: Text('${s['free'] ?? ''}', style: TextStyle(fontSize: 12, color: c.text2))),
              const Spacer(),
              Text(s['price'] == '—' ? '—' : '${s['price']} ت/kWh', style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

/// فیلتر ایستگاه‌ها مثل وب‌اپ: همه / شارژ سریع / عمومی / آزاد اکنون.
List<Map<String, dynamic>> filterStations(List<Map<String, dynamic>> list, String filter, [String query = '']) {
  final q = enDigits(query.trim());
  return list.where((s) {
    final okFilter = switch (filter) {
      'fast' => s['cat'] == 'fast',
      'public' => s['cat'] == 'public',
      'free' => s['has_free'] == true,
      'fav' => s['favorite'] == true,
      _ => true,
    };
    final okQuery = q.isEmpty || '${s['name']} ${s['type']} ${s['code']}'.contains(q);
    return okFilter && okQuery;
  }).toList();
}
