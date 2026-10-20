import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';

/// تنظیمات Firebase در زمان build (بدون فایل google-services.json):
/// `--dart-define=FCM_PROJECT_ID=... --dart-define=FCM_API_KEY=... --dart-define=FCM_SENDER_ID=...`
/// `--dart-define=FCM_ANDROID_APP_ID=... --dart-define=FCM_IOS_APP_ID=...`
class FcmConfig {
  static const projectId = String.fromEnvironment('FCM_PROJECT_ID');
  static const apiKey = String.fromEnvironment('FCM_API_KEY');
  static const senderId = String.fromEnvironment('FCM_SENDER_ID');
  static const androidAppId = String.fromEnvironment('FCM_ANDROID_APP_ID');
  static const iosAppId = String.fromEnvironment('FCM_IOS_APP_ID');

  static bool get configured =>
      !kIsWeb && projectId.isNotEmpty && apiKey.isNotEmpty && senderId.isNotEmpty && (defaultTargetPlatform == TargetPlatform.iOS ? iosAppId.isNotEmpty : androidAppId.isNotEmpty);

  static FirebaseOptions get options => FirebaseOptions(
        apiKey: apiKey,
        appId: defaultTargetPlatform == TargetPlatform.iOS ? iosAppId : androidAppId,
        messagingSenderId: senderId,
        projectId: projectId,
        iosBundleId: 'ir.safircharge.app',
      );
}

/// اعلان‌ها: پوش FCM (در صورت پیکربندی) و در غیر این صورت اعلان محلی از روی صندوق اعلان‌های سرور
/// (هر یک دقیقه تا وقتی اپ باز است). پایان شارژ، کمبود موجودی و شارژ کیف پول را سرور می‌سازد.
class NotificationsService {
  NotificationsService(this._prefs);

  final SharedPreferences _prefs;
  final _local = FlutterLocalNotificationsPlugin();
  bool _ready = false;
  bool _fcmActive = false;
  String? _fcmToken;
  Timer? _poll;
  ApiClient? _api;

  static const _channel = AndroidNotificationDetails(
    'safircharge',
    'SafirCharge',
    channelDescription: 'پایان شارژ، کیف پول و پیام‌های پشتیبانی',
    importance: Importance.high,
    priority: Priority.high,
  );

  Future<void> _init() async {
    if (_ready || kIsWeb) return;
    _ready = true;
    try {
      await _local.initialize(
        settings: const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: DarwinInitializationSettings(),
        ),
      );
      await _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.requestNotificationsPermission();
    } catch (e) {
      debugPrint('local notifications unavailable: $e');
    }
  }

  Future<void> show(int id, String title, String body) async {
    if (kIsWeb) return;
    await _init();
    try {
      await _local.show(id: id, title: title, body: body, notificationDetails: const NotificationDetails(android: _channel, iOS: DarwinNotificationDetails()));
    } catch (_) {}
  }

  /// پس از ورود: ثبت توکن FCM یا شروع پرسش دوره‌ای اعلان‌ها.
  Future<void> start(ApiClient api) async {
    _api = api;
    if (kIsWeb) return;
    await _init();
    if (FcmConfig.configured) {
      try {
        if (Firebase.apps.isEmpty) await Firebase.initializeApp(options: FcmConfig.options);
        final messaging = FirebaseMessaging.instance;
        await messaging.requestPermission();
        // توکن ثبت (registration token) همان چیزی است که سرور در FCM HTTP v1 (messages:send) استفاده می‌کند.
        // ignore: deprecated_member_use
        _fcmToken = await messaging.getToken();
        if (_fcmToken != null) await _register(_fcmToken!);
        // ignore: deprecated_member_use
        messaging.onTokenRefresh.listen(_register);
        // پیام‌هایی که در حالت باز بودن اپ می‌رسند را سیستم نشان نمی‌دهد؛ اعلان محلی می‌سازیم.
        FirebaseMessaging.onMessage.listen((m) {
          final n = m.notification;
          if (n != null) show(m.hashCode, n.title ?? 'SafirCharge', n.body ?? '');
        });
        _fcmActive = true;
      } catch (e) {
        debugPrint('FCM init failed: $e');
      }
    }
    if (!_fcmActive) {
      _poll?.cancel();
      await _checkInbox(silentFirst: !_prefs.containsKey('notif_last_id'));
      _poll = Timer.periodic(const Duration(minutes: 1), (_) => _checkInbox());
    }
  }

  Future<void> _register(String token) async {
    try {
      await _api?.post('devices', {'token': token, 'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android'});
    } catch (_) {}
  }

  Future<void> _checkInbox({bool silentFirst = false}) async {
    final api = _api;
    if (api == null || api.token == null) return;
    try {
      final last = _prefs.getInt('notif_last_id') ?? 0;
      final res = await api.get('notifications/since', {'after': last});
      final items = (res['items'] as List?) ?? const [];
      if (!silentFirst) {
        for (final item in items.whereType<Map>()) {
          await show((item['id'] as num).toInt(), '${item['title']}', '${item['body'] ?? ''}');
        }
      }
      await _prefs.setInt('notif_last_id', (res['latest_id'] as num?)?.toInt() ?? last);
    } catch (_) {
      // بدون اینترنت؛ بار بعد.
    }
  }

  Future<void> stop() async {
    _poll?.cancel();
    if (_fcmToken != null) {
      try {
        await _api?.delete('devices?token=${Uri.encodeQueryComponent(_fcmToken!)}');
      } catch (_) {}
    }
    await _prefs.remove('notif_last_id');
  }
}

/// موقعیت گوشی برای «نزدیک‌ترین ایستگاه‌ها»؛ حداکثر هر ۱۰ دقیقه به سرور فرستاده می‌شود.
class LocationService {
  LocationService(this._prefs);

  final SharedPreferences _prefs;
  ({double lat, double lng})? last;

  Future<({double lat, double lng})?> current({bool ask = true}) async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return null;
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied && ask) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return null;
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium, timeLimit: Duration(seconds: 12)),
      );
      return last = (lat: pos.latitude, lng: pos.longitude);
    } catch (_) {
      return null;
    }
  }

  /// true اگر موقعیت تازه‌ای ثبت شد (صفحهٔ خانه باید فاصله‌ها را دوباره بخواند).
  Future<bool> sync(ApiClient api, {bool force = false}) async {
    final at = DateTime.tryParse(_prefs.getString('loc_sent_at') ?? '');
    if (!force && at != null && DateTime.now().difference(at) < const Duration(minutes: 10)) return false;
    final p = await current();
    if (p == null) return false;
    try {
      await api.post('location', {'lat': p.lat, 'lng': p.lng});
      await _prefs.setString('loc_sent_at', DateTime.now().toIso8601String());
      return true;
    } catch (_) {
      return false;
    }
  }
}
