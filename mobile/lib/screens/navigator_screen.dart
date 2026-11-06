import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';

import '../core/api.dart';
import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/external_station.dart';
import 'map_screen.dart';

class _Point {
  _Point(this.lat, this.lng, this.label);
  final double lat, lng;
  final String label;
  LatLng get ll => LatLng(lat, lng);
  Map<String, dynamic> toJson() => {'lat': lat, 'lng': lng, 'label': label};
}

/// مثل وب‌اپ: حرکت با باتری پر، رزرو ۱۰٪ و شارژ تا ۸۰٪ در ایستگاه‌های بین راه.
const _kTrip = {'current_soc': 100, 'target_soc': 10, 'charge_to_soc': 80};

/// مسیریاب برقی: مبدا/مقصد و خودرو؛ تحلیل سرور (OSRM + ارتفاع + آب‌وهوا) و برنامهٔ توقف شارژ.
class NavigatorScreen extends StatefulWidget {
  const NavigatorScreen({super.key});

  @override
  State<NavigatorScreen> createState() => _NavigatorScreenState();
}

class _NavigatorScreenState extends State<NavigatorScreen> {
  final mapCtl = MapController();
  final originCtl = TextEditingController(text: 'تهران (پیش‌فرض)');
  final destCtl = TextEditingController();
  final battery = TextEditingController(text: '60');
  final consumption = TextEditingController(text: '16');
  _Point? origin;
  _Point? dest;
  String? vehicle;
  bool analyzing = false;
  bool locating = false;
  bool pickingDest = true;
  Map<String, dynamic>? result;

  Future<Map<String, dynamic>> _load() async {
    final d = await context.api.get('navigator');
    final center = asMap(d['center']);
    origin ??= _Point(asDouble(center['lat'], 35.6892), asDouble(center['lng'], 51.389), 'تهران');
    final vehicles = asList(d['vehicles']);
    final def = asMap(d['default_vehicle']);
    vehicle ??= def['uuid'] != null ? 'user:${def['uuid']}' : (vehicles.isNotEmpty ? 'user:${vehicles.first['uuid']}' : 'preset:byd_atto3');
    return d;
  }

