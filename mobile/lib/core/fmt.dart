/// قالب‌بندی اعداد و مبالغ مطابق وب‌اپ (ارقام فارسی، جداکنندهٔ هزارگان).
library;

const _fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

String fa(Object? value) {
  if (value == null) return '—';
  final s = value.toString();
  final b = StringBuffer();
  for (final ch in s.runes) {
    if (ch >= 48 && ch <= 57) {
      b.write(_fa[ch - 48]);
    } else {
      b.writeCharCode(ch);
    }
  }
  return b.toString();
}

/// ارقام فارسی/عربی ورودی کاربر را به لاتین تبدیل می‌کند.
String enDigits(String s) {
  const ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  var out = s;
  for (var i = 0; i < 10; i++) {
    out = out.replaceAll(_fa[i], '$i').replaceAll(ar[i], '$i');
  }
  return out;
}

String money(num? amount) {
  final n = (amount ?? 0).round();
  final neg = n < 0;
  final digits = n.abs().toString();
  final b = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) b.write(',');
    b.write(digits[i]);
  }
  return '${neg ? '−' : ''}${fa(b.toString())}';
}

String kwh(num? v, [int decimals = 1]) => fa((v ?? 0).toStringAsFixed(decimals));

String duration(int secs) {
  final h = secs ~/ 3600;
  final m = (secs % 3600) ~/ 60;
  final s = secs % 60;
  String two(int x) => x.toString().padLeft(2, '0');
  return fa(h > 0 ? '${two(h)}:${two(m)}:${two(s)}' : '${two(m)}:${two(s)}');
}

int asInt(Object? v, [int fallback = 0]) {
  if (v is num) return v.toInt();
  if (v is String) return int.tryParse(v) ?? double.tryParse(v)?.toInt() ?? fallback;
  return fallback;
}

double asDouble(Object? v, [double fallback = 0]) {
  if (v is num) return v.toDouble();
  if (v is String) return double.tryParse(v) ?? fallback;
  return fallback;
}

List<Map<String, dynamic>> asList(Object? v) =>
    v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : <Map<String, dynamic>>[];

Map<String, dynamic> asMap(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
