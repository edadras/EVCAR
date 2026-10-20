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
import 'map_screen.dart';

class _Point {
  _Point(this.lat, this.lng, this.label);
  final double lat, lng;
  final String label;
  LatLng get ll => LatLng(lat, lng);
  Map<String, dynamic> toJson() => {'lat': lat, 'lng': lng, 'label': label};
}

/// مسیریاب برقی: مبدا/مقصد، خودرو، درصد شارژ؛ تحلیل سرور (OSRM + ارتفاع + آب‌وهوا) و برنامهٔ توقف شارژ.
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
  double soc = 80, target = 20, chargeTo = 80;
  bool analyzing = false;
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
      'current_soc': soc.round(),
      'target_soc': target.round(),
      'charge_to_soc': chargeTo.round(),
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
            final chargers = asList(r?['chargers']);
            return Column(children: [
              const PageHeader('مسیریاب برقی', subtitle: 'برنامه‌ریزی مسیر و توقف شارژ'),
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
                            Marker(point: LatLng(asDouble(ch['lat']), asDouble(ch['lng'])), width: 26, height: 26, child: const _Dot(Palette.green, Icons.bolt_rounded)),
                        for (final s in stops)
                          if (s['lat'] != null)
                            Marker(point: LatLng(asDouble(s['lat']), asDouble(s['lng'])), width: 34, height: 34, child: const _Dot(Palette.orange, Icons.ev_station_rounded)),
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
    Widget slider(String label, double value, double min, double max, ValueChanged<double> onChanged, {int? divisions}) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2))),
            Text('${fa(value.round())}٪', style: const TextStyle(fontWeight: FontWeight.w800)),
          ]),
          Slider(value: value, min: min, max: max, divisions: divisions ?? (max - min).round(), onChanged: onChanged),
        ]);
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
      TextField(
        controller: originCtl,
        textInputAction: TextInputAction.search,
        onTap: () => setState(() => pickingDest = false),
        onSubmitted: (q) => _geocode(q, true),
        decoration: InputDecoration(hintText: 'مبدا — جستجو یا انتخاب روی نقشه', prefixIcon: Icon(Icons.trip_origin_rounded, color: c.accent)),
      ),
      const SizedBox(height: 10),
      TextField(
        controller: destCtl,
        textInputAction: TextInputAction.search,
        onTap: () => setState(() => pickingDest = true),
        onSubmitted: (q) => _geocode(q, false),
        decoration: const InputDecoration(hintText: 'مقصد — جستجو یا انتخاب روی نقشه', prefixIcon: Icon(Icons.flag_rounded, color: Palette.red)),
      ),
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
      const SizedBox(height: 12),
      slider('درصد شارژ فعلی باتری', soc, 10, 100, (v) => setState(() => soc = v)),
      slider('درصد شارژ هدف در مقصد', target, 5, 90, (v) => setState(() => target = v)),
      slider('شارژ تا چند درصد در ایستگاه‌های بین راه', chargeTo, 50, 100, (v) => setState(() => chargeTo = v), divisions: 10),
      const SizedBox(height: 8),
      AppButton('تحلیل مسیر و مصرف انرژی', icon: Icons.navigation_outlined, loading: analyzing, onPressed: _analyze),
    ]);
  }

  Widget _results(Palette c, Map<String, dynamic> r) {
    final s = asMap(r['summary']);
    final v = asMap(r['verdict']);
    final route = asMap(r['route']);
    final plan = asList(r['charge_plan']);
    final chargers = asList(r['chargers']);
    final warnings = (v['warnings'] as List?)?.map((e) => '$e').toList() ?? const <String>[];
    final ok = v['can_reach_destination'] == true;
    final withPlan = v['can_reach_with_plan'] == true;
    final tone = ok ? Palette.green : (withPlan ? Palette.orange : Palette.red);
    Widget row(String k, String val, {Color? color}) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(width: 130, child: Text(k, style: TextStyle(fontSize: 12.5, color: c.muted))),
            Expanded(child: Text(val, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: color))),
          ]),
        );
    return ListView(padding: const EdgeInsets.fromLTRB(18, 12, 18, 24), children: [
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: tone.withValues(alpha: .1), borderRadius: BorderRadius.circular(18), border: Border.all(color: tone.withValues(alpha: .35))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(ok ? Icons.check_circle_rounded : (withPlan ? Icons.ev_station_rounded : Icons.warning_amber_rounded), color: tone),
            const SizedBox(width: 8),
            Expanded(child: Text('${v['title'] ?? ''}', style: TextStyle(fontWeight: FontWeight.w800, color: tone))),
          ]),
          const SizedBox(height: 8),
          Text('${v['message'] ?? ''}', style: TextStyle(fontSize: 13, height: 1.8, color: c.text2)),
          for (final w in warnings) Padding(padding: const EdgeInsets.only(top: 6), child: Text('• $w', style: const TextStyle(fontSize: 12, color: Palette.amber))),
        ]),
      ),
      const SecTitle('خلاصه مسیر'),
      AppCard(
        child: Column(children: [
          if (route['direction'] != null) row('جهت', '${route['direction']}'),
          row('مسافت', '${fa(route['distance_km'])} کیلومتر · ${fa(route['duration_min'])} دقیقه'),
          if (route['mode'] == 'via_chargers') row('مسیر', 'عبور از ${fa(plan.length)} ایستگاه شارژ${asDouble(route['detour_km']) > 0 ? ' (+${fa(route['detour_km'])} کیلومتر)' : ''}', color: Palette.orange),
          row('خودرو و باتری', '${asMap(r['vehicle'])['name'] ?? ''} · ${fa(s['battery_kwh'])} کیلووات‌ساعت'),
          if (asDouble(s['range_km_on_start']) > 0) row('برد', 'حدود ${fa(s['range_km_on_start'])} کیلومتر تا رزرو ${fa(s['reserve_soc'] ?? 10)}٪'),
          row('مصرف این مسیر', '${fa(s['consumption_per_100km'])} کیلووات‌ساعت در ۱۰۰ کیلومتر · کل ${fa(s['total_kwh'])}'),
          row('شارژ بدون توقف', '${fa(s['start_soc'])}٪ → ${fa(s['arrival_soc'])}٪ در مقصد'),
          if (asInt(s['charge_stops']) > 0) row('با توقف شارژ پیشنهادی', '${fa(s['start_soc'])}٪ → ${fa(s['arrival_soc_planned'])}٪ در مقصد', color: Palette.green),
          row('هدف در مقصد', '${fa(s['target_soc'])}٪'),
          if (asInt(s['total_charge_minutes']) > 0)
            row('زمان و هزینهٔ شارژ بین راه', 'حدود ${fa(s['total_charge_minutes'])} دقیقه · ${fa(s['total_charge_kwh'])} kWh${asDouble(s['total_charge_cost']) > 0 ? ' · ${money(asDouble(s['total_charge_cost']) / 10)} تومان' : ''}'),
          if (s['stranded_km'] != null) row('تمام شدن شارژ', 'حدود کیلومتر ${fa(s['stranded_km'])}', color: Palette.red),
          row('کمینه شارژ در مسیر', '${fa(s['min_soc_on_route'] ?? s['arrival_soc'])}٪'),
          row('دما و باد', '${fa(s['avg_temp_c'])}°C · ${fa(s['wind_ms'])} m/s'),
        ]),
      ),
      const SecTitle('برنامه شارژ پیشنهادی'),
      if (plan.isEmpty)
        AppCard(child: Text(s['needs_charge'] == true ? 'با شارژ فعلی به هدف مقصد نمی‌رسید — ایستگاه مناسب در مسیر پیدا نشد.' : 'با شارژ فعلی بدون توقف اضافه به مقصد می‌رسید.', style: TextStyle(color: c.muted, fontSize: 13)))
      else
        for (final p in plan)
          AppCard(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            onTap: p['code'] != null ? () => openScreen(context, 'charger', {'station': p['code']}) : null,
            child: Row(children: [
              const IconTile(Icons.ev_station_rounded, tone: 'orange'),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${p['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Text(fa('کیلومتر ${p['km']} · ${p['reason']}'), style: TextStyle(fontSize: 12, color: c.muted, height: 1.6)),
                  if (p['energy_kwh'] != null)
                    Text(fa('${p['energy_kwh']} کیلووات‌ساعت · حدود ${p['duration_min']} دقیقه${p['cost_rial'] != null ? ' · ${money(asDouble(p['cost_rial']) / 10)} تومان' : ''}'),
                        style: TextStyle(fontSize: 12, color: c.muted)),
                ]),
              ),
              Pill('→ ${fa(p['charge_to_soc'] ?? s['target_soc'])}٪', tone: 'blue'),
            ]),
          ),
      if (chargers.isNotEmpty) ...[
        const SecTitle('ایستگاه‌های شارژ در مسیر'),
        AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          child: Column(children: [
            for (final ch in chargers.take(10))
              ListRow(
                icon: Icons.bolt_rounded,
                tone: 'green',
                title: '${ch['name']}',
                subtitle: fa('کیلومتر ${ch['route_km'] ?? ch['km'] ?? '—'} · ${ch['free_ports'] ?? 0} آزاد · ${ch['max_power_kw'] ?? '—'} kW'),
                chevron: ch['code'] != null,
                onTap: ch['code'] != null ? () => openScreen(context, 'charger', {'station': ch['code']}) : null,
              ),
          ]),
        ),
      ],
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
