import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';

/// لبهٔ محلی ایستگاه که از روی WiFi همان ایستگاه در دسترس است.
class EdgeLink {
  EdgeLink({required this.base, required this.label, this.ssid, required this.api});

  final String base;
  final String label;
  final String? ssid;

  /// کلاینت `‎/api/v1/*` لبه با هویت کاربر.
  final ApiClient api;
}

/// کشف لبهٔ محلی و نگهداری اعتبارنامهٔ آفلاین — معادل edge-router.js وب‌اپ.
///
/// هویت کاربر روی لبه به دو روش است:
/// * هدرهای `X-SC-User-UUID` و `X-SC-Edge-Token` (امضای روزانهٔ سرور ابری؛ امروز و دیروز معتبر)
///   که هنگام آنلاین بودن از `context` گرفته و ذخیره می‌شود؛
/// * توکن نشست لبه که با ورود پیامکی روی خود ایستگاه (بدون اینترنت) گرفته می‌شود.
class EdgeManager extends ChangeNotifier {
  EdgeManager(this._prefs, {http.Client? client}) : _client = client ?? http.Client() {
    _load();
  }

  final SharedPreferences _prefs;
  final http.Client _client;

  String? userUuid;
  String? edgeToken;
  DateTime? tokenAt;
  String? sessionToken;
  String? manualUrl;
  List<Map<String, dynamic>> candidates = [];

  EdgeLink? active;
  DateTime? _lastProbe;
  Future<EdgeLink?>? _probing;

  static const probeTimeout = Duration(milliseconds: 2500);

  void _load() {
    try {
      final raw = _prefs.getString('edge_conn');
      if (raw != null) {
        final m = jsonDecode(raw) as Map<String, dynamic>;
        userUuid = m['user_uuid'] as String?;
        edgeToken = m['edge_token'] as String?;
        tokenAt = DateTime.tryParse('${m['token_at']}');
        candidates = ((m['candidates'] as List?) ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      }
    } catch (_) {}
    sessionToken = _prefs.getString('edge_session');
    manualUrl = _prefs.getString('edge_manual');
  }

  /// امضای روزانهٔ سرور فقط تا پایان روز بعد معتبر است.
  bool get cloudTokenValid => userUuid != null && edgeToken != null && tokenAt != null && DateTime.now().difference(tokenAt!) < const Duration(hours: 24);

  bool get hasCredentials => cloudTokenValid || sessionToken != null;

  /// ذخیرهٔ پاسخ `context` سرور ابری (فراخوانی هنگام آنلاین بودن).
  Future<void> storeConnectivity(Map<String, dynamic> connectivity) async {
    userUuid = connectivity['userUuid'] as String?;
    edgeToken = connectivity['edgeToken'] as String?;
    tokenAt = DateTime.now();
    candidates = ((connectivity['candidates'] as List?) ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .where((c) => c['kind'] == 'edge')
        .toList();
    await _prefs.setString('edge_conn', jsonEncode({
      'user_uuid': userUuid,
      'edge_token': edgeToken,
      'token_at': tokenAt!.toIso8601String(),
      'candidates': candidates,
    }));
  }

  Future<void> setManualUrl(String? url) async {
    var u = url?.trim() ?? '';
    if (u.isNotEmpty && !u.startsWith('http')) u = 'http://$u';
    manualUrl = u.isEmpty ? null : u.replaceAll(RegExp(r'/+$'), '');
    if (manualUrl == null) {
      await _prefs.remove('edge_manual');
    } else {
      await _prefs.setString('edge_manual', manualUrl!);
    }
    await discover(force: true);
  }

  Future<void> setSessionToken(String? token) async {
    sessionToken = token;
    if (token == null) {
      await _prefs.remove('edge_session');
    } else {
      await _prefs.setString('edge_session', token);
    }
    notifyListeners();
  }

  Future<void> clear() async {
    userUuid = null;
    edgeToken = null;
    tokenAt = null;
    await _prefs.remove('edge_conn');
    await setSessionToken(null);
  }

  Map<String, String> _authHeaders() {
    if (cloudTokenValid) return {'X-SC-User-UUID': userUuid!, 'X-SC-Edge-Token': edgeToken!};
    if (sessionToken != null) return {'Authorization': 'Bearer $sessionToken'};
    return {};
  }

  ApiClient clientFor(String base) => ApiClient(baseUrl: base, prefix: 'api/v1', client: _client, extraHeaders: _authHeaders, timeout: const Duration(seconds: 15));

  /// جست‌وجوی لبه‌های شناخته‌شده (و آدرس دستی)؛ نتیجه ۳۰ ثانیه کش می‌شود.
  Future<EdgeLink?> discover({bool force = false}) {
    if (!force && _lastProbe != null && DateTime.now().difference(_lastProbe!) < const Duration(seconds: 30)) {
      return Future.value(active);
    }
    return _probing ??= _probe().whenComplete(() => _probing = null);
  }

  Future<EdgeLink?> _probe() async {
    final list = <Map<String, dynamic>>[
      if (manualUrl != null) {'base': manualUrl, 'label': 'لبهٔ محلی ایستگاه', 'manual': true},
      ...candidates,
    ];
    final seen = <String>{};
    EdgeLink? found;
    final results = await Future.wait(list.where((c) => seen.add('${c['base']}')).map((c) async {
      final base = '${c['base']}'.replaceAll(RegExp(r'/+$'), '');
      try {
        final res = await _client.get(Uri.parse('$base/api/v1/health'), headers: {'Accept': 'application/json'}).timeout(probeTimeout);
        if (res.statusCode != 200) return null;
        final body = jsonDecode(utf8.decode(res.bodyBytes));
        if (body is! Map || body['service'] != 'safircharge-edge') return null;
        final station = body['station'] is Map ? body['station'] as Map : const {};
        return EdgeLink(
          base: base,
          label: station['name'] != null ? 'لبه محلی — ${station['name']}' : '${c['label'] ?? 'لبهٔ محلی ایستگاه'}',
          ssid: c['wifiSsid'] as String?,
          api: clientFor(base),
        );
      } catch (_) {
        return null;
      }
    }));
    for (final r in results) {
      if (r != null) {
        found = r;
        break;
      }
    }
    _lastProbe = DateTime.now();
    final changed = found?.base != active?.base;
    active = found;
    if (changed) notifyListeners();
    return found;
  }
}
