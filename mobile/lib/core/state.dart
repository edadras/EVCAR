import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'edge.dart';

/// آدرس پیش‌فرض سرور ابری؛ در زمان build با `--dart-define=API_BASE_URL=https://...` تنظیم می‌شود.
const String kDefaultBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://localhost:8000');

/// کلید ناوبری سراسری (برای بازگشت به ریشه هنگام خروج یا انقضای توکن).
final GlobalKey<NavigatorState> kNavKey = GlobalKey<NavigatorState>();

/// وضعیت سراسری اپ: آدرس سرور، توکن، کاربر و تنظیمات ظاهری (مثل وب‌اپ).
class AppState extends ChangeNotifier {
  AppState(this._prefs, {http.Client? client})
      : api = ApiClient(
          baseUrl: _prefs.getString('base_url') ?? kDefaultBaseUrl,
          token: _prefs.getString('token'),
          client: client,
        ),
        edge = EdgeManager(_prefs, client: client) {
    api.onUnauthorized = () {
      if (api.token != null) logoutLocal();
    };
    final dark = _prefs.getBool('dark_theme');
    if (dark != null) prefs['dark_theme'] = dark;
    final accent = _prefs.getString('accent');
    if (accent != null) prefs['accent'] = accent;
  }

  final SharedPreferences _prefs;
  final ApiClient api;

  /// لبهٔ محلی ایستگاه (شارژ روی WiFi ایستگاه، حتی بدون اینترنت).
  final EdgeManager edge;

  Map<String, dynamic>? user;
  Map<String, dynamic> prefs = {};

  bool get loggedIn => api.token != null;
  String get baseUrl => api.baseUrl;
  bool get dark => prefs['dark_theme'] == true;

  Color get accent {
    final hex = (prefs['accent'] as String?)?.replaceAll('#', '');
    if (hex != null && hex.length == 6) {
      final v = int.tryParse(hex, radix: 16);
      if (v != null) return Color(0xFF000000 | v);
    }
    return const Color(0xFF0E6BA8);
  }

  int get chargeTargetSoc => (prefs['charge_target_soc'] as num?)?.toInt() ?? 80;

  Future<void> setBaseUrl(String url) async {
    var u = url.trim();
    if (u.isEmpty) u = kDefaultBaseUrl;
    if (!u.startsWith('http')) u = 'https://$u';
    api.baseUrl = u;
    await _prefs.setString('base_url', u);
    notifyListeners();
  }

  Future<void> signIn(Map<String, dynamic> response) async {
    api.token = response['access_token'] as String?;
    if (api.token != null) await _prefs.setString('token', api.token!);
    _applyUser(response['user'] as Map<String, dynamic>?);
    notifyListeners();
    refreshConnectivity();
  }

  /// گرفتن امضای روزانهٔ لبه و فهرست لبه‌ها از سرور برای استفادهٔ آفلاین.
  Future<void> refreshConnectivity() async {
    if (!loggedIn) return;
    try {
      final res = await api.get('context');
      if (res['connectivity'] is Map) await edge.storeConnectivity(Map<String, dynamic>.from(res['connectivity'] as Map));
    } catch (_) {}
    edge.discover(force: true);
  }

  void _applyUser(Map<String, dynamic>? u) {
    user = u;
    final p = u?['prefs'];
    if (p is Map) {
      prefs = {...prefs, ...Map<String, dynamic>.from(p)};
      _persistLook();
    }
  }

  /// بارگذاری کاربر هنگام باز شدن اپ؛ اگر توکن معتبر نباشد به ورود برمی‌گردد.
  Future<void> refreshMe() async {
    if (!loggedIn) return;
    try {
      final res = await api.get('me');
      _applyUser(res['user'] as Map<String, dynamic>?);
      notifyListeners();
      await refreshConnectivity();
    } on ApiException catch (e) {
      if (e.unauthorized) await logoutLocal();
      // بدون اینترنت: اگر لبهٔ محلی در دسترس باشد، شارژ محلی ممکن است.
      edge.discover(force: true);
    }
  }

  void updateUser(Map<String, dynamic> u) {
    _applyUser(u);
    notifyListeners();
  }

  Future<void> savePrefs(Map<String, dynamic> changes) async {
    prefs = {...prefs, ...changes};
    _persistLook();
    notifyListeners();
    final res = await api.post('settings', changes);
    if (res['prefs'] is Map) {
      prefs = {...prefs, ...Map<String, dynamic>.from(res['prefs'] as Map)};
      notifyListeners();
    }
  }

  void _persistLook() {
    if (prefs['dark_theme'] is bool) _prefs.setBool('dark_theme', prefs['dark_theme'] as bool);
    if (prefs['accent'] is String) _prefs.setString('accent', prefs['accent'] as String);
  }

  Future<void> logout() async {
    try {
      await api.post('auth/logout');
    } catch (_) {}
    await logoutLocal();
  }

  Future<void> logoutLocal() async {
    kNavKey.currentState?.popUntil((r) => r.isFirst);
    api.token = null;
    user = null;
    await _prefs.remove('token');
    await edge.clear();
    notifyListeners();
  }
}

/// دسترسی به AppState از هر ویجت.
class AppScope extends InheritedNotifier<AppState> {
  const AppScope({super.key, required AppState state, required super.child}) : super(notifier: state);

  static AppState of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AppScope>()!.notifier!;

  static AppState read(BuildContext context) =>
      (context.getElementForInheritedWidgetOfExactType<AppScope>()!.widget as AppScope).notifier!;
}

extension AppContext on BuildContext {
  AppState get app => AppScope.read(this);
  ApiClient get api => AppScope.read(this).api;
}
