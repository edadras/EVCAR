import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:safircharge/core/api.dart';
import 'package:safircharge/core/fmt.dart';
import 'package:safircharge/core/state.dart';
import 'package:safircharge/main.dart';
import 'package:safircharge/screens/scan_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

http.Response _json(Object body, [int status = 200]) =>
    http.Response.bytes(utf8.encode(jsonEncode(body)), status, headers: {'content-type': 'application/json; charset=utf-8'});

void main() {
  group('fmt', () {
    test('Persian digits and money grouping like the web app', () {
      expect(fa(1405), '۱۴۰۵');
      expect(money(477259), '۴۷۷,۲۵۹');
      expect(money(-1503), '−۱,۵۰۳');
      expect(enDigits('۰۹۱۲۳۴۵۶۷۸۹'), '09123456789');
      expect(duration(36), '۰۰:۳۶');
      expect(duration(3725), '۰۱:۰۲:۰۵');
    });
  });

  group('QR parsing', () {
    test('web-app charger URL', () {
      expect(parseChargerQr('https://cloud.safircharge.ir/app/charger?station=ST-1&connector=7&qr=QR-ABC'),
          {'station': 'ST-1', 'connector': '7', 'qr': 'QR-ABC'});
    });
    test('bare token', () => expect(parseChargerQr('QR-M1'), {'qr': 'QR-M1'}));
    test('foreign QR is rejected', () {
      expect(parseChargerQr('https://example.com/?a=1'), isNull);
      expect(parseChargerQr('سلام دنیا'), isNull);
    });
  });

  group('ApiClient', () {
    test('maps Laravel 422 errors to the first field message', () async {
      final api = ApiClient(
        baseUrl: 'http://x',
        client: MockClient((r) async => _json({'message': 'x', 'errors': {'charging': ['موجودی کیف پول کافی نیست.']}}, 422)),
      );
      await expectLater(
        api.post('charging/start', {'connector_id': 1}),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'موجودی کیف پول کافی نیست.').having((e) => e.field('charging'), 'field', isNotNull)),
      );
    });

    test('sends bearer token and JSON to /api/v1/mobile', () async {
      late http.Request seen;
      final api = ApiClient(baseUrl: 'http://host:8000/', token: 'T', client: MockClient((r) async {
        seen = r;
        return _json({'ok': true});
      }));
      await api.get('charger', {'station': 'ST', 'qr': null});
      expect(seen.url.toString(), 'http://host:8000/api/v1/mobile/charger?station=ST');
      expect(seen.headers['Authorization'], 'Bearer T');
    });
  });

  testWidgets('logged-out app shows the login wizard (RTL)', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final state = AppState(await SharedPreferences.getInstance());
    await tester.pumpWidget(SafirChargeApp(state: state));
    await tester.pumpAndSettle();
    expect(find.text('ورود / ثبت‌نام'), findsOneWidget);
    expect(find.text('ادامه'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('ادامه'))), TextDirection.rtl);
  });

  testWidgets('logged-in app renders home from the mobile API', (tester) async {
    SharedPreferences.setMockInitialValues({'token': 'T', 'base_url': 'http://api'});
    final client = MockClient((r) async {
      final path = r.url.path.replaceFirst('/api/v1/mobile/', '');
      return switch (path) {
        'home' => _json({
            'header_date': 'سه‌شنبه ۲۸ مهر · تهران',
            'first_name': 'مهدی',
            'unread_notifications': 1,
            'active_session': null,
            'featured_offer': null,
            'campaigns_count': 0,
            'user': {'balance': 477259},
            'nearby_stations': [
              {'code': 'ST-1', 'name': 'میدان آزادی', 'type': 'DC سریع · CCS2', 'distance': '۴٫۷ کیلومتر', 'free': '۵ از ۸ آزاد', 'price': '۸۵۰', 'status': 'آزاد', 'status_pill': 'pill-green', 'cat': 'fast', 'has_free': true},
            ],
          }),
        'me' => _json({'user': {'name': 'مهدی', 'prefs': {}}}),
        _ => _json({}),
      };
    });
    final state = AppState(await SharedPreferences.getInstance(), client: client);
    await tester.pumpWidget(SafirChargeApp(state: state));
    await tester.pumpAndSettle();
    expect(find.text('سلام، مهدی 👋'), findsOneWidget);
    expect(find.textContaining('۴۷۷,۲۵۹', findRichText: true), findsOneWidget);
    expect(find.text('میدان آزادی'), findsOneWidget);
    expect(find.text('پیشخان'), findsOneWidget);
  });
}