  Future<void> _geocode(String q, bool isOrigin) async {
    if (q.trim().isEmpty) return;
    try {
      final res = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ir&q=${Uri.encodeComponent(q)}'),
        headers: {'User-Agent': kTileUa, 'Accept-Language': 'fa'},
      );
      final list = jsonDecode(utf8.decode(res.bodyBytes)) as List;
      if (list.isEmpty) throw Exception();
      final hit = list.first as Map;
      final p = _Point(double.parse('${hit['lat']}'), double.parse('${hit['lon']}'), '${hit['display_name']}'.split(',').first);
      setState(() {
        if (isOrigin) {
          origin = p;
          originCtl.text = p.label;
        } else {
          dest = p;
          destCtl.text = p.label;
        }
        result = null;
      });
      mapCtl.move(p.ll, 11);
    } catch (_) {
      if (mounted) toast(context, 'مکانی با این نام پیدا نشد.', error: true);
    }
  }

  Future<void> _useMyLocation() async {
    setState(() => locating = true);
    final p = await context.app.location.current();
    if (!mounted) return;
    setState(() => locating = false);
    if (p == null) return toast(context, 'دسترسی به موقعیت مکانی داده نشده یا GPS خاموش است.', error: true);
    setState(() {
      origin = _Point(p.lat, p.lng, 'موقعیت من');
      originCtl.text = origin!.label;
      result = null;
    });
    mapCtl.move(origin!.ll, 11);
  }

  void _swap() {
    if (dest == null) return;
    setState(() {
      final o = origin!;
      origin = dest;
      dest = o;
      originCtl.text = origin!.label;
      destCtl.text = dest!.label;
      result = null;
    });
  }

  void _setRoute(Map<String, dynamic> r) {
    final o = asMap(r['origin']), d = asMap(r['destination']);
    setState(() {
      origin = _Point(asDouble(o['lat']), asDouble(o['lng']), '${o['label']}');
      dest = _Point(asDouble(d['lat']), asDouble(d['lng']), '${d['label']}');
      originCtl.text = origin!.label;
      destCtl.text = dest!.label;
      result = null;
    });
    mapCtl.fitCamera(CameraFit.bounds(bounds: LatLngBounds(origin!.ll, dest!.ll), padding: const EdgeInsets.all(40)));
  }

  Future<void> _analyze() async {
    if (dest == null) return toast(context, 'مقصد را انتخاب کنید.', error: true);
    final payload = <String, dynamic>{
      'origin': origin!.toJson(),
      'destination': dest!.toJson(),
      ..._kTrip,
    };
    final v = vehicle ?? 'preset:byd_atto3';
    if (v.startsWith('user:')) {
      payload['vehicle_uuid'] = v.substring(5);
    } else {
      payload['vehicle_preset'] = v.substring(7);
      if (v == 'preset:custom') {
        payload['battery_kwh'] = double.tryParse(enDigits(battery.text)) ?? 60;
        payload['consumption_kwh100'] = double.tryParse(enDigits(consumption.text)) ?? 16;
      }
    }
    setState(() => analyzing = true);
    try {
      final r = await context.api.post('navigator/analyze', payload);
      if (!mounted) return;
      setState(() => result = r);
      final pts = asList(asMap(r['route'])['polyline']).map((p) => LatLng(asDouble(p['lat']), asDouble(p['lng']))).toList();
      if (pts.length > 1) mapCtl.fitCamera(CameraFit.coordinates(coordinates: pts, padding: const EdgeInsets.all(30)));
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => analyzing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: AsyncBody(
          refreshable: false,
          load: _load,
          builder: (context, d, _) {
            final c = context.c;
            final r = result;
            final polyline = asList(asMap(r?['route'])['polyline']).map((p) => LatLng(asDouble(p['lat']), asDouble(p['lng']))).toList();
            final stops = asList(r?['charge_plan']);
            final chargers = _routeStations(r);
            return Column(children: [
              const PageHeader('مسیریاب برقی', subtitle: 'مبدا و مقصد را بدهید؛ می‌گوییم می‌رسید یا کجا شارژ کنید'),
              SizedBox(
                height: 230,
                child: Stack(children: [
                  FlutterMap(
                    mapController: mapCtl,
                    options: MapOptions(
                      initialCenter: origin!.ll,
                      initialZoom: 7,
                      onTap: (_, ll) => setState(() {
                        final p = _Point(ll.latitude, ll.longitude, 'نقطهٔ انتخابی');
                        if (pickingDest) {
                          dest = p;
                          destCtl.text = p.label;
                        } else {
                          origin = p;
                          originCtl.text = p.label;
                        }
                        result = null;
                      }),
                    ),
                    children: [
                      TileLayer(urlTemplate: kTileUrl, userAgentPackageName: kTileUa),
                      if (polyline.length > 1) PolylineLayer(polylines: [Polyline(points: polyline, strokeWidth: 5, color: c.accent)]),
                      MarkerLayer(markers: [
                        for (final ch in chargers)
                          if (ch['lat'] != null)
                            Marker(
                              point: LatLng(asDouble(ch['lat']), asDouble(ch['lng'])),
                              width: 22,
                              height: 22,
                              child: _Dot(ch['external'] == true ? kExtBlue : Palette.green, Icons.bolt_rounded),
                            ),
                        for (final s in stops)
                          if (s['lat'] != null)
                            Marker(
                              point: LatLng(asDouble(s['lat']), asDouble(s['lng'])),
                              width: 34,
                              height: 34,
                              child: s['external'] == true
                                  ? OperatorLogo(s['operator_logo'] as String?, size: 34, radius: 17, borderColor: Palette.orange)
                                  : const _Dot(Palette.orange, Icons.ev_station_rounded),
                            ),
                        Marker(point: origin!.ll, width: 30, height: 30, child: _Dot(c.accent, Icons.trip_origin_rounded)),
                        if (dest != null) Marker(point: dest!.ll, width: 30, height: 30, child: const _Dot(Palette.red, Icons.flag_rounded)),
                      ]),
                    ],
                  ),
                  PositionedDirectional(
                    bottom: 8,
                    start: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(color: c.surface.withValues(alpha: .92), borderRadius: BorderRadius.circular(10)),
                      child: Text(pickingDest ? 'لمس نقشه: انتخاب مقصد' : 'لمس نقشه: انتخاب مبدا', style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ]),
              ),
              Expanded(child: r == null ? _form(c, d) : _results(c, r)),
            ]);
          },
        ),
      ),
    );
  }

  Widget _form(Palette c, Map<String, dynamic> d) {
    final vehicles = asList(d['vehicles']);
    final presets = asList(d['presets']);
    final routes = asList(d['popular_routes']);
    return ListView(padding: const EdgeInsets.fromLTRB(18, 12, 18, 24), children: [
      if (routes.isNotEmpty)
        SizedBox(
          height: 38,
          child: ListView(scrollDirection: Axis.horizontal, children: [
            for (final rt in routes)
              Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: InkWell(
                  borderRadius: BorderRadius.circular(20),
                  onTap: () => _setRoute(rt),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: c.surface, borderRadius: BorderRadius.circular(20), border: Border.all(color: c.border)),
                    child: Text('${rt['label']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                  ),
                ),
              ),
          ]),
        ),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(
          child: Column(children: [
            TextField(
              controller: originCtl,
              textInputAction: TextInputAction.search,
              onTap: () => setState(() => pickingDest = false),
              onSubmitted: (q) => _geocode(q, true),
              decoration: InputDecoration(
                hintText: 'مبدا — جستجو یا انتخاب روی نقشه',
                prefixIcon: Icon(Icons.trip_origin_rounded, color: c.accent),
                suffixIcon: TextButton(
                  onPressed: locating ? null : _useMyLocation,
                  child: locating ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('من', style: TextStyle(fontWeight: FontWeight.w800)),
                ),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: destCtl,
              textInputAction: TextInputAction.search,
              onTap: () => setState(() => pickingDest = true),
              onSubmitted: (q) => _geocode(q, false),
              decoration: const InputDecoration(hintText: 'مقصد — جستجو یا انتخاب روی نقشه', prefixIcon: Icon(Icons.flag_rounded, color: Palette.red)),
            ),
          ]),
        ),
        const SizedBox(width: 8),
        IconButton.filledTonal(
          tooltip: 'جابجایی مبدا و مقصد',
          onPressed: dest == null ? null : _swap,
          icon: const Icon(Icons.swap_vert_rounded),
        ),
      ]),
      const SizedBox(height: 14),
      Text('خودرو', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
      const SizedBox(height: 7),
      DropdownButtonFormField<String>(
        initialValue: vehicle,
        isExpanded: true,
        items: [
          for (final v in vehicles) DropdownMenuItem(value: 'user:${v['uuid']}', child: Text(fa('${v['brand_model']} (${v['battery_kwh']} کیلووات‌ساعت)'), overflow: TextOverflow.ellipsis)),
          for (final p in presets) DropdownMenuItem(value: 'preset:${p['id']}', child: Text('${p['name']}', overflow: TextOverflow.ellipsis)),
        ],
        onChanged: (v) => setState(() => vehicle = v),
      ),
      if (vehicle == 'preset:custom') ...[
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: LabeledField(label: 'باتری (kWh)', controller: battery, keyboard: TextInputType.number)),
          const SizedBox(width: 10),
          Expanded(child: LabeledField(label: 'مصرف (kWh/100km)', controller: consumption, keyboard: TextInputType.number)),
        ]),
      ],
      const SizedBox(height: 18),
      AppButton('بررسی مسیر', icon: Icons.navigation_outlined, loading: analyzing, onPressed: _analyze),
      const SizedBox(height: 8),
      Text('محاسبه با فرض حرکت با باتری پر و بر اساس مسافت، شیب، دما و مصرف واقعی خودرو',
          textAlign: TextAlign.center, style: TextStyle(fontSize: 11, color: c.muted, height: 1.6)),
    ]);
  }

  static List<Map<String, dynamic>> _routeStations(Map<String, dynamic>? r) {
    final list = asList(r?['route_stations']);
    return list.isNotEmpty ? list : asList(r?['chargers']);
  }

  VoidCallback? _stationTap(Map<String, dynamic> st) {
    if (st['external'] == true) {
      return st['lat'] != null ? () => openDirections(asDouble(st['lat']), asDouble(st['lng'])) : null;
    }
    return st['code'] != null ? () => openScreen(context, 'charger', {'station': st['code']}) : null;
  }

  Widget _routeStationRow(Palette c, Map<String, dynamic> ch) {
    final ext = ch['external'] == true;
    final free = asInt(ch['free_ports']);
    final meta = [
      'کیلومتر ${fa(asDouble(ch['route_km'] ?? ch['km']).round())} مسیر',
      if (asDouble(ch['offset_km']) >= 1) '${fa(ch['offset_km'])} کیلومتر از جاده',
      if (ch['max_power_kw'] != null) '${fa(ch['max_power_kw'])} کیلووات',
    ].join(' · ');
    return InkWell(
      onTap: _stationTap(ch),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(children: [
          ext ? OperatorLogo(ch['operator_logo'] as String?, size: 40, radius: 12) : const IconTile(Icons.bolt_rounded, tone: 'green', size: 40),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${ch['name']}', style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
              const SizedBox(height: 3),
              Text(meta, style: TextStyle(fontSize: 11.5, color: c.muted)),
            ]),
          ),
          const SizedBox(width: 8),
          if (ext)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFE0EDFF), borderRadius: BorderRadius.circular(20)),
              child: Text('${ch['operator'] ?? 'سایر اپراتورها'}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF1D4F91))),
            )
          else
            free > 0 ? Pill('${fa(free)} آزاد', dot: true) : const Pill('پر / خاموش', tone: 'muted'),
        ]),
      ),
    );
  }

  Widget _results(Palette c, Map<String, dynamic> r) {
    final s = asMap(r['summary']);
    final v = asMap(r['verdict']);
    final route = asMap(r['route']);
    final plan = asList(r['charge_plan']);
    final chargers = _routeStations(r);
    final warnings = (v['warnings'] as List?)?.map((e) => '$e').toList() ?? const <String>[];
    final status = '${v['status'] ?? (s['needs_charge'] == true ? 'needs_charge' : 'ok')}';
    final tone = switch (status) {
      'ok' => Palette.green,
      'needs_charge' => Palette.orange,
      _ => const Color(0xFFE24B4A),
    };
    Color socColor(double soc) => soc < 15
        ? const Color(0xFFE24B4A)
        : soc < 30
            ? const Color(0xFFEF9F27)
            : soc >= 60
                ? const Color(0xFF1D9E75)
                : const Color(0xFF378ADD);
    Widget row(String k, String val, {Color? color}) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(children: [
            Expanded(child: Text(k, style: TextStyle(fontSize: 13, color: c.text2))),
            Text(val, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: color)),
          ]),
        );
    final arrival = plan.isNotEmpty ? s['arrival_soc_planned'] : s['arrival_soc'];
    return ListView(padding: const EdgeInsets.fromLTRB(18, 12, 18, 24), children: [
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: tone.withValues(alpha: .1), borderRadius: BorderRadius.circular(18), border: Border.all(color: tone, width: 2)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${v['title'] ?? 'نتیجه تحلیل'}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: tone, height: 1.4)),
          const SizedBox(height: 6),
          Text('${v['message'] ?? ''}', style: TextStyle(fontSize: 13, height: 1.6, color: c.text2)),
          for (final w in warnings)
            Container(
              margin: const EdgeInsets.only(top: 10),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(color: const Color(0x0F000000), borderRadius: BorderRadius.circular(10)),
              child: Text('⚠ $w', style: const TextStyle(fontSize: 12, height: 1.5)),
            ),
        ]),
      ),
      const SizedBox(height: 14),
      AppCard(
        child: Column(children: [
          row('مسافت و زمان رانندگی', '${fa(route['distance_km'])} کیلومتر · ${fa(route['duration_min'])} دقیقه'),
          row('خودرو', '${asMap(r['vehicle'])['name'] ?? '—'}'),
          row('انرژی لازم برای مسیر', '${fa(s['total_kwh'])} کیلووات‌ساعت'),
          if (s['stranded_km'] != null && plan.isEmpty)
            row('شارژ تمام می‌شود حدود', 'کیلومتر ${fa(asDouble(s['stranded_km']).round())}', color: const Color(0xFFE24B4A))
          else if (arrival != null)
            row('شارژ باتری هنگام رسیدن', '${fa(arrival)}٪', color: socColor(asDouble(arrival))),
          if (plan.isNotEmpty) ...[
            row('توقف شارژ', '${fa(plan.length)} بار${asInt(s['total_charge_minutes']) > 0 ? ' · حدود ${fa(s['total_charge_minutes'])} دقیقه' : ''}'),
            if (asDouble(s['total_charge_cost']) > 0) row('هزینهٔ تقریبی شارژ بین راه', '${money(asDouble(s['total_charge_cost']) / 10)} تومان'),
          ],
        ]),
      ),
      if (plan.isNotEmpty) ...[
        const SecTitle('کجا شارژ کنید'),
        for (final (i, p) in plan.indexed)
          AppCard(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            onTap: _stationTap(p),
            child: Row(children: [
              p['external'] == true
                  ? OperatorLogo(p['operator_logo'] as String?, size: 44, radius: 13)
                  : const IconTile(Icons.ev_station_rounded, tone: 'orange'),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${fa(i + 1)}. ${p['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Text(fa('کیلومتر ${asDouble(p['km']).round()} مسیر · ${p['reason']}'), style: TextStyle(fontSize: 12, color: c.muted, height: 1.6)),
                  if (p['energy_kwh'] != null)
                    Text(fa('${p['energy_kwh']} کیلووات‌ساعت · حدود ${p['duration_min']} دقیقه${p['cost_rial'] != null ? ' · ${money(asDouble(p['cost_rial']) / 10)} تومان' : ''}'),
                        style: TextStyle(fontSize: 12, color: c.muted)),
                  if (p['external'] == true)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text('ایستگاه ${p['operator'] ?? ''}${(p['connectors'] ?? '') != '' ? ' (${p['connectors']})' : ''} — پرداخت در اپ همان اپراتور',
                          style: const TextStyle(fontSize: 11.5, color: Color(0xFF1D4F91), fontWeight: FontWeight.w600)),
                    ),
                ]),
              ),
              Pill('تا ${fa(p['charge_to_soc'] ?? _kTrip['charge_to_soc'])}٪', tone: 'blue'),
            ]),
          ),
      ],
      const SecTitle('ایستگاه‌های شارژ در مسیر'),
      if (chargers.isNotEmpty)
        AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          child: Column(children: [
            for (final ch in chargers) _routeStationRow(c, ch),
          ]),
        )
      else if (s['needs_charge_trip'] != true && s['needs_charge'] != true)
        AppCard(child: Text('ایستگاه شارژی نزدیک این مسیر ثبت نشده است؛ برای این سفر هم لازم نیست.', style: TextStyle(color: c.muted, fontSize: 13)))
      else
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: const Color(0xFFFDE8E8), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFE24B4A), width: 2)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(s['stations_missing'] == true ? '⛔ به شارژ نیاز دارید — ایستگاه در محل نیاز نیست' : '⚠ ایستگاه شارژ در مسیر نیست',
                style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFFE24B4A))),
            const SizedBox(height: 4),
            Text(
              v['status'] == 'no_chargers' && v['message'] != null ? '${v['message']}' : 'هیچ ایستگاه QUAD EV در محدوده نیاز به شارژ یافت نشد. بدون توقف به مقصد نمی‌رسید.',
              style: const TextStyle(fontSize: 13, height: 1.55, color: Color(0xFFE24B4A)),
            ),
            if (asInt(s['estimated_charge_stops']) > 1)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text('حدود ${fa(s['estimated_charge_stops'])} توقف شارژ لازم است.', style: const TextStyle(fontSize: 13, color: Color(0xFFE24B4A))),
              ),
          ]),
        ),
      const SizedBox(height: 14),
      AppButton('مسیر جدید', icon: Icons.edit_location_alt_outlined, kind: BtnKind.soft, onPressed: () => setState(() => result = null)),
    ]);
  }
}

class _Dot extends StatelessWidget {
  const _Dot(this.color, this.icon);
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(color: color, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2), boxShadow: const [BoxShadow(color: Color(0x40000000), blurRadius: 4)]),
        child: Icon(icon, color: Colors.white, size: 15),
      );
}
