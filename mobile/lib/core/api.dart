import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

/// خطای قابل نمایش به کاربر (پیام فارسی سرور یا خطای شبکه).
class ApiException implements Exception {
  ApiException(this.message, {this.status = 0, this.errors = const {}});

  final String message;
  final int status;
  final Map<String, dynamic> errors;

  bool get unauthorized => status == 401;

  /// اولین خطای یک فیلد فرم (پاسخ 422 لاراول).
  String? field(String name) {
    final v = errors[name];
    if (v is List && v.isNotEmpty) return v.first.toString();
    return null;
  }

  @override
  String toString() => message;
}

/// کلاینت API اپ موبایل: `/api/v1/mobile/*` روی سرور ابری SafirCharge.
class ApiClient {
  ApiClient({required this.baseUrl, this.token, http.Client? client}) : _http = client ?? http.Client();

  String baseUrl;
  String? token;
  final http.Client _http;

  /// وقتی توکن منقضی یا باطل شود صدا زده می‌شود تا اپ به صفحهٔ ورود برگردد.
  void Function()? onUnauthorized;

  static const _timeout = Duration(seconds: 25);

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final base = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final clean = path.startsWith('/') ? path.substring(1) : path;
    final q = query?.map((k, v) => MapEntry(k, v?.toString() ?? ''))?..removeWhere((k, v) => v.isEmpty);
    return Uri.parse('$base/api/v1/mobile/$clean').replace(queryParameters: (q == null || q.isEmpty) ? null : q);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Client': 'safircharge-flutter',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> get(String path, [Map<String, dynamic>? query]) =>
      _send(() => _http.get(_uri(path, query), headers: _headers));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => _http.post(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<Map<String, dynamic>> put(String path, [Map<String, dynamic>? body]) =>
      _send(() => _http.put(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<Map<String, dynamic>> delete(String path) => _send(() => _http.delete(_uri(path), headers: _headers));

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() request) async {
    http.Response res;
    try {
      res = await request().timeout(_timeout);
    } on TimeoutException {
      throw ApiException('پاسخی از سرور دریافت نشد. اتصال اینترنت را بررسی کنید.');
    } catch (_) {
      throw ApiException('اتصال به سرور برقرار نشد. اینترنت یا آدرس سرور را بررسی کنید.');
    }

    dynamic data;
    try {
      data = res.body.isEmpty ? <String, dynamic>{} : jsonDecode(utf8.decode(res.bodyBytes));
    } catch (_) {
      data = <String, dynamic>{};
    }
    final map = data is Map<String, dynamic> ? data : <String, dynamic>{'data': data};

    if (res.statusCode >= 200 && res.statusCode < 300) return map;

    if (res.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException('نشست شما منقضی شده است؛ دوباره وارد شوید.', status: 401);
    }

    final errors = map['errors'] is Map<String, dynamic> ? map['errors'] as Map<String, dynamic> : <String, dynamic>{};
    var message = map['message']?.toString();
    if (errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) message = first.first.toString();
    }
    message ??= switch (res.statusCode) {
      403 => 'دسترسی مجاز نیست.',
      404 => 'مورد درخواستی یافت نشد.',
      429 => 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.',
      _ => 'خطای سرور (${res.statusCode}).',
    };
    throw ApiException(message, status: res.statusCode, errors: errors);
  }
}
