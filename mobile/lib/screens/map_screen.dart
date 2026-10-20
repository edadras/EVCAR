import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/station_card.dart';

const kTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const kTileUa = 'ir.safircharge.app';

/// نقشهٔ ایستگاه‌ها (OSM) با جست‌وجو، فیلتر و فهرست کشویی پایین مانند وب‌اپ.
class MapTab extends StatefulWidget {
  const MapTab({super.key});

  @override
  State<MapTab> createState() => _MapTabState();
}

class _MapTabState extends State<MapTab> {
  final mapCtl = MapController();
  final search = TextEditingController();
  String filter = 'all';
  LatLng? me;

  Future<void> _locate() async {
    final p = await context.app.location.current();
    if (!mounted) return;
    if (p == null) {
      toast(context, 'دسترسی به موقعیت مکانی داده نشده یا GPS خاموش است.', error: true);
      return;
    }
    setState(() => me = LatLng(p.lat, p.lng));
    mapCtl.move(me!, 13);
    context.app.location.sync(context.api, force: true);
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AsyncBody(
      refreshable: false,
      load: () => context.api.get('map'),
      builder: (context, d, reload) {
        final c = context.c;
        final all = asList(d['stations']);
        final stations = filterStations(all, filter, search.text);
        final codes = stations.map((s) => s['code']).toSet();
        final center = asMap(d['center']);
        final stats = asMap(d['stats']);

        return Stack(children: [
          FlutterMap(
            mapController: mapCtl,
            options: MapOptions(
              initialCenter: LatLng(asDouble(center['lat'], 35.6997), asDouble(center['lng'], 51.338)),
              initialZoom: 11,
            ),
            children: [
              TileLayer(urlTemplate: kTileUrl, userAgentPackageName: kTileUa),
              MarkerLayer(markers: [
                if (me != null)
                  Marker(
                    point: me!,
                    width: 22,
                    height: 22,
                    child: Container(decoration: BoxDecoration(color: const Color(0xFF2563EB), shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 3), boxShadow: const [BoxShadow(color: Color(0x552563EB), blurRadius: 10, spreadRadius: 4)])),
                  ),
                for (final s in all)
                  if (s['lat'] != null && s['lng'] != null && codes.contains(s['code']))
                    Marker(
                      point: LatLng(asDouble(s['lat']), asDouble(s['lng'])),
                      width: 40,
                      height: 48,
                      alignment: Alignment.topCenter,
                      child: GestureDetector(
                        onTap: () => openScreen(context, 'charger', {'station': s['code']}),
                        child: _Pin(color: s['has_free'] == true ? Palette.green : Palette.amber),
                      ),
                    ),
              ]),
              const RichAttributionWidget(attributions: [TextSourceAttribution('OpenStreetMap contributors')]),
            ],
          ),
          Positioned(
            top: 8,
            left: 16,
            right: 16,
            child: Column(children: [
              Container(
                height: 50,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: c.surface,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: const [BoxShadow(color: Color(0x240F1E2E), blurRadius: 18, offset: Offset(0, 6))],
                ),
                child: Row(children: [
                  GestureDetector(onTap: () => openScreen(context, 'scan'), child: Icon(Icons.qr_code_scanner_rounded, color: c.accent)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: search,
                      onChanged: (_) => setState(() {}),
                      decoration: const InputDecoration(
                        hintText: 'جستجوی ایستگاه یا مقصد...',
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        filled: false,
                        contentPadding: EdgeInsets.zero,
                      ),
                    ),
                  ),
                  Icon(Icons.filter_alt_outlined, color: c.muted),
                ]),
              ),
              const SizedBox(height: 10),
              SizedBox(
                height: 40,
                child: ListView(scrollDirection: Axis.horizontal, children: [
                  _Chip('مسیریاب برقی', gradient: true, onTap: () => openScreen(context, 'navigator')),
                  for (final (v, l) in const [('all', 'همه'), ('fast', 'شارژ سریع'), ('free', 'آزاد اکنون'), ('fav', 'علاقه‌مندی‌ها'), ('public', 'عمومی')])
                    _Chip(l, selected: filter == v, onTap: () => setState(() => filter = v)),
                ]),
              ),
            ]),
          ),
          PositionedDirectional(
            top: 112,
            end: 16,
            child: RoundIconButton(Icons.my_location_rounded, onTap: _locate),
          ),
          DraggableScrollableSheet(
            initialChildSize: .42,
            minChildSize: .16,
            maxChildSize: .9,
            builder: (context, scroll) => Container(
              decoration: BoxDecoration(
                color: c.bg,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
                boxShadow: const [BoxShadow(color: Color(0x1F0F1E2E), blurRadius: 24, offset: Offset(0, -6))],
              ),
              child: ListView(controller: scroll, padding: const EdgeInsets.fromLTRB(18, 10, 18, 24), children: [
                Center(child: Container(width: 44, height: 5, decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(4)))),
                const SizedBox(height: 14),
                Row(children: [
                  Expanded(child: Text('${fa(stations.length)} ایستگاه در این محدوده', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
                  Pill('${fa(stats['free'])} آزاد', dot: true),
                ]),
                const SizedBox(height: 14),
                if (stations.isEmpty) const EmptyState('ایستگاهی یافت نشد.', icon: Icons.search_off_rounded),
                for (final s in stations)
                  StationCard(
                    station: s,
                    onTap: () {
                      if (s['lat'] != null) mapCtl.move(LatLng(asDouble(s['lat']), asDouble(s['lng'])), 14);
                      openScreen(context, 'charger', {'station': s['code']});
                    },
                  ),
              ]),
            ),
          ),
        ]);
      },
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip(this.label, {this.selected = false, this.gradient = false, required this.onTap});

  final String label;
  final bool selected;
  final bool gradient;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: gradient ? null : (selected ? c.accent : c.surface),
            gradient: gradient ? LinearGradient(colors: [c.accent, Palette.orange]) : null,
            borderRadius: BorderRadius.circular(20),
            boxShadow: const [BoxShadow(color: Color(0x1A0F1E2E), blurRadius: 10, offset: Offset(0, 3))],
          ),
          child: Text(label, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: gradient || selected ? Colors.white : c.text)),
        ),
      ),
    );
  }
}

class _Pin extends StatelessWidget {
  const _Pin({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 2.5),
          boxShadow: const [BoxShadow(color: Color(0x40000000), blurRadius: 6, offset: Offset(0, 3))],
        ),
        child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 20),
      ),
      CustomPaint(size: const Size(10, 8), painter: _Tail(color)),
    ]);
  }
}

class _Tail extends CustomPainter {
  _Tail(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final path = ui.Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width / 2, size.height)
      ..close();
    canvas.drawPath(path, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_Tail old) => old.color != color;
}
