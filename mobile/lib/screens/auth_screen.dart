import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'misc_screens.dart';

enum _Step { phone, password, otp, register }

/// ورود / ثبت‌نام — همان مراحل auth-wizard وب‌اپ: شماره → رمز (کاربر موجود) یا کد تأیید → تعیین رمز.
class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  _Step step = _Step.phone;
  final phone = TextEditingController();
  final password = TextEditingController();
  final otp = TextEditingController();
  final newPass = TextEditingController();
  final newPass2 = TextEditingController();
  String normalized = '';
  String? registrationToken;
  String? message;
  bool messageError = false;
  bool busy = false;
  String? testHint;
  int resendIn = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in [phone, password, otp, newPass, newPass2]) {
      c.dispose();
    }
    super.dispose();
  }

  void _msg(String? m, {bool error = false}) => setState(() {
        message = m;
        messageError = error;
      });

  Future<T?> _guard<T>(Future<T> Function() fn) async {
    setState(() => busy = true);
    try {
      return await fn();
    } on ApiException catch (e) {
      _msg(e.message, error: true);
      return null;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _next() async {
    final p = enDigits(phone.text.trim());
    if (!RegExp(r'^(\+98|0098|98|0)?9\d{9}$').hasMatch(p)) {
      _msg('شمارهٔ موبایل معتبر وارد کنید (مثلاً ۰۹۱۲۳۴۵۶۷۸۹).', error: true);
      return;
    }
    final res = await _guard(() => context.api.post('auth/lookup', {'phone': p}));
    if (res == null) return;
    normalized = (res['phone'] ?? p).toString();
    _msg(null);
    if (res['registered'] == true && res['has_password'] == true) {
      setState(() => step = _Step.password);
    } else {
      await _requestOtp();
    }
  }

  Future<void> _requestOtp() async {
    final res = await _guard(() => context.api.post('auth/otp/request', {'phone': normalized, 'purpose': 'register'}));
    if (res == null) return;
    setState(() {
      step = _Step.otp;
      testHint = res['debug_code'] != null ? 'کد آزمایشی (فقط محیط توسعه): ${fa(res['debug_code'])}' : null;
      resendIn = 60;
    });
    _msg(res['message']?.toString());
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted || resendIn <= 0) return t.cancel();
      setState(() => resendIn--);
    });
  }

  Future<void> _login() async {
    if (password.text.isEmpty) return _msg('رمز عبور را وارد کنید.', error: true);
    final res = await _guard(() => context.api.post('auth/login', {'phone': normalized, 'password': password.text, 'device_name': 'SafirCharge app'}));
    if (res != null && mounted) await context.app.signIn(res);
  }

  Future<void> _verify() async {
    final code = enDigits(otp.text.trim());
    if (code.length != 6) return _msg('کد ۶ رقمی را کامل وارد کنید.', error: true);
    final res = await _guard(() => context.api.post('auth/otp/verify', {'phone': normalized, 'code': code, 'purpose': 'register'}));
    if (res == null) return;
    registrationToken = res['registration_token']?.toString();
    _msg(null);
    setState(() => step = _Step.register);
  }

  Future<void> _register() async {
    if (newPass.text.length < 8) return _msg('رمز عبور باید حداقل ۸ کاراکتر باشد.', error: true);
    if (newPass.text != newPass2.text) return _msg('تکرار رمز عبور یکسان نیست.', error: true);
    final res = await _guard(() => context.api.post('auth/register', {
          'phone': normalized,
          'password': newPass.text,
          'password_confirmation': newPass2.text,
          'registration_token': registrationToken,
          'device_name': 'SafirCharge app',
        }));
    if (res != null && mounted) await context.app.signIn(res);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.orange, .5)!], begin: Alignment.topRight, end: Alignment.bottomLeft),
        ),
        child: SafeArea(
          bottom: false,
          child: Column(children: [
            Align(
              alignment: AlignmentDirectional.topEnd,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: RoundIconButton(Icons.dns_outlined, dark: true, onTap: () => push(context, const ServerSettingsScreen())),
              ),
            ),
            Expanded(
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  width: 84,
                  height: 84,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: .16), borderRadius: BorderRadius.circular(26)),
                  child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 48),
                ),
                const SizedBox(height: 14),
                const Text('SafirCharge', style: TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text('شارژ هوشمند خودرو برقی', style: TextStyle(color: Colors.white.withValues(alpha: .9), fontSize: 14)),
              ]),
            ),
            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: c.surface,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(30)),
                boxShadow: const [BoxShadow(color: Color(0x2E000000), blurRadius: 40, offset: Offset(0, -10))],
              ),
              padding: EdgeInsets.fromLTRB(24, 26, 24, 26 + MediaQuery.of(context).padding.bottom),
              child: AnimatedSize(duration: const Duration(milliseconds: 200), child: _sheet(c)),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _back(String label, _Step to) => Align(
        alignment: AlignmentDirectional.centerStart,
        child: TextButton.icon(
          onPressed: () => setState(() {
            step = to;
            message = null;
          }),
          icon: const LtrIcon(Icons.chevron_right_rounded, size: 20),
          label: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
          style: TextButton.styleFrom(padding: EdgeInsets.zero),
        ),
      );

  Widget _sheet(Palette c) {
    final msg = message == null
        ? const SizedBox(height: 4)
        : Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text(message!, style: TextStyle(fontSize: 12.5, color: messageError ? Palette.red : Palette.green, fontWeight: FontWeight.w600)),
          );
    final phoneLabel = fa(normalized);

    final List<Widget> body = switch (step) {
      _Step.phone => [
          Row(children: [
            const Expanded(child: Text('ورود / ثبت‌نام', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
            const Pill('مرکز ابری', tone: 'blue'),
          ]),
          const SizedBox(height: 8),
          Text('شماره موبایل خود را وارد کنید. اگر ثبت‌نام کرده‌اید رمز می‌خواهیم؛ در غیر این صورت کد تأیید ارسال می‌شود.',
              style: TextStyle(color: c.muted, fontSize: 13, height: 1.7)),
          const SizedBox(height: 18),
          LabeledField(label: 'شماره موبایل', controller: phone, hint: '۰۹۱۲۳۴۵۶۷۸۹', keyboard: TextInputType.phone, ltr: true, textAlign: TextAlign.left),
          msg,
          AppButton('ادامه', loading: busy, onPressed: _next),
        ],
      _Step.password => [
          _back('تغییر شماره', _Step.phone),
          const Text('رمز عبور', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text('رمز عبور حساب $phoneLabel را وارد کنید.', style: TextStyle(color: c.muted, fontSize: 13)),
          const SizedBox(height: 18),
          LabeledField(label: 'رمز عبور', controller: password, hint: 'رمز عبور', obscure: true),
          msg,
          AppButton('ورود', loading: busy, onPressed: _login),
        ],
      _Step.otp => [
          _back('تغییر شماره', _Step.phone),
          const Text('کد تأیید', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text('کد ۶ رقمی ارسال‌شده به $phoneLabel را وارد کنید.', style: TextStyle(color: c.muted, fontSize: 13)),
          const SizedBox(height: 18),
          TextField(
            controller: otp,
            keyboardType: TextInputType.number,
            maxLength: 6,
            textAlign: TextAlign.center,
            textDirection: TextDirection.ltr,
            style: const TextStyle(fontSize: 26, letterSpacing: 14, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(counterText: '', hintText: '––––––'),
            onChanged: (v) {
              if (enDigits(v).length == 6) _verify();
            },
          ),
          if (testHint != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(testHint!, style: TextStyle(fontSize: 11.5, color: c.muted))),
          const SizedBox(height: 12),
          Center(
            child: resendIn > 0
                ? Text('ارسال مجدد تا ${duration(resendIn)}', style: TextStyle(color: c.muted, fontSize: 13))
                : TextButton(onPressed: busy ? null : _requestOtp, child: const Text('ارسال مجدد کد')),
          ),
          const SizedBox(height: 8),
          msg,
          AppButton('تأیید کد', loading: busy, onPressed: _verify),
        ],
      _Step.register => [
          _back('بازگشت', _Step.otp),
          const Text('تعیین رمز عبور', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text('برای $phoneLabel یک رمز عبور انتخاب کنید.', style: TextStyle(color: c.muted, fontSize: 13)),
          const SizedBox(height: 18),
          LabeledField(label: 'رمز عبور جدید', controller: newPass, hint: 'حداقل ۸ کاراکتر', obscure: true),
          LabeledField(label: 'تکرار رمز عبور', controller: newPass2, hint: 'تکرار رمز', obscure: true),
          msg,
          AppButton('ثبت‌نام و ورود', loading: busy, onPressed: _register),
        ],
    };

    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
      ...body,
      const SizedBox(height: 16),
      Center(
        child: GestureDetector(
          onTap: () => push(context, const LegalScreen(kind: 'terms')),
          child: Text.rich(TextSpan(style: TextStyle(fontSize: 12, color: c.muted), children: [
            const TextSpan(text: 'با ورود، '),
            TextSpan(text: 'قوانین و مقررات', style: TextStyle(color: c.accent, fontWeight: FontWeight.w700)),
            const TextSpan(text: ' را می‌پذیرید.'),
          ])),
        ),
      ),
    ]);
  }
}
